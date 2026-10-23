<?php
/**
 * Édition et gestion du profil professionnel et de ses compétences — FindPro.
 * 
 * - Accès strictement réservé aux professionnels authentifiés (exiger_role('professionnel')).
 * - L'identifiant du profil est STRICTEMENT dérivé de la session ($_SESSION['utilisateur']['id']).
 * - Aucun paramètre d'URL n'est accepté pour cibler un profil (protection anti-IDOR).
 * - Enregistrement en brouillon possible même si incomplet.
 * - Téléversement sécurisé de photo de profil (televerser_image dans uploads/avatars/).
 *   Remplacement de la photo supprime l'ancien fichier du disque.
 *   Image par défaut affichée si aucune photo (avec espace réservé gris si le visuel est absent).
 * - Règle RG06 : modification d'un profil approuvé réinitialise le statut à 'soumis' et masque de la recherche.
 * - Gestion des compétences associées : max 15, pas de doublon, suppression protégée par CSRF,
 *   purge automatique si changement de catégorie de métier avec message explicatif.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/upload.php';

// RÈGLE OBLIGATOIRE : exiger_role('professionnel') en tête
demarrer_session();
$utilisateur = exiger_role('professionnel');
$idUtilisateur = (int) $utilisateur['id'];

$pdo = db();

// 1. Récupération du profil professionnel existant (toujours via l'ID de session)
$stmtProfil = $pdo->prepare('
    SELECT p.*, c.libelle AS categorie_libelle 
    FROM profil_professionnel p
    LEFT JOIN categorie c ON p.id_categorie = c.id_categorie
    WHERE p.id_utilisateur = :id_user
    LIMIT 1
');
$stmtProfil->execute(['id_user' => $idUtilisateur]);
$profil = $stmtProfil->fetch();

// 2. Chargement des catégories disponibles
$categories = $pdo->query('SELECT id_categorie, libelle FROM categorie ORDER BY libelle ASC')->fetchAll();

// 3. Initialisation des champs du formulaire de profil
$idCategorie = $profil ? (int) $profil['id_categorie'] : ($categories[0]['id_categorie'] ?? 0);
$titre = $profil ? (string) $profil['titre'] : '';
$description = $profil ? (string) $profil['description'] : '';
$zone = $profil ? (string) $profil['zone'] : '';
$disponibilite = $profil ? (string) $profil['disponibilite'] : 'disponible';
$photoActuelle = $profil && !empty($profil['photo']) ? (string) $profil['photo'] : null;
$statutValidation = $profil ? (string) $profil['statut_validation'] : 'brouillon';

$erreurs = [];

// 4. Traitement des formulaires POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifier();

    $action = (string) ($_POST['action'] ?? 'brouillon');

    // -------------------------------------------------------------------------
    // CAS A : Ajout d'une compétence
    // -------------------------------------------------------------------------
    if ($action === 'ajouter_competence') {
        if (!$profil) {
            flash_set('warning', 'Veuillez d\'abord enregistrer votre profil ci-dessous avant d\'y associer des compétences.');
            redirect(url('pro/profil.php'));
        }

        $idProfil = (int) $profil['id_profil'];
        $idCompetence = (int) ($_POST['id_competence'] ?? 0);
        $niveauDeclare = (string) ($_POST['niveau_declare'] ?? 'intermediaire');

        // Validation du niveau
        if (!in_array($niveauDeclare, ['debutant', 'intermediaire', 'avance', 'expert'], true)) {
            $niveauDeclare = 'intermediaire';
        }

        // Compter les compétences déjà associées (maximum 15)
        $stmtCountComp = $pdo->prepare('SELECT COUNT(*) FROM profil_competence WHERE id_profil = :id_profil');
        $stmtCountComp->execute(['id_profil' => $idProfil]);
        $totalCompetences = (int) $stmtCountComp->fetchColumn();

        if ($totalCompetences >= 15) {
            flash_set('danger', 'Limite atteinte : vous ne pouvez pas associer plus de 15 compétences à votre profil.');
            redirect(url('pro/profil.php#competences'));
        }

        // Vérifier que la compétence appartient bien à la catégorie actuelle du profil
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
            redirect(url('pro/profil.php#competences'));
        }

        // Vérifier qu'elle n'est pas déjà associée (anti-doublon)
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
            redirect(url('pro/profil.php#competences'));
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
        redirect(url('pro/profil.php#competences'));
    }

    // -------------------------------------------------------------------------
    // CAS B : Suppression d'une compétence
    // -------------------------------------------------------------------------
    if ($action === 'supprimer_competence') {
        if (!$profil) {
            redirect(url('pro/profil.php'));
        }

        $idProfil = (int) $profil['id_profil'];
        $idCompetence = (int) ($_POST['id_competence'] ?? 0);

        // Suppression avec vérification stricte d'appartenance au profil du pro connecté
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
        }

        redirect(url('pro/profil.php#competences'));
    }

    // -------------------------------------------------------------------------
    // CAS C : Enregistrement ou mise à jour du profil (Brouillon / Soumission)
    // -------------------------------------------------------------------------
    $idCategorie = (int) ($_POST['id_categorie'] ?? 0);
    $titre = trim((string) ($_POST['titre'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $zone = trim((string) ($_POST['zone'] ?? ''));
    $disponibiliteSaisie = (string) ($_POST['disponibilite'] ?? 'disponible');

    // Validation de la disponibilité
    if (in_array($disponibiliteSaisie, ['disponible', 'limitee', 'indisponible'], true)) {
        $disponibilite = $disponibiliteSaisie;
    } else {
        $disponibilite = 'disponible';
    }

    // Vérification de l'existence de la catégorie
    $stmtVerifCat = $pdo->prepare('SELECT COUNT(*) FROM categorie WHERE id_categorie = :id');
    $stmtVerifCat->execute(['id' => $idCategorie]);
    if ((int) $stmtVerifCat->fetchColumn() === 0) {
        $erreurs['id_categorie'] = 'Veuillez sélectionner une catégorie valide.';
    }

    // Validation des longueurs maximales
    if (mb_strlen($titre) > 120) {
        $erreurs['titre'] = 'Le titre ne peut pas dépasser 120 caractères.';
    }

    if (mb_strlen($description) > 2000) {
        $erreurs['description'] = 'La description ne peut pas dépasser 2000 caractères.';
    }

    if (mb_strlen($zone) > 120) {
        $erreurs['zone'] = 'La ville ou zone d\'intervention ne peut pas dépasser 120 caractères.';
    }

    // Si l'utilisateur clique sur "Soumettre pour validation", tous les champs obligatoires sont requis
    if ($action === 'soumettre') {
        if ($titre === '') {
            $erreurs['titre'] = 'Le titre professionnel est obligatoire pour soumettre votre profil.';
        }
        if ($description === '') {
            $erreurs['description'] = 'La description de vos compétences et prestations est obligatoire.';
        } elseif (mb_strlen($description) < 100) {
            $erreurs['description'] = 'La description doit comporter au moins 100 caractères pour être soumise (actuellement ' . mb_strlen($description) . ' caractères).';
        }
        if ($zone === '') {
            $erreurs['zone'] = 'La ville ou zone d\'intervention est obligatoire.';
        }
    }

    // Gestion du téléversement de la photo de profil (optionnelle)
    $nouvellePhotoChemin = null;
    $supprimerPhotoDemandee = isset($_POST['supprimer_photo']) && $_POST['supprimer_photo'] === '1';

    if (isset($_FILES['photo']) && is_array($_FILES['photo']) && (int) ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        try {
            // Téléversement sécurisé dans uploads/avatars/
            $nouvellePhotoChemin = televerser_image($_FILES['photo'], 'avatars');

            // Si un ancien fichier existait sur le disque, le supprimer
            if ($photoActuelle !== null) {
                $cheminAncien = realpath(__DIR__ . '/../' . $photoActuelle);
                $racineUploads = realpath(__DIR__ . '/../uploads');
                if ($cheminAncien !== false && $racineUploads !== false && str_starts_with($cheminAncien, $racineUploads) && file_exists($cheminAncien)) {
                    @unlink($cheminAncien);
                }
            }
            $photoActuelle = $nouvellePhotoChemin;
        } catch (Exception $e) {
            $erreurs['photo'] = $e->getMessage();
        }
    } elseif ($supprimerPhotoDemandee && $photoActuelle !== null) {
        // Demande explicite de suppression de la photo
        $cheminAncien = realpath(__DIR__ . '/../' . $photoActuelle);
        $racineUploads = realpath(__DIR__ . '/../uploads');
        if ($cheminAncien !== false && $racineUploads !== false && str_starts_with($cheminAncien, $racineUploads) && file_exists($cheminAncien)) {
            @unlink($cheminAncien);
        }
        $photoActuelle = null;
    }

    // Si aucune erreur bloquante
    if (empty($erreurs)) {
        $nouveauStatut = $statutValidation;
        $messageRg06Declenche = false;
        $nbCompetencesPurgees = 0;

        if ($action === 'soumettre') {
            $nouveauStatut = 'soumis';
        } elseif ($action === 'brouillon') {
            // RÈGLE RG06 : Si le profil était déjà approuvé et qu'une information clé est modifiée
            if ($profil && $profil['statut_validation'] === 'approuve') {
                $aModifieCategorie = ((int) $profil['id_categorie'] !== $idCategorie);
                $aModifieTitre = ($profil['titre'] !== $titre);
                $aModifieDescription = ($profil['description'] !== $description);

                if ($aModifieCategorie || $aModifieTitre || $aModifieDescription) {
                    $nouveauStatut = 'soumis';
                    $messageRg06Declenche = true;
                }
            } elseif (!$profil || $profil['statut_validation'] === 'brouillon' || $profil['statut_validation'] === 'a_corriger') {
                $nouveauStatut = 'brouillon';
            }
        }

        if (!$profil) {
            // Création initiale du profil (INSERT)
            $stmtInsert = $pdo->prepare('
                INSERT INTO profil_professionnel (
                    id_utilisateur, id_categorie, titre, description, zone, disponibilite, photo, statut_validation, date_soumission
                ) VALUES (
                    :id_user, :id_cat, :titre, :description, :zone, :dispo, :photo, :statut, :date_soumission
                )
            ');
            $stmtInsert->execute([
                'id_user'         => $idUtilisateur,
                'id_cat'          => $idCategorie,
                'titre'           => $titre !== '' ? $titre : 'Professionnel du numérique',
                'description'     => $description,
                'zone'            => $zone !== '' ? $zone : 'Abidjan',
                'dispo'           => $disponibilite,
                'photo'           => $photoActuelle,
                'statut'          => $nouveauStatut,
                'date_soumission' => ($nouveauStatut === 'soumis') ? date('Y-m-d H:i:s') : null,
            ]);

            flash_set('success', 'Votre profil a été créé en mode brouillon.');
        } else {
            $idProfil = (int) $profil['id_profil'];
            $ancienneCategorie = (int) $profil['id_categorie'];

            // RÈGLE : Si la catégorie du profil change, retirer les compétences incompatibles
            if ($ancienneCategorie !== $idCategorie) {
                $stmtPurge = $pdo->prepare('
                    DELETE FROM profil_competence 
                    WHERE id_profil = :id_profil 
                      AND id_competence IN (
                          SELECT id_competence FROM competence WHERE id_categorie != :nouveau_id_cat OR id_categorie IS NULL
                      )
                ');
                $stmtPurge->execute([
                    'id_profil'       => $idProfil,
                    'nouveau_id_cat'  => $idCategorie,
                ]);
                $nbCompetencesPurgees = $stmtPurge->rowCount();
            }

            // Mise à jour du profil existant (UPDATE strict sur l'ID de session)
            $stmtUpdate = $pdo->prepare('
                UPDATE profil_professionnel SET
                    id_categorie = :id_cat,
                    titre = :titre,
                    description = :description,
                    zone = :zone,
                    disponibilite = :dispo,
                    photo = :photo,
                    statut_validation = :statut,
                    date_soumission = CASE WHEN :statut_est_soumis = 1 THEN NOW() ELSE date_soumission END
                WHERE id_utilisateur = :id_user
            ');
            $stmtUpdate->execute([
                'id_cat'             => $idCategorie,
                'titre'              => $titre,
                'description'        => $description,
                'zone'               => $zone,
                'dispo'              => $disponibilite,
                'photo'              => $photoActuelle,
                'statut'             => $nouveauStatut,
                'statut_est_soumis'  => ($nouveauStatut === 'soumis') ? 1 : 0,
                'id_user'            => $idUtilisateur,
            ]);

            if ($nbCompetencesPurgees > 0) {
                flash_set('info', "Votre corps de métier principal a été modifié : {$nbCompetencesPurgees} compétence(s) ne correspondant plus à cette catégorie ont été automatiquement retirées de votre profil.");
            }

            if ($messageRg06Declenche) {
                flash_set('warning', 'Règle RG06 appliquée : Vos modifications sur vos informations clés nécessitent une nouvelle vérification administrative. Votre profil est temporairement retiré de la recherche publique.');
            } elseif ($nouveauStatut === 'soumis') {
                flash_set('success', 'Votre profil a été soumis à l\'équipe de modération FindPro. Vous recevrez une notification dès sa validation.');
            } else {
                flash_set('success', 'Vos modifications ont été enregistrées avec succès.');
            }
        }

        redirect(url('pro/profil.php'));
    }
}

// 5. Chargement des compétences associées et disponibles (si profil existant)
$competencesProfil = [];
$competencesDisponibles = [];

if ($profil) {
    $idProfil = (int) $profil['id_profil'];
    $catActuelle = (int) $profil['id_categorie'];

    // Compétences déjà associées
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

// 6. Configuration visuelle du badge de statut
$statutCourant = $profil ? $profil['statut_validation'] : 'brouillon';
$badgesStatut = [
    'brouillon'       => ['label' => 'Brouillon', 'class' => 'bg-secondary text-white', 'icon' => 'bi-pencil-square'],
    'soumis'          => ['label' => 'En attente de validation', 'class' => 'bg-info text-dark', 'icon' => 'bi-hourglass-split'],
    'en_verification' => ['label' => 'En cours d\'examen', 'class' => 'bg-warning text-dark', 'icon' => 'bi-search'],
    'a_corriger'      => ['label' => 'Corrections demandées', 'class' => 'bg-warning text-dark', 'icon' => 'bi-exclamation-circle'],
    'approuve'        => ['label' => 'Profil vérifié & public', 'class' => 'bg-success text-white', 'icon' => 'bi-check-circle-fill'],
    'refuse'          => ['label' => 'Profil refusé', 'class' => 'bg-danger text-white', 'icon' => 'bi-x-circle-fill'],
    'suspendu'        => ['label' => 'Profil suspendu', 'class' => 'bg-dark text-white', 'icon' => 'bi-slash-circle-fill'],
];
$badgeActuel = $badgesStatut[$statutCourant] ?? $badgesStatut['brouillon'];

$labelsNiveaux = [
    'debutant'      => ['label' => 'Débutant', 'badge' => 'bg-light text-secondary border'],
    'intermediaire' => ['label' => 'Intermédiaire', 'badge' => 'bg-info bg-opacity-10 text-info border border-info border-opacity-25'],
    'avance'        => ['label' => 'Avancé', 'badge' => 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25'],
    'expert'        => ['label' => 'Expert', 'badge' => 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25'],
];

// Vérification de la présence de l'avatar par défaut
$cheminAvatarDefautRel = 'assets/img/avatar-defaut.svg';
$avatarDefautExiste = file_exists(__DIR__ . '/../' . $cheminAvatarDefautRel);

$titre_page = 'Mon profil professionnel';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4 py-lg-5">
    
    <!-- En-tête de page avec badge de statut dynamique -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-2 border-bottom">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h3 fw-bold mb-0">Mon profil professionnel</h1>
                <span class="badge <?= e($badgeActuel['class']) ?> px-2.5 py-1.5 rounded-pill small">
                    <i class="bi <?= e($badgeActuel['icon']) ?> me-1"></i><?= e($badgeActuel['label']) ?>
                </span>
            </div>
            <p class="text-secondary small mb-0">
                Renseignez votre photo, vos compétences et votre présentation pour attirer des clients sur FindPro.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="<?= url('pro/index.php') ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Tableau de bord
            </a>
            <a href="<?= url('pro/competences.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-award me-1"></i>Mes compétences
            </a>
            <a href="<?= url('pro/portfolio.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-images me-1"></i>Mon Portfolio
            </a>
        </div>
    </div>

    <!-- Alerte prompte pour la règle RG06 si le profil est approuvé -->
    <?php if ($statutCourant === 'approuve'): ?>
        <div class="alert alert-warning d-flex align-items-start mb-4 shadow-sm" role="alert">
            <i class="bi bi-shield-exclamation fs-4 me-3 flex-shrink-0 text-warning"></i>
            <div>
                <strong class="d-block mb-1">Règle de gestion RG06 :</strong>
                <p class="small mb-0">
                    Votre profil est actuellement <strong>approuvé et visible dans la recherche publique</strong>. 
                    Si vous modifiez votre <strong>catégorie de métier</strong>, votre <strong>titre</strong> ou votre <strong>description</strong>, votre profil repassera automatiquement au statut <em>« En attente de validation »</em> et disparaîtra temporairement des résultats publics le temps de la revérification administrative.
                </p>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4 justify-content-center">
        
        <!-- COLONNE 1 : Informations générales et Photo -->
        <div class="col-12 col-lg-7">
            <div class="card p-4 p-md-5 shadow-sm border h-100">
                
                <h2 class="h5 fw-bold mb-3 pb-2 border-bottom">
                    <i class="bi bi-person-lines-fill text-primary me-2"></i>Informations générales
                </h2>

                <form method="POST" action="<?= url('pro/profil.php') ?>" enctype="multipart/form-data" novalidate>
                    <?= csrf_field() ?>

                    <!-- Bloc Photo de profil avec prévisualisation -->
                    <div class="mb-4 p-3 bg-light rounded border">
                        <label class="form-label fw-semibold small mb-2 d-block">
                            Photo de profil professionnelle (optionnelle)
                        </label>
                        <div class="d-flex align-items-center gap-3">
                            
                            <!-- Rendu Avatar ou Espace Réservé Gris -->
                            <div class="position-relative flex-shrink-0">
                                <?php if ($photoActuelle !== null && file_exists(__DIR__ . '/../' . $photoActuelle)): ?>
                                    <img 
                                        src="<?= url($photoActuelle) ?>" 
                                        id="apercu-avatar" 
                                        alt="Photo de profil" 
                                        class="rounded-circle border shadow-sm object-fit-cover" 
                                        style="width: 80px; height: 80px;"
                                    >
                                <?php elseif ($avatarDefautExiste): ?>
                                    <img 
                                        src="<?= url($cheminAvatarDefautRel) ?>" 
                                        id="apercu-avatar" 
                                        alt="Avatar par défaut" 
                                        class="rounded-circle border shadow-sm object-fit-cover" 
                                        style="width: 80px; height: 80px;"
                                    >
                                <?php else: ?>
                                    <!-- Espace réservé gris si avatar-defaut.svg n'a pas encore été déposé -->
                                    <div 
                                        id="apercu-avatar-placeholder" 
                                        class="rounded-circle bg-secondary bg-opacity-25 text-secondary d-flex align-items-center justify-content-center border shadow-sm" 
                                        style="width: 80px; height: 80px;"
                                        title="Espace réservé gris : assets/img/avatar-defaut.svg manquant"
                                    >
                                        <i class="bi bi-person-fill fs-1 text-secondary opacity-75"></i>
                                    </div>
                                    <img 
                                        src="" 
                                        id="apercu-avatar" 
                                        alt="Aperçu photo" 
                                        class="rounded-circle border shadow-sm object-fit-cover d-none" 
                                        style="width: 80px; height: 80px;"
                                    >
                                <?php endif; ?>
                            </div>

                            <div class="flex-grow-1">
                                <input 
                                    type="file" 
                                    name="photo" 
                                    id="photo" 
                                    class="form-control form-control-sm <?= isset($erreurs['photo']) ? 'is-invalid' : '' ?>"
                                    accept="image/jpeg,image/png,image/webp"
                                >
                                <?php if (isset($erreurs['photo'])): ?>
                                    <div class="invalid-feedback d-block"><?= e($erreurs['photo']) ?></div>
                                <?php else: ?>
                                    <div class="form-text small text-secondary mt-1">
                                        JPG, PNG ou WEBP. Max 2 Mo. Dimensions max 4000x4000 px.
                                    </div>
                                <?php endif; ?>

                                <?php if ($photoActuelle !== null): ?>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="supprimer_photo" value="1" id="supprimer_photo">
                                        <label class="form-check-label small text-danger" for="supprimer_photo">
                                            Supprimer la photo actuelle
                                        </label>
                                    </div>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>

                    <!-- Catégorie principale -->
                    <div class="mb-3">
                        <label for="id_categorie" class="form-label fw-medium small">
                            Corps de métier principal <span class="text-danger">*</span>
                        </label>
                        <select 
                            name="id_categorie" 
                            id="id_categorie" 
                            class="form-select <?= isset($erreurs['id_categorie']) ? 'is-invalid' : '' ?>"
                            required
                        >
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int) $cat['id_categorie'] ?>" <?= $idCategorie === (int) $cat['id_categorie'] ? 'selected' : '' ?>>
                                    <?= e($cat['libelle']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($erreurs['id_categorie'])): ?>
                            <div class="invalid-feedback"><?= e($erreurs['id_categorie']) ?></div>
                        <?php else: ?>
                            <div class="form-text small text-secondary">
                                Attention : changer de catégorie retirera les compétences qui ne s'y rattachent plus.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Titre professionnel -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="titre" class="form-label fw-medium small mb-0">
                                Titre professionnel <span class="text-secondary small">(ex: Développeur PHP Fullstack)</span>
                            </label>
                            <span class="text-muted small" id="compteur-titre">0/120</span>
                        </div>
                        <input 
                            type="text" 
                            class="form-control <?= isset($erreurs['titre']) ? 'is-invalid' : '' ?>" 
                            id="titre" 
                            name="titre" 
                            value="<?= e($titre) ?>" 
                            maxlength="120"
                            placeholder="Votre spécialité ou titre d'activité"
                        >
                        <?php if (isset($erreurs['titre'])): ?>
                            <div class="invalid-feedback"><?= e($erreurs['titre']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Ville / Zone d'intervention -->
                    <div class="mb-3">
                        <label for="zone" class="form-label fw-medium small">
                            Ville / Zone de service <span class="text-secondary small">(ex: Abidjan - Cocody, Télétravail)</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-secondary"><i class="bi bi-geo-alt"></i></span>
                            <input 
                                type="text" 
                                class="form-control <?= isset($erreurs['zone']) ? 'is-invalid' : '' ?>" 
                                id="zone" 
                                name="zone" 
                                value="<?= e($zone) ?>" 
                                maxlength="120"
                                placeholder="Localisation ou périmètre d'intervention"
                            >
                        </div>
                        <?php if (isset($erreurs['zone'])): ?>
                            <div class="invalid-feedback d-block"><?= e($erreurs['zone']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Disponibilité -->
                    <div class="mb-3">
                        <label for="disponibilite" class="form-label fw-medium small">
                            Disponibilité actuelle pour des missions
                        </label>
                        <select 
                            name="disponibilite" 
                            id="disponibilite" 
                            class="form-select"
                        >
                            <option value="disponible" <?= $disponibilite === 'disponible' ? 'selected' : '' ?>>Disponible immédiatement</option>
                            <option value="limitee" <?= $disponibilite === 'limitee' ? 'selected' : '' ?>>Disponibilité limitée (délais à prévoir)</option>
                            <option value="indisponible" <?= $disponibilite === 'indisponible' ? 'selected' : '' ?>>Indisponible pour le moment</option>
                        </select>
                        <div class="form-text small text-secondary">Aide les clients à évaluer votre réactivité.</div>
                    </div>

                    <!-- Description détaillée -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="description" class="form-label fw-medium small mb-0">
                                Présentation détaillée & prestations
                            </label>
                            <span class="text-muted small" id="compteur-desc">0/2000 (min 100 pour soumission)</span>
                        </div>
                        <textarea 
                            class="form-control <?= isset($erreurs['description']) ? 'is-invalid' : '' ?>" 
                            id="description" 
                            name="description" 
                            rows="7" 
                            maxlength="2000"
                            placeholder="Décrivez votre expérience, les technologies ou outils que vous maîtrisez, vos réalisations et votre méthode de travail..."
                        ><?= e($description) ?></textarea>
                        <?php if (isset($erreurs['description'])): ?>
                            <div class="invalid-feedback"><?= e($erreurs['description']) ?></div>
                        <?php else: ?>
                            <div class="form-text small text-secondary">Au moins 100 caractères exigés pour soumettre à validation. Les brouillons acceptent un texte court.</div>
                        <?php endif; ?>
                    </div>

                    <!-- Boutons d'action -->
                    <div class="d-flex flex-column flex-sm-row justify-content-between gap-2 pt-2 border-top">
                        <button type="submit" name="action" value="brouillon" class="btn btn-outline-primary py-2 px-3">
                            <i class="bi bi-save me-1"></i>Enregistrer le brouillon
                        </button>
                        <button type="submit" name="action" value="soumettre" class="btn btn-brand py-2 px-4">
                            <i class="bi bi-send-check me-1"></i>Soumettre pour validation
                        </button>
                    </div>

                </form>
            </div>
        </div>

        <!-- COLONNE 2 : Gestion des compétences du profil -->
        <div class="col-12 col-lg-5" id="competences">
            <div class="card p-4 shadow-sm border h-100 d-flex flex-col justify-content-between">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <h2 class="h5 fw-bold mb-0">
                            <i class="bi bi-award text-primary me-2"></i>Compétences clés
                        </h2>
                        <span class="badge bg-light text-dark border px-2.5 py-1.5 font-monospace small">
                            <?= count($competencesProfil) ?> / 15
                        </span>
                    </div>

                    <?php if (!$profil): ?>
                        <div class="alert alert-secondary text-center p-4">
                            <i class="bi bi-info-circle fs-3 text-secondary d-block mb-2"></i>
                            <p class="small mb-0 text-secondary">
                                Enregistrez d'abord vos informations générales ci-contre pour activer l'ajout de compétences.
                            </p>
                        </div>
                    <?php else: ?>

                        <!-- Formulaire d'ajout de compétence -->
                        <?php if (count($competencesProfil) < 15 && !empty($competencesDisponibles)): ?>
                            <form method="POST" action="<?= url('pro/profil.php#competences') ?>" class="bg-light p-3 rounded border mb-4">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="ajouter_competence">

                                <div class="mb-2">
                                    <label for="id_competence" class="form-label fw-semibold small mb-1">
                                        Sélectionner une compétence
                                    </label>
                                    <select name="id_competence" id="id_competence" class="form-select form-select-sm" required>
                                        <option value="" disabled selected>-- Choisir dans votre métier --</option>
                                        <?php foreach ($competencesDisponibles as $comp): ?>
                                            <option value="<?= (int) $comp['id_competence'] ?>">
                                                <?= e($comp['libelle']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="niveau_declare" class="form-label fw-semibold small mb-1">
                                        Niveau déclaré
                                    </label>
                                    <select name="niveau_declare" id="niveau_declare" class="form-select form-select-sm" required>
                                        <option value="debutant">Débutant (Bases théoriques / premiers projets)</option>
                                        <option value="intermediaire" selected>Intermédiaire (Autonome sur tâches courantes)</option>
                                        <option value="avance">Avancé (Maîtrise approfondie / projets complexes)</option>
                                        <option value="expert">Expert (Référence technique / résolution pointue)</option>
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-brand btn-sm w-100">
                                    <i class="bi bi-plus-circle me-1"></i>Ajouter à mon profil
                                </button>
                            </form>
                        <?php elseif (count($competencesProfil) >= 15): ?>
                            <div class="alert alert-warning small py-2 mb-4">
                                <i class="bi bi-exclamation-triangle me-1"></i>Vous avez atteint le quota maximal de 15 compétences. Supprimez-en une pour en ajouter une autre.
                            </div>
                        <?php elseif (empty($competencesDisponibles)): ?>
                            <div class="alert alert-info small py-2 mb-4">
                                <i class="bi bi-check-circle me-1"></i>Vous avez associé toutes les compétences disponibles pour votre métier.
                            </div>
                        <?php endif; ?>

                        <!-- Liste des compétences actuelles -->
                        <h3 class="h6 fw-semibold text-secondary mb-2">Compétences actuellement associées :</h3>

                        <?php if (empty($competencesProfil)): ?>
                            <div class="text-center py-4 text-muted bg-light rounded border border-dashed">
                                <i class="bi bi-award fs-2 text-secondary opacity-50 d-block mb-1"></i>
                                <small>Aucune compétence associée pour l'instant.</small>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush border rounded">
                                <?php foreach ($competencesProfil as $cp): 
                                    $cfgNiveau = $labelsNiveaux[$cp['niveau_declare']] ?? $labelsNiveaux['intermediaire'];
                                ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="fw-medium small"><?= e($cp['libelle']) ?></span>
                                            <span class="badge <?= e($cfgNiveau['badge']) ?> rounded-pill font-monospace" style="font-size: 0.72rem;">
                                                <?= e($cfgNiveau['label']) ?>
                                            </span>
                                        </div>
                                        <form method="POST" action="<?= url('pro/profil.php#competences') ?>" class="m-0 p-0" onsubmit="return confirm('Retirer cette compétence de votre profil ?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="supprimer_competence">
                                            <input type="hidden" name="id_competence" value="<?= (int) $cp['id_competence'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm p-1 border-0" title="Retirer cette compétence" aria-label="Supprimer <?= e($cp['libelle']) ?>">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                    <?php endif; ?>

                </div>
            </div>
        </div>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const titreInput = document.getElementById('titre');
    const compteurTitre = document.getElementById('compteur-titre');
    const descInput = document.getElementById('description');
    const compteurDesc = document.getElementById('compteur-desc');
    const photoInput = document.getElementById('photo');
    const apercuAvatar = document.getElementById('apercu-avatar');
    const placeholderAvatar = document.getElementById('apercu-avatar-placeholder');

    function updateCompteurs() {
        if (titreInput && compteurTitre) {
            compteurTitre.textContent = titreInput.value.length + '/120';
        }
        if (descInput && compteurDesc) {
            compteurDesc.textContent = descInput.value.length + '/2000 (min 100 pour soumission)';
        }
    }

    if (titreInput) titreInput.addEventListener('input', updateCompteurs);
    if (descInput) descInput.addEventListener('input', updateCompteurs);
    updateCompteurs();

    // Prévisualisation instantanée de l'avatar sélectionné
    if (photoInput) {
        photoInput.addEventListener('change', function (e) {
            const fichier = e.target.files[0];
            if (fichier) {
                const reader = new FileReader();
                reader.onload = function (evt) {
                    if (apercuAvatar) {
                        apercuAvatar.src = evt.target.result;
                        apercuAvatar.classList.remove('d-none');
                    }
                    if (placeholderAvatar) {
                        placeholderAvatar.classList.add('d-none');
                    }
                };
                reader.readAsDataURL(fichier);
            }
        });
    }
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
