<?php
/**
 * Page de connexion à la plateforme FindPro.
 * 
 * - Authentification sécurisée par email et mot de passe (password_verify).
 * - Protection anti-CSRF obligatoire.
 * - Protection anti-bruteforce (limitation à 5 échecs en 15 minutes par email ou adresse IP).
 * - Enregistrement de chaque tentative (succès ou échec dans tentative_connexion).
 * - Contrôle du statut du compte (actif / désactivé).
 * - Initialisation de session durcie (regenerer_session, exclusion du hash).
 * - Redirection selon le rôle ou vers l'URL de retour interne validée.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/errors.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/csrf.php';

demarrer_session();

// 1. Si déjà connecté, rediriger directement selon le rôle
$utilisateurConnecte = utilisateur_courant();
if ($utilisateurConnecte !== null) {
    if ($utilisateurConnecte['role'] === 'admin') {
        redirect(url('admin/index.php'));
    } elseif ($utilisateurConnecte['role'] === 'professionnel') {
        redirect(url('pro/index.php'));
    } else {
        redirect(url('client/index.php'));
    }
}

// 2. Gestion et validation stricte de l'URL de retour (protection anti-redirection ouverte)
// Règle : chemin interne commençant par / et ne contenant ni "//" ni ":" (ni "\")
$retourBrut = trim((string) ($_POST['retour'] ?? $_GET['retour'] ?? ''));
$urlRetour = null;

if ($retourBrut !== '') {
    if (str_starts_with($retourBrut, '/') && !str_contains($retourBrut, '//') && !str_contains($retourBrut, ':') && !str_contains($retourBrut, '\\')) {
        $urlRetour = $retourBrut;
    }
}

// 3. Récupération de l'adresse IP du client
$adresseIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
if (str_contains($adresseIp, ',')) {
    $adresseIp = trim(explode(',', $adresseIp)[0]);
}
$adresseIp = mb_substr($adresseIp, 0, 45);

$email = '';
$erreur = null;

// 4. Traitement du formulaire POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification du jeton CSRF obligatoire
    csrf_verifier();

    $email = trim((string) ($_POST['email'] ?? ''));
    $motDePasse = (string) ($_POST['mot_de_passe'] ?? '');
    $pdo = db();

    // Limitation anti-bruteforce : maximum 5 échecs en 15 minutes pour le même email OU la même IP
    $stmtTentatives = $pdo->prepare('
        SELECT COUNT(*) 
        FROM tentative_connexion 
        WHERE (email = :email OR adresse_ip = :ip)
          AND succes = 0 
          AND date_tentative >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ');
    $stmtTentatives->execute([
        'email' => $email,
        'ip'    => $adresseIp,
    ]);
    $nbEchecsRecents = (int) $stmtTentatives->fetchColumn();

    if ($nbEchecsRecents >= 5) {
        // Enregistrement de la tentative bloquée
        $stmtLogBlocage = $pdo->prepare('
            INSERT INTO tentative_connexion (email, adresse_ip, succes, date_tentative)
            VALUES (:email, :ip, 0, NOW())
        ');
        $stmtLogBlocage->execute([
            'email' => $email,
            'ip'    => $adresseIp,
        ]);

        $erreur = 'Trop de tentatives de connexion échouées. Par mesure de sécurité, la connexion est refusée pendant 15 minutes. Veuillez patienter avant de réessayer.';
    } elseif ($email === '' || $motDePasse === '') {
        // Enregistrement de la tentative échouée
        $stmtLog = $pdo->prepare('
            INSERT INTO tentative_connexion (email, adresse_ip, succes, date_tentative)
            VALUES (:email, :ip, 0, NOW())
        ');
        $stmtLog->execute([
            'email' => $email,
            'ip'    => $adresseIp,
        ]);

        $erreur = 'Email ou mot de passe incorrect.';
    } else {
        // Recherche de l'utilisateur par son email
        $stmtUser = $pdo->prepare('
            SELECT id_utilisateur, prenom, nom, email, mot_de_passe_hash, role, statut_compte
            FROM utilisateur 
            WHERE email = :email 
            LIMIT 1
        ');
        $stmtUser->execute(['email' => $email]);
        $utilisateur = $stmtUser->fetch();

        // Vérification sécurisée du mot de passe avec password_verify
        $identifiantsValides = $utilisateur && password_verify($motDePasse, (string) $utilisateur['mot_de_passe_hash']);

        if (!$identifiantsValides) {
            // Enregistrement de l'échec
            $stmtLogEchec = $pdo->prepare('
                INSERT INTO tentative_connexion (email, adresse_ip, succes, date_tentative)
                VALUES (:email, :ip, 0, NOW())
            ');
            $stmtLogEchec->execute([
                'email' => $email,
                'ip'    => $adresseIp,
            ]);

            // Message unique et générique dans tous les cas d'échec
            $erreur = 'Email ou mot de passe incorrect.';
        } else {
            // Contrôle du statut du compte
            if ($utilisateur['statut_compte'] === 'desactive') {
                // Enregistrement de la tentative
                $stmtLogDesactive = $pdo->prepare('
                    INSERT INTO tentative_connexion (email, adresse_ip, succes, date_tentative)
                    VALUES (:email, :ip, 0, NOW())
                ');
                $stmtLogDesactive->execute([
                    'email' => $email,
                    'ip'    => $adresseIp,
                ]);

                $erreur = 'Votre compte est désactivé. Veuillez contacter l\'administration pour plus d\'informations.';
            } else {
                // Connexion réussie : enregistrement du succès
                $stmtLogSucces = $pdo->prepare('
                    INSERT INTO tentative_connexion (email, adresse_ip, succes, date_tentative)
                    VALUES (:email, :ip, 1, NOW())
                ');
                $stmtLogSucces->execute([
                    'email' => $email,
                    'ip'    => $adresseIp,
                ]);

                // Renouvellement de l'identifiant de session contre la fixation
                regenerer_session();

                // Enregistrement de l'id, du rôle et du prénom en session
                $_SESSION['utilisateur'] = [
                    'id'             => (int) $utilisateur['id_utilisateur'],
                    'id_utilisateur' => (int) $utilisateur['id_utilisateur'],
                    'role'           => (string) $utilisateur['role'],
                    'prenom'         => (string) $utilisateur['prenom'],
                    'nom'            => (string) ($utilisateur['nom'] ?? ''),
                ];
                $_SESSION['dernier_acces'] = time();

                flash_set('success', 'Ravi de vous revoir, ' . $utilisateur['prenom'] . ' !');

                // Redirection prioritaire vers l'URL de retour interne validée
                if ($urlRetour !== null) {
                    redirect($urlRetour);
                }

                // Redirection selon le rôle
                if ($utilisateur['role'] === 'admin') {
                    redirect(url('admin/index.php'));
                } elseif ($utilisateur['role'] === 'professionnel') {
                    redirect(url('pro/index.php'));
                } else {
                    redirect(url('client/index.php'));
                }
            }
        }
    }
}

// 5. Affichage de la page de connexion
$titre_page = 'Connexion';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-5 col-xl-4">

            <div class="card p-4 p-md-5 shadow-sm border">
                <div class="text-center mb-4">
                    <h1 class="h3 fw-bold mb-2">Connexion</h1>
                    <p class="text-secondary small mb-0">
                        Accédez à votre espace FindPro en toute sécurité.
                    </p>
                </div>

                <?php if ($erreur !== null): ?>
                    <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5 flex-shrink-0"></i>
                        <div><?= e($erreur) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= url('login.php') ?>" novalidate>
                    <?= csrf_field() ?>

                    <?php if ($urlRetour !== null): ?>
                        <input type="hidden" name="retour" value="<?= e($urlRetour) ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="email" class="form-label fw-medium small">Adresse email</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-secondary"><i class="bi bi-envelope"></i></span>
                            <input 
                                type="email" 
                                class="form-control <?= $erreur !== null ? 'is-invalid' : '' ?>" 
                                id="email" 
                                name="email" 
                                value="<?= e($email) ?>" 
                                placeholder="nom@exemple.com" 
                                autocomplete="email" 
                                required
                                autofocus
                            >
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="mot_de_passe" class="form-label fw-medium small mb-0">Mot de passe</label>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-secondary"><i class="bi bi-lock"></i></span>
                            <input 
                                type="password" 
                                class="form-control <?= $erreur !== null ? 'is-invalid' : '' ?>" 
                                id="mot_de_passe" 
                                name="mot_de_passe" 
                                placeholder="Votre mot de passe" 
                                autocomplete="current-password" 
                                required
                            >
                        </div>
                    </div>

                    <button type="submit" class="btn btn-brand w-100 py-2 mb-3">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Se connecter
                    </button>

                    <div class="text-center small text-secondary">
                        Vous n'avez pas encore de compte ? 
                        <a href="<?= url('register.php' . ($urlRetour !== null ? '?retour=' . urlencode($urlRetour) : '')) ?>" class="fw-semibold text-primary">Créer un compte</a>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
