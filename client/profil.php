<?php
/**
 * Mon Profil & Paramètres du Compte Client — FindPro
 *
 * Permet aux clients de compléter et personnaliser leur compte :
 * - Type de client (particulier / indépendant ou société / entreprise)
 * - Informations d'entreprise (nom société, secteur d'activité, site web)
 * - Photo de profil ou logo professionnel
 * - Coordonnées et adresse (téléphone, ville, commune)
 * - Présentation / Bio (description des besoins et activités)
 * - Sécurité (changement de mot de passe)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/upload.php';

demarrer_session();
$utilisateur = exiger_role('client');
$idClient = (int) ($utilisateur['id'] ?? $utilisateur['id_utilisateur']);
$pdo = db();

// ============================================================================
// 1. RÉCUPÉRATION DES INFORMATIONS UTILISATEUR ET DU PROFIL CLIENT
// ============================================================================

$stmtU = $pdo->prepare('
    SELECT id_utilisateur, nom, prenom, email, telephone, mot_de_passe_hash, date_inscription 
    FROM utilisateur 
    WHERE id_utilisateur = :id
');
$stmtU->execute(['id' => $idClient]);
$user = $stmtU->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    redirect(url('logout.php'));
}

// Initialisation / Récupération de la ligne dans profil_client
$stmtC = $pdo->prepare('SELECT * FROM profil_client WHERE id_utilisateur = :id');
$stmtC->execute(['id' => $idClient]);
$profilClient = $stmtC->fetch(PDO::FETCH_ASSOC);

if (!$profilClient) {
    try {
        $pdo->prepare('INSERT IGNORE INTO profil_client (id_utilisateur, type_structure) VALUES (:id, "particulier")')->execute(['id' => $idClient]);
        $stmtC->execute(['id' => $idClient]);
        $profilClient = $stmtC->fetch(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        $profilClient = [
            'type_structure'   => 'particulier',
            'type_client'      => 'particulier',
            'nom_structure'    => '',
            'nom_societe'      => '',
            'secteur_activite' => '',
            'poste_occupe'     => '',
            'siret_rccm'       => '',
            'photo'            => null,
            'ville'            => '',
            'adresse'          => '',
            'site_web'         => '',
            'bio'              => '',
        ];
    }
}

$erreurs = [];

// ============================================================================
// 2. TRAITEMENT DES FORMULAIRES POST
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifier();
    $action = trim((string) ($_POST['action'] ?? ''));

    // --- FORMULAIRE 1 : MISE À JOUR DU PROFIL & INFORMATIONS ---
    if ($action === 'profil') {
        $prenom = trim((string) ($_POST['prenom'] ?? ''));
        $nom = trim((string) ($_POST['nom'] ?? ''));
        $telephone = trim((string) ($_POST['telephone'] ?? ''));
        $typeClient = trim((string) ($_POST['type_client'] ?? 'particulier'));
        $typeStructure = ($typeClient === 'societe' || $typeClient === 'entreprise') ? 'entreprise' : (($typeClient === 'organisation') ? 'organisation' : 'particulier');
        $nomStructure = trim((string) ($_POST['nom_structure'] ?? $_POST['nom_societe'] ?? ''));
        $secteurActivite = trim((string) ($_POST['secteur_activite'] ?? ''));
        $posteOccupe = trim((string) ($_POST['poste_occupe'] ?? ''));
        $siretRccm = trim((string) ($_POST['siret_rccm'] ?? ''));
        $ville = trim((string) ($_POST['ville'] ?? ''));
        $adresse = trim((string) ($_POST['adresse'] ?? ''));
        $siteWeb = trim((string) ($_POST['site_web'] ?? ''));
        $bio = trim((string) ($_POST['bio'] ?? ''));

        // Validation
        if ($prenom === '') {
            $erreurs[] = 'Le prénom est obligatoire.';
        }
        if ($nom === '') {
            $erreurs[] = 'Le nom de famille est obligatoire.';
        }
        if ($typeStructure !== 'particulier' && $nomStructure === '') {
            $erreurs[] = 'Le nom de la société ou structure est obligatoire pour un compte entreprise/société.';
        }
        if ($siteWeb !== '' && !filter_var($siteWeb, FILTER_VALIDATE_URL) && !filter_var('https://' . $siteWeb, FILTER_VALIDATE_URL)) {
            $erreurs[] = 'L\'adresse du site web n\'est pas valide.';
        }

        // Gestion de la photo / logo
        $cheminPhoto = $profilClient['photo'] ?? null;

        // Suppression de la photo si demandée
        if (isset($_POST['supprimer_photo']) && $_POST['supprimer_photo'] === '1') {
            if (!empty($cheminPhoto) && file_exists(__DIR__ . '/../' . $cheminPhoto)) {
                @unlink(__DIR__ . '/../' . $cheminPhoto);
            }
            $cheminPhoto = null;
        }

        // Téléversement d'une nouvelle photo
        if (isset($_FILES['photo']) && is_array($_FILES['photo']) && (int) ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $nouveauChemin = televerser_image($_FILES['photo'], 'avatars');
                // Nettoyer l'ancienne photo si existante
                if (!empty($cheminPhoto) && file_exists(__DIR__ . '/../' . $cheminPhoto)) {
                    @unlink(__DIR__ . '/../' . $cheminPhoto);
                }
                $cheminPhoto = $nouveauChemin;
            } catch (RuntimeException $e) {
                $erreurs[] = $e->getMessage();
            }
        }

        // Si aucune erreur : sauvegarde en base de données
        if (empty($erreurs)) {
            try {
                $pdo->beginTransaction();

                // 1. Mise à jour utilisateur
                $stmtUpUser = $pdo->prepare('
                    UPDATE utilisateur 
                    SET prenom = :prenom, nom = :nom, telephone = :telephone 
                    WHERE id_utilisateur = :id
                ');
                $stmtUpUser->execute([
                    'prenom'    => $prenom,
                    'nom'       => $nom,
                    'telephone' => $telephone !== '' ? $telephone : null,
                    'id'        => $idClient,
                ]);

                // 2. Mise à jour profil_client
                $stmtUpClient = $pdo->prepare('
                    INSERT INTO profil_client (
                        id_utilisateur, type_structure, nom_structure, secteur_activite, 
                        poste_occupe, siret_rccm, photo, ville, adresse, site_web, bio
                    ) VALUES (
                        :id, :type_structure, :nom_structure, :secteur_activite, 
                        :poste_occupe, :siret_rccm, :photo, :ville, :adresse, :site_web, :bio
                    )
                    ON DUPLICATE KEY UPDATE 
                        type_structure = VALUES(type_structure),
                        nom_structure = VALUES(nom_structure),
                        secteur_activite = VALUES(secteur_activite),
                        poste_occupe = VALUES(poste_occupe),
                        siret_rccm = VALUES(siret_rccm),
                        photo = VALUES(photo),
                        ville = VALUES(ville),
                        adresse = VALUES(adresse),
                        site_web = VALUES(site_web),
                        bio = VALUES(bio),
                        date_maj = NOW()
                ');
                $stmtUpClient->execute([
                    'id'               => $idClient,
                    'type_structure'   => $typeStructure,
                    'nom_structure'    => $nomStructure !== '' ? $nomStructure : null,
                    'secteur_activite' => $secteurActivite !== '' ? $secteurActivite : null,
                    'poste_occupe'     => $posteOccupe !== '' ? $posteOccupe : null,
                    'siret_rccm'       => $siretRccm !== '' ? $siretRccm : null,
                    'photo'            => $cheminPhoto,
                    'ville'            => $ville !== '' ? $ville : null,
                    'adresse'          => $adresse !== '' ? $adresse : null,
                    'site_web'         => $siteWeb !== '' ? $siteWeb : null,
                    'bio'              => $bio !== '' ? $bio : null,
                ]);

                $pdo->commit();

                // Mettre à jour la session utilisateur pour refléter le prénom immédiatement
                if (isset($_SESSION['utilisateur']) && is_array($_SESSION['utilisateur'])) {
                    $_SESSION['utilisateur']['prenom'] = $prenom;
                }

                flash_set('success', 'Vos informations de profil client ont été enregistrées avec succès.');
                redirect(url('client/profil.php'));
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $erreurs[] = 'Une erreur est survenue lors de l\'enregistrement : ' . $e->getMessage();
            }
        }
    }

    // --- FORMULAIRE 2 : SÉCURITÉ / CHANGEMENT DE MOT DE PASSE ---
    elseif ($action === 'securite') {
        $ancienMdp = (string) ($_POST['ancien_mot_de_passe'] ?? '');
        $nouveauMdp = (string) ($_POST['nouveau_mot_de_passe'] ?? '');
        $confMdp = (string) ($_POST['confirmation_mot_de_passe'] ?? '');

        if ($ancienMdp === '' || !password_verify($ancienMdp, (string) $user['mot_de_passe_hash'])) {
            $erreurs[] = 'Votre mot de passe actuel est incorrect.';
        }
        if (mb_strlen($nouveauMdp, 'UTF-8') < 8) {
            $erreurs[] = 'Le nouveau mot de passe doit comporter au moins 8 caractères.';
        }
        if ($nouveauMdp !== $confMdp) {
            $erreurs[] = 'La confirmation ne correspond pas au nouveau mot de passe.';
        }

        if (empty($erreurs)) {
            $nouveauHash = password_hash($nouveauMdp, PASSWORD_DEFAULT);
            $stmtMdp = $pdo->prepare('UPDATE utilisateur SET mot_de_passe_hash = :hash WHERE id_utilisateur = :id');
            $stmtMdp->execute(['hash' => $nouveauHash, 'id' => $idClient]);

            flash_set('success', 'Votre mot de passe a été modifié avec succès.');
            redirect(url('client/profil.php#securite'));
        }
    }
}

// Données courantes pour affichage
$photoActuelle = $profilClient['photo'] ?? null;
$photoAfficheeUrl = (!empty($photoActuelle) && file_exists(__DIR__ . '/../' . $photoActuelle)) 
    ? url($photoActuelle) 
    : url('assets/img/avatar-defaut.svg');

$typeStructureActuelle = (string) ($profilClient['type_structure'] ?? $profilClient['type_client'] ?? 'particulier');
$isStructure = in_array($typeStructureActuelle, ['entreprise', 'societe', 'organisation'], true);
$typeClientActuel = $isStructure ? 'societe' : 'particulier';
$nomSocieteActuelle = (string) ($profilClient['nom_structure'] ?? $profilClient['nom_societe'] ?? '');

$titre_page = 'Mon Profil & Paramètres Client';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4 py-lg-5">
    
    <!-- En-tête de la page -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-2 border-bottom">
        <div>
            <h1 class="h3 fw-bold mb-1">
                Mon Profil &amp; Paramètres
            </h1>
            <p class="text-secondary small mb-0">
                Personnalisez vos coordonnées, votre statut (particulier ou entreprise), votre photo et sécurisez votre compte.
            </p>
        </div>
        <div>
            <a href="<?= url('client/index.php') ?>" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1.5 shadow-2xs">
                <i class="bi bi-arrow-left"></i>
                <span>Retour au tableau de bord</span>
            </a>
        </div>
    </div>

    <!-- Alertes d'erreurs -->
    <?php if (!empty($erreurs)): ?>
        <div class="alert alert-danger border-danger border-opacity-50 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center gap-2 fw-semibold mb-1">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <span>Veuillez corriger les erreurs suivantes :</span>
            </div>
            <ul class="mb-0 small ps-4">
                <?php foreach ($erreurs as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        
        <!-- Navigation latérale Desktop -->
        <aside class="col-lg-3 d-none d-lg-block">
            <div class="card shadow-sm border mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <span class="fw-semibold small text-uppercase text-secondary tracking-wider">Navigation Client</span>
                </div>
                <div class="list-group list-group-flush small">
                    <a href="<?= url('client/index.php') ?>" class="list-group-item list-group-item-action text-secondary d-flex align-items-center justify-content-between py-2">
                        <span><i class="bi bi-speedometer2 me-2"></i>Vue d'ensemble</span>
                        <i class="bi bi-chevron-right small"></i>
                    </a>
                    <a href="<?= url('client/demandes.php') ?>" class="list-group-item list-group-item-action text-secondary d-flex align-items-center justify-content-between py-2">
                        <span><i class="bi bi-grid me-2"></i>Mes demandes</span>
                        <i class="bi bi-chevron-right small"></i>
                    </a>
                    <a href="<?= url('client/profil.php') ?>" class="list-group-item list-group-item-action active fw-medium d-flex align-items-center justify-content-between py-2">
                        <span><i class="bi bi-person-gear me-2"></i>Mon profil &amp; Paramètres</span>
                        <i class="bi bi-chevron-right small"></i>
                    </a>
                </div>
            </div>

            <!-- Aperçu de profil rapide dans la colonne gauche -->
            <div class="card shadow-sm border p-3 text-center">
                <div class="position-relative d-inline-block mx-auto mb-2">
                    <img src="<?= e($photoAfficheeUrl) ?>" alt="Photo de profil" class="rounded-circle object-fit-cover border shadow-sm" style="width: 80px; height: 80px;">
                    <span class="position-absolute bottom-0 end-0 badge rounded-pill <?= $typeClientActuel === 'societe' ? 'bg-primary' : 'bg-success' ?> p-1.5" title="<?= $typeClientActuel === 'societe' ? 'Entreprise / Société' : 'Particulier / Indépendant' ?>">
                        <i class="bi <?= $typeClientActuel === 'societe' ? 'bi-building' : 'bi-person' ?>"></i>
                    </span>
                </div>
                <h6 class="fw-bold text-dark mb-0"><?= e($user['prenom'] . ' ' . $user['nom']) ?></h6>
                <span class="badge bg-light text-secondary border small mt-1 mb-2">
                    <?= $typeClientActuel === 'societe' ? '🏢 Société / Entreprise' : '👤 Particulier / Indépendant' ?>
                </span>
                <?php if ($typeClientActuel === 'societe' && !empty($nomSocieteActuelle)): ?>
                    <p class="small text-muted mb-0 fw-semibold">
                        <i class="bi bi-briefcase me-1"></i><?= e($nomSocieteActuelle) ?>
                    </p>
                <?php endif; ?>
                <p class="small text-secondary mb-0 mt-1">
                    <i class="bi bi-envelope me-1"></i><?= e($user['email']) ?>
                </p>
            </div>
        </aside>

        <!-- Contenu principal -->
        <main class="col-12 col-lg-9">
            
            <!-- Carte 1 : Profil Client & Type de Client -->
            <div class="card shadow-sm border mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <h2 class="h5 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-person-badge text-primary"></i>
                        <span>Informations du profil client</span>
                    </h2>
                    <span class="badge bg-light text-secondary border small">
                        Modifiable à tout moment
                    </span>
                </div>
                <div class="card-body p-4">
                    
                    <form action="<?= url('client/profil.php') ?>" method="POST" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="profil">

                        <!-- SECTION : TYPE DE CLIENT -->
                        <div class="mb-4 pb-3 border-bottom">
                            <label class="form-label fw-bold text-dark d-block mb-2">
                                Type de compte client <span class="text-danger">*</span>
                            </label>
                            <p class="text-secondary small mb-3">
                                Précisez si vous sollicitez des spécialistes pour vos projets personnels ou pour le compte d'une entreprise / société.
                            </p>

                            <div class="row g-3">
                                <!-- Option 1 : Particulier / Indépendant -->
                                <div class="col-12 col-sm-6">
                                    <div class="card p-3 border rounded-3 h-100 cursor-pointer type-client-card <?= $typeClientActuel === 'particulier' ? 'border-primary bg-primary bg-opacity-10' : '' ?>" id="card-particulier">
                                        <div class="form-check d-flex align-items-start gap-2 m-0">
                                            <input class="form-check-input mt-1" type="radio" name="type_client" id="type_particulier" value="particulier" <?= $typeClientActuel === 'particulier' ? 'checked' : '' ?> onchange="majTypeClient()">
                                            <label class="form-check-label w-100 cursor-pointer" for="type_particulier">
                                                <div class="d-flex align-items-center gap-2 mb-1">
                                                    <i class="bi bi-person text-success fs-5"></i>
                                                    <strong class="text-dark">Particulier / Indépendant</strong>
                                                </div>
                                                <span class="text-secondary small d-block">
                                                    Projet individuel, porteur de projet personnel ou créateur de contenu.
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Option 2 : Société / Entreprise -->
                                <div class="col-12 col-sm-6">
                                    <div class="card p-3 border rounded-3 h-100 cursor-pointer type-client-card <?= $typeClientActuel === 'societe' ? 'border-primary bg-primary bg-opacity-10' : '' ?>" id="card-societe">
                                        <div class="form-check d-flex align-items-start gap-2 m-0">
                                            <input class="form-check-input mt-1" type="radio" name="type_client" id="type_societe" value="societe" <?= $typeClientActuel === 'societe' ? 'checked' : '' ?> onchange="majTypeClient()">
                                            <label class="form-check-label w-100 cursor-pointer" for="type_societe">
                                                <div class="d-flex align-items-center gap-2 mb-1">
                                                    <i class="bi bi-building text-primary fs-5"></i>
                                                    <strong class="text-dark">Entreprise / Société / PME</strong>
                                                </div>
                                                <span class="text-secondary small d-block">
                                                    Start-up, agence, commerçant, institution ou organisation professionnelle.
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SECTION : INFORMATIONS ENTREPRISE (AFFICHÉE CONDITIONNELLEMENT) -->
                        <div id="section-societe" class="<?= $typeClientActuel === 'societe' ? '' : 'd-none' ?> mb-4 p-3.5 bg-light rounded-3 border">
                            <h3 class="h6 fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                                <i class="bi bi-building-check text-primary"></i>
                                <span>Informations sur votre Entreprise / Organisation</span>
                            </h3>
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label for="nom_societe" class="form-label small fw-semibold text-dark">
                                        Raison sociale / Nom de la structure <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="nom_societe" name="nom_societe" 
                                           placeholder="Ex : Ivoire Tech Solutions SARL" 
                                           value="<?= e($nomSocieteActuelle) ?>">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="secteur_activite" class="form-label small fw-semibold text-dark">
                                        Secteur d'activité
                                    </label>
                                    <input type="text" class="form-control" id="secteur_activite" name="secteur_activite" 
                                           placeholder="Ex : E-commerce, Finance, Santé, BTP..." 
                                           value="<?= e($profilClient['secteur_activite'] ?? '') ?>">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="poste_occupe" class="form-label small fw-semibold text-dark">
                                        Votre poste / fonction au sein de la structure
                                    </label>
                                    <input type="text" class="form-control" id="poste_occupe" name="poste_occupe" 
                                           placeholder="Ex : Directeur Général, Chef de Projet, Gérant..." 
                                           value="<?= e($profilClient['poste_occupe'] ?? '') ?>">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="siret_rccm" class="form-label small fw-semibold text-dark">
                                        N° RCCM / Registre du commerce (optionnel)
                                    </label>
                                    <input type="text" class="form-control" id="siret_rccm" name="siret_rccm" 
                                           placeholder="Ex : CI-ABJ-2024-B-12345" 
                                           value="<?= e($profilClient['siret_rccm'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label for="site_web" class="form-label small fw-semibold text-dark">
                                        Site internet de l'entreprise (optionnel)
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white text-muted small"><i class="bi bi-globe"></i></span>
                                        <input type="url" class="form-control" id="site_web" name="site_web" 
                                               placeholder="https://www.monsite.ci" 
                                               value="<?= e($profilClient['site_web'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SECTION : PHOTO DE PROFIL OU LOGO -->
                        <div class="mb-4 pb-3 border-bottom">
                            <label class="form-label fw-bold text-dark d-block mb-2">
                                Photo de profil ou logo professionnel
                            </label>
                            <div class="d-flex flex-column flex-sm-row align-items-start gap-3">
                                <div class="position-relative flex-shrink-0">
                                    <img src="<?= e($photoAfficheeUrl) ?>" id="preview-photo" alt="Photo actuelle" 
                                         class="rounded-circle object-fit-cover border shadow-sm" style="width: 88px; height: 88px;">
                                </div>
                                <div class="flex-grow-1">
                                    <input type="file" class="form-control mb-1" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" onchange="previsualiserPhoto(event)">
                                    <div class="form-text small text-secondary">
                                        Formats acceptés : JPG, PNG, WebP. Taille maximale : 2 Mo. Une photo soignée ou un logo rassure vos prestataires.
                                    </div>
                                    <?php if (!empty($photoActuelle)): ?>
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" name="supprimer_photo" value="1" id="supprimer_photo">
                                            <label class="form-check-label text-danger small" for="supprimer_photo">
                                                <i class="bi bi-trash me-1"></i>Supprimer cette photo et utiliser l'avatar par défaut
                                            </label>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- SECTION : IDENTITÉ ET COORDONNÉES PERSONNELLES -->
                        <div class="mb-4 pb-3 border-bottom">
                            <h3 class="h6 fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                                <i class="bi bi-person-lines-fill text-primary"></i>
                                <span>Coordonnées du contact</span>
                            </h3>
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label for="prenom" class="form-label small fw-semibold text-dark">
                                        Prénom <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="prenom" name="prenom" 
                                           value="<?= e($user['prenom']) ?>" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="nom" class="form-label small fw-semibold text-dark">
                                        Nom de famille <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="nom" name="nom" 
                                           value="<?= e($user['nom']) ?>" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="email" class="form-label small fw-semibold text-dark">
                                        Adresse email (identifiant)
                                    </label>
                                    <input type="email" class="form-control bg-light" id="email" 
                                           value="<?= e($user['email']) ?>" readonly disabled>
                                    <div class="form-text small">L'email de connexion est rattaché à votre compte.</div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="telephone" class="form-label small fw-semibold text-dark">
                                        Numéro de téléphone
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white text-muted small"><i class="bi bi-telephone"></i></span>
                                        <input type="tel" class="form-control" id="telephone" name="telephone" 
                                               placeholder="Ex : +225 07 00 00 00 00" 
                                               value="<?= e($user['telephone'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="ville" class="form-label small fw-semibold text-dark">
                                        Ville / Région
                                    </label>
                                    <input type="text" class="form-control" id="ville" name="ville" 
                                           placeholder="Ex : Abidjan, Yamoussoukro, San-Pédro..." 
                                           value="<?= e($profilClient['ville'] ?? '') ?>">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="adresse" class="form-label small fw-semibold text-dark">
                                        Commune / Quartier / Adresse
                                    </label>
                                    <input type="text" class="form-control" id="adresse" name="adresse" 
                                           placeholder="Ex : Cocody Angré, Marcory Zone 4..." 
                                           value="<?= e($profilClient['adresse'] ?? '') ?>">
                                </div>
                            </div>
                        </div>

                        <!-- SECTION : PRÉSENTATION / BESOINS HABITUELS -->
                        <div class="mb-4">
                            <label for="bio" class="form-label fw-bold text-dark d-block mb-1">
                                Présentation &amp; Projets habituels
                            </label>
                            <p class="text-secondary small mb-2">
                                Décrivez brièvement vos attentes ou la nature des projets que vous confiez habituellement aux indépendants.
                            </p>
                            <textarea class="form-control" id="bio" name="bio" rows="3" 
                                      placeholder="Ex : Je recherche régulièrement des développeurs web pour créer des vitrines e-commerce et des monteurs vidéo pour nos réseaux sociaux..."><?= e($profilClient['bio'] ?? '') ?></textarea>
                        </div>

                        <!-- Bouton de validation -->
                        <div class="d-flex justify-content-end pt-2">
                            <button type="submit" class="btn btn-brand px-4 py-2 d-inline-flex align-items-center gap-2 shadow-sm">
                                <i class="bi bi-check-circle-fill"></i>
                                <span>Enregistrer mes modifications</span>
                            </button>
                        </div>

                    </form>

                </div>
            </div>

            <!-- Carte 2 : Sécurité & Changement de mot de passe -->
            <div class="card shadow-sm border" id="securite">
                <div class="card-header bg-white py-3 border-bottom">
                    <h2 class="h5 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-shield-lock text-primary"></i>
                        <span>Sécurité &amp; Mot de passe</span>
                    </h2>
                </div>
                <div class="card-body p-4">
                    
                    <form action="<?= url('client/profil.php') ?>" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="securite">

                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label for="ancien_mot_de_passe" class="form-label small fw-semibold text-dark">
                                    Mot de passe actuel <span class="text-danger">*</span>
                                </label>
                                <input type="password" class="form-control" id="ancien_mot_de_passe" name="ancien_mot_de_passe" required>
                            </div>
                            <div class="col-12 col-md-4">
                                <label for="nouveau_mot_de_passe" class="form-label small fw-semibold text-dark">
                                    Nouveau mot de passe <span class="text-danger">*</span>
                                </label>
                                <input type="password" class="form-control" id="nouveau_mot_de_passe" name="nouveau_mot_de_passe" minlength="8" required>
                                <div class="form-text small">Minimum 8 caractères.</div>
                            </div>
                            <div class="col-12 col-md-4">
                                <label for="confirmation_mot_de_passe" class="form-label small fw-semibold text-dark">
                                    Confirmer le mot de passe <span class="text-danger">*</span>
                                </label>
                                <input type="password" class="form-control" id="confirmation_mot_de_passe" name="confirmation_mot_de_passe" minlength="8" required>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" class="btn btn-outline-primary px-3 py-2 d-inline-flex align-items-center gap-2">
                                <i class="bi bi-key-fill"></i>
                                <span>Mettre à jour le mot de passe</span>
                            </button>
                        </div>
                    </form>

                </div>
            </div>

        </main>

    </div>

</div>

<script>
function majTypeClient() {
    const isSociete = document.getElementById('type_societe').checked;
    const secSociete = document.getElementById('section-societe');
    const cardParticulier = document.getElementById('card-particulier');
    const cardSociete = document.getElementById('card-societe');

    if (isSociete) {
        secSociete.classList.remove('d-none');
        cardSociete.classList.add('border-primary', 'bg-primary', 'bg-opacity-10');
        cardParticulier.classList.remove('border-primary', 'bg-primary', 'bg-opacity-10');
        document.getElementById('nom_societe').focus();
    } else {
        secSociete.classList.add('d-none');
        cardParticulier.classList.add('border-primary', 'bg-primary', 'bg-opacity-10');
        cardSociete.classList.remove('border-primary', 'bg-primary', 'bg-opacity-10');
    }
}

function previsualiserPhoto(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('preview-photo').src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
}
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
