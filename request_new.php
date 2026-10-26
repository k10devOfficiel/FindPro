<?php
/**
 * Formulaire de nouvelle demande de service — FindPro
 *
 * RÈGLES D'ACCÈS & SÉCURITÉ (RG01, RG02, RG03, RG05, RG10) :
 * - Accès strictement réservé aux clients authentifiés via exiger_role('client').
 * - Un visiteur non connecté est redirigé vers login.php?retour=... et sera ramené
 *   sur ce formulaire après connexion ou inscription.
 * - Un professionnel connecté reçoit un message explicite HTTP 403 :
 *   "Seuls les comptes clients peuvent envoyer une demande".
 * - Le profil cible doit exister, être approuvé et avoir un compte actif sinon HTTP 404.
 * - Un utilisateur ne peut pas envoyer de demande à son propre profil.
 * - Validation stricte des champs côté serveur et protection anti-CSRF.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/errors.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

demarrer_session();

// ============================================================================
// 1. CONTRÔLE D'ACCÈS DU RÔLE (CLIENT REQUIS)
// ============================================================================
// Si visiteur : redirige vers login.php?retour=[cette page]
// Si professionnel : émet un HTTP 403 avec le message explicite configuré
$utilisateur = exiger_role('client');

$pdo = db();

// ============================================================================
// 2. VALIDATION ET RÉCUPÉRATION DU PROFIL CIBLE
// ============================================================================

$idProfilParam = $_GET['profil'] ?? $_GET['id'] ?? null;
if ($idProfilParam === null || !ctype_digit((string) $idProfilParam) || (int) $idProfilParam <= 0) {
    page_introuvable("L'identifiant du professionnel est manquant ou invalide.", "Profil introuvable");
}
$idProfil = (int) $idProfilParam;

$stmtProfil = $pdo->prepare('
    SELECT 
        p.id_profil,
        p.id_utilisateur,
        p.id_categorie,
        p.titre,
        p.description,
        p.zone,
        p.disponibilite,
        p.photo,
        p.statut_validation,
        p.engagement_cyber,
        u.nom,
        u.prenom,
        u.statut_compte,
        c.libelle AS categorie_libelle,
        c.slug AS categorie_slug
    FROM profil_professionnel p
    JOIN utilisateur u ON p.id_utilisateur = u.id_utilisateur
    LEFT JOIN categorie c ON p.id_categorie = c.id_categorie
    WHERE p.id_profil = :id
    LIMIT 1
');
$stmtProfil->execute(['id' => $idProfil]);
$profilCible = $stmtProfil->fetch(PDO::FETCH_ASSOC);

// Condition stricte : doit exister, être approuvé et avoir un compte actif sinon 404
if (!$profilCible || $profilCible['statut_validation'] !== 'approuve' || $profilCible['statut_compte'] !== 'actif') {
    page_introuvable("Le professionnel demandé est introuvable ou n'est plus en mesure de recevoir des demandes.", "Profil introuvable");
}

// Formatage du nom public du professionnel (RG05)
$prenomPro = trim((string) $profilCible['prenom']);
$nomPro = trim((string) $profilCible['nom']);
$initialeNomPro = $nomPro !== '' ? mb_strtoupper(mb_substr($nomPro, 0, 1, 'UTF-8'), 'UTF-8') . '.' : '';
$nomAffichePro = trim($prenomPro . ' ' . $initialeNomPro);
if ($nomAffichePro === '') {
    $nomAffichePro = 'Professionnel FindPro';
}

// Résolution de l'avatar du professionnel
$photoUrl = null;
if (!empty($profilCible['photo']) && file_exists(__DIR__ . '/' . $profilCible['photo'])) {
    $photoUrl = url($profilCible['photo']);
} elseif (file_exists(__DIR__ . '/assets/img/avatar-defaut.svg')) {
    $photoUrl = url('assets/img/avatar-defaut.svg');
}

// Récupération des catégories pour la sélection du formulaire
$categories = $pdo->query('
    SELECT id_categorie, libelle, slug 
    FROM categorie 
    ORDER BY id_categorie ASC
')->fetchAll(PDO::FETCH_ASSOC);

$categoriesValidesIds = array_map(static fn($c) => (int) $c['id_categorie'], $categories);

// ============================================================================
// 3. VÉRIFICATION DE LA RÈGLE : INTERDICTION DE DEMANDE À SON PROPRE PROFIL
// ============================================================================

$estSonPropreProfil = ((int) $utilisateur['id'] === (int) $profilCible['id_utilisateur']);

// ============================================================================
// 4. TRAITEMENT DE LA SOUMISSION DU FORMULAIRE (MÉTHODE POST)
// ============================================================================

$titre = '';
$description = '';
$idCategorieChoisie = (int) $profilCible['id_categorie'];
$delaiSouhaite = '';
$budgetIndicatif = '';
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 4.1. Validation CSRF
    csrf_verifier();

    // 4.2. Refus immédiat si demande à son propre profil
    if ($estSonPropreProfil) {
        $erreurs['general'] = "Vous ne pouvez pas envoyer de demande de prestation à votre propre profil professionnel.";
    }

    // 4.3. Récupération et assainissement des entrées
    $titre = trim((string) ($_POST['titre'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $idCategoriePost = filter_var($_POST['categorie'] ?? null, FILTER_VALIDATE_INT);
    $delaiPost = trim((string) ($_POST['delai_souhaite'] ?? ''));
    $budgetPost = trim((string) ($_POST['budget_indicatif'] ?? ''));

    // 4.4. Contrôles de validation des champs
    // Titre : obligatoire, max 150 caractères
    if ($titre === '') {
        $erreurs['titre'] = "Veuillez indiquer un titre clair pour votre demande.";
    } elseif (mb_strlen($titre, 'UTF-8') > 150) {
        $erreurs['titre'] = "Le titre ne doit pas dépasser 150 caractères (actuellement : " . mb_strlen($titre, 'UTF-8') . ").";
    }

    // Description : obligatoire, min 30, max 3000 caractères
    $longueurDesc = mb_strlen($description, 'UTF-8');
    if ($description === '') {
        $erreurs['description'] = "Veuillez décrire précisément votre besoin (minimum 30 caractères).";
    } elseif ($longueurDesc < 30) {
        $erreurs['description'] = "La description est trop courte. Veuillez détailler votre besoin (minimum 30 caractères, actuellement : {$longueurDesc}).";
    } elseif ($longueurDesc > 3000) {
        $erreurs['description'] = "La description ne doit pas dépasser 3 000 caractères (actuellement : {$longueurDesc}).";
    }

    // Catégorie : obligatoire et valide
    if ($idCategoriePost === false || !in_array((int) $idCategoriePost, $categoriesValidesIds, true)) {
        $erreurs['categorie'] = "Veuillez sélectionner une catégorie de métier valide.";
    } else {
        $idCategorieChoisie = (int) $idCategoriePost;
    }

    // Délai souhaité : optionnel, mais si renseigné, doit être une date future valide
    $delaiValide = null;
    if ($delaiPost !== '') {
        $dateObj = DateTimeImmutable::createFromFormat('Y-m-d', $delaiPost);
        $aujourdhui = new DateTimeImmutable('today');
        if ($dateObj === false || $dateObj->format('Y-m-d') !== $delaiPost) {
            $erreurs['delai_souhaite'] = "Le format de la date est invalide (format attendu : AAAA-MM-JJ).";
        } elseif ($dateObj < $aujourdhui) {
            $erreurs['delai_souhaite'] = "Le délai souhaité doit être une date future ou celle d'aujourd'hui.";
        } else {
            $delaiValide = $delaiPost;
            $delaiSouhaite = $delaiPost;
        }
    }

    // Budget indicatif en FCFA : optionnel, entier positif si renseigné
    $budgetValide = null;
    if ($budgetPost !== '') {
        // Nettoyage des espaces éventuels
        $budgetNettoye = str_replace(' ', '', $budgetPost);
        if (!ctype_digit($budgetNettoye)) {
            $erreurs['budget_indicatif'] = "Le budget indicatif doit être un montant entier positif en FCFA.";
        } else {
            $valBudget = (int) $budgetNettoye;
            if ($valBudget < 0) {
                $erreurs['budget_indicatif'] = "Le budget indicatif ne peut pas être négatif.";
            } else {
                $budgetValide = $valBudget;
                $budgetIndicatif = (string) $valBudget;
            }
        }
    }

    // 4.5. Vérifications d'intégrité métier et anti-spam avant transaction
    $idClient = (int) ($utilisateur['id'] ?? $utilisateur['id_utilisateur'] ?? 0);

    // Vérification que le profil cible est toujours approuvé et actif au moment de l'envoi
    $stmtCheckPro = $pdo->prepare('
        SELECT p.id_profil, p.statut_validation, u.statut_compte
        FROM profil_professionnel p
        JOIN utilisateur u ON p.id_utilisateur = u.id_utilisateur
        WHERE p.id_profil = :id
        LIMIT 1
    ');
    $stmtCheckPro->execute(['id' => $idProfil]);
    $proActuel = $stmtCheckPro->fetch(PDO::FETCH_ASSOC);

    if (!$proActuel || $proActuel['statut_validation'] !== 'approuve' || $proActuel['statut_compte'] !== 'actif') {
        $erreurs['general'] = "Ce profil professionnel n'est plus disponible pour recevoir de nouvelles demandes.";
    }

    // Limite anti-spam 1 : Pas plus de 3 demandes au statut 'envoyee' par ce client vers ce même pro
    if (empty($erreurs['general'])) {
        $stmtSpamPro = $pdo->prepare('
            SELECT COUNT(*) 
            FROM demande_service 
            WHERE id_client = :id_client 
              AND id_profil = :id_profil 
              AND statut = "envoyee"
        ');
        $stmtSpamPro->execute([
            'id_client' => $idClient,
            'id_profil' => $idProfil,
        ]);
        if ((int) $stmtSpamPro->fetchColumn() >= 3) {
            $erreurs['general'] = "Vous avez déjà 3 demandes en attente auprès de ce professionnel. Veuillez attendre sa réponse avant d'en envoyer une nouvelle.";
        }
    }

    // Limite anti-spam 2 : Pas plus de 10 demandes envoyées par ce client par 24 heures
    if (empty($erreurs['general'])) {
        $stmtSpam24h = $pdo->prepare('
            SELECT COUNT(*) 
            FROM demande_service 
            WHERE id_client = :id_client 
              AND date_creation >= NOW() - INTERVAL 24 HOUR
        ');
        $stmtSpam24h->execute([
            'id_client' => $idClient,
        ]);
        if ((int) $stmtSpam24h->fetchColumn() >= 10) {
            $erreurs['general'] = "Vous avez atteint la limite maximale de 10 demandes envoyées par 24 heures. Veuillez réessayer ultérieurement.";
        }
    }

    // 4.6. Enregistrement dans UNE SEULE TRANSACTION ATOMIQUE PDO
    if (empty($erreurs)) {
        try {
            $pdo->beginTransaction();

            // Étape 1 : Insertion de la demande au statut 'envoyee' (T07)
            $stmtInsert = $pdo->prepare('
                INSERT INTO demande_service (
                    id_client,
                    id_profil,
                    id_categorie,
                    titre,
                    description,
                    budget_indicatif,
                    delai_souhaite,
                    statut,
                    date_creation
                ) VALUES (
                    :id_client,
                    :id_profil,
                    :id_categorie,
                    :titre,
                    :description,
                    :budget_indicatif,
                    :delai_souhaite,
                    \'envoyee\',
                    NOW()
                )
            ');

            $stmtInsert->execute([
                'id_client'        => $idClient,
                'id_profil'        => $idProfil,
                'id_categorie'     => $idCategorieChoisie,
                'titre'            => $titre,
                'description'      => $description,
                'budget_indicatif' => $budgetValide,
                'delai_souhaite'   => $delaiValide,
            ]);

            $idDemande = (int) $pdo->lastInsertId();

            // Étape 2 : Création de la conversation liée (statut 'active')
            $stmtConv = $pdo->prepare('
                INSERT INTO conversation (
                    id_demande,
                    statut,
                    date_creation
                ) VALUES (
                    :id_demande,
                    \'active\',
                    NOW()
                )
            ');
            $stmtConv->execute(['id_demande' => $idDemande]);
            $idConversation = (int) $pdo->lastInsertId();

            // Étape 3 : Insertion du premier message (reprise de la description de la demande comme premier message du client)
            $stmtMsg = $pdo->prepare('
                INSERT INTO message (
                    id_conversation,
                    id_expediteur,
                    contenu,
                    date_envoi,
                    lu
                ) VALUES (
                    :id_conversation,
                    :id_expediteur,
                    :contenu,
                    NOW(),
                    0
                )
            ');
            $stmtMsg->execute([
                'id_conversation' => $idConversation,
                'id_expediteur'   => $idClient,
                'contenu'         => $description,
            ]);

            // Validation de toutes les opérations conjointes
            $pdo->commit();

            // Notification flash et redirection vers la page de la demande
            flash_set('success', 'Demande envoyée');
            redirect(url('demande_detail.php?id=' . $idDemande));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            log_erreur("Erreur transaction création demande de service : " . $e->getMessage());
            $erreurs['general'] = "Une erreur technique est survenue lors de l'envoi de votre demande. Veuillez réessayer.";
        }
    }
}

// Titre de la page
$titre_page = 'Nouvelle demande pour ' . $nomAffichePro . ' — FindPro';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ========================================================================= -->
<!-- FIL D'ARIANE ACCESSIBLE                                                  -->
<!-- ========================================================================= -->
<nav class="py-3 bg-light border-bottom" aria-label="Fil d'Ariane">
    <div class="container">
        <ol class="breadcrumb small mb-0">
            <li class="breadcrumb-item">
                <a href="<?= url('') ?>" class="text-secondary text-decoration-none hover-primary">Accueil</a>
            </li>
            <li class="breadcrumb-item">
                <a href="<?= url('search.php') ?>" class="text-secondary text-decoration-none hover-primary">Recherche</a>
            </li>
            <li class="breadcrumb-item">
                <a href="<?= url('profile.php?id=' . $profilCible['id_profil']) ?>" class="text-secondary text-decoration-none hover-primary">
                    <?= e($nomAffichePro) ?>
                </a>
            </li>
            <li class="breadcrumb-item active text-dark fw-medium" aria-current="page">
                Envoyer une demande
            </li>
        </ol>
    </div>
</nav>

<div class="container py-4 py-lg-5">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10 col-xl-8">

            <!-- ================================================================= -->
            <!-- ALERTE SI DEMANDE À SON PROPRE PROFIL (REFUS STRICT)              -->
            <!-- ================================================================= -->
            <?php if ($estSonPropreProfil): ?>
                <div class="alert alert-danger d-flex align-items-start gap-3 shadow-2xs mb-4" role="alert">
                    <i class="bi bi-x-octagon-fill fs-3 text-danger flex-shrink-0" aria-hidden="true"></i>
                    <div>
                        <strong class="d-block fs-6 mb-1">Action non autorisée</strong>
                        <span>Vous êtes le titulaire de ce profil professionnel. Vous ne pouvez pas vous envoyer de demande de prestation à vous-même.</span>
                        <div class="mt-2">
                            <a href="<?= url('profile.php?id=' . $profilCible['id_profil']) ?>" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Retourner à la fiche du profil
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Message d'erreur général éventuel -->
            <?php if (!empty($erreurs['general'])): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 shadow-2xs mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill flex-shrink-0" aria-hidden="true"></i>
                    <div><?= e($erreurs['general']) ?></div>
                </div>
            <?php endif; ?>

            <!-- ================================================================= -->
            <!-- RÉSUMÉ DU PROFESSIONNEL EN HAUT DU FORMULAIRE                     -->
            <!-- ================================================================= -->
            <div class="card shadow-2xs border mb-4">
                <div class="card-body p-3.5 p-md-4">
                    <div class="d-flex align-items-center gap-3">
                        
                        <!-- Photo / Avatar du professionnel -->
                        <div class="flex-shrink-0">
                            <?php if ($photoUrl !== null): ?>
                                <img src="<?= e($photoUrl) ?>" 
                                     alt="Photo de <?= e($nomAffichePro) ?>" 
                                     class="rounded-3 border object-fit-cover shadow-2xs" 
                                     style="width: 72px; height: 72px;">
                            <?php else: ?>
                                <div class="img-placeholder rounded-3 shadow-2xs d-flex align-items-center justify-content-center" style="width: 72px; height: 72px; min-height: 72px; padding: 0;">
                                    <i class="bi bi-person fs-3" aria-hidden="true"></i>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Informations synthétiques -->
                        <div class="min-w-0 flex-grow-1">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <span class="badge bg-light text-secondary border small">Destinataire</span>
                                <h2 class="h5 fw-bold text-dark mb-0">
                                    <?= e($nomAffichePro) ?>
                                </h2>
                                <span class="badge bg-success-subtle text-success border border-success-subtle small d-inline-flex align-items-center gap-1">
                                    <i class="bi bi-patch-check-fill" aria-hidden="true"></i>
                                    <span>Vérifié</span>
                                </span>
                            </div>
                            <p class="text-primary fw-semibold small mb-1.5 text-truncate">
                                <?= e($profilCible['titre']) ?>
                            </p>
                            <div class="d-flex flex-wrap align-items-center gap-2 text-secondary small" style="font-size: 0.8rem;">
                                <span><i class="bi bi-briefcase me-1" aria-hidden="true"></i><?= e($profilCible['categorie_libelle'] ?? 'Métier') ?></span>
                                <span>•</span>
                                <span><i class="bi bi-geo-alt me-1" aria-hidden="true"></i><?= e($profilCible['zone']) ?></span>
                            </div>
                        </div>

                        <!-- Lien vers le profil -->
                        <div class="d-none d-sm-block text-end">
                            <a href="<?= url('profile.php?id=' . $profilCible['id_profil']) ?>" 
                               class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1"
                               target="_blank"
                               title="Consulter le profil complet dans un nouvel onglet">
                                <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                                <span>Fiche</span>
                            </a>
                        </div>

                    </div>
                </div>
            </div>

            <!-- ================================================================= -->
            <!-- FORMULAIRE DE DEMANDE DE SERVICE                                  -->
            <!-- ================================================================= -->
            <div class="card shadow-2xs border">
                <div class="card-header bg-white border-bottom py-3.5">
                    <h1 class="h5 fw-bold text-dark mb-1">
                        Formulaire de demande de prestation
                    </h1>
                    <p class="text-secondary small mb-0">
                        Décrivez précisément votre projet. Ce professionnel prendra connaissance de votre cahier des charges et pourra accepter votre demande pour démarrer l'échange.
                    </p>
                </div>
                
                <div class="card-body p-4 p-md-5">
                    
                    <form method="POST" action="<?= url('request_new.php?profil=' . $profilCible['id_profil']) ?>" novalidate>
                        <?= csrf_field() ?>

                        <!-- 1. Titre de la mission -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="titre" class="form-label fw-semibold text-dark small mb-0">
                                    Titre de votre demande <span class="text-danger">*</span>
                                </label>
                                <span class="text-muted small" id="titre-compteur" style="font-size: 0.78rem;">
                                    Max 150 caractères
                                </span>
                            </div>
                            <input type="text" 
                                   class="form-control <?= isset($erreurs['titre']) ? 'is-invalid' : '' ?>" 
                                   id="titre" 
                                   name="titre" 
                                   value="<?= e($titre) ?>" 
                                   maxlength="150" 
                                   placeholder="Ex : Création d'une API REST pour application mobile, Montage de 3 Reels..." 
                                   required 
                                   <?= $estSonPropreProfil ? 'disabled' : '' ?>
                                   autofocus>
                            <?php if (isset($erreurs['titre'])): ?>
                                <div class="invalid-feedback d-block"><?= e($erreurs['titre']) ?></div>
                            <?php else: ?>
                                <div class="form-text small text-secondary">
                                    Donnez un titre court et représentatif de la prestation attendue.
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- 2. Catégorie de métier pré-sélectionnée -->
                        <div class="mb-4">
                            <label for="categorie" class="form-label fw-semibold text-dark small mb-1">
                                Catégorie de métier <span class="text-danger">*</span>
                            </label>
                            <select class="form-select <?= isset($erreurs['categorie']) ? 'is-invalid' : '' ?>" 
                                    id="categorie" 
                                    name="categorie" 
                                    required 
                                    <?= $estSonPropreProfil ? 'disabled' : '' ?>>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int) $cat['id_categorie'] ?>" <?= $idCategorieChoisie === (int) $cat['id_categorie'] ? 'selected' : '' ?>>
                                        <?= e($cat['libelle']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($erreurs['categorie'])): ?>
                                <div class="invalid-feedback d-block"><?= e($erreurs['categorie']) ?></div>
                            <?php else: ?>
                                <div class="form-text small text-secondary">
                                    Pré-sélectionnée selon le profil du professionnel sollicité.
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- 3. Description détaillée du besoin -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="description" class="form-label fw-semibold text-dark small mb-0">
                                    Description détaillée du besoin <span class="text-danger">*</span>
                                </label>
                                <span class="text-muted small" id="desc-compteur" style="font-size: 0.78rem;">
                                    De 30 à 3 000 caractères
                                </span>
                            </div>
                            <textarea class="form-control <?= isset($erreurs['description']) ? 'is-invalid' : '' ?>" 
                                      id="description" 
                                      name="description" 
                                      rows="6" 
                                      minlength="30" 
                                      maxlength="3000" 
                                      placeholder="Expliquez en détail vos attentes : contexte du projet, livrables attendus, contraintes techniques, délais ou technologies spécifiques..." 
                                      required 
                                      <?= $estSonPropreProfil ? 'disabled' : '' ?>><?= e($description) ?></textarea>
                            <?php if (isset($erreurs['description'])): ?>
                                <div class="invalid-feedback d-block"><?= e($erreurs['description']) ?></div>
                            <?php else: ?>
                                <div class="form-text small text-secondary">
                                    Une description précise permet au professionnel d'évaluer rapidement la faisabilité de votre demande.
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="row g-3 mb-4">
                            
                            <!-- 4. Délai souhaité (Optionnel) -->
                            <div class="col-12 col-md-6">
                                <label for="delai_souhaite" class="form-label fw-semibold text-dark small mb-1">
                                    Délai souhaité <span class="text-muted fw-normal">(optionnel)</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white text-secondary">
                                        <i class="bi bi-calendar-event" aria-hidden="true"></i>
                                    </span>
                                    <input type="date" 
                                           class="form-control <?= isset($erreurs['delai_souhaite']) ? 'is-invalid' : '' ?>" 
                                           id="delai_souhaite" 
                                           name="delai_souhaite" 
                                           value="<?= e($delaiSouhaite) ?>" 
                                           min="<?= date('Y-m-d') ?>" 
                                           <?= $estSonPropreProfil ? 'disabled' : '' ?>>
                                </div>
                                <?php if (isset($erreurs['delai_souhaite'])): ?>
                                    <div class="invalid-feedback d-block"><?= e($erreurs['delai_souhaite']) ?></div>
                                <?php else: ?>
                                    <div class="form-text small text-secondary">
                                        Date indicative à laquelle vous aimeriez finaliser la prestation.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- 5. Budget indicatif en FCFA (Optionnel) -->
                            <div class="col-12 col-md-6">
                                <label for="budget_indicatif" class="form-label fw-semibold text-dark small mb-1">
                                    Budget indicatif (FCFA) <span class="text-muted fw-normal">(optionnel)</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white text-secondary fw-semibold">
                                        FCFA
                                    </span>
                                    <input type="number" 
                                           class="form-control <?= isset($erreurs['budget_indicatif']) ? 'is-invalid' : '' ?>" 
                                           id="budget_indicatif" 
                                           name="budget_indicatif" 
                                           value="<?= e($budgetIndicatif) ?>" 
                                           min="0" 
                                           step="5000" 
                                           placeholder="Ex : 150000" 
                                           <?= $estSonPropreProfil ? 'disabled' : '' ?>>
                                </div>
                                <?php if (isset($erreurs['budget_indicatif'])): ?>
                                    <div class="invalid-feedback d-block"><?= e($erreurs['budget_indicatif']) ?></div>
                                <?php else: ?>
                                    <div class="form-text small text-secondary">
                                        Estimation indicative pour orienter les discussions.
                                    </div>
                                <?php endif; ?>
                            </div>

                        </div>


                        <!-- Boutons d'action -->
                        <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between gap-3 pt-3 border-top">
                            <a href="<?= url('profile.php?id=' . $profilCible['id_profil']) ?>" 
                               class="btn btn-outline-secondary w-100 w-sm-auto d-inline-flex align-items-center justify-content-center gap-1.5">
                                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                                <span>Annuler et retourner au profil</span>
                            </a>
                            
                            <button type="submit" 
                                    class="btn btn-primary w-100 w-sm-auto d-inline-flex align-items-center justify-content-center gap-2 shadow-2xs py-2 px-4" 
                                    <?= $estSonPropreProfil ? 'disabled' : '' ?>>
                                <i class="bi bi-send-fill" aria-hidden="true"></i>
                                <span>Transmettre ma demande</span>
                            </button>
                        </div>

                    </form>

                </div>
            </div>

        </div>
    </div>
</div>

<script>
// Compteur dynamique de caractères pour le titre et la description
document.addEventListener('DOMContentLoaded', function() {
    var descEl = document.getElementById('description');
    var descCompteur = document.getElementById('desc-compteur');
    if (descEl && descCompteur) {
        function majDesc() {
            var len = descEl.value.length;
            descCompteur.textContent = len + ' / 3000 caractères' + (len < 30 ? ' (min. 30)' : '');
            if (len < 30) {
                descCompteur.className = 'text-warning small';
            } else if (len > 3000) {
                descCompteur.className = 'text-danger small';
            } else {
                descCompteur.className = 'text-muted small';
            }
        }
        descEl.addEventListener('input', majDesc);
        majDesc();
    }
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
