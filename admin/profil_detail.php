<?php
/**
 * Fiche détaillée et arbitrage administratif d'un profil professionnel — FindPro.
 * 
 * - Accès strictement réservé aux administrateurs (exiger_role('admin')).
 * - Paramètre GET 'id' numérique obligatoire, sinon code HTTP 404.
 * - Fiche complète : infos profil, compétences, portfolio, historique validation_profil,
 *   coordonnées privées visibles UNIQUEMENT ici pour l'administration.
 * - Automatisme : à l'ouverture d'un profil 'soumis', statut passe à 'en_verification'.
 * - Actions de décision (POST + CSRF) dans UNE TRANSACTION PDO :
 *     * Approuver (commentaire optionnel)
 *     * Refuser (motif >= 10 caractères obligatoire)
 *     * Demander une correction (motif >= 10 caractères obligatoire)
 *     * Suspendre (motif >= 10 caractères obligatoire)
 * - Transitions d'états strictement contrôlées côté serveur :
 *     * soumis / en_verification -> approuve, refuse, a_corriger
 *     * approuve -> suspendu
 *     * suspendu -> approuve (réactivation)
 *     * Tout autre changement est strictement interdit.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

// 1. Contrôle d'accès administrateur strict
demarrer_session();
$admin = exiger_role('admin');
$idAdmin = (int) $admin['id'];

$pdo = db();

// 2. Validation stricte du paramètre GET 'id'
$idRaw = $_GET['id'] ?? '';
if (!is_string($idRaw) || !ctype_digit($idRaw) || (int) $idRaw <= 0) {
    http_response_code(404);
    $titre_page = 'Profil introuvable — Erreur 404';
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="container py-5 text-center">
        <div class="card p-5 shadow-sm border mx-auto" style="max-width: 540px;">
            <i class="bi bi-exclamation-triangle fs-1 text-warning mb-3"></i>
            <h1 class="h4 fw-bold">Profil professionnel introuvable</h1>
            <p class="text-secondary small mb-4">L'identifiant de profil spécifié est invalide ou inexistant.</p>
            <a href="<?= url('admin/validation.php') ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Retour à la file de validation
            </a>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$idProfil = (int) $idRaw;

// 3. Récupération du profil et de son utilisateur associé
$stmt = $pdo->prepare('
    SELECT 
        p.*,
        u.id_utilisateur,
        u.nom,
        u.prenom,
        u.email,
        u.telephone,
        u.date_inscription,
        u.statut_compte,
        c.libelle AS categorie_libelle,
        c.slug AS categorie_slug,
        c.icone AS categorie_icone
    FROM profil_professionnel p
    JOIN utilisateur u ON p.id_utilisateur = u.id_utilisateur
    LEFT JOIN categorie c ON p.id_categorie = c.id_categorie
    WHERE p.id_profil = :id
    LIMIT 1
');
$stmt->execute(['id' => $idProfil]);
$profil = $stmt->fetch();

if (!$profil) {
    http_response_code(404);
    $titre_page = 'Profil non trouvé — Erreur 404';
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="container py-5 text-center">
        <div class="card p-5 shadow-sm border mx-auto" style="max-width: 540px;">
            <i class="bi bi-person-x fs-1 text-danger mb-3"></i>
            <h1 class="h4 fw-bold">Dossier introuvable</h1>
            <p class="text-secondary small mb-4">Ce profil professionnel n'existe pas en base de données.</p>
            <a href="<?= url('admin/validation.php') ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Retour à la file de validation
            </a>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// 4. AUTOMATISME : Si le profil est au statut 'soumis', passage à 'en_verification'
if ($profil['statut_validation'] === 'soumis') {
    $stmtAutoVerif = $pdo->prepare("UPDATE profil_professionnel SET statut_validation = 'en_verification' WHERE id_profil = :id");
    $stmtAutoVerif->execute(['id' => $idProfil]);
    $profil['statut_validation'] = 'en_verification';
}

$statutCourant = (string) $profil['statut_validation'];
$isCyber = ($profil['categorie_slug'] === 'cybersecurite');

// 5. Traitement des décisions administratives (POST + CSRF)
$erreurDecision = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifier();

    $decision = (string) ($_POST['decision'] ?? '');
    $motif = trim((string) ($_POST['motif'] ?? ''));

    // Validation des décisions permises par le schéma SQL
    $decisionsValides = ['approuve', 'refuse', 'a_corriger', 'suspendu'];
    if (!in_array($decision, $decisionsValides, true)) {
        $erreurDecision = "Décision administrative invalide.";
    }

    // Contrôle strict de la matrice des transitions autorisées :
    // - soumis / en_verification -> approuve, refuse, a_corriger
    // - approuve -> suspendu
    // - suspendu -> approuve (réactivation)
    $transitionAutorisee = false;
    if (in_array($statutCourant, ['soumis', 'en_verification'], true)) {
        if (in_array($decision, ['approuve', 'refuse', 'a_corriger'], true)) {
            $transitionAutorisee = true;
        }
    } elseif ($statutCourant === 'approuve') {
        if ($decision === 'suspendu') {
            $transitionAutorisee = true;
        }
    } elseif ($statutCourant === 'suspendu') {
        if ($decision === 'approuve') {
            $transitionAutorisee = true;
        }
    }

    if (!$transitionAutorisee && $erreurDecision === null) {
        $erreurDecision = "La transition du statut '{$statutCourant}' vers '{$decision}' n'est pas autorisée.";
    }

    // Contrôle de longueur du motif
    // Refuser, Demander une correction et Suspendre exigent au moins 10 caractères
    if (in_array($decision, ['refuse', 'a_corriger', 'suspendu'], true)) {
        if (mb_strlen($motif) < 10) {
            $erreurDecision = "Le motif est obligatoire et doit comporter au moins 10 caractères (actuellement " . mb_strlen($motif) . " caractère(s)).";
        }
    }

    // Exécution atomique de la décision dans UNE TRANSACTION PDO
    if ($erreurDecision === null) {
        try {
            $pdo->beginTransaction();

            // 1. Mise à jour de statut_validation et date_validation dans profil_professionnel
            $stmtUpdateProfil = $pdo->prepare('
                UPDATE profil_professionnel SET
                    statut_validation = :decision,
                    date_validation = NOW()
                WHERE id_profil = :id_profil
            ');
            $stmtUpdateProfil->execute([
                'decision'  => $decision,
                'id_profil' => $idProfil,
            ]);

            // 2. Insertion dans l\'historique validation_profil
            $stmtInsertLog = $pdo->prepare('
                INSERT INTO validation_profil (
                    id_profil,
                    id_administrateur,
                    decision,
                    motif,
                    date_decision
                ) VALUES (
                    :id_profil,
                    :id_admin,
                    :decision,
                    :motif,
                    NOW()
                )
            ');
            $stmtInsertLog->execute([
                'id_profil' => $idProfil,
                'id_admin'  => $idAdmin,
                'decision'  => $decision,
                'motif'     => $motif !== '' ? $motif : null,
            ]);

            $pdo->commit();

            // Libellés clairs de confirmation
            $messagesSucces = [
                'approuve'   => 'Le profil a été approuvé avec succès et est désormais visible publiquement.',
                'refuse'     => 'Le profil a été refusé avec motif notifié.',
                'a_corriger' => 'Une demande de correction a été formulée au professionnel.',
                'suspendu'   => 'Le profil professionnel a été suspendu.',
            ];

            flash_set('success', $messagesSucces[$decision] ?? 'Décision enregistrée.');
            redirect(url('admin/profil_detail.php?id=' . $idProfil));

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            log_erreur('Erreur transactionnelle lors de la décision admin sur profil #' . $idProfil . ' : ' . $e->getMessage());
            $erreurDecision = "Une erreur système est survenue lors de l'enregistrement de la décision. Rien n'a été modifié.";
        }
    }
}

// 6. Chargement des données détaillées
// A. Compétences
$stmtComps = $pdo->prepare('
    SELECT pc.niveau_declare, c.libelle
    FROM profil_competence pc
    JOIN competence c ON pc.id_competence = c.id_competence
    WHERE pc.id_profil = :id_profil
    ORDER BY c.libelle ASC
');
$stmtComps->execute(['id_profil' => $idProfil]);
$competences = $stmtComps->fetchAll();

// B. Projets portfolio
$stmtPortfolio = $pdo->prepare('
    SELECT * FROM projet_portfolio
    WHERE id_profil = :id_profil
    ORDER BY date_ajout DESC, id_projet DESC
');
$stmtPortfolio->execute(['id_profil' => $idProfil]);
$projets = $stmtPortfolio->fetchAll();

// C. Historique des décisions (validation_profil)
$stmtHistorique = $pdo->prepare('
    SELECT 
        vp.*,
        u.prenom AS admin_prenom,
        u.nom AS admin_nom,
        u.email AS admin_email
    FROM validation_profil vp
    JOIN utilisateur u ON vp.id_administrateur = u.id_utilisateur
    WHERE vp.id_profil = :id_profil
    ORDER BY vp.date_decision DESC, vp.id_validation DESC
');
$stmtHistorique->execute(['id_profil' => $idProfil]);
$historiqueDecisions = $stmtHistorique->fetchAll();

// Configuration des badges de niveau
$labelsNiveaux = [
    'debutant'      => ['label' => 'Débutant', 'badge' => 'bg-secondary text-white'],
    'intermediaire' => ['label' => 'Intermédiaire', 'badge' => 'bg-info text-dark'],
    'avance'        => ['label' => 'Avancé', 'badge' => 'bg-primary text-white'],
    'expert'        => ['label' => 'Expert', 'badge' => 'bg-success text-white'],
];

// Configuration des badges de statut
$badgesStatut = [
    'brouillon'       => ['label' => 'Brouillon', 'badge' => 'bg-secondary text-white', 'icon' => 'bi-pencil'],
    'soumis'          => ['label' => 'Soumis', 'badge' => 'bg-info text-dark', 'icon' => 'bi-hourglass-split'],
    'en_verification' => ['label' => 'En cours d\'examen', 'badge' => 'bg-warning text-dark', 'icon' => 'bi-search'],
    'a_corriger'      => ['label' => 'À corriger', 'badge' => 'bg-warning text-dark', 'icon' => 'bi-exclamation-circle'],
    'approuve'        => ['label' => 'Approuvé (Public)', 'badge' => 'bg-success text-white', 'icon' => 'bi-check-circle'],
    'refuse'          => ['label' => 'Refusé', 'badge' => 'bg-danger text-white', 'icon' => 'bi-x-circle'],
    'suspendu'        => ['label' => 'Suspendu', 'badge' => 'bg-dark text-white', 'icon' => 'bi-slash-circle'],
];
$badgeActuel = $badgesStatut[$statutCourant] ?? ['label' => $statutCourant, 'badge' => 'bg-light text-dark', 'icon' => 'bi-dot'];

// Résolution de l'avatar
$avatarDefautRel = 'assets/img/avatar-defaut.svg';
$avatarDefautExiste = file_exists(__DIR__ . '/../' . $avatarDefautRel);
$photoUrl = !empty($profil['photo']) && file_exists(__DIR__ . '/../' . $profil['photo']) ? url($profil['photo']) : null;
$avatarDefaut = $avatarDefautExiste ? url($avatarDefautRel) : null;

$titre_page = 'Dossier #' . $idProfil . ' — ' . $profil['prenom'] . ' ' . $profil['nom'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4 py-lg-5">

    <!-- Fil d'Ariane & Bouton retour -->
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-4 pb-2 border-bottom">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb small mb-1">
                    <li class="breadcrumb-item"><a href="<?= url('admin/index.php') ?>" class="text-decoration-none text-secondary">Administration</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('admin/validation.php') ?>" class="text-decoration-none text-secondary">Validation des profils</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Dossier #<?= (int) $idProfil ?></li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0">
                Arbitrage du dossier professionnel #<?= (int) $idProfil ?>
            </h1>
        </div>
        <div>
            <a href="<?= url('admin/validation.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Retour à la file
            </a>
        </div>
    </div>

    <!-- Alertes et Messages Flash -->
    <?php if ($erreurDecision !== null): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
            <strong>Erreur d'arbitrage :</strong> <?= e($erreurDecision) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
        </div>
    <?php endif; ?>

    <?php 
    $flashes = flash_get();
    foreach ($flashes as $flash): 
    ?>
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <?= e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
        </div>
    <?php endforeach; ?>

    <div class="row g-4">

        <!-- =================================================================== -->
        <!-- COLONNE GAUCHE : Fiche complète du profil                           -->
        <!-- =================================================================== -->
        <div class="col-12 col-lg-8">
            
            <!-- Carte 1 : Identité et présentation générale -->
            <div class="card shadow-sm border mb-4">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-3 mb-4 pb-3 border-bottom">
                        <div class="flex-shrink-0">
                            <?php if ($photoUrl !== null): ?>
                                <img src="<?= $photoUrl ?>" alt="Photo de <?= e($profil['prenom']) ?>" class="rounded-circle object-fit-cover border shadow-sm" style="width: 72px; height: 72px;">
                            <?php elseif ($avatarDefaut !== null): ?>
                                <img src="<?= $avatarDefaut ?>" alt="Avatar par défaut" class="rounded-circle object-fit-cover border shadow-sm" style="width: 72px; height: 72px;">
                            <?php else: ?>
                                <div class="rounded-circle bg-secondary bg-opacity-25 d-flex align-items-center justify-content-center border shadow-sm text-secondary" style="width: 72px; height: 72px;">
                                    <i class="bi bi-person fs-2"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <h2 class="h4 fw-bold text-dark mb-0">
                                    <?= e($profil['prenom'] . ' ' . $profil['nom']) ?>
                                </h2>
                                <span class="badge <?= e($badgeActuel['badge']) ?> px-2.5 py-1">
                                    <i class="bi <?= e($badgeActuel['icon']) ?> me-1"></i><?= e($badgeActuel['label']) ?>
                                </span>
                            </div>
                            <span class="fw-semibold text-primary d-block mb-1">
                                <?= e($profil['titre']) ?>
                            </span>
                            <div class="d-flex flex-wrap align-items-center gap-3 text-secondary small">
                                <span><i class="bi bi-briefcase me-1"></i><?= e($profil['categorie_libelle'] ?? 'Catégorie non assignée') ?></span>
                                <span><i class="bi bi-geo-alt me-1"></i><?= e($profil['zone'] ?? 'Non spécifiée') ?></span>
                                <span><i class="bi bi-clock me-1"></i>Disponibilité : <strong class="text-dark"><?= e($profil['disponibilite']) ?></strong></span>
                            </div>
                        </div>
                    </div>

                    <!-- Si catégorie cybersécurité : afficher l'engagement déontologique -->
                    <?php if ($isCyber): ?>
                        <div class="alert <?= !empty($profil['engagement_cyber']) ? 'alert-success border-success' : 'alert-danger border-danger' ?> small d-flex align-items-center gap-2 mb-4">
                            <i class="bi <?= !empty($profil['engagement_cyber']) ? 'bi-shield-check text-success' : 'bi-shield-x text-danger' ?> fs-4 flex-shrink-0"></i>
                            <div>
                                <strong>Engagement déontologique Cybersécurité :</strong>
                                <?php if (!empty($profil['engagement_cyber'])): ?>
                                    Attestation validée le <?= !empty($profil['date_engagement_cyber']) ? e(date('d/m/Y à H:i', strtotime($profil['date_engagement_cyber']))) : 'date non précisée' ?>.
                                    <span class="d-block text-muted" style="font-size: 0.76rem;">"Prestations autorisées par le propriétaire du système uniquement."</span>
                                <?php else: ?>
                                    <span class="text-danger fw-semibold">NON SIGNÉ ! Ce profil ne doit pas être approuvé sans engagement légal.</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Description complète -->
                    <div>
                        <h3 class="h6 fw-bold text-secondary text-uppercase tracking-wider mb-2">Description professionnelle :</h3>
                        <div class="p-3 bg-light rounded border text-dark" style="white-space: pre-wrap; font-size: 0.92rem; line-height: 1.6;"><?= e($profil['description']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Carte 2 : Compétences déclarées -->
            <div class="card shadow-sm border mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <span class="fw-semibold small text-uppercase text-secondary tracking-wider">
                        Compétences techniques déclarées (<?= count($competences) ?>)
                    </span>
                    <span class="badge bg-light text-secondary border small">Min 3 requises pour soumission</span>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($competences)): ?>
                        <p class="text-secondary small mb-0 fst-italic">Aucune compétence associée à ce profil.</p>
                    <?php else: ?>
                        <div class="row g-2">
                            <?php foreach ($competences as $c): 
                                $cfgNiveau = $labelsNiveaux[$c['niveau_declare']] ?? $labelsNiveaux['intermediaire'];
                            ?>
                                <div class="col-12 col-sm-6">
                                    <div class="p-2.5 bg-light rounded border d-flex justify-content-between align-items-center">
                                        <span class="small fw-semibold text-dark"><?= e($c['libelle']) ?></span>
                                        <span class="badge <?= e($cfgNiveau['badge']) ?> rounded-pill font-monospace" style="font-size: 0.72rem;">
                                            <?= e($cfgNiveau['label']) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Carte 3 : Projets du portfolio avec liens vérifiés -->
            <div class="card shadow-sm border mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <span class="fw-semibold small text-uppercase text-secondary tracking-wider">
                        Réalisations du portfolio (<?= count($projets) ?>)
                    </span>
                    <span class="badge bg-light text-secondary border small">Min 1 requis pour soumission</span>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($projets)): ?>
                        <p class="text-secondary small mb-0 fst-italic">Aucun projet enregistré dans le portfolio.</p>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($projets as $pj): 
                                $hasImage = !empty($pj['image']) && file_exists(__DIR__ . '/../' . $pj['image']);
                                $imageUrl = $hasImage ? url($pj['image']) : null;
                                $lienUrl = (string) ($pj['lien_externe'] ?? '');
                                $isSafeLien = str_starts_with($lienUrl, 'http://') || str_starts_with($lienUrl, 'https://');
                            ?>
                                <div class="p-3 bg-light rounded border">
                                    <div class="d-flex flex-column flex-sm-row gap-3">
                                        <?php if ($imageUrl !== null): ?>
                                            <div class="flex-shrink-0">
                                                <img src="<?= $imageUrl ?>" alt="Capture <?= e($pj['titre']) ?>" class="rounded border shadow-sm object-fit-cover" style="width: 120px; height: 80px;">
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <h4 class="h6 fw-bold text-dark mb-0"><?= e($pj['titre']) ?></h4>
                                                <?php if (!empty($pj['date_projet'])): ?>
                                                    <span class="text-muted small"><?= e(date('m/Y', strtotime($pj['date_projet']))) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($pj['description'])): ?>
                                                <p class="small text-secondary mb-2" style="white-space: pre-wrap;"><?= e($pj['description']) ?></p>
                                            <?php endif; ?>

                                            <?php if ($isSafeLien): ?>
                                                <div>
                                                    <a href="<?= e($lienUrl) ?>" target="_blank" rel="noopener noreferrer nofollow" class="btn btn-sm btn-outline-primary py-0.5 px-2 font-monospace" style="font-size: 0.78rem;">
                                                        <i class="bi bi-box-arrow-up-right me-1"></i><?= e($lienUrl) ?>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Carte 4 : Historique des décisions antérieures (validation_profil) -->
            <div class="card shadow-sm border mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <span class="fw-semibold small text-uppercase text-secondary tracking-wider">
                        Historique des décisions administratives (<?= count($historiqueDecisions) ?>)
                    </span>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($historiqueDecisions)): ?>
                        <div class="p-3 bg-light rounded text-center text-muted small">
                            <i class="bi bi-clock-history fs-3 d-block mb-1 opacity-50"></i>
                            Aucune décision administrative passée pour ce profil. Il s'agit d'un premier examen.
                        </div>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($historiqueDecisions as $h): 
                                $cfgDec = $badgesStatut[$h['decision']] ?? ['label' => $h['decision'], 'badge' => 'bg-secondary', 'icon' => 'bi-dot'];
                                $dateDec = !empty($h['date_decision']) ? date('d/m/Y à H:i', strtotime($h['date_decision'])) : 'Date inconnue';
                            ?>
                                <div class="p-3 mb-2 rounded border bg-light">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge <?= e($cfgDec['badge']) ?> small">
                                                <i class="bi <?= e($cfgDec['icon']) ?> me-1"></i><?= e($cfgDec['label']) ?>
                                            </span>
                                            <span class="small text-secondary">
                                                par <strong><?= e($h['admin_prenom'] . ' ' . $h['admin_nom']) ?></strong> (<?= e($h['admin_email']) ?>)
                                            </span>
                                        </div>
                                        <small class="text-muted font-monospace"><?= e($dateDec) ?></small>
                                    </div>
                                    <?php if (!empty($h['motif'])): ?>
                                        <div class="small text-dark mt-2 p-2 bg-white rounded border">
                                            <strong class="text-secondary d-block" style="font-size: 0.72rem;">MOTIF / COMMENTAIRE CONSIGNÉ :</strong>
                                            <?= e($h['motif']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- =================================================================== -->
        <!-- COLONNE DROITE : Coordonnées privées & Actions d'arbitrage          -->
        <!-- =================================================================== -->
        <div class="col-12 col-lg-4">

            <!-- Coordonnées privées (Strictement réservées à l'administrateur) -->
            <div class="card shadow-sm border border-warning mb-4">
                <div class="card-header bg-warning bg-opacity-10 py-3 border-bottom border-warning">
                    <span class="fw-bold small text-uppercase text-dark tracking-wider d-flex align-items-center gap-1.5">
                        <i class="bi bi-shield-lock text-warning-emphasis fs-5"></i>
                        <span>Coordonnées privées (Admin)</span>
                    </span>
                </div>
                <div class="card-body p-3.5 small">
                    <p class="text-secondary mb-3" style="font-size: 0.78rem;">
                        <i class="bi bi-info-circle me-1"></i>Ces coordonnées directes sont invisibles pour le public et réservées à l'audit administratif.
                    </p>
                    <ul class="list-unstyled mb-0 d-flex flex-column gap-2">
                        <li>
                            <strong class="text-secondary d-block" style="font-size: 0.72rem;">EMAIL DU COMPTE :</strong>
                            <a href="mailto:<?= e($profil['email']) ?>" class="text-dark fw-semibold text-break">
                                <?= e($profil['email']) ?>
                            </a>
                        </li>
                        <li>
                            <strong class="text-secondary d-block" style="font-size: 0.72rem;">TÉLÉPHONE DIRECT :</strong>
                            <span class="font-monospace fw-semibold text-dark">
                                <?= !empty($profil['telephone']) ? e($profil['telephone']) : '<em class="text-muted">Non renseigné</em>' ?>
                            </span>
                        </li>
                        <li>
                            <strong class="text-secondary d-block" style="font-size: 0.72rem;">DATE D'INSCRIPTION SUR LA PLATEFORME :</strong>
                            <span class="text-dark"><?= !empty($profil['date_inscription']) ? e(date('d/m/Y à H:i', strtotime($profil['date_inscription']))) : 'N/C' ?></span>
                        </li>
                        <li>
                            <strong class="text-secondary d-block" style="font-size: 0.72rem;">DATE DE SOUMISSION DU DOSSIER :</strong>
                            <span class="text-dark"><?= !empty($profil['date_soumission']) ? e(date('d/m/Y à H:i', strtotime($profil['date_soumission']))) : 'N/C' ?></span>
                        </li>
                        <?php if (!empty($profil['date_validation'])): ?>
                            <li>
                                <strong class="text-secondary d-block" style="font-size: 0.72rem;">DERNIÈRE DÉCISION / VALIDATION :</strong>
                                <span class="text-dark"><?= e(date('d/m/Y à H:i', strtotime($profil['date_validation']))) ?></span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- Panneau d'arbitrage administratif (POST + CSRF) -->
            <div class="card shadow-sm border border-primary sticky-top" style="top: 1rem; z-index: 10;">
                <div class="card-header bg-primary text-white py-3">
                    <h2 class="h6 fw-bold mb-0 d-flex align-items-center gap-1.5">
                        <i class="bi bi-patch-check"></i>
                        <span>Décision administrative</span>
                    </h2>
                </div>
                <div class="card-body p-4">
                    
                    <p class="small text-secondary mb-3">
                        Statut actuel du profil :
                        <span class="badge <?= e($badgeActuel['badge']) ?> ms-1">
                            <?= e($badgeActuel['label']) ?>
                        </span>
                    </p>

                    <!-- Formulaire unifié de prise de décision -->
                    <form method="POST" action="<?= url('admin/profil_detail.php?id=' . (int) $idProfil) ?>" id="form-arbitrage">
                        <?= csrf_field() ?>

                        <!-- 1. CAS : Profil soumis ou en_verification -->
                        <?php if (in_array($statutCourant, ['soumis', 'en_verification'], true)): ?>
                            
                            <div class="mb-3">
                                <label for="motif" class="form-label small fw-semibold text-secondary mb-1">
                                    Motif / Commentaire de décision :
                                </label>
                                <textarea 
                                    name="motif" 
                                    id="motif" 
                                    class="form-control form-control-sm" 
                                    rows="4" 
                                    placeholder="Motif obligatoire pour refus ou demande de correction (min 10 caractères). Optionnel pour approbation."
                                ></textarea>
                                <div class="form-text small" style="font-size: 0.72rem;">
                                    Exigé ($\ge 10$ caractères) si vous refusez ou demandez une correction.
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <!-- Bouton Approuver -->
                                <button type="submit" name="decision" value="approuve" class="btn btn-success btn-sm py-2 fw-semibold" onclick="return confirm('Confirmez-vous l\'approbation de ce profil ? Il deviendra visible publiquement.');">
                                    <i class="bi bi-check2-circle me-1"></i>Approuver le profil
                                </button>

                                <!-- Bouton Demander une correction -->
                                <button type="submit" name="decision" value="a_corriger" class="btn btn-warning btn-sm py-2 fw-semibold text-dark" onclick="return validerMotifObligatoire('une demande de correction');">
                                    <i class="bi bi-pencil-square me-1"></i>Demander une correction
                                </button>

                                <!-- Bouton Refuser -->
                                <button type="submit" name="decision" value="refuse" class="btn btn-danger btn-sm py-2 fw-semibold" onclick="return validerMotifObligatoire('le refus');">
                                    <i class="bi bi-x-circle me-1"></i>Refuser le profil
                                </button>
                            </div>

                        <!-- 2. CAS : Profil actuellement Approuvé -> Peut être Suspendu -->
                        <?php elseif ($statutCourant === 'approuve'): ?>

                            <div class="alert alert-success small py-2 mb-3">
                                <i class="bi bi-check-circle me-1"></i>Ce profil est actif et visible du public.
                            </div>

                            <div class="mb-3">
                                <label for="motif" class="form-label small fw-semibold text-secondary mb-1">
                                    Motif de suspension (obligatoire, min 10 car.) :
                                </label>
                                <textarea 
                                    name="motif" 
                                    id="motif" 
                                    class="form-control form-control-sm" 
                                    rows="3" 
                                    placeholder="Ex: Signalement pour manquement aux CGU, coordonnées frauduleuses..."
                                    required
                                ></textarea>
                            </div>

                            <div class="d-grid">
                                <button type="submit" name="decision" value="suspendu" class="btn btn-danger btn-sm py-2 fw-semibold" onclick="return validerMotifObligatoire('la suspension');">
                                    <i class="bi bi-slash-circle me-1"></i>Suspendre le profil
                                </button>
                            </div>

                        <!-- 3. CAS : Profil actuellement Suspendu -> Peut être Réactivé (Approuvé) -->
                        <?php elseif ($statutCourant === 'suspendu'): ?>

                            <div class="alert alert-dark small py-2 mb-3">
                                <i class="bi bi-slash-circle me-1"></i>Ce profil est actuellement suspendu.
                            </div>

                            <div class="mb-3">
                                <label for="motif" class="form-label small fw-semibold text-secondary mb-1">
                                    Commentaire de réactivation (optionnel) :
                                </label>
                                <textarea 
                                    name="motif" 
                                    id="motif" 
                                    class="form-control form-control-sm" 
                                    rows="2" 
                                    placeholder="Ex: Vérifications complémentaires concluantes, réactivation."
                                ></textarea>
                            </div>

                            <div class="d-grid">
                                <button type="submit" name="decision" value="approuve" class="btn btn-success btn-sm py-2 fw-semibold" onclick="return confirm('Confirmez-vous la réactivation de ce profil ?');">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Réactiver (Approuver)
                                </button>
                            </div>

                        <!-- 4. CAS : Déjà refusé ou à corriger -->
                        <?php else: ?>
                            <div class="alert alert-light border small text-secondary mb-0">
                                <i class="bi bi-info-circle me-1"></i>Ce profil est au statut <strong><?= e($statutCourant) ?></strong>. Aucune action immédiate n'est requise de votre part tant que le professionnel n'a pas soumis une nouvelle version.
                            </div>
                        <?php endif; ?>

                    </form>

                </div>
            </div>

        </div>

    </div>

</div>

<script>
function validerMotifObligatoire(actionNom) {
    const motifEl = document.getElementById('motif');
    const motif = motifEl ? motifEl.value.trim() : '';
    if (motif.length < 10) {
        alert('Un motif explicite d\'au moins 10 caractères est obligatoire pour ' + actionNom + ' (actuellement ' + motif.length + ' caractères).');
        if (motifEl) motifEl.focus();
        return false;
    }
    return confirm('Confirmez-vous ' + actionNom + ' de ce dossier professionnel ?');
}
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
