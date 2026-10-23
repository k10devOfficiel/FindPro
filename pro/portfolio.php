<?php
/**
 * Gestion du portfolio et des réalisations du professionnel connecté — FindPro.
 * 
 * Règles et exigences :
 * - Accès strictement réservé aux professionnels authentifiés (exiger_role('professionnel')).
 * - Profil strictement dérivé de la session ($_SESSION['utilisateur']['id']).
 * - Validation stricte de l'URL externe :
 *     * Longueur max 500 caractères.
 *     * Doit commencer par http:// ou https://.
 *     * Vérification filter_var(FILTER_VALIDATE_URL) et schéma parse_url().
 *     * Rejet strict de tout autre schéma (javascript:, data:, file:, vbscript:, etc.).
 *     * Affichage avec rel="noopener noreferrer nofollow" et target="_blank".
 * - Champs du projet :
 *     * Titre (max 120 caractères, obligatoire).
 *     * Description (max 1000 caractères, facultatif).
 *     * Lien externe (URL validée, max 500 caractères, facultatif si image présente).
 *     * Image optionnelle (téléversement sécurisé televerser_image dans sous-dossier portfolio).
 *     * Date de réalisation facultative (format AAAA-MM-JJ).
 * - Règle métier obligatoire : Un projet doit avoir au moins un lien externe OU une image.
 * - Limite : Maximum 12 projets par profil professionnel.
 * - Actions protégées : Ajouter, Modifier, Supprimer (POST + CSRF + vérification de propriété).
 * - Nettoyage disque : Suppression de l'ancienne image lors du remplacement ou de la suppression du projet.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/upload.php';

// 1. Authentification stricte du professionnel
demarrer_session();
$utilisateur = exiger_role('professionnel');
$idUtilisateur = (int) $utilisateur['id'];

$pdo = db();

// 2. Récupération du profil professionnel lié à l'utilisateur de session
$stmtProfil = $pdo->prepare('
    SELECT p.*, c.libelle AS categorie_libelle 
    FROM profil_professionnel p
    LEFT JOIN categorie c ON p.id_categorie = c.id_categorie
    WHERE p.id_utilisateur = :id_user
    LIMIT 1
');
$stmtProfil->execute(['id_user' => $idUtilisateur]);
$profil = $stmtProfil->fetch();

$idProfil = $profil ? (int) $profil['id_profil'] : null;

// Helpers de validation
/**
 * Valide une URL externe selon les critères stricts de sécurité.
 * 
 * @param string|null $url URL brute
 * @return array{valide: bool, url: ?string, erreur: ?string}
 */
function valider_url_portfolio(?string $url): array
{
    $url = trim((string) $url);
    if ($url === '') {
        return ['valide' => true, 'url' => null, 'erreur' => null];
    }

    if (mb_strlen($url, 'UTF-8') > 500) {
        return [
            'valide' => false,
            'url'    => null,
            'erreur' => 'L\'URL du lien externe ne peut pas dépasser 500 caractères.',
        ];
    }

    // Doit obligatoirement commencer par http:// ou https://
    if (!preg_match('#^https?://#i', $url)) {
        return [
            'valide' => false,
            'url'    => null,
            'erreur' => 'L\'URL externe doit obligatoirement commencer par http:// ou https://.',
        ];
    }

    // Refus formel de préfixes suspects (javascript:, data:, vbscript:, etc.)
    $urlBasse = strtolower($url);
    $schemasDangereux = ['javascript:', 'data:', 'vbscript:', 'file:', 'about:'];
    foreach ($schemasDangereux as $schema) {
        if (str_starts_with($urlBasse, $schema)) {
            return [
                'valide' => false,
                'url'    => null,
                'erreur' => 'Ce schéma d\'URL n\'est pas autorisé pour des raisons de sécurité.',
            ];
        }
    }

    // Validation formelle filter_var
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return [
            'valide' => false,
            'url'    => null,
            'erreur' => 'Le format de l\'URL externe n\'est pas valide.',
        ];
    }

    // Vérification approfondie du schéma et de l'hôte via parse_url
    $parsed = parse_url($url);
    if ($parsed === false || empty($parsed['scheme']) || empty($parsed['host'])) {
        return [
            'valide' => false,
            'url'    => null,
            'erreur' => 'L\'URL externe est incomplète ou invalide (hôte manquant).',
        ];
    }

    $scheme = strtolower((string) $parsed['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) {
        return [
            'valide' => false,
            'url'    => null,
            'erreur' => 'Seuls les schémas http:// et https:// sont acceptés.',
        ];
    }

    return ['valide' => true, 'url' => $url, 'erreur' => null];
}

/**
 * Valide une date facultative de réalisation (AAAA-MM-JJ).
 * 
 * @param string|null $date Date brute
 * @return array{valide: bool, date: ?string, erreur: ?string}
 */
