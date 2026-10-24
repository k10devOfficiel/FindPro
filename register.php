<?php
/**
 * Inscription sur la plateforme FindPro (Clients et Professionnels).
 * 
 * - Choix guidé du type de compte (Client ou Professionnel).
 * - Contrôles de validation stricts côté serveur.
 * - Sécurisation anti-CSRF et hachage sécurisé du mot de passe.
 * - Protection anti-énumération des adresses email.
 * - Initialisation de session durcie et redirection vers l'espace dédié.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/errors.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/csrf.php';

demarrer_session();

// 1. Si déjà connecté, rediriger selon le rôle
$utilisateurConnecte = utilisateur_courant();
if ($utilisateurConnecte !== null) {
    if ($utilisateurConnecte['role'] === 'professionnel') {
        redirect(url('pro/index.php'));
    } elseif ($utilisateurConnecte['role'] === 'admin') {
        redirect(url('admin/dashboard.php'));
    } else {
        redirect(url('client/index.php'));
    }
}

// 2. Gestion et validation de l'URL de retour (sécurisée contre Open Redirect)
$urlRetourBrute = trim((string) ($_POST['retour'] ?? $_GET['retour'] ?? $_POST['redirect'] ?? $_GET['redirect'] ?? ''));
$urlRetourValidee = null;

if ($urlRetourBrute !== '') {
    // Interdiction formelle des schémas absolus (http://, https://) et des protocol-relative (//)
    if (!str_starts_with($urlRetourBrute, '//') && !preg_match('#^[a-z]+://#i', $urlRetourBrute)) {
        if (str_starts_with($urlRetourBrute, '/') && !str_contains($urlRetourBrute, '\\')) {
            $urlRetourValidee = $urlRetourBrute;
        } elseif (!str_contains($urlRetourBrute, '..') && preg_match('#^[a-zA-Z0-9_\-\./\?=&]+$#', $urlRetourBrute)) {
            $urlRetourValidee = url($urlRetourBrute);
        }
    }
}

// 3. Initialisation des variables du formulaire
$role = 'client';
$nom = '';
$prenom = '';
$email = '';
$telephone = '';
$conditionsAcceptees = false;
$erreurs = [];

// 4. Traitement de la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification du jeton CSRF obligatoire
    csrf_verifier();

    // Récupération des données brutes
    $roleSaisi = trim((string) ($_POST['role'] ?? 'client'));
    $nom = trim((string) ($_POST['nom'] ?? ''));
    $prenom = trim((string) ($_POST['prenom'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $telephone = trim((string) ($_POST['telephone'] ?? ''));
    $motDePasse = (string) ($_POST['mot_de_passe'] ?? '');
    $motDePasseConfirme = (string) ($_POST['mot_de_passe_confirme'] ?? '');
    $conditionsAcceptees = isset($_POST['conditions']) && $_POST['conditions'] === '1';

    // Validation du rôle : uniquement 'client' ou 'professionnel' ('admin' strictement interdit)
    if ($roleSaisi === 'professionnel') {
        $role = 'professionnel';
    } elseif ($roleSaisi === 'client') {
        $role = 'client';
    } else {
        $erreurs['role'] = 'Veuillez sélectionner un type de compte valide (Client ou Professionnel).';
    }

    // Validation du nom (obligatoire, max 100 caractères)
    if ($nom === '') {
        $erreurs['nom'] = 'Le nom est obligatoire.';
    } elseif (mb_strlen($nom) > 100) {
        $erreurs['nom'] = 'Le nom ne peut pas dépasser 100 caractères.';
    }

    // Validation du prénom (obligatoire, max 100 caractères)
    if ($prenom === '') {
        $erreurs['prenom'] = 'Le prénom est obligatoire.';
    } elseif (mb_strlen($prenom) > 100) {
        $erreurs['prenom'] = 'Le prénom ne peut pas dépasser 100 caractères.';
    }

    // Validation de l'email (obligatoire, format valide, max 190 caractères)
    $emailUnique = true;
    if ($email === '') {
        $erreurs['email'] = 'L\'adresse email est obligatoire.';
    } elseif (mb_strlen($email) > 190) {
        $erreurs['email'] = 'L\'adresse email ne peut pas dépasser 190 caractères.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurs['email'] = 'Veuillez renseigner une adresse email valide.';
    } else {
        // Contrôle d'unicité en base de données avec requête préparée PDO
        try {
            $pdo = db();
            $stmt = $pdo->prepare('SELECT id_utilisateur FROM utilisateur WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $email]);
            $utilisateurExistant = $stmt->fetch();

            if ($utilisateurExistant) {
                $emailUnique = false;
                // Formulation générique pour ne pas divulguer l'existence de l'email
                $erreurs['general'] = 'Impossible de finaliser l\'inscription avec ces informations. Si vous possédez déjà un compte, veuillez vous connecter.';
                $erreurs['email'] = 'Cette adresse email ne peut pas être utilisée. Veuillez vérifier vos données.';
            }
        } catch (Throwable $e) {
            log_erreur('Erreur SQL vérification email dans register.php : ' . $e->getMessage());
            $erreurs['general'] = 'Une erreur technique est survenue. Veuillez réessayer ultérieurement.';
        }
    }

    // Validation du téléphone (optionnel, max 25 caractères, format simple)
    if ($telephone !== '') {
        if (mb_strlen($telephone) > 25) {
            $erreurs['telephone'] = 'Le numéro de téléphone ne peut pas dépasser 25 caractères.';
        } elseif (!preg_match('/^[0-9+\s().-]{6,25}$/', $telephone)) {
            $erreurs['telephone'] = 'Le format du numéro de téléphone est invalide (chiffres, espaces, +, -, parenthèses autorisés).';
        }
    }

    // Validation du mot de passe (obligatoire, min 10 caractères)
    if ($motDePasse === '') {
        $erreurs['mot_de_passe'] = 'Le mot de passe est obligatoire.';
    } elseif (mb_strlen($motDePasse) < 10) {
        $erreurs['mot_de_passe'] = 'Le mot de passe doit comporter au moins 10 caractères.';
    }

    // Validation de la confirmation du mot de passe
    if ($motDePasseConfirme === '') {
        $erreurs['mot_de_passe_confirme'] = 'Veuillez confirmer votre mot de passe.';
    } elseif ($motDePasse !== $motDePasseConfirme) {
        $erreurs['mot_de_passe_confirme'] = 'Les deux mots de passe ne correspondent pas.';
    }

    // Validation de la case obligatoire des règles d'usage
    if (!$conditionsAcceptees) {
        $erreurs['conditions'] = 'Vous devez accepter les règles d\'usage pour pouvoir créer votre compte.';
    }

    // Si aucune erreur, enregistrement en base de données
    if (empty($erreurs)) {
        try {
            $pdo = db();

            // Hachage sécurisé du mot de passe
            $motDePasseHash = password_hash($motDePasse, PASSWORD_DEFAULT);

            $stmtInsert = $pdo->prepare('
                INSERT INTO utilisateur (nom, prenom, email, telephone, mot_de_passe_hash, role, statut_compte)
                VALUES (:nom, :prenom, :email, :telephone, :mot_de_passe_hash, :role, \'actif\')
            ');

            $stmtInsert->execute([
                'nom'               => $nom,
                'prenom'            => $prenom,
                'email'             => $email,
                'telephone'         => $telephone !== '' ? $telephone : null,
                'mot_de_passe_hash' => $motDePasseHash,
                'role'              => $role,
            ]);

            $idNouvelUtilisateur = (int) $pdo->lastInsertId();

            // Renouvellement de l'ID de session après connexion / inscription
            regenerer_session();

            // Enregistrement des informations minimales de l'utilisateur en session (SANS mot de passe ni hash)
            $_SESSION['utilisateur'] = [
                'id'             => $idNouvelUtilisateur,
                'id_utilisateur' => $idNouvelUtilisateur,
                'prenom'         => $prenom,
                'nom'            => $nom,
                'role'           => $role,
            ];
            $_SESSION['dernier_acces'] = time();

            // Message flash de confirmation
            flash_set('success', 'Bienvenue sur FindPro ! Votre compte a été créé avec succès.');

            // Redirection vers l'URL de retour ou vers l'espace dédié
            if ($urlRetourValidee !== null) {
                redirect($urlRetourValidee);
            }

            if ($role === 'professionnel') {
                redirect(url('pro/index.php'));
            } else {
                redirect(url('client/index.php'));
            }
        } catch (Throwable $e) {
            log_erreur('Erreur SQL insertion utilisateur dans register.php : ' . $e->getMessage());
            $erreurs['general'] = 'Une erreur inattendue est survenue lors de la création de votre compte. Veuillez réessayer.';
        }
    }
}

// 5. Affichage du gabarit HTML
$titre_page = 'Créer un compte';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-7 col-xl-6">

            <div class="card p-4 p-md-5 shadow-sm border">
                <div class="text-center mb-4">
                    <h1 class="h3 fw-bold mb-2">Rejoindre FindPro</h1>
                    <p class="text-secondary small mb-0">
                        Créez votre compte en quelques instants pour entrer en relation avec les experts du numérique.
                    </p>
                </div>

                <?php if (!empty($erreurs['general'])): ?>
                    <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                        <div><?= e($erreurs['general']) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= url('register.php') ?>" novalidate>
                    <?= csrf_field() ?>

                    <?php if ($urlRetourValidee !== null): ?>
                        <input type="hidden" name="retour" value="<?= e($urlRetourValidee) ?>">
                    <?php endif; ?>

                    <!-- Choix guidé du type de compte (cartes radio) -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold d-block mb-2">
                            Vous souhaitez vous inscrire en tant que :
                        </label>
                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <label class="card h-100 p-3 role-option text-center cursor-pointer border <?= $role === 'client' ? 'border-primary bg-light' : '' ?>" for="role_client">
                                    <div class="form-check p-0 d-flex flex-column align-items-center">
                                        <input class="form-check-input d-none" type="radio" name="role" id="role_client" value="client" <?= $role === 'client' ? 'checked' : '' ?>>
                                        <div class="rounded-circle bg-white shadow-sm p-3 mb-2 text-primary">
                                            <i class="bi bi-person fs-3"></i>
                                        </div>
                                        <span class="fw-bold d-block text-dark">Client</span>
                                        <span class="small text-secondary mt-1">
                                            Je cherche un professionnel pour un projet numérique.
                                        </span>
                                    </div>
                                </label>
                            </div>

                            <div class="col-12 col-sm-6">
                                <label class="card h-100 p-3 role-option text-center cursor-pointer border <?= $role === 'professionnel' ? 'border-primary bg-light' : '' ?>" for="role_professionnel">
                                    <div class="form-check p-0 d-flex flex-column align-items-center">
                                        <input class="form-check-input d-none" type="radio" name="role" id="role_professionnel" value="professionnel" <?= $role === 'professionnel' ? 'checked' : '' ?>>
                                        <div class="rounded-circle bg-white shadow-sm p-3 mb-2 text-primary">
                                            <i class="bi bi-briefcase fs-3"></i>
                                        </div>
                                        <span class="fw-bold d-block text-dark">Professionnel</span>
                                        <span class="small text-secondary mt-1">
                                            Je propose mes services (dev, vidéo, cybersécurité).
                                        </span>
                                    </div>
                                </label>
                            </div>
                        </div>
                        <?php if (!empty($erreurs['role'])): ?>
                            <div class="text-danger small mt-2">
                                <i class="bi bi-exclamation-circle me-1"></i><?= e($erreurs['role']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Identité : Nom et Prénom -->
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label for="nom" class="form-label fw-medium small">Nom <span class="text-danger">*</span></label>
                            <input 
                                type="text" 
                                class="form-control <?= isset($erreurs['nom']) ? 'is-invalid' : '' ?>" 
                                id="nom" 
                                name="nom" 
                                value="<?= e($nom) ?>" 
                                maxlength="100" 
                                placeholder="Dupont" 
                                required
                            >
                            <?php if (isset($erreurs['nom'])): ?>
                                <div class="invalid-feedback"><?= e($erreurs['nom']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12 col-sm-6">
                            <label for="prenom" class="form-label fw-medium small">Prénom <span class="text-danger">*</span></label>
                            <input 
                                type="text" 
                                class="form-control <?= isset($erreurs['prenom']) ? 'is-invalid' : '' ?>" 
                                id="prenom" 
                                name="prenom" 
                                value="<?= e($prenom) ?>" 
                                maxlength="100" 
                                placeholder="Jean" 
                                required
                            >
                            <?php if (isset($erreurs['prenom'])): ?>
                                <div class="invalid-feedback"><?= e($erreurs['prenom']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Coordonnées : Email et Téléphone -->
                    <div class="mb-3">
                        <label for="email" class="form-label fw-medium small">Adresse email <span class="text-danger">*</span></label>
                        <div class="input-group has-validation">
                            <span class="input-group-text bg-white text-secondary"><i class="bi bi-envelope"></i></span>
                            <input 
                                type="email" 
                                class="form-control <?= isset($erreurs['email']) ? 'is-invalid' : '' ?>" 
                                id="email" 
                                name="email" 
                                value="<?= e($email) ?>" 
                                maxlength="190" 
                                placeholder="nom@exemple.com" 
                                autocomplete="email" 
                                required
                            >
                            <?php if (isset($erreurs['email'])): ?>
                                <div class="invalid-feedback"><?= e($erreurs['email']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="telephone" class="form-label fw-medium small">
                            Téléphone <span class="text-muted fw-normal">(optionnel)</span>
                        </label>
                        <div class="input-group has-validation">
                            <span class="input-group-text bg-white text-secondary"><i class="bi bi-telephone"></i></span>
                            <input 
                                type="tel" 
                                class="form-control <?= isset($erreurs['telephone']) ? 'is-invalid' : '' ?>" 
                                id="telephone" 
                                name="telephone" 
                                value="<?= e($telephone) ?>" 
                                maxlength="25" 
                                placeholder="+225 07 00 00 00 00"
                                autocomplete="tel"
                            >
                            <?php if (isset($erreurs['telephone'])): ?>
                                <div class="invalid-feedback"><?= e($erreurs['telephone']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Sécurité : Mot de passe et Confirmation -->
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label for="mot_de_passe" class="form-label fw-medium small">Mot de passe <span class="text-danger">*</span></label>
                            <input 
                                type="password" 
                                class="form-control <?= isset($erreurs['mot_de_passe']) ? 'is-invalid' : '' ?>" 
                                id="mot_de_passe" 
                                name="mot_de_passe" 
                                minlength="10" 
                                placeholder="Au moins 10 caractères" 
                                autocomplete="new-password" 
                                required
                            >
                            <?php if (isset($erreurs['mot_de_passe'])): ?>
                                <div class="invalid-feedback"><?= e($erreurs['mot_de_passe']) ?></div>
                            <?php else: ?>
                                <div class="form-text small">10 caractères minimum.</div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12 col-sm-6">
                            <label for="mot_de_passe_confirme" class="form-label fw-medium small">Confirmation <span class="text-danger">*</span></label>
                            <input 
                                type="password" 
                                class="form-control <?= isset($erreurs['mot_de_passe_confirme']) ? 'is-invalid' : '' ?>" 
                                id="mot_de_passe_confirme" 
                                name="mot_de_passe_confirme" 
                                placeholder="Confirmez le mot de passe" 
                                autocomplete="new-password" 
                                required
                            >
                            <?php if (isset($erreurs['mot_de_passe_confirme'])): ?>
                                <div class="invalid-feedback"><?= e($erreurs['mot_de_passe_confirme']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Règles d'usage obligatoires -->
                    <div class="mb-4 pt-2">
                        <div class="form-check">
                            <input 
                                class="form-check-input <?= isset($erreurs['conditions']) ? 'is-invalid' : '' ?>" 
                                type="checkbox" 
                                id="conditions" 
                                name="conditions" 
                                value="1" 
                                <?= $conditionsAcceptees ? 'checked' : '' ?> 
                                required
                            >
                            <label class="form-check-label small" for="conditions">
                                J'accepte les <a href="<?= url('regles.php') ?>" target="_blank" class="text-decoration-underline text-primary">règles d'utilisation</a>, les <a href="<?= url('mentions.php') ?>" target="_blank" class="text-decoration-underline text-primary">mentions légales</a> et la <a href="<?= url('confidentialite.php') ?>" target="_blank" class="text-decoration-underline text-primary">politique de confidentialité</a> de FindPro. <span class="text-danger">*</span>
                            </label>
                            <?php if (isset($erreurs['conditions'])): ?>
                                <div class="invalid-feedback d-block"><?= e($erreurs['conditions']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Bouton de soumission -->
                    <button type="submit" class="btn btn-brand w-100 py-2 mb-3">
                        <i class="bi bi-person-check me-2"></i>Créer mon compte
                    </button>

                    <div class="text-center small text-secondary">
                        Vous avez déjà un compte ? 
                        <a href="<?= url('login.php' . ($urlRetourValidee !== null ? '?retour=' . urlencode($urlRetourValidee) : '')) ?>" class="fw-semibold text-primary">Se connecter</a>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<script>
// Script léger pour synchroniser l'apparence des cartes de rôles avec les radios
document.querySelectorAll('input[name="role"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.role-option').forEach(function(card) {
            card.classList.remove('border-primary', 'bg-light');
        });
        var label = document.querySelector('label[for="' + this.id + '"]');
        if (label) {
            label.classList.add('border-primary', 'bg-light');
        }
    });
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
