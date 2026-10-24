<?php
/**
 * Fiche de profil professionnel public — FindPro
 *
 * RÈGLES DE CONFIDENTIALITÉ & DE SÉCURITÉ (RG01, RG05, RG11) :
 * - Accès public sans authentification préalable pour les profils approuvés (RG01).
 * - Si l'id n'est pas un entier ou si le profil n'est pas approuvé ou si le compte
 *   est inactif/désactivé : HTTP 404 stricte via page_introuvable(), MÊME pour un accès direct
 *   par URL (un profil non approuvé ne doit jamais être devinable).
 * - Exception : le professionnel propriétaire voit son propre profil avec un bandeau
 *   "Aperçu - non public" ; l'administrateur également.
 * - Le nom public respecte la confidentialité (Prénom + Initiale du nom + point).
 * - Respect strict de docs/CHARTE_GRAPHIQUE_FINDPRO.md, Bootstrap 5 et Bootstrap Icons.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/errors.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/session.php';

demarrer_session();
$pdo = db();

// ============================================================================
// 1. VALIDATION DE L'IDENTIFIANT EN PARAMÈTRE
// ============================================================================

$idParam = $_GET['id'] ?? null;
if ($idParam === null || !ctype_digit((string) $idParam) || (int) $idParam <= 0) {
    page_introuvable("L'identifiant du professionnel est invalide ou manquant.", "Profil introuvable");
}
$idProfil = (int) $idParam;

// ============================================================================
// 2. RÉCUPÉRATION DU PROFIL ET DU COMPTE UTILISATEUR ASSOCIÉ
// ============================================================================

$stmt = $pdo->prepare('
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
        p.date_engagement_cyber,
        p.date_validation,
        p.date_maj,
        u.nom,
        u.prenom,
        u.statut_compte,
        u.role AS user_role,
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
$profil = $stmt->fetch(PDO::FETCH_ASSOC);

// Si le profil n'existe pas en base : HTTP 404 stricte
if (!$profil) {
    page_introuvable("Le profil professionnel demandé n'existe pas.", "Profil introuvable");
}

// ============================================================================
// 3. CONTRÔLE D'ACCÈS ET RÈGLE D'EXCEPTION (PROPRIÉTAIRE & ADMINISTRATEUR)
// ============================================================================

$estApprouve = ($profil['statut_validation'] === 'approuve');
$estActif = ($profil['statut_compte'] === 'actif');
$estPublic = ($estApprouve && $estActif);

// Identification de l'utilisateur courant en session
$userConnecte = utilisateur_courant();
$estConnecte = ($userConnecte !== null);
$idUserConnecte = $estConnecte ? (int) ($userConnecte['id'] ?? $userConnecte['id_utilisateur'] ?? 0) : null;
$roleUserConnecte = $estConnecte ? (string) $userConnecte['role'] : null;

$estProprietaire = ($estConnecte && $idUserConnecte === (int) $profil['id_utilisateur']);
$estAdmin = ($estConnecte && $roleUserConnecte === 'admin');
$peutVoirApercu = ($estProprietaire || $estAdmin);

// Si le profil n'est pas approuvé ou inactif et que le visiteur n'a pas les droits d'aperçu :
// Renvoyer strictement HTTP 404 pour empêcher toute devinette ou énumération d'ID.
if (!$estPublic && !$peutVoirApercu) {
    page_introuvable("Le profil professionnel demandé est introuvable ou n'est plus accessible.", "Profil introuvable");
}

// ============================================================================
// 4. RÉCUPÉRATION DES COMPÉTENCES AVEC NIVEAU DÉCLARÉ
// ============================================================================

$stmtComps = $pdo->prepare('
    SELECT 
        c.id_competence,
        c.libelle,
        pc.niveau_declare
    FROM profil_competence pc
    JOIN competence c ON pc.id_competence = c.id_competence
    WHERE pc.id_profil = :id_profil
    ORDER BY 
        CASE pc.niveau_declare 
            WHEN \'expert\' THEN 4 
            WHEN \'avance\' THEN 3 
            WHEN \'intermediaire\' THEN 2 
            ELSE 1 
        END DESC, c.libelle ASC
');
$stmtComps->execute(['id_profil' => $idProfil]);
$competences = $stmtComps->fetchAll(PDO::FETCH_ASSOC);

$libellesNiveau = [
    'expert'        => 'Expert',
    'avance'        => 'Avancé',
    'intermediaire' => 'Intermédiaire',
    'debutant'      => 'Débutant',
];

// ============================================================================
// 5. RÉCUPÉRATION DES RÉALISATIONS DU PORTFOLIO
// ============================================================================

$stmtPort = $pdo->prepare('
    SELECT 
        id_projet,
        titre,
        description,
        lien_externe,
        image,
        date_projet,
        url_media,
        type_media,
        date_ajout
    FROM projet_portfolio
    WHERE id_profil = :id_profil
    ORDER BY COALESCE(date_projet, date_ajout) DESC, id_projet DESC
');
$stmtPort->execute(['id_profil' => $idProfil]);
$projets = $stmtPort->fetchAll(PDO::FETCH_ASSOC);

// ============================================================================
// 6. RÉCUPÉRATION DES AVIS PUBLIÉS ET DE LA NOTE MOYENNE
// ============================================================================

$stmtAvisStats = $pdo->prepare('
    SELECT 
        ROUND(AVG(a.note), 1) AS note_moyenne,
        COUNT(a.id_avis) AS nb_avis
    FROM avis a
    JOIN demande_service d ON a.id_demande = d.id_demande
    WHERE d.id_profil = :id_profil AND a.statut = \'publie\'
');
$stmtAvisStats->execute(['id_profil' => $idProfil]);
$statsAvis = $stmtAvisStats->fetch(PDO::FETCH_ASSOC);
$noteMoyenne = $statsAvis['note_moyenne'] !== null ? (float) $statsAvis['note_moyenne'] : null;
$nbAvis = (int) ($statsAvis['nb_avis'] ?? 0);

$stmtAvis = $pdo->prepare('
    SELECT 
        a.id_avis,
        a.note,
        a.commentaire,
        a.date_avis,
        u.prenom,
        u.nom
    FROM avis a
    JOIN demande_service d ON a.id_demande = d.id_demande
    JOIN utilisateur u ON a.id_client = u.id_utilisateur
    WHERE d.id_profil = :id_profil AND a.statut = \'publie\'
    ORDER BY a.date_avis DESC
    LIMIT 10
');
$stmtAvis->execute(['id_profil' => $idProfil]);
$avisPublies = $stmtAvis->fetchAll(PDO::FETCH_ASSOC);

// ============================================================================
// 7. FORMATAGE DES DONNÉES D'AFFICHAGE (RG05)
// ============================================================================

$prenom = trim((string) $profil['prenom']);
$nom = trim((string) $profil['nom']);
$initialeNom = $nom !== '' ? mb_strtoupper(mb_substr($nom, 0, 1, 'UTF-8'), 'UTF-8') . '.' : '';
$nomAffiche = trim($prenom . ' ' . $initialeNom);
if ($nomAffiche === '') {
    $nomAffiche = 'Professionnel FindPro';
}

$titre = (string) ($profil['titre'] ?? 'Professionnel du numérique');
$categorieLibelle = (string) ($profil['categorie_libelle'] ?? 'Métier du numérique');
$categorieSlug = (string) ($profil['categorie_slug'] ?? '');
$zone = (string) ($profil['zone'] ?? 'Non précisée');
$dispo = (string) ($profil['disponibilite'] ?? 'disponible');
$isCyber = ($categorieSlug === 'cybersecurite') || !empty($profil['engagement_cyber']);

// Résolution de la photo de profil
$avatarDefaut = url('assets/img/avatar-defaut.svg');
$photoUrl = null;
if (!empty($profil['photo']) && file_exists(__DIR__ . '/' . $profil['photo'])) {
    $photoUrl = url($profil['photo']);
} elseif (file_exists(__DIR__ . '/assets/img/avatar-defaut.svg')) {
    $photoUrl = $avatarDefaut;
}

$titre_page = $nomAffiche . ' — ' . $titre;
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
            <?php if ($categorieSlug !== ''): ?>
                <li class="breadcrumb-item">
                    <a href="<?= url('category.php?slug=' . urlencode($categorieSlug)) ?>" class="text-secondary text-decoration-none hover-primary">
                        <?= e($categorieLibelle) ?>
                    </a>
                </li>
            <?php endif; ?>
            <li class="breadcrumb-item active text-dark fw-medium" aria-current="page">
                <?= e($nomAffiche) ?>
            </li>
        </ol>
    </div>
</nav>

<div class="container py-4 py-lg-5">

    <!-- ===================================================================== -->
    <!-- BANDEAU D'EXCEPTION : "APERÇU - NON PUBLIC"                           -->
    <!-- Visible uniquement par le propriétaire du profil ou l'administrateur  -->
    <!-- ===================================================================== -->
    <?php if (!$estPublic && $peutVoirApercu): ?>
        <div class="alert alert-warning border-warning shadow-2xs mb-4" role="alert">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center gap-2.5">
                    <i class="bi bi-eye-slash-fill fs-3 text-warning-emphasis flex-shrink-0" aria-hidden="true"></i>
                    <div>
                        <strong class="text-dark d-block fs-6">Aperçu - non public</strong>
                        <span class="small text-secondary">
                            Ce profil est actuellement masqué au public. (Statut : 
                            <span class="badge bg-white text-dark border"><?= e($profil['statut_validation']) ?></span>
                            — Compte : 
                            <span class="badge bg-white text-dark border"><?= e($profil['statut_compte']) ?></span>).
                            Vous le visualisez en mode aperçu en tant que <?= $estAdmin ? '<strong>administrateur</strong>' : '<strong>titulaire du compte</strong>' ?>.
                        </span>
                    </div>
                </div>
                <div>
                    <?php if ($estProprietaire): ?>
                        <a href="<?= url('pro/profil.php') ?>" class="btn btn-sm btn-outline-dark d-inline-flex align-items-center gap-1.5">
                            <i class="bi bi-pencil-square" aria-hidden="true"></i>
                            <span>Modifier mes informations</span>
                        </a>
                    <?php elseif ($estAdmin): ?>
                        <a href="<?= url('admin/demandes.php') ?>" class="btn btn-sm btn-outline-dark d-inline-flex align-items-center gap-1.5">
                            <i class="bi bi-shield-lock" aria-hidden="true"></i>
                            <span>Gestion admin</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ===================================================================== -->
    <!-- EN-TÊTE PRINCIPAL DU PROFIL (CARTE IDENTITÉ & ACTIONS)                 -->
    <!-- ===================================================================== -->
    <div class="card shadow-2xs border mb-4">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center gy-4">
                
                <!-- Avatar avec indicateur d'approbation -->
                <div class="col-12 col-md-auto text-center text-md-start">
                    <div class="position-relative d-inline-block">
                        <?php if ($photoUrl !== null): ?>
                            <img src="<?= e($photoUrl) ?>" 
                                 alt="Photo de <?= e($nomAffiche) ?>" 
                                 class="rounded-3 border object-fit-cover shadow-sm" 
                                 style="width: 120px; height: 120px;">
                        <?php else: ?>
                            <div class="img-placeholder rounded-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 120px; height: 120px; min-height: 120px; padding: 0;">
                                <i class="bi bi-person fs-1" aria-hidden="true"></i>
                            </div>
                        <?php endif; ?>

                        <?php if ($estApprouve): ?>
                            <span class="position-absolute bottom-0 end-0 translate-middle-y me-n1 badge rounded-pill bg-success border border-2 border-white shadow-sm" 
                                  title="Profil approuvé par FindPro" 
                                  aria-label="Profil approuvé par FindPro">
                                <i class="bi bi-check-lg" aria-hidden="true"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Informations principales -->
                <div class="col-12 col-md">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1.5">
                        <h1 class="h2 fw-bold text-dark mb-0">
                            <?= e($nomAffiche) ?>
                        </h1>
                        <?php if ($peutVoirApercu && $nom !== ''): ?>
                            <span class="text-secondary small fw-normal">
                                (<?= e($prenom . ' ' . $nom) ?>)
                            </span>
                        <?php endif; ?>

                        <!-- Statut de disponibilité -->
                        <?php if ($dispo === 'disponible'): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle d-inline-flex align-items-center gap-1 py-1.5 px-2.5">
                                <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                                <span>Disponible immédiatement</span>
                            </span>
                        <?php elseif ($dispo === 'limitee'): ?>
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle d-inline-flex align-items-center gap-1 py-1.5 px-2.5">
                                <i class="bi bi-dash-circle-fill" aria-hidden="true"></i>
                                <span>Disponibilité limitée</span>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle d-inline-flex align-items-center gap-1 py-1.5 px-2.5">
                                <i class="bi bi-x-circle-fill" aria-hidden="true"></i>
                                <span>Indisponible actuellement</span>
                            </span>
                        <?php endif; ?>
                    </div>

                    <p class="fs-5 text-primary fw-semibold mb-2">
                        <?= e($titre) ?>
                    </p>

                    <!-- Métadonnées : Catégorie, Zone, Note moyenne -->
                    <div class="d-flex flex-wrap align-items-center gap-3 text-secondary small">
                        <span class="d-inline-flex align-items-center gap-1.5">
                            <i class="bi bi-briefcase text-primary" aria-hidden="true"></i>
                            <a href="<?= url('search.php?categorie=' . urlencode($categorieSlug)) ?>" class="text-secondary text-decoration-none hover-primary">
                                <?= e($categorieLibelle) ?>
                            </a>
                        </span>
                        <span>•</span>
                        <span class="d-inline-flex align-items-center gap-1.5">
                            <i class="bi bi-geo-alt text-primary" aria-hidden="true"></i>
                            <span><?= e($zone) ?></span>
                        </span>
                        <span>•</span>
                        <span class="d-inline-flex align-items-center gap-1.5">
                            <i class="bi bi-star-fill text-warning" aria-hidden="true"></i>
                            <?php if ($noteMoyenne !== null): ?>
                                <strong class="text-dark"><?= number_format($noteMoyenne, 1, ',', ' ') ?> / 5</strong>
                                <span>(<?= $nbAvis ?> avis)</span>
                            <?php else: ?>
                                <span class="text-muted">Nouveau (aucun avis)</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <!-- Boutons d'action -->
                <div class="col-12 col-lg-auto text-lg-end">
                    <div class="d-flex flex-wrap flex-lg-column gap-2 justify-content-md-start justify-content-lg-end">
                        <a href="<?= url('request_new.php?profil=' . $profil['id_profil']) ?>" 
                           class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2 shadow-2xs py-2 px-3.5">
                            <i class="bi bi-send-fill" aria-hidden="true"></i>
                            <span>Envoyer une demande</span>
                        </a>

                        <!-- Emplacement préparé pour la fonctionnalité Signaler (activée au Jour 12) -->
                        <button type="button" 
                                class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center gap-1.5" 
                                data-bs-toggle="modal" 
                                data-bs-target="#modalSignalerProfil"
                                aria-label="Signaler ce profil">
                            <i class="bi bi-flag" aria-hidden="true"></i>
                            <span>Signaler</span>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- CORPS DE PAGE : CONTENU DÉTAILLÉ & COLONNE LATÉRALE                   -->
    <!-- ===================================================================== -->
    <div class="row g-4">
        
        <!-- COLONNE PRINCIPALE (Gauhe) -->
        <div class="col-12 col-lg-8">
            
            <!-- 1. Description professionnelle -->
            <section class="card shadow-2xs border mb-4" aria-labelledby="section-presentation-title">
                <div class="card-header bg-white border-bottom py-3">
                    <h2 id="section-presentation-title" class="h6 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-person-lines-fill text-primary" aria-hidden="true"></i>
                        <span>Présentation &amp; Parcours</span>
                    </h2>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($profil['description'])): ?>
                        <div class="text-secondary leading-relaxed" style="white-space: pre-line;">
                            <?= e($profil['description']) ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted fst-italic mb-0">
                            Ce professionnel n'a pas encore rédigé de description détaillée.
                        </p>
                    <?php endif; ?>
                </div>
            </section>

            <!-- 2. Compétences déclarées avec niveaux -->
            <section class="card shadow-2xs border mb-4" aria-labelledby="section-competences-title">
                <div class="card-header bg-white border-bottom py-3">
                    <h2 id="section-competences-title" class="h6 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-tools text-primary" aria-hidden="true"></i>
                        <span>Compétences déclarées</span>
                    </h2>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($competences)): ?>
                        <div class="row g-2.5">
                            <?php foreach ($competences as $comp): ?>
                                <?php 
                                    $niv = (string) ($comp['niveau_declare'] ?? 'debutant');
                                    $badgeClass = match($niv) {
                                        'expert'        => 'bg-primary text-white',
                                        'avance'        => 'bg-dark-subtle text-dark border',
                                        'intermediaire' => 'bg-light text-secondary border',
                                        default         => 'bg-light text-muted border',
                                    };
                                ?>
                                <div class="col-12 col-sm-6">
                                    <div class="p-2.5 rounded border bg-light d-flex align-items-center justify-content-between gap-2 shadow-2xs">
                                        <span class="fw-medium text-dark small">
                                            <?= e($comp['libelle']) ?>
                                        </span>
                                        <span class="badge <?= $badgeClass ?> small">
                                            <?= e($libellesNiveau[$niv] ?? $niv) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted fst-italic mb-0">
                            Aucune compétence spécifique déclarée pour l'instant.
                        </p>
                    <?php endif; ?>
                </div>
            </section>

            <!-- 3. Portfolio & Réalisations techniques -->
            <section class="card shadow-2xs border mb-4" aria-labelledby="section-portfolio-title">
                <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                    <h2 id="section-portfolio-title" class="h6 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-collection text-primary" aria-hidden="true"></i>
                        <span>Portfolio &amp; Réalisations</span>
                    </h2>
                    <span class="badge bg-light text-secondary border small">
                        <?= count($projets) ?> projet<?= count($projets) > 1 ? 's' : '' ?>
                    </span>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($projets)): ?>
                        <div class="row row-cols-1 row-cols-md-2 g-4">
                            <?php foreach ($projets as $projet): ?>
                                <?php
                                    $imgProjetUrl = null;
                                    if (!empty($projet['image']) && file_exists(__DIR__ . '/' . $projet['image'])) {
                                        $imgProjetUrl = url($projet['image']);
                                    }
                                    $lienExterne = !empty($projet['lien_externe']) ? trim((string) $projet['lien_externe']) : null;
                                ?>
                                <div class="col">
                                    <div class="card h-100 border shadow-2xs overflow-hidden">
                                        <?php if ($imgProjetUrl !== null): ?>
                                            <img src="<?= e($imgProjetUrl) ?>" 
                                                 alt="<?= e($projet['titre']) ?>" 
                                                 class="card-img-top object-fit-cover" 
                                                 style="height: 180px;">
                                        <?php else: ?>
                                            <div class="img-placeholder rounded-0 d-flex flex-column align-items-center justify-content-center" style="height: 180px;">
                                                <i class="bi bi-image" aria-hidden="true"></i>
                                                <span class="small">Visuel de réalisation</span>
                                            </div>
                                        <?php endif; ?>

                                        <div class="card-body p-3 d-flex flex-column">
                                            <h3 class="h6 fw-bold text-dark mb-1">
                                                <?= e($projet['titre']) ?>
                                            </h3>
                                            <?php if (!empty($projet['date_projet'])): ?>
                                                <span class="text-secondary small mb-2 d-block">
                                                    <i class="bi bi-calendar3 me-1" aria-hidden="true"></i><?= date('F Y', strtotime($projet['date_projet'])) ?>
                                                </span>
                                            <?php endif; ?>

                                            <p class="text-secondary small mb-3 flex-grow-1">
                                                <?= e($projet['description'] ?? '') ?>
                                            </p>

                                            <?php if ($lienExterne !== null): ?>
                                                <div class="pt-2 border-top mt-auto">
                                                    <a href="<?= e($lienExterne) ?>" 
                                                       target="_blank" 
                                                       rel="noopener noreferrer nofollow" 
                                                       class="btn btn-outline-primary btn-sm w-100 d-inline-flex align-items-center justify-content-center gap-1.5">
                                                        <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                                                        <span>Consulter le projet</span>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <div class="rounded-circle bg-light border d-inline-flex align-items-center justify-content-center text-secondary mb-2" style="width: 56px; height: 56px;">
                                <i class="bi bi-folder2-open fs-3" aria-hidden="true"></i>
                            </div>
                            <p class="text-muted small mb-0">
                                Aucune réalisation enregistrée dans le portfolio pour le moment.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- 4. Avis clients & Évaluations (Section préparée, formulaire au Jour 12) -->
            <section class="card shadow-2xs border mb-4" aria-labelledby="section-avis-title">
                <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                    <h2 id="section-avis-title" class="h6 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-chat-square-quote text-primary" aria-hidden="true"></i>
                        <span>Avis &amp; Évaluations clients</span>
                    </h2>
                    <?php if ($noteMoyenne !== null): ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle small">
                            <i class="bi bi-star-fill text-warning me-1" aria-hidden="true"></i><?= number_format($noteMoyenne, 1, ',', ' ') ?> / 5 (<?= $nbAvis ?> avis)
                        </span>
                    <?php endif; ?>
                </div>
                <div class="card-body p-4">
                    
                    <?php if (!empty($avisPublies)): ?>
                        <div class="vstack gap-3">
                            <?php foreach ($avisPublies as $av): ?>
                                <div class="border rounded p-3 bg-light shadow-2xs">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle bg-white border d-flex align-items-center justify-content-center text-primary fw-bold small" style="width: 32px; height: 32px;">
                                                <?= e(mb_substr((string) ($av['prenom'] ?? 'C'), 0, 1, 'UTF-8')) ?>
                                            </div>
                                            <div>
                                                <strong class="text-dark small d-block">
                                                    <?= e(trim(($av['prenom'] ?? 'Client') . ' ' . (isset($av['nom']) && $av['nom'] !== '' ? mb_substr((string) $av['nom'], 0, 1, 'UTF-8') . '.' : ''))) ?>
                                                </strong>
                                                <span class="text-muted" style="font-size: 0.75rem;">
                                                    Prestation vérifiée • <?= date('d/m/Y', strtotime((string) $av['date_avis'])) ?>
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Étoiles de notation -->
                                        <div class="text-warning small" aria-label="Note : <?= (int) $av['note'] ?> sur 5">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="bi bi-star<?= $i <= (int) $av['note'] ? '-fill' : '' ?>" aria-hidden="true"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>

                                    <?php if (!empty($av['commentaire'])): ?>
                                        <p class="text-secondary small mb-0 fst-italic">
                                            « <?= e($av['commentaire']) ?> »
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <div class="rounded-circle bg-light border d-inline-flex align-items-center justify-content-center text-secondary mb-2" style="width: 56px; height: 56px;">
                                <i class="bi bi-star fs-3" aria-hidden="true"></i>
                            </div>
                            <p class="text-muted small mb-1">
                                Aucun avis client publié pour le moment.
                            </p>
                            <span class="text-secondary" style="font-size: 0.8rem;">
                                Seuls les clients ayant finalisé une prestation avec ce professionnel peuvent déposer un avis certifié.
                            </span>
                        </div>
                    <?php endif; ?>

                    <div class="alert alert-light border small text-secondary mt-3 mb-0">
                        <i class="bi bi-info-circle me-1 text-primary" aria-hidden="true"></i>
                        <em>FindPro applique une politique stricte : aucun faux avis ni notation non vérifiée. Le formulaire de dépôt d'avis est accessible dans le tableau de bord client à la clôture de chaque prestation.</em>
                    </div>

                </div>
            </section>

        </div>

        <!-- COLONNE LATÉRALE (Droite) -->
        <div class="col-12 col-lg-4">
            
            <!-- Badge "Profil approuvé par FindPro" avec mention obligatoire -->
            <div class="card border-success shadow-2xs mb-4">
                <div class="card-body p-3.5">
                    <div class="d-flex align-items-start gap-3">
                        <div class="rounded-circle bg-success text-white d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                            <i class="bi bi-patch-check-fill fs-4" aria-hidden="true"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-1.5 mb-1">
                                <h3 class="h6 fw-bold text-dark mb-0">
                                    Profil approuvé par FindPro
                                </h3>
                            </div>
                            <p class="text-secondary small mb-2 leading-relaxed">
                                Ce professionnel a fait l'objet d'une revue documentaire et de modération manuelle par nos administrateurs.
                            </p>
                            <div class="p-2 bg-light border rounded small text-muted fst-italic">
                                <strong>Information de transparence :</strong> ce badge n'est pas une certification de compétence.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Encadré déontologique RG11 si Cybersécurité -->
            <?php if ($isCyber): ?>
                <div class="card border-warning-subtle bg-warning-subtle bg-opacity-25 shadow-2xs mb-4">
                    <div class="card-body p-3.5">
                        <div class="d-flex align-items-start gap-2.5">
                            <i class="bi bi-shield-lock-fill text-warning fs-4 flex-shrink-0" aria-hidden="true"></i>
                            <div>
                                <h3 class="h6 fw-bold text-dark mb-1">
                                    Engagement cybersécurité (RG11)
                                </h3>
                                <p class="small text-secondary mb-0 leading-relaxed">
                                    Conformément à la déontologie FindPro, toute prestation d'audit ou de test de sécurité nécessite une autorisation écrite explicite du propriétaire du système cible avant tout diagnostic.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Bloc "Passer commande / Contacter" -->
            <div class="card shadow-2xs border mb-4">
                <div class="card-body p-4 text-center">
                    <h3 class="h6 fw-bold text-dark mb-2">
                        Intéressé par ce profil ?
                    </h3>
                    <p class="text-secondary small mb-3">
                        Décrivez votre besoin en quelques clics pour entrer en contact direct avec <?= e($nomAffiche) ?>.
                    </p>
                    <a href="<?= url('request_new.php?profil=' . $profil['id_profil']) ?>" 
                       class="btn btn-primary w-100 d-inline-flex align-items-center justify-content-center gap-2 shadow-2xs py-2.5 mb-2">
                        <i class="bi bi-send-fill" aria-hidden="true"></i>
                        <span>Envoyer une demande</span>
                    </a>
                    <span class="text-muted" style="font-size: 0.78rem;">
                        <i class="bi bi-shield-check text-success me-1" aria-hidden="true"></i>Mise en relation sécurisée
                    </span>
                </div>
            </div>

            <!-- Bloc de signalement préparé -->
            <div class="card shadow-2xs border">
                <div class="card-body p-3 text-center">
                    <span class="text-secondary small d-block mb-2">
                        Un contenu inapproprié ou une anomalie ?
                    </span>
                    <button type="button" 
                            class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1.5" 
                            data-bs-toggle="modal" 
                            data-bs-target="#modalSignalerProfil">
                        <i class="bi bi-flag" aria-hidden="true"></i>
                        <span>Signaler ce profil</span>
                    </button>
                </div>
            </div>

        </div>

    </div>

</div>

<!-- ========================================================================= -->
<!-- MODAL DE SIGNALEMENT (EMPLACEMENT PRÉPARÉ POUR JOUR 12)                   -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalSignalerProfil" tabindex="-1" aria-labelledby="modalSignalerLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header border-bottom">
                <h5 class="modal-title h6 fw-bold d-flex align-items-center gap-2 text-dark" id="modalSignalerLabel">
                    <i class="bi bi-flag text-danger" aria-hidden="true"></i>
                    <span>Signaler ce profil professionnel</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info border-info-subtle small mb-3">
                    <i class="bi bi-info-circle me-1 text-primary" aria-hidden="true"></i>
                    <strong>Fonctionnalité en cours de déploiement (Jour 12) :</strong>
                    Le dispositif complet de modération et de traitement automatisé des signalements sera activé à l'étape dédiée.
                </div>
                <p class="text-secondary small mb-3">
                    Si vous constatez une usurpation d'identité, un manquement déontologique ou un contenu suspect concernant <strong><?= e($nomAffiche) ?></strong>, vous pouvez également contacter l'équipe d'administration à :
                </p>
                <div class="p-2.5 bg-light border rounded small font-monospace text-center text-dark mb-0">
                    contact@findpro.ci
                </div>
            </div>
            <div class="modal-footer border-top bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