function valider_date_portfolio(?string $date): array
{
    $date = trim((string) $date);
    if ($date === '') {
        return ['valide' => true, 'date' => null, 'erreur' => null];
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return [
            'valide' => false,
            'date'   => null,
            'erreur' => 'Le format de la date est invalide (format attendu : AAAA-MM-JJ).',
        ];
    }

    $d = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) {
        return [
            'valide' => false,
            'date'   => null,
            'erreur' => 'La date renseignée n\'est pas une date calendaire valide.',
        ];
    }

    $annee = (int) $d->format('Y');
    $anneeCourante = (int) date('Y');
    if ($annee < 1990 || $annee > ($anneeCourante + 5)) {
        return [
            'valide' => false,
            'date'   => null,
            'erreur' => 'L\'année indiquée pour le projet doit être comprise entre 1990 et ' . ($anneeCourante + 5) . '.',
        ];
    }

    return ['valide' => true, 'date' => $date, 'erreur' => null];
}

/**
 * Supprime de façon sécurisée une image de portfolio du disque.
 * 
 * @param string|null $cheminRelatif Chemin relatif (ex: 'uploads/portfolio/xxx.jpg')
 * @return void
 */
function supprimer_fichier_portfolio(?string $cheminRelatif): void
{
    if (empty($cheminRelatif)) {
        return;
    }

    $racineUploadsPortfolio = realpath(__DIR__ . '/../uploads/portfolio');
    $fichierReel = realpath(__DIR__ . '/../' . $cheminRelatif);

    if ($racineUploadsPortfolio !== false && $fichierReel !== false) {
        if (str_starts_with($fichierReel, $racineUploadsPortfolio) && file_exists($fichierReel)) {
            @unlink($fichierReel);
        }
    }
}

