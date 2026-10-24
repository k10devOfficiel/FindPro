<?php
/**
 * Gestion dédiée des compétences du professionnel connecté — FindPro.
 * 
 * Règles et exigences :
 * - Accès strictement réservé aux professionnels authentifiés (exiger_role('professionnel')).
 * - Profil strictement dérivé de la session ($_SESSION['utilisateur']['id']).
 * - Liste déroulante des compétences de la catégorie du profil (table competence).
 * - Niveaux déclarés : debutant, intermediaire, avance, expert.
 * - Ajout et suppression protégés par CSRF et vérification d'appartenance.
 * - Contrôle anti-doublon (une même compétence une seule fois par profil).
 * - Maximum 15 compétences par profil.
 * - Aucune création libre de compétence (uniquement les compétences de référence du métier).
 * - Si la catégorie du profil change (géré sur pro/profil.php), purge automatique des compétences incompatibles.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

// 1. Authentification stricte du professionnel
demarrer_session();
$utilisateur = exiger_role('professionnel');
$idUtilisateur = (int) $utilisateur['id'];

$pdo = db();

// 2. Récupération du profil professionnel lié à la session
$stmtProfil = $pdo->prepare('
    SELECT p.*, c.libelle AS categorie_libelle, c.slug AS categorie_slug
    FROM profil_professionnel p
    LEFT JOIN categorie c ON p.id_categorie = c.id_categorie
    WHERE p.id_utilisateur = :id_user
    LIMIT 1
');
$stmtProfil->execute(['id_user' => $idUtilisateur]);
$profil = $stmtProfil->fetch();

$idProfil = $profil ? (int) $profil['id_profil'] : null;

// Configuration des badges et libellés par niveau déclaré
$labelsNiveaux = [
    'debutant'      => [
        'label' => 'Débutant',
        'badge' => 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25',
        'desc'  => 'Notions de base & premiers travaux',
    ],
    'intermediaire' => [
        'label' => 'Intermédiaire',
        'badge' => 'bg-info bg-opacity-10 text-info-emphasis border border-info border-opacity-25',
        'desc'  => 'Autonomie sur les missions courantes',
    ],
    'avance'        => [
        'label' => 'Avancé',
        'badge' => 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25',
        'desc'  => 'Maîtrise approfondie & projets complexes',
    ],
    'expert'        => [
        'label' => 'Expert',
        'badge' => 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25',
        'desc'  => 'Référence technique & résolution pointue',
    ],
];

// 3. Traitement des requêtes POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifier();

    if (!$profil) {
        flash_set('warning', 'Veuillez configurer et enregistrer votre profil professionnel avant d\'y associer des compétences.');
        redirect(url('pro/profil.php'));
    }

    $statutProfil = (string) ($profil['statut_validation'] ?? 'brouillon');
    if (in_array($statutProfil, ['soumis', 'en_verification'], true)) {
        flash_set('warning', 'Votre profil est actuellement en cours d\'examen par notre équipe. Les compétences sont verrouillées en lecture seule.');
        redirect(url('pro/competences.php'));
    }

    $action = (string) ($_POST['action'] ?? '');

    // -------------------------------------------------------------------------
    // ACTION 1 : AJOUT D'UNE COMPÉTENCE
    // -------------------------------------------------------------------------
    if ($action === 'ajouter_competence') {
        $idCompetence = (int) ($_POST['id_competence'] ?? 0);
        $niveauDeclare = (string) ($_POST['niveau_declare'] ?? 'intermediaire');

        // Validation stricte du niveau déclaré
        if (!array_key_exists($niveauDeclare, $labelsNiveaux)) {
            $niveauDeclare = 'intermediaire';
        }

        // Compter les compétences déjà associées (maximum 15)
        $stmtCountComp = $pdo->prepare('SELECT COUNT(*) FROM profil_competence WHERE id_profil = :id_profil');
        $stmtCountComp->execute(['id_profil' => $idProfil]);
        $totalCompetences = (int) $stmtCountComp->fetchColumn();

        if ($totalCompetences >= 15) {
            flash_set('danger', 'Limite atteinte : vous ne pouvez pas associer plus de 15 compétences à votre profil.');
            redirect(url('pro/competences.php'));
        }

        // Vérifier que la compétence appartient bien à la catégorie actuelle du profil (pas de création libre)
        $stmtVerifCompCat = $pdo->prepare('
            SELECT COUNT(*) 
            FROM competence 
            WHERE id_competence = :id_comp AND id_categorie = :id_cat
        ');
        $stmtVerifCompCat->execute([
            'id_comp' => $idCompetence,
            'id_cat'  => (int) $profil['id_categorie'],
        ]);

        if ((int) $stmtVerifCompCat->fetchColumn() === 0) {
            flash_set('danger', 'Cette compétence n\'appartient pas à votre corps de métier actuel.');
            redirect(url('pro/competences.php'));
        }

        // Contrôle anti-doublon (une même compétence une seule fois par profil)
        $stmtVerifDoublon = $pdo->prepare('
            SELECT COUNT(*) 
            FROM profil_competence 
            WHERE id_profil = :id_profil AND id_competence = :id_comp
        ');
        $stmtVerifDoublon->execute([
            'id_profil' => $idProfil,
            'id_comp'   => $idCompetence,
        ]);

        if ((int) $stmtVerifDoublon->fetchColumn() > 0) {
            flash_set('warning', 'Cette compétence est déjà présente dans votre profil.');
            redirect(url('pro/competences.php'));
        }

        // Insertion sécurisée
        $stmtInsertComp = $pdo->prepare('
            INSERT INTO profil_competence (id_profil, id_competence, niveau_declare)
            VALUES (:id_profil, :id_comp, :niveau)
        ');
        $stmtInsertComp->execute([
            'id_profil' => $idProfil,
            'id_comp'   => $idCompetence,
            'niveau'    => $niveauDeclare,
        ]);

        flash_set('success', 'Compétence ajoutée avec succès à votre profil.');
        redirect(url('pro/competences.php'));
    }

    // -------------------------------------------------------------------------
    // ACTION 2 : SUPPRESSION D'UNE COMPÉTENCE
    // -------------------------------------------------------------------------
    if ($action === 'supprimer_competence') {
        $idCompetence = (int) ($_POST['id_competence'] ?? 0);

        // Suppression avec vérification stricte d'appartenance au profil du pro connecté (anti-IDOR)
        $stmtSupprComp = $pdo->prepare('
            DELETE FROM profil_competence 
            WHERE id_profil = :id_profil AND id_competence = :id_comp
        ');
        $stmtSupprComp->execute([
            'id_profil' => $idProfil,
            'id_comp'   => $idCompetence,
        ]);

        if ($stmtSupprComp->rowCount() > 0) {
            flash_set('info', 'Compétence retirée de votre profil.');
        } else {
            flash_set('warning', 'La compétence demandée n\'a pas pu être retirée (introuvable dans votre profil).');
        }

        redirect(url('pro/competences.php'));
    }
}

// 4. Chargement des compétences associées et disponibles
$competencesProfil = [];
$competencesDisponibles = [];

if ($profil) {
    $catActuelle = (int) $profil['id_categorie'];

    // Compétences déjà associées au profil
    $stmtMesComp = $pdo->prepare('
        SELECT c.id_competence, c.libelle, pc.niveau_declare 
        FROM profil_competence pc
        JOIN competence c ON pc.id_competence = c.id_competence
        WHERE pc.id_profil = :id_profil
        ORDER BY c.libelle ASC
    ');
    $stmtMesComp->execute(['id_profil' => $idProfil]);
    $competencesProfil = $stmtMesComp->fetchAll();

    // Compétences disponibles dans la catégorie et non encore associées
    $stmtCompDispo = $pdo->prepare('
        SELECT id_competence, libelle 
        FROM competence 
        WHERE id_categorie = :id_cat 
          AND id_competence NOT IN (
              SELECT id_competence FROM profil_competence WHERE id_profil = :id_profil
          )
        ORDER BY libelle ASC
    ');
    $stmtCompDispo->execute([
        'id_cat'    => $catActuelle,
        'id_profil' => $idProfil,
    ]);
    $competencesDisponibles = $stmtCompDispo->fetchAll();
}

$nbCompetences = count($competencesProfil);
$limiteAtteinte = ($nbCompetences >= 15);
$statutProfil = (string) ($profil['statut_validation'] ?? 'brouillon');
$estLectureSeule = in_array($statutProfil, ['soumis', 'en_verification'], true);

$titre_page = 'Gestion des compétences';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4 py-lg-5">

    <?php if ($estLectureSeule): ?>
        <div class="alert alert-info d-flex align-items-center mb-4 shadow-sm border-info" role="alert">
            <i class="bi bi-shield-lock-fill fs-3 text-info me-3 flex-shrink-0"></i>
            <div>
                <strong class="d-block">Profil en cours d'examen administratif</strong>
                <span class="small">Vos compétences sont verrouillées en <strong>lecture seule</strong> pendant toute la durée de la vérification.</span>
            </div>
        </div>
    <?php endif; ?>

    <!-- En-tête principal -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3 border-bottom">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                <h1 class="h3 fw-bold mb-0">Mes compétences professionnelles</h1>
                <span class="badge <?= $nbCompetences >= 15 ? 'bg-danger' : ($nbCompetences >= 10 ? 'bg-warning text-dark' : 'bg-primary') ?> px-2.5 py-1.5 rounded-pill small font-monospace">
                    <?= $nbCompetences ?> / 15 compétences
                </span>
            </div>
            <p class="text-secondary small mb-0">
                Déclarez vos savoir-faire techniques pour valoriser votre profil auprès des donneurs d'ordres.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="<?= url('pro/index.php') ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Tableau de bord
            </a>
            <a href="<?= url('pro/profil.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-person-lines-fill me-1"></i>Mon profil
            </a>
            <a href="<?= url('pro/portfolio.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-images me-1"></i>Portfolio
            </a>
        </div>
    </div>

    <!-- Si profil inexistant -->
    <?php if (!$profil): ?>
        <div class="card p-4 p-md-5 text-center shadow-sm border mb-4">
            <div class="mb-3 text-secondary opacity-75">
                <i class="bi bi-person-exclamation fs-1 text-warning"></i>
            </div>
            <h2 class="h5 fw-bold mb-2">Configurez d'abord votre profil professionnel</h2>
            <p class="text-secondary small mx-auto mb-4" style="max-width: 520px;">
                Vous devez renseigner votre métier principal et vos informations professionnelles avant de pouvoir associer des compétences.
            </p>
            <div>
                <a href="<?= url('pro/profil.php') ?>" class="btn btn-brand">
                    <i class="bi bi-pencil-square me-1"></i>Créer mon profil professionnel
                </a>
            </div>
        </div>
    <?php else: ?>

        <!-- Bandeau d'information sur la catégorie et règle de purge -->
        <div class="card p-3 shadow-sm border mb-4 bg-white">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-briefcase text-primary fs-5"></i>
                    <span class="small fw-semibold text-secondary">
                        Corps de métier actuel :
                        <strong class="text-dark"><?= e($profil['categorie_libelle'] ?? 'Non défini') ?></strong>
                    </span>
                </div>
                <div class="small text-secondary">
                    <i class="bi bi-info-circle me-1"></i>Les compétences proposées sont strictement liées à cette catégorie.
                </div>
            </div>

            <!-- Jauge de capacité (max 15 compétences) -->
            <div class="d-flex justify-content-between align-items-center mb-1 small">
                <span class="text-secondary">
                    Capacité déclarative : <strong><?= $nbCompetences ?></strong> / 15
                </span>
                <span class="text-secondary">
                    <?= 15 - $nbCompetences ?> emplacement(s) libre(s)
                </span>
            </div>
            <div class="progress" style="height: 8px;" role="progressbar" aria-valuenow="<?= $nbCompetences ?>" aria-valuemin="0" aria-valuemax="15">
                <?php
                $pourcentage = round(($nbCompetences / 15) * 100);
                $barClass = $nbCompetences >= 15 ? 'bg-danger' : ($nbCompetences >= 10 ? 'bg-warning' : 'bg-primary');
                ?>
                <div class="progress-bar <?= $barClass ?> progress-bar-striped" style="width: <?= $pourcentage ?>%;"></div>
            </div>

            <?php if ($limiteAtteinte): ?>
                <small class="text-danger mt-2 d-block">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>Limite atteinte : vous avez renseigné le maximum de 15 compétences. Pour en ajouter une autre, veuillez en supprimer une de la liste ci-dessous.
                </small>
            <?php endif; ?>
        </div>

        <div class="row g-4">
            
            <!-- COLONNE 1 : Formulaire d'ajout de compétence -->
            <div class="col-12 col-lg-5">
                <div class="card p-4 shadow-sm border h-100">
                    <h2 class="h5 fw-bold mb-3 pb-2 border-bottom">
                        <i class="bi bi-plus-circle text-primary me-2"></i>Ajouter une compétence
                    </h2>

                    <?php if ($estLectureSeule): ?>
                        <div class="alert alert-secondary small mb-0">
                            <i class="bi bi-lock-fill me-1"></i>L'ajout de compétences est désactivé pendant que votre profil est en attente ou en cours d'examen.
                        </div>
                    <?php elseif ($limiteAtteinte): ?>
                        <div class="alert alert-warning small mb-0">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>Plafond atteint. Vous avez déjà associé 15 compétences à votre profil.
                        </div>
                    <?php elseif (empty($competencesDisponibles)): ?>
                        <div class="alert alert-info small mb-0">
                            <i class="bi bi-check-circle-fill me-1"></i>Félicitations, vous avez déjà associé toutes les compétences répertoriées pour votre métier (<strong><?= e($profil['categorie_libelle']) ?></strong>).
                        </div>
                    <?php else: ?>
                        <form method="POST" action="<?= url('pro/competences.php') ?>" novalidate>
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="ajouter_competence">

                            <div class="mb-3">
                                <label for="id_competence" class="form-label fw-semibold small mb-1">
                                    Compétence de votre métier <span class="text-danger">*</span>
                                </label>
                                <select name="id_competence" id="id_competence" class="form-select" required>
                                    <option value="" disabled selected>-- Sélectionnez une compétence --</option>
                                    <?php foreach ($competencesDisponibles as $comp): ?>
                                        <option value="<?= (int) $comp['id_competence'] ?>">
                                            <?= e($comp['libelle']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text small">
                                    Liste officielle issue de la catégorie <em><?= e($profil['categorie_libelle']) ?></em>. Aucune saisie libre.
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="niveau_declare" class="form-label fw-semibold small mb-1">
                                    Niveau d'expertise déclaré <span class="text-danger">*</span>
                                </label>
                                <select name="niveau_declare" id="niveau_declare" class="form-select" required>
                                    <option value="debutant">Débutant — Notions de base & premiers projets</option>
                                    <option value="intermediaire" selected>Intermédiaire — Autonome sur missions courantes</option>
                                    <option value="avance">Avancé — Maîtrise approfondie & projets complexes</option>
                                    <option value="expert">Expert — Référence technique & audit de pointe</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-brand w-100">
                                <i class="bi bi-plus-lg me-1"></i>Ajouter à mon profil
                            </button>
                        </form>
                    <?php endif; ?>

                    <div class="mt-4 pt-3 border-top bg-light p-3 rounded">
                        <h3 class="h6 fw-semibold mb-2 text-dark">Règles de gestion</h3>
                        <ul class="small text-secondary ps-3 mb-0">
                            <li>Pas de doublon : chaque compétence ne peut être associée qu'une seule fois.</li>
                            <li>Plafond maximum : 15 compétences par profil.</li>
                            <li>En cas de changement de métier sur <a href="<?= url('pro/profil.php') ?>">votre profil</a>, les compétences incompatibles sont automatiquement retirées.</li>
                        </ul>
                    </div>

                </div>
            </div>

            <!-- COLONNE 2 : Liste des compétences associées -->
            <div class="col-12 col-lg-7">
                <div class="card p-4 shadow-sm border h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <h2 class="h5 fw-bold mb-0">
                            <i class="bi bi-award text-primary me-2"></i>Compétences associées (<?= $nbCompetences ?>)
                        </h2>
                    </div>

                    <?php if (empty($competencesProfil)): ?>
                        <div class="text-center py-5 text-muted bg-light rounded border border-dashed">
                            <i class="bi bi-award fs-1 text-secondary opacity-50 d-block mb-2"></i>
                            <p class="fw-medium mb-1">Aucune compétence associée pour l'instant</p>
                            <small class="text-secondary">Sélectionnez une compétence dans le formulaire ci-contre pour enrichir votre profil.</small>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush border rounded">
                            <?php foreach ($competencesProfil as $cp): 
                                $cfgNiveau = $labelsNiveaux[$cp['niveau_declare']] ?? $labelsNiveaux['intermediaire'];
                            ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center py-3 px-3">
                                    <div class="d-flex flex-column gap-1">
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <span class="fw-semibold text-dark"><?= e($cp['libelle']) ?></span>
                                            <span class="badge <?= e($cfgNiveau['badge']) ?> rounded-pill font-monospace" style="font-size: 0.75rem;">
                                                <?= e($cfgNiveau['label']) ?>
                                            </span>
                                        </div>
                                        <small class="text-secondary" style="font-size: 0.8rem;">
                                            <?= e($cfgNiveau['desc']) ?>
                                        </small>
                                    </div>

                                    <?php if (!$estLectureSeule): ?>
                                        <form method="POST" action="<?= url('pro/competences.php') ?>" class="m-0 p-0" onsubmit="return confirm('Retirer la compétence « <?= e(addslashes($cp['libelle'])) ?> » de votre profil ?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="supprimer_competence">
                                            <input type="hidden" name="id_competence" value="<?= (int) $cp['id_competence'] ?>">
                                            <button 
                                                type="submit" 
                                                class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1"
                                                title="Retirer cette compétence"
                                                aria-label="Supprimer <?= e($cp['libelle']) ?>"
                                            >
                                                <i class="bi bi-trash3"></i>
                                                <span class="d-none d-sm-inline">Retirer</span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </div>

        </div>

    <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