// 3. Traitement des requêtes POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifier();

    if ($idProfil === null) {
        flash_set('warning', 'Veuillez configurer et enregistrer votre profil professionnel avant de gérer votre portfolio.');
        redirect(url('pro/profil.php'));
    }

    $action = (string) ($_POST['action'] ?? '');

    // -------------------------------------------------------------------------
    // ACTION 1 : AJOUT D'UN PROJET
    // -------------------------------------------------------------------------
    if ($action === 'ajouter') {
        // Vérification de la limite de 12 projets
        $stmtCount = $pdo->prepare('SELECT COUNT(*) FROM projet_portfolio WHERE id_profil = :id_profil');
        $stmtCount->execute(['id_profil' => $idProfil]);
        $totalProjets = (int) $stmtCount->fetchColumn();

        if ($totalProjets >= 12) {
            flash_set('danger', 'Limite atteinte : vous ne pouvez pas enregistrer plus de 12 projets dans votre portfolio.');
            redirect(url('pro/portfolio.php'));
        }

        $titre = trim((string) ($_POST['titre'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $lienBrut = (string) ($_POST['lien_externe'] ?? '');
        $dateBrute = (string) ($_POST['date_projet'] ?? '');

        $erreurs = [];

        // Validation du titre (obligatoire, max 120 caractères)
        if ($titre === '') {
            $erreurs[] = 'Le titre du projet est obligatoire.';
        } elseif (mb_strlen($titre, 'UTF-8') > 120) {
            $erreurs[] = 'Le titre du projet ne peut pas dépasser 120 caractères (actuel : ' . mb_strlen($titre, 'UTF-8') . ').';
        }

        // Validation de la description (facultative, max 1000 caractères)
        if ($description !== '' && mb_strlen($description, 'UTF-8') > 1000) {
            $erreurs[] = 'La description ne peut pas dépasser 1000 caractères (actuel : ' . mb_strlen($description, 'UTF-8') . ').';
        }

        // Validation de l'URL externe (facultative, max 500 caractères, http/https)
        $resUrl = valider_url_portfolio($lienBrut);
        if (!$resUrl['valide']) {
            $erreurs[] = $resUrl['erreur'];
        }
        $lienValide = $resUrl['url'];

        // Validation de la date facultative
        $resDate = valider_date_portfolio($dateBrute);
        if (!$resDate['valide']) {
            $erreurs[] = $resDate['erreur'];
        }
        $dateValide = $resDate['date'];

        // Téléversement sécurisé de l'image optionnelle
        $cheminImage = null;
        $fichierPresent = isset($_FILES['image']) && is_array($_FILES['image']) 
            && !empty($_FILES['image']['name']) 
            && ((int) $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE);

        if ($fichierPresent) {
            try {
                $cheminImage = televerser_image($_FILES['image'], 'portfolio');
            } catch (Exception $e) {
                $erreurs[] = 'Erreur d\'image : ' . $e->getMessage();
            }
        }

        // RÈGLE MÉTIER : Un projet doit avoir au moins un lien OU une image
        if (empty($erreurs) && $lienValide === null && $cheminImage === null) {
            $erreurs[] = 'Un projet doit comporter au moins une image d\'illustration ou un lien externe.';
        }

        // Si erreurs : supprimer l'image qui viendrait d'être uploadée et notifier
        if (!empty($erreurs)) {
            if ($cheminImage !== null) {
                supprimer_fichier_portfolio($cheminImage);
            }
            flash_set('danger', implode(' ', $erreurs));
            redirect(url('pro/portfolio.php'));
        }

        // Insertion en base de données
        $stmtInsert = $pdo->prepare('
            INSERT INTO projet_portfolio (
                id_profil, 
                titre, 
                description, 
                lien_externe, 
                image, 
                date_projet, 
                url_media, 
                type_media, 
                date_ajout
            ) VALUES (
                :id_profil, 
                :titre, 
                :description, 
                :lien_externe, 
                :image, 
                :date_projet, 
                :url_media, 
                :type_media, 
                NOW()
            )
        ');

        $stmtInsert->execute([
            'id_profil'    => $idProfil,
            'titre'        => $titre,
            'description'  => $description !== '' ? $description : null,
            'lien_externe' => $lienValide,
            'image'        => $cheminImage,
            'date_projet'  => $dateValide,
            'url_media'    => $cheminImage ?: $lienValide,
            'type_media'   => $cheminImage ? 'image' : 'lien',
        ]);

        flash_set('success', 'Le projet a été ajouté avec succès à votre portfolio.');
        redirect(url('pro/portfolio.php'));
    }

    // -------------------------------------------------------------------------
    // ACTION 2 : MODIFICATION D'UN PROJET
    // -------------------------------------------------------------------------
    if ($action === 'modifier') {
        $idProjet = (int) ($_POST['id_projet'] ?? 0);

        // VÉRIFICATION DE PROPRIÉTÉ DU PROJET (anti-IDOR)
        $stmtVerif = $pdo->prepare('
            SELECT * FROM projet_portfolio 
            WHERE id_projet = :id_projet AND id_profil = :id_profil 
            LIMIT 1
        ');
        $stmtVerif->execute([
            'id_projet' => $idProjet,
            'id_profil' => $idProfil,
        ]);
        $projetExistant = $stmtVerif->fetch();

        if (!$projetExistant) {
            flash_set('danger', 'Action refusée : ce projet n\'existe pas ou ne vous appartient pas.');
            redirect(url('pro/portfolio.php'));
        }

        $titre = trim((string) ($_POST['titre'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $lienBrut = (string) ($_POST['lien_externe'] ?? '');
        $dateBrute = (string) ($_POST['date_projet'] ?? '');
        $supprimerImageDemandee = !empty($_POST['supprimer_image']);

        $erreurs = [];

        // Validation titre
        if ($titre === '') {
            $erreurs[] = 'Le titre du projet est obligatoire.';
        } elseif (mb_strlen($titre, 'UTF-8') > 120) {
            $erreurs[] = 'Le titre du projet ne peut pas dépasser 120 caractères.';
        }

        // Validation description
        if ($description !== '' && mb_strlen($description, 'UTF-8') > 1000) {
            $erreurs[] = 'La description ne peut pas dépasser 1000 caractères.';
        }

        // Validation URL externe
        $resUrl = valider_url_portfolio($lienBrut);
        if (!$resUrl['valide']) {
            $erreurs[] = $resUrl['erreur'];
        }
        $lienValide = $resUrl['url'];

        // Validation date
        $resDate = valider_date_portfolio($dateBrute);
        if (!$resDate['valide']) {
            $erreurs[] = $resDate['erreur'];
        }
        $dateValide = $resDate['date'];

        $nouvelleImage = null;
        $imageFinale = $projetExistant['image'];
        $ancienneImageASupprimer = null;

        // Téléversement d'une nouvelle image si fournie
        $fichierPresent = isset($_FILES['image']) && is_array($_FILES['image']) 
            && !empty($_FILES['image']['name']) 
            && ((int) $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE);

        if ($fichierPresent) {
            try {
                $nouvelleImage = televerser_image($_FILES['image'], 'portfolio');
                $ancienneImageASupprimer = $projetExistant['image'];
                $imageFinale = $nouvelleImage;
            } catch (Exception $e) {
                $erreurs[] = 'Erreur d\'image : ' . $e->getMessage();
            }
        } elseif ($supprimerImageDemandee) {
            $ancienneImageASupprimer = $projetExistant['image'];
            $imageFinale = null;
        }

        // RÈGLE MÉTIER : Un projet doit avoir au moins un lien OU une image
        if (empty($erreurs) && $lienValide === null && $imageFinale === null) {
            $erreurs[] = 'Un projet doit comporter au moins une image d\'illustration ou un lien externe.';
        }

        if (!empty($erreurs)) {
            // Nettoyage de la nouvelle image si l'opération échoue
            if ($nouvelleImage !== null) {
                supprimer_fichier_portfolio($nouvelleImage);
            }
            flash_set('danger', implode(' ', $erreurs));
            redirect(url('pro/portfolio.php'));
        }

        // Mise à jour en base de données
        $stmtUpdate = $pdo->prepare('
            UPDATE projet_portfolio SET
                titre = :titre,
                description = :description,
                lien_externe = :lien_externe,
                image = :image,
                date_projet = :date_projet,
                url_media = :url_media,
                type_media = :type_media
            WHERE id_projet = :id_projet AND id_profil = :id_profil
        ');

        $stmtUpdate->execute([
            'titre'        => $titre,
            'description'  => $description !== '' ? $description : null,
            'lien_externe' => $lienValide,
            'image'        => $imageFinale,
            'date_projet'  => $dateValide,
            'url_media'    => $imageFinale ?: $lienValide,
            'type_media'   => $imageFinale ? 'image' : 'lien',
            'id_projet'    => $idProjet,
            'id_profil'    => $idProfil,
        ]);

        // Nettoyer l'ancienne image du disque
        if ($ancienneImageASupprimer !== null && $ancienneImageASupprimer !== $imageFinale) {
            supprimer_fichier_portfolio($ancienneImageASupprimer);
        }

        flash_set('success', 'Le projet a été modifié avec succès.');
        redirect(url('pro/portfolio.php'));
    }

    // -------------------------------------------------------------------------
    // ACTION 3 : SUPPRESSION D'UN PROJET
    // -------------------------------------------------------------------------
    if ($action === 'supprimer') {
        $idProjet = (int) ($_POST['id_projet'] ?? 0);

        // VÉRIFICATION DE PROPRIÉTÉ DU PROJET (anti-IDOR)
        $stmtVerif = $pdo->prepare('
            SELECT * FROM projet_portfolio 
            WHERE id_projet = :id_projet AND id_profil = :id_profil 
            LIMIT 1
        ');
        $stmtVerif->execute([
            'id_projet' => $idProjet,
            'id_profil' => $idProfil,
        ]);
        $projetExistant = $stmtVerif->fetch();

        if (!$projetExistant) {
            flash_set('danger', 'Action refusée : ce projet n\'existe pas ou ne vous appartient pas.');
            redirect(url('pro/portfolio.php'));
        }

        // Suppression de l'image associée du disque
        if (!empty($projetExistant['image'])) {
            supprimer_fichier_portfolio($projetExistant['image']);
        }

        // Suppression de l'enregistrement en base
        $stmtDelete = $pdo->prepare('
            DELETE FROM projet_portfolio 
            WHERE id_projet = :id_projet AND id_profil = :id_profil
        ');
        $stmtDelete->execute([
            'id_projet' => $idProjet,
            'id_profil' => $idProfil,
        ]);

        flash_set('success', 'Le projet a été supprimé de votre portfolio.');
        redirect(url('pro/portfolio.php'));
    }
}

// 4. Chargement des projets du profil connecté
$projets = [];
$totalProjets = 0;

if ($idProfil !== null) {
    $stmtProjets = $pdo->prepare('
        SELECT * FROM projet_portfolio 
        WHERE id_profil = :id_profil 
        ORDER BY COALESCE(date_projet, date_ajout) DESC, id_projet DESC
    ');
    $stmtProjets->execute(['id_profil' => $idProfil]);
    $projets = $stmtProjets->fetchAll();
    $totalProjets = count($projets);
}

$limiteAtteinte = ($totalProjets >= 12);

$titre_page = 'Mon Portfolio — Réalisations';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4 py-lg-5">
    
    <!-- En-tête principal -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-3 border-bottom">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                <h1 class="h3 fw-bold mb-0">Portfolio &amp; Réalisations</h1>
                <span class="badge <?= $totalProjets >= 12 ? 'bg-danger' : ($totalProjets >= 8 ? 'bg-warning text-dark' : 'bg-primary') ?> px-2.5 py-1.5 rounded-pill small font-monospace">
                    <?= $totalProjets ?> / 12 projets
                </span>
            </div>
            <p class="text-secondary small mb-0">
                Mettez en valeur vos travaux, chantiers, captures d'écran et liens de démonstration pour convaincre les clients.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="<?= url('pro/index.php') ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Tableau de bord
            </a>
            <a href="<?= url('pro/profil.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-person-lines-fill me-1"></i>Mon profil
            </a>
            <a href="<?= url('pro/competences.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-award me-1"></i>Mes compétences
            </a>
            <?php if ($profil): ?>
                <button 
                    type="button" 
                    class="btn btn-brand btn-sm d-inline-flex align-items-center gap-1 shadow-sm"
                    data-bs-toggle="modal" 
                    data-bs-target="#modalAjouterProjet"
                    <?= $limiteAtteinte ? 'disabled title="Limite maximale de 12 projets atteinte"' : '' ?>
                >
                    <i class="bi bi-plus-circle me-1"></i>Ajouter un projet
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Si aucun profil créé encore -->
    <?php if (!$profil): ?>
        <div class="card p-4 p-md-5 text-center shadow-sm border mb-4">
            <div class="mb-3 text-secondary opacity-75">
                <i class="bi bi-person-exclamation fs-1 text-warning"></i>
            </div>
            <h2 class="h5 fw-bold mb-2">Configurez d'abord votre profil professionnel</h2>
            <p class="text-secondary small mx-auto mb-4" style="max-width: 520px;">
                Votre compte professionnel est bien actif, mais votre fiche métier n'est pas encore initialisée. Veuillez enregistrer vos informations principales avant d'enrichir votre portfolio.
            </p>
            <div>
                <a href="<?= url('pro/profil.php') ?>" class="btn btn-brand">
                    <i class="bi bi-pencil-square me-1"></i>Créer et configurer mon profil
                </a>
            </div>
        </div>
    <?php else: ?>

        <!-- Barre de jauge du quota (max 12 projets) -->
        <div class="card p-3 shadow-sm border mb-4 bg-white">
            <div class="d-flex justify-content-between align-items-center mb-1 small">
                <span class="fw-semibold text-secondary">
                    Capacité du portfolio : <strong class="text-dark"><?= $totalProjets ?></strong> sur 12 réalisations
                </span>
                <span class="text-secondary small">
                    <?= 12 - $totalProjets ?> emplacement(s) libre(s)
                </span>
            </div>
            <div class="progress" style="height: 8px;" role="progressbar" aria-valuenow="<?= $totalProjets ?>" aria-valuemin="0" aria-valuemax="12">
                <?php 
                $pourcentage = round(($totalProjets / 12) * 100);
                $barClass = $totalProjets >= 12 ? 'bg-danger' : ($totalProjets >= 8 ? 'bg-warning' : 'bg-primary');
                ?>
                <div class="progress-bar <?= $barClass ?> progress-bar-striped" style="width: <?= $pourcentage ?>%;"></div>
            </div>
            <?php if ($limiteAtteinte): ?>
                <small class="text-danger mt-2 d-block">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>Vous avez atteint le plafond de 12 projets autorisés. Pour ajouter un nouveau projet, veuillez supprimer ou modifier une réalisation existante.
                </small>
            <?php endif; ?>
        </div>

        <!-- LISTE DES PROJETS DU PORTFOLIO -->
        <?php if ($totalProjets === 0): ?>
            <!-- État vide (aucun projet) -->
            <div class="card p-5 text-center shadow-sm border">
                <div class="mb-3 text-secondary opacity-50">
                    <i class="bi bi-images" style="font-size: 3.5rem;"></i>
                </div>
                <h2 class="h5 fw-bold mb-2">Votre portfolio est actuellement vide</h2>
                <p class="text-secondary small mx-auto mb-4" style="max-width: 480px;">
                    Partagez vos réalisations les plus représentatives (captures d'écran d'applications, chantiers livrés, démos en ligne, dépôts GitHub ou vidéos).
                    <br><strong class="text-dark">Règle :</strong> chaque projet doit inclure au moins une image ou un lien externe.
                </p>
                <div>
                    <button 
                        type="button" 
                        class="btn btn-brand shadow-sm d-inline-flex align-items-center gap-2"
                        data-bs-toggle="modal" 
                        data-bs-target="#modalAjouterProjet"
                    >
                        <i class="bi bi-plus-lg"></i>
                        <span>Ajouter ma première réalisation</span>
                    </button>
                </div>
            </div>
        <?php else: ?>
            <!-- Grille responsive des projets -->
            <div class="row g-4">
                <?php foreach ($projets as $projet): 
                    $idP = (int) $projet['id_projet'];
                    $hasImage = !empty($projet['image']) && file_exists(__DIR__ . '/../' . $projet['image']);
                    $hasLien = !empty($projet['lien_externe']);
                    $dateAffichee = null;
                    if (!empty($projet['date_projet'])) {
                        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $projet['date_projet']);
                        if ($dt) {
                            $dateAffichee = $dt->format('d/m/Y');
                        }
                    }
                ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card h-100 shadow-sm border d-flex flex-column overflow-hidden position-relative">
                            
                            <!-- Visuel du projet ou espace réservé -->
                            <div class="position-relative bg-light border-bottom" style="height: 200px; overflow: hidden;">
                                <?php if ($hasImage): ?>
                                    <img 
                                        src="<?= url($projet['image']) ?>" 
                                        alt="<?= e($projet['titre']) ?>" 
                                        class="w-100 h-100 object-fit-cover"
                                        loading="lazy"
                                    >
                                <?php else: ?>
                                    <div class="w-100 h-100 d-flex flex-column align-items-center justify-content-center text-secondary opacity-75 p-3 text-center">
                                        <i class="bi bi-link-45deg fs-1 mb-1 text-primary"></i>
                                        <span class="small fw-semibold">Projet avec lien web</span>
                                        <small class="text-muted" style="font-size: 0.75rem;">Aucune image téléversée</small>
                                    </div>
                                <?php endif; ?>

                                <!-- Badge type / média -->
                                <div class="position-absolute top-0 end-0 m-2 d-flex gap-1">
                                    <?php if ($hasImage): ?>
                                        <span class="badge bg-dark bg-opacity-75 text-white shadow-sm" title="Image d'illustration">
                                            <i class="bi bi-image"></i>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($hasLien): ?>
                                        <span class="badge bg-primary text-white shadow-sm" title="Lien externe disponible">
                                            <i class="bi bi-globe"></i>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Corps de la carte -->
                            <div class="card-body p-3 p-xl-4 d-flex flex-column flex-grow-1">
                                
                                <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                    <h3 class="h6 fw-bold mb-0 text-dark" style="word-break: break-word;">
                                        <?= e($projet['titre']) ?>
                                    </h3>
                                </div>

                                <?php if ($dateAffichee !== null): ?>
                                    <div class="text-secondary small mb-2 d-flex align-items-center gap-1">
                                        <i class="bi bi-calendar3"></i>
                                        <span>Réalisé le <?= e($dateAffichee) ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($projet['description'])): ?>
                                    <p class="text-secondary small mb-3 flex-grow-1" style="white-space: pre-line; word-break: break-word;">
                                        <?= nl2br(e($projet['description'])) ?>
                                    </p>
                                <?php else: ?>
                                    <p class="text-muted small fst-italic mb-3 flex-grow-1">
                                        Aucune description détaillée renseignée.
                                    </p>
                                <?php endif; ?>

                                <!-- Lien externe sécurisé obligatoire rel="noopener noreferrer nofollow" target="_blank" -->
                                <?php if ($hasLien): ?>
                                    <div class="mb-3 pt-2 border-top">
                                        <a 
                                            href="<?= e($projet['lien_externe']) ?>" 
                                            target="_blank" 
                                            rel="noopener noreferrer nofollow" 
                                            class="btn btn-outline-primary btn-sm w-100 d-inline-flex align-items-center justify-content-center gap-2 text-truncate"
                                            title="Ouvrir le projet dans un nouvel onglet"
                                        >
                                            <i class="bi bi-box-arrow-up-right flex-shrink-0"></i>
                                            <span class="text-truncate">Voir le projet en ligne</span>
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <!-- Actions : Modifier / Supprimer -->
                                <div class="d-flex align-items-center justify-content-between gap-2 pt-2 border-top mt-auto">
                                    <button 
                                        type="button" 
                                        class="btn btn-light border btn-sm text-dark d-inline-flex align-items-center gap-1"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#modalModifierProjet<?= $idP ?>"
                                    >
                                        <i class="bi bi-pencil-square"></i>
                                        <span>Modifier</span>
                                    </button>

                                    <button 
                                        type="button" 
                                        class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#modalSupprimerProjet<?= $idP ?>"
                                    >
                                        <i class="bi bi-trash3"></i>
                                        <span>Supprimer</span>
                                    </button>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- MODAL DE MODIFICATION DU PROJET -->
                    <div class="modal fade" id="modalModifierProjet<?= $idP ?>" tabindex="-1" aria-labelledby="modalLabelModifier<?= $idP ?>" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content">
                                <form method="POST" action="<?= url('pro/portfolio.php') ?>" enctype="multipart/form-data" novalidate>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="modifier">
                                    <input type="hidden" name="id_projet" value="<?= $idP ?>">

                                    <div class="modal-header border-bottom">
                                        <h3 class="modal-title h6 fw-bold mb-0" id="modalLabelModifier<?= $idP ?>">
                                            <i class="bi bi-pencil-square text-primary me-2"></i>Modifier le projet : <?= e($projet['titre']) ?>
                                        </h3>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                                    </div>

                                    <div class="modal-body p-4">
                                        
                                        <!-- Titre (max 120) -->
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <label for="edit_titre_<?= $idP ?>" class="form-label fw-semibold small mb-0">
                                                    Titre du projet <span class="text-danger">*</span>
                                                </label>
                                                <small class="text-secondary" id="counter_edit_titre_<?= $idP ?>">
                                                    <?= mb_strlen((string)$projet['titre'], 'UTF-8') ?> / 120
                                                </small>
                                            </div>
                                            <input 
                                                type="text" 
                                                class="form-control" 
                                                id="edit_titre_<?= $idP ?>" 
                                                name="titre" 
                                                maxlength="120" 
                                                required 
                                                value="<?= e($projet['titre']) ?>"
                                                oninput="document.getElementById('counter_edit_titre_<?= $idP ?>').textContent = this.value.length + ' / 120'"
                                            >
                                        </div>

                                        <!-- Description (max 1000) -->
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <label for="edit_desc_<?= $idP ?>" class="form-label fw-semibold small mb-0">
                                                    Description (contexte, technologies, rôle)
                                                </label>
                                                <small class="text-secondary" id="counter_edit_desc_<?= $idP ?>">
                                                    <?= mb_strlen((string)($projet['description'] ?? ''), 'UTF-8') ?> / 1000
                                                </small>
                                            </div>
                                            <textarea 
                                                class="form-control" 
                                                id="edit_desc_<?= $idP ?>" 
                                                name="description" 
                                                rows="4" 
                                                maxlength="1000"
                                                oninput="document.getElementById('counter_edit_desc_<?= $idP ?>').textContent = this.value.length + ' / 1000'"
                                            ><?= e($projet['description'] ?? '') ?></textarea>
                                        </div>

                                        <!-- Lien externe (max 500) -->
                                        <div class="mb-3">
                                            <label for="edit_lien_<?= $idP ?>" class="form-label fw-semibold small mb-1">
                                                Lien externe (URL vers le site, GitHub, vidéo, etc.)
                                            </label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="bi bi-globe"></i></span>
                                                <input 
                                                    type="url" 
                                                    class="form-control" 
                                                    id="edit_lien_<?= $idP ?>" 
                                                    name="lien_externe" 
                                                    maxlength="500" 
                                                    placeholder="https://mon-projet.com"
                                                    value="<?= e($projet['lien_externe'] ?? '') ?>"
                                                >
                                            </div>
                                            <div class="form-text small">
                                                Doit obligatoirement commencer par <code>http://</code> ou <code>https://</code> (max 500 car.).
                                            </div>
                                        </div>

                                        <!-- Date facultative -->
                                        <div class="mb-3">
                                            <label for="edit_date_<?= $idP ?>" class="form-label fw-semibold small mb-1">
                                                Date de réalisation (facultative)
                                            </label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="bi bi-calendar3"></i></span>
                                                <input 
                                                    type="date" 
                                                    class="form-control" 
                                                    id="edit_date_<?= $idP ?>" 
                                                    name="date_projet"
                                                    value="<?= e($projet['date_projet'] ?? '') ?>"
                                                >
                                            </div>
                                        </div>

                                        <!-- Image actuelle et remplacement -->
                                        <div class="mb-3 p-3 bg-light rounded border">
                                            <label class="form-label fw-semibold small mb-2 d-block">
                                                Image d'illustration
                                            </label>
                                            
                                            <?php if ($hasImage): ?>
                                                <div class="d-flex align-items-center gap-3 mb-3">
                                                    <img 
                                                        src="<?= url($projet['image']) ?>" 
                                                        alt="Visuel actuel" 
                                                        class="rounded border shadow-sm object-fit-cover" 
                                                        style="width: 100px; height: 65px;"
                                                    >
                                                    <div>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="supprimer_image" value="1" id="suppr_img_<?= $idP ?>">
                                                            <label class="form-check-label text-danger small fw-semibold" for="suppr_img_<?= $idP ?>">
                                                                Supprimer l'image actuelle (valide uniquement si un lien externe est conservé)
                                                            </label>
                                                        </div>
                                                        <small class="text-secondary d-block">Ou sélectionnez ci-dessous un nouveau fichier pour la remplacer :</small>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <input 
                                                type="file" 
                                                class="form-control" 
                                                id="edit_file_<?= $idP ?>" 
                                                name="image" 
                                                accept="image/jpeg,image/png,image/webp"
                                            >
                                            <div class="form-text small mt-1">
                                                Formats JPG, PNG ou WEBP. Taille max : 2 Mo. Remplacement automatique de l'ancienne image.
                                            </div>
                                        </div>

                                        <div class="alert alert-info py-2 small mb-0 d-flex align-items-center">
                                            <i class="bi bi-info-circle me-2 flex-shrink-0"></i>
                                            <div>Rappel : Le projet doit obligatoirement conserver au moins une image ou un lien externe valide.</div>
                                        </div>

                                    </div>

                                    <div class="modal-footer border-top bg-light">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="bi bi-check-lg me-1"></i>Enregistrer les modifications
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- MODAL DE CONFIRMATION DE SUPPRESSION -->
                    <div class="modal fade" id="modalSupprimerProjet<?= $idP ?>" tabindex="-1" aria-labelledby="modalLabelSuppr<?= $idP ?>" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <form method="POST" action="<?= url('pro/portfolio.php') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="supprimer">
                                    <input type="hidden" name="id_projet" value="<?= $idP ?>">

                                    <div class="modal-header border-bottom bg-danger bg-opacity-10">
                                        <h3 class="modal-title h6 fw-bold mb-0 text-danger" id="modalLabelSuppr<?= $idP ?>">
                                            <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirmer la suppression
                                        </h3>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                                    </div>

                                    <div class="modal-body p-4">
                                        <p class="mb-2">Êtes-vous sûr de vouloir supprimer définitivement le projet suivant de votre portfolio ?</p>
                                        <div class="p-3 bg-light rounded border mb-3">
                                            <strong class="text-dark d-block"><?= e($projet['titre']) ?></strong>
                                            <?php if ($dateAffichee): ?>
                                                <small class="text-secondary">Date : <?= e($dateAffichee) ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-danger d-block">
                                            <i class="bi bi-info-circle me-1"></i>Cette action est irréversible. L'éventuelle image hébergée sur le serveur sera également supprimée du disque.
                                        </small>
                                    </div>

                                    <div class="modal-footer border-top bg-light">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="bi bi-trash3 me-1"></i>Supprimer définitivement
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- MODAL D'AJOUT D'UN PROJET -->
        <div class="modal fade" id="modalAjouterProjet" tabindex="-1" aria-labelledby="modalLabelAjouter" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="<?= url('pro/portfolio.php') ?>" enctype="multipart/form-data" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="ajouter">

                        <div class="modal-header border-bottom">
                            <h3 class="modal-title h6 fw-bold mb-0" id="modalLabelAjouter">
                                <i class="bi bi-plus-circle text-primary me-2"></i>Ajouter une nouvelle réalisation
                            </h3>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                        </div>

                        <div class="modal-body p-4">
                            
                            <!-- Titre (max 120) -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label for="ajout_titre" class="form-label fw-semibold small mb-0">
                                        Titre du projet <span class="text-danger">*</span>
                                    </label>
                                    <small class="text-secondary" id="counter_ajout_titre">0 / 120</small>
                                </div>
                                <input 
                                    type="text" 
                                    class="form-control" 
                                    id="ajout_titre" 
                                    name="titre" 
                                    maxlength="120" 
                                    placeholder="Ex: Refonte de plateforme e-commerce, Audit de sécurité WAF, Montage clip 4K..."
                                    required
                                    oninput="document.getElementById('counter_ajout_titre').textContent = this.value.length + ' / 120'"
                                >
                            </div>

                            <!-- Description (max 1000) -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label for="ajout_description" class="form-label fw-semibold small mb-0">
                                        Description de la mission (contexte, technologies, livrables)
                                    </label>
                                    <small class="text-secondary" id="counter_ajout_desc">0 / 1000</small>
                                </div>
                                <textarea 
                                    class="form-control" 
                                    id="ajout_description" 
                                    name="description" 
                                    rows="4" 
                                    maxlength="1000"
                                    placeholder="Détaillez les objectifs, défis résolus, technologies employées (ex: Laravel, DaVinci Resolve, Wireshark) et résultats concrets obtenus..."
                                    oninput="document.getElementById('counter_ajout_desc').textContent = this.value.length + ' / 1000'"
                                ></textarea>
                            </div>

                            <!-- Lien externe (max 500) -->
                            <div class="mb-3">
                                <label for="ajout_lien" class="form-label fw-semibold small mb-1">
                                    Lien externe du projet (URL)
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-globe"></i></span>
                                    <input 
                                        type="url" 
                                        class="form-control" 
                                        id="ajout_lien" 
                                        name="lien_externe" 
                                        maxlength="500" 
                                        placeholder="https://www.exemple.com/mon-projet"
                                    >
                                </div>
                                <div class="form-text small">
                                    Doit obligatoirement commencer par <code>http://</code> ou <code>https://</code> (max 500 caractères). Tout autre schéma est refusé.
                                </div>
                            </div>

                            <!-- Date facultative -->
                            <div class="mb-3">
                                <label for="ajout_date" class="form-label fw-semibold small mb-1">
                                    Date de réalisation (facultative)
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-calendar3"></i></span>
                                    <input 
                                        type="date" 
                                        class="form-control" 
                                        id="ajout_date" 
                                        name="date_projet"
                                    >
                                </div>
                            </div>

                            <!-- Image optionnelle -->
                            <div class="mb-3 p-3 bg-light rounded border">
                                <label for="ajout_image" class="form-label fw-semibold small mb-1 d-block">
                                    Image ou capture d'écran d'illustration (optionnelle)
                                </label>
                                <input 
                                    type="file" 
                                    class="form-control" 
                                    id="ajout_image" 
                                    name="image" 
                                    accept="image/jpeg,image/png,image/webp"
                                >
                                <div class="form-text small mt-1">
                                    Formats acceptés : JPG, PNG, WEBP. Taille maximale : 2 Mo. Enregistrement sécurisé dans <code>uploads/portfolio/</code>.
                                </div>
                            </div>

                            <div class="alert alert-info py-2 small mb-0 d-flex align-items-center">
                                <i class="bi bi-info-circle me-2 flex-shrink-0"></i>
                                <div><strong>Règle obligatoire :</strong> Vous devez renseigner <em>au minimum</em> une image d'illustration ou un lien externe valide.</div>
                            </div>

                        </div>

                        <div class="modal-footer border-top bg-light">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-brand btn-sm">
                                <i class="bi bi-plus-circle me-1"></i>Ajouter au portfolio
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
