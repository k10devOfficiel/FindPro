<?php
/**
 * Moteur de recherche et filtres de professionnels — FindPro
 *
 * Paramètres GET pris en charge :
 * - q : mot-clé textuel (titre, description, nom, compétences)
 * - categorie : slug de la catégorie (ex: developpement, montage-video, cybersecurite)
 * - competence : identifiant entier de la compétence
 * - disponibilite : liste blanche ('disponible', 'limitee', 'indisponible')
 * - zone : commune ou zone géographique (ex: Abidjan, Cocody, Yopougon...)
 * - tri : liste blanche ('pertinence', 'recents', 'note')
 * - page : numéro de page (entier >= 1, pagination de 12 par page)
 *
 * RÈGLES DE GESTION & SÉCURITÉ :
 * - RG05 : seuls les profils approuvés avec un compte utilisateur actif sont retournés
 *   via profils_publics() et compter_profils_publics().
 * - Échappement anti-injection SQL sur LIKE (% et _) dans les requêtes préparées.
 * - Validation stricte côté serveur avec repli sécurisé sur les valeurs par défaut.
 * - Respect strict de docs/CHARTE_GRAPHIQUE_FINDPRO.md et Bootstrap 5.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/errors.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/carte_profil.php';

demarrer_session();
$pdo = db();

// ============================================================================
// 1. RÉCUPÉRATION DES DONNÉES DE RÉFÉRENCE (CATÉGORIES ET COMPÉTENCES)
// ============================================================================

// Récupération de toutes les catégories
$stmtCats = $pdo->query('
    SELECT id_categorie, libelle, slug, icone 
    FROM categorie 
    ORDER BY id_categorie ASC
');
$toutesCategories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);

$categoriesBySlug = [];
foreach ($toutesCategories as $cat) {
    $categoriesBySlug[$cat['slug']] = $cat;
}

// Récupération de toutes les compétences
$stmtComps = $pdo->query('
    SELECT id_competence, id_categorie, libelle 
    FROM competence 
    ORDER BY libelle ASC
');
$toutesCompetences = $stmtComps->fetchAll(PDO::FETCH_ASSOC);

$competencesById = [];
foreach ($toutesCompetences as $comp) {
    $competencesById[(int) $comp['id_competence']] = $comp;
}

// ============================================================================
// 2. VALIDATION STRICTE DES PARAMÈTRES EN ENTRÉE (MÉTHODE GET)
// ============================================================================

// Mot-clé 'q'
$qBrut = trim((string) ($_GET['q'] ?? ''));
$q = mb_substr($qBrut, 0, 150, 'UTF-8');

// Catégorie (slug)
$categorieSlug = trim((string) ($_GET['categorie'] ?? ''));
if ($categorieSlug !== '' && !isset($categoriesBySlug[$categorieSlug])) {
    // Si slug inconnu, on ne filtre pas sur une catégorie invalide
    $categorieSlug = '';
}

// Compétence (ID entier)
$idCompetence = null;
if (isset($_GET['competence']) && filter_var($_GET['competence'], FILTER_VALIDATE_INT) !== false) {
    $valComp = (int) $_GET['competence'];
    if ($valComp > 0 && isset($competencesById[$valComp])) {
        $idCompetence = $valComp;
    }
}

// Disponibilité (liste blanche stricte)
$disponibilitesValides = ['disponible', 'limitee', 'indisponible'];
$disponibilite = trim((string) ($_GET['disponibilite'] ?? ''));
if (!in_array($disponibilite, $disponibilitesValides, true)) {
    $disponibilite = '';
}

$libellesDispo = [
    'disponible'   => 'Disponible immédiatement',
    'limitee'      => 'Disponibilité limitée',
    'indisponible' => 'Indisponible',
];

// Zone géographique (texte assaini)
$zoneBrute = trim((string) ($_GET['zone'] ?? ''));
$zone = mb_substr($zoneBrute, 0, 100, 'UTF-8');

// Tri (liste blanche stricte : pertinence, recents, note)
$triParam = trim((string) ($_GET['tri'] ?? ''));
$trisValides = ['pertinence', 'recents', 'note'];
if (!in_array($triParam, $trisValides, true)) {
    // Si aucun mot-clé n'est saisi, la pertinence n'a pas de sens : on trie par défaut sur les plus récents
    $tri = ($q !== '') ? 'pertinence' : 'recents';
} else {
    $tri = $triParam;
}

// Pagination (12 profils par page)
$limiteParPage = 12;
$pageDemandee = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$pageCourante = ($pageDemandee !== false && $pageDemandee > 0) ? (int) $pageDemandee : 1;

// ============================================================================
// 3. PRÉPARATION DU TABLEAU DE FILTRES ET INTERROGATION (RG05)
// ============================================================================

$filtres = [
    'tri' => $tri,
];

if ($q !== '') {
    $filtres['q'] = $q;
}
if ($categorieSlug !== '') {
    $filtres['categorie'] = $categorieSlug;
}
if ($idCompetence !== null) {
    $filtres['competence'] = $idCompetence;
}
if ($disponibilite !== '') {
    $filtres['disponibilite'] = $disponibilite;
}
if ($zone !== '') {
    $filtres['zone'] = $zone;
}

// Nombre total de résultats approuvés (RG05)
$totalProfils = compter_profils_publics($pdo, $filtres);
$totalPages = max(1, (int) ceil($totalProfils / $limiteParPage));

// Ajustement si la page demandée dépasse le total
if ($pageCourante > $totalPages) {
    $pageCourante = $totalPages;
}
$offset = ($pageCourante - 1) * $limiteParPage;

// Récupération des profils approuvés pour la page courante (RG05)
$profils = profils_publics($pdo, $filtres, $limiteParPage, $offset);

// ============================================================================
// 4. CONSTRUCTION DES FILTRES ACTIFS ET DES URLS DYNAMIQUES
// ============================================================================

if (!function_exists('url_recherche')) {
    /**
     * Génère une URL vers search.php en conservant ou modifiant les paramètres actuels.
     *
     * @param array<string, mixed> $modifs Modifications à apporter aux paramètres GET
     * @return string
     */
    function url_recherche(array $modifs = []): string
    {
        $params = $_GET;
        foreach ($modifs as $cle => $valeur) {
            if ($valeur === null || $valeur === '') {
                unset($params[$cle]);
            } else {
                $params[$cle] = (string) $valeur;
            }
        }

        // Réinitialiser la page à 1 si on modifie un filtre sans spécifier la page
        if (!isset($modifs['page'])) {
            unset($params['page']);
        }

        $query = http_build_query($params);
        return url('search.php' . ($query !== '' ? '?' . $query : ''));
    }
}

// Liste des filtres actifs affichés sous forme de badges avec bouton de retrait
$filtresActifs = [];

if ($q !== '') {
    $filtresActifs[] = [
        'cle'        => 'q',
        'libelle'    => 'Mot-clé : « ' . $q . ' »',
        'url_retrait'=> url_recherche(['q' => null]),
    ];
}

if ($categorieSlug !== '') {
    $nomCat = $categoriesBySlug[$categorieSlug]['libelle'] ?? $categorieSlug;
    $filtresActifs[] = [
        'cle'        => 'categorie',
        'libelle'    => 'Catégorie : ' . $nomCat,
        'url_retrait'=> url_recherche(['categorie' => null]),
    ];
}

if ($idCompetence !== null) {
    $nomComp = $competencesById[$idCompetence]['libelle'] ?? ('Compétence #' . $idCompetence);
    $filtresActifs[] = [
        'cle'        => 'competence',
        'libelle'    => 'Compétence : ' . $nomComp,
        'url_retrait'=> url_recherche(['competence' => null]),
    ];
}

if ($disponibilite !== '') {
    $filtresActifs[] = [
        'cle'        => 'disponibilite',
        'libelle'    => 'Disponibilité : ' . ($libellesDispo[$disponibilite] ?? $disponibilite),
        'url_retrait'=> url_recherche(['disponibilite' => null]),
    ];
}

if ($zone !== '') {
    $filtresActifs[] = [
        'cle'        => 'zone',
        'libelle'    => 'Zone : ' . $zone,
        'url_retrait'=> url_recherche(['zone' => null]),
    ];
}

// Titre dynamique de la page
if ($q !== '') {
    $titre_page = 'Résultats pour « ' . $q . ' » — Recherche FindPro';
} elseif ($categorieSlug !== '') {
    $titre_page = ($categoriesBySlug[$categorieSlug]['libelle'] ?? 'Professionnels') . ' — Recherche FindPro';
} else {
    $titre_page = 'Recherche de professionnels qualifiés — FindPro';
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- ========================================================================= -->
<!-- SECTION HAUTE : BARRE DE RECHERCHE PRINCIPALE ET FIL D'ARIANE             -->
<!-- ========================================================================= -->
<section class="py-4 bg-light border-bottom" aria-labelledby="search-header-title">
    <div class="container">
        
        <!-- Fil d'Ariane -->
        <nav aria-label="Fil d'Ariane" class="mb-3">
            <ol class="breadcrumb small mb-0">
                <li class="breadcrumb-item">
                    <a href="<?= url('') ?>" class="text-secondary text-decoration-none hover-primary">Accueil</a>
                </li>
                <li class="breadcrumb-item active text-dark fw-medium" aria-current="page">
                    Recherche
                </li>
            </ol>
        </nav>

        <!-- Formulaire de recherche textuelle rapide -->
        <div class="row align-items-center gy-3">
            <div class="col-12 col-lg-8">
                <h1 id="search-header-title" class="h3 fw-bold text-dark mb-1">
                    Rechercher un professionnel qualifié
                </h1>
                <p class="text-secondary small mb-0">
                    Trouvez un développeur, un monteur vidéo ou un expert en cybersécurité certifié et approuvé par FindPro.
                </p>
            </div>
            <div class="col-12 col-lg-4 text-lg-end">
                <span class="badge bg-white text-dark border px-3 py-2 shadow-2xs fs-6 font-monospace">
                    <strong class="text-primary"><?= $totalProfils ?></strong> résultat<?= $totalProfils > 1 ? 's' : '' ?> trouvé<?= $totalProfils > 1 ? 's' : '' ?>
                </span>
            </div>
        </div>

        <form action="<?= url('search.php') ?>" method="GET" class="mt-3" role="search" aria-label="Moteur de recherche textuel">
            <!-- Préservation des filtres secondaires lors de la soumission du mot-clé principal -->
            <?php if ($categorieSlug !== ''): ?>
                <input type="hidden" name="categorie" value="<?= e($categorieSlug) ?>">
            <?php endif; ?>
            <?php if ($idCompetence !== null): ?>
                <input type="hidden" name="competence" value="<?= (int) $idCompetence ?>">
            <?php endif; ?>
            <?php if ($disponibilite !== ''): ?>
                <input type="hidden" name="disponibilite" value="<?= e($disponibilite) ?>">
            <?php endif; ?>
            <?php if ($zone !== ''): ?>
                <input type="hidden" name="zone" value="<?= e($zone) ?>">
            <?php endif; ?>
            <?php if ($triParam !== ''): ?>
                <input type="hidden" name="tri" value="<?= e($triParam) ?>">
            <?php endif; ?>

            <div class="input-group shadow-sm">
                <span class="input-group-text bg-white border-end-0 text-secondary" id="search-addon">
                    <i class="bi bi-search" aria-hidden="true"></i>
                </span>
                <input type="search" 
                       name="q" 
                       value="<?= e($q) ?>" 
                       class="form-control border-start-0 py-2.5 ps-0" 
                       placeholder="Ex : Développeur PHP, Monteur DaVinci, Audit sécurité..." 
                       aria-label="Mot-clé de recherche" 
                       aria-describedby="search-addon">
                <button type="submit" class="btn btn-primary px-4 d-inline-flex align-items-center gap-2">
                    <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                    <span>Rechercher</span>
                </button>
            </div>
        </form>

        <!-- Affichage des filtres actifs (chips) -->
        <?php if (!empty($filtresActifs)): ?>
            <div class="d-flex flex-wrap align-items-center gap-2 mt-3 pt-2 border-top">
                <span class="text-secondary small fw-medium me-1">
                    <i class="bi bi-funnel-fill text-primary me-1" aria-hidden="true"></i>Filtres appliqués :
                </span>
                <?php foreach ($filtresActifs as $fa): ?>
                    <span class="badge bg-white text-dark border shadow-2xs d-inline-flex align-items-center gap-1.5 py-1.5 px-2.5 fw-normal">
                        <span><?= e($fa['libelle']) ?></span>
                        <a href="<?= $fa['url_retrait'] ?>" 
                           class="text-secondary text-decoration-none hover-primary ms-1" 
                           title="Retirer ce filtre"
                           aria-label="Retirer le filtre <?= e($fa['libelle']) ?>">
                            <i class="bi bi-x-circle-fill text-muted" aria-hidden="true"></i>
                        </a>
                    </span>
                <?php endforeach; ?>
                <a href="<?= url('search.php') ?>" class="btn btn-link btn-sm text-decoration-none text-danger p-0 ms-2 small">
                    <i class="bi bi-trash3 me-1" aria-hidden="true"></i>Tout effacer
                </a>
            </div>
        <?php endif; ?>

    </div>
</section>

<!-- ========================================================================= -->
<!-- ESPACE DE TRAVAIL PRINCIPAL : FILTRES & RÉSULTATS                         -->
<!-- ========================================================================= -->
<section class="py-4 py-lg-5" aria-label="Espace de recherche">
    <div class="container">
        
        <!-- Bouton mobile pour déplier/replier les filtres -->
        <div class="d-lg-none mb-3">
            <button class="btn btn-outline-primary w-100 d-flex align-items-center justify-content-between shadow-2xs py-2.5" 
                    type="button" 
                    data-bs-toggle="collapse" 
                    data-bs-target="#filtresMobilePanneau" 
                    aria-expanded="false" 
                    aria-controls="filtresMobilePanneau">
                <span class="d-inline-flex align-items-center gap-2">
                    <i class="bi bi-sliders2-vertical text-primary" aria-hidden="true"></i>
                    <strong>Filtres &amp; Tri</strong>
                </span>
                <?php if (!empty($filtresActifs)): ?>
                    <span class="badge bg-primary text-white rounded-pill">
                        <?= count($filtresActifs) ?> actif<?= count($filtresActifs) > 1 ? 's' : '' ?>
                    </span>
                <?php else: ?>
                    <i class="bi bi-chevron-down text-secondary" aria-hidden="true"></i>
                <?php endif; ?>
            </button>
        </div>

        <div class="row g-4">
            
            <!-- ----------------------------------------------------------------- -->
            <!-- COLONNE GAUCHE : PANNEAU DES FILTRES (RESPONSIVE)                 -->
            <!-- ----------------------------------------------------------------- -->
            <aside class="col-12 col-lg-4 col-xl-3">
                <div class="collapse d-lg-block" id="filtresMobilePanneau">
                    <div class="card shadow-2xs border">
                        <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                            <h2 class="h6 fw-bold mb-0 d-flex align-items-center gap-2 text-dark">
                                <i class="bi bi-sliders text-primary" aria-hidden="true"></i>
                                <span>Filtres de recherche</span>
                            </h2>
                            <?php if (!empty($filtresActifs)): ?>
                                <a href="<?= url('search.php') ?>" class="text-secondary small hover-primary text-decoration-none">
                                    Réinitialiser
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="card-body p-3 p-xl-4">
                            <form action="<?= url('search.php') ?>" method="GET" id="filtres-form" role="search" aria-label="Filtres détaillés">
                                
                                <!-- Conservation du mot-clé courant -->
                                <?php if ($q !== ''): ?>
                                    <input type="hidden" name="q" value="<?= e($q) ?>">
                                <?php endif; ?>

                                <!-- 1. Catégorie de métier -->
                                <div class="mb-3.5">
                                    <label for="filtre-categorie" class="form-label small fw-semibold text-dark mb-1">
                                        Catégorie de métier
                                    </label>
                                    <select name="categorie" id="filtre-categorie" class="form-select form-select-sm">
                                        <option value="">Toutes les catégories</option>
                                        <?php foreach ($toutesCategories as $cat): ?>
                                            <option value="<?= e($cat['slug']) ?>" <?= $categorieSlug === $cat['slug'] ? 'selected' : '' ?>>
                                                <?= e($cat['libelle']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- 2. Compétence ciblée -->
                                <div class="mb-3.5">
                                    <label for="filtre-competence" class="form-label small fw-semibold text-dark mb-1">
                                        Compétence technique
                                    </label>
                                    <select name="competence" id="filtre-competence" class="form-select form-select-sm">
                                        <option value="">Toutes les compétences</option>
                                        <?php foreach ($toutesCompetences as $comp): ?>
                                            <option value="<?= (int) $comp['id_competence'] ?>" <?= $idCompetence === (int) $comp['id_competence'] ? 'selected' : '' ?>>
                                                <?= e($comp['libelle']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- 3. Disponibilité -->
                                <div class="mb-3.5">
                                    <label for="filtre-disponibilite" class="form-label small fw-semibold text-dark mb-1">
                                        Disponibilité
                                    </label>
                                    <select name="disponibilite" id="filtre-disponibilite" class="form-select form-select-sm">
                                        <option value="">Toutes disponibilités</option>
                                        <option value="disponible" <?= $disponibilite === 'disponible' ? 'selected' : '' ?>>
                                            Disponible immédiatement
                                        </option>
                                        <option value="limitee" <?= $disponibilite === 'limitee' ? 'selected' : '' ?>>
                                            Disponibilité limitée
                                        </option>
                                        <option value="indisponible" <?= $disponibilite === 'indisponible' ? 'selected' : '' ?>>
                                            Indisponible actuellement
                                        </option>
                                    </select>
                                </div>

                                <!-- 4. Zone géographique / Commune -->
                                <div class="mb-3.5">
                                    <label for="filtre-zone" class="form-label small fw-semibold text-dark mb-1">
                                        Zone géographique / Commune
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white text-secondary">
                                            <i class="bi bi-geo-alt" aria-hidden="true"></i>
                                        </span>
                                        <input type="text" 
                                               name="zone" 
                                               id="filtre-zone" 
                                               value="<?= e($zone) ?>" 
                                               class="form-control" 
                                               placeholder="Ex : Abidjan, Cocody...">
                                    </div>
                                </div>

                                <!-- 5. Critère de tri -->
                                <div class="mb-4">
                                    <label for="filtre-tri" class="form-label small fw-semibold text-dark mb-1">
                                        Trier les résultats par
                                    </label>
                                    <select name="tri" id="filtre-tri" class="form-select form-select-sm">
                                        <option value="pertinence" <?= $tri === 'pertinence' ? 'selected' : '' ?>>
                                            Pertinence (mots-clés)
                                        </option>
                                        <option value="recents" <?= $tri === 'recents' ? 'selected' : '' ?>>
                                            Mises à jour récentes
                                        </option>
                                        <option value="note" <?= $tri === 'note' ? 'selected' : '' ?>>
                                            Mieux notés (avis clients)
                                        </option>
                                    </select>
                                </div>

                                <!-- Boutons d'action -->
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center justify-content-center gap-1.5 shadow-2xs">
                                        <i class="bi bi-check2" aria-hidden="true"></i>
                                        <span>Appliquer les filtres</span>
                                    </button>
                                    <?php if (!empty($filtresActifs)): ?>
                                        <a href="<?= url('search.php') ?>" class="btn btn-outline-primary btn-sm">
                                            Réinitialiser tout
                                        </a>
                                    <?php endif; ?>
                                </div>

                            </form>
                        </div>
                        
                        <!-- Rappel déontologique et de conformité RG05 -->
                        <div class="card-footer bg-light border-top p-3 small text-secondary">
                            <div class="d-flex align-items-start gap-2">
                                <i class="bi bi-shield-check text-success fs-5 flex-shrink-0" aria-hidden="true"></i>
                                <div>
                                    <span class="fw-semibold text-dark d-block">Garantie FindPro (RG05)</span>
                                    <span>Tous les profils affichés ont été rigoureusement audités et approuvés par nos administrateurs.</span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </aside>

            <!-- ----------------------------------------------------------------- -->
            <!-- COLONNE DROITE : LISTE DES PROFILS OU MESSAGE D'ABSENCE           -->
            <!-- ----------------------------------------------------------------- -->
            <main class="col-12 col-lg-8 col-xl-9">
                
                <!-- Barre d'état des résultats et sélecteur de tri rapide sur grand écran -->
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-2 border-bottom">
                    <div>
                        <span class="fw-bold text-dark fs-5">
                            <?= $totalProfils ?> professionnel<?= $totalProfils > 1 ? 's' : '' ?> disponible<?= $totalProfils > 1 ? 's' : '' ?>
                        </span>
                        <?php if ($totalProfils > 0): ?>
                            <span class="text-secondary small d-block">
                                Affichage de <?= $offset + 1 ?> à <?= min($offset + $limiteParPage, $totalProfils) ?> sur <?= $totalProfils ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Tri rapide en tête de liste -->
                    <div class="d-flex align-items-center gap-2">
                        <label for="tri-rapide" class="small text-secondary text-nowrap mb-0 d-none d-sm-inline">
                            Trier par :
                        </label>
                        <select id="tri-rapide" 
                                class="form-select form-select-sm" 
                                style="width: auto;" 
                                onchange="window.location.href = this.value;">
                            <option value="<?= url_recherche(['tri' => 'pertinence']) ?>" <?= $tri === 'pertinence' ? 'selected' : '' ?>>
                                Pertinence
                            </option>
                            <option value="<?= url_recherche(['tri' => 'recents']) ?>" <?= $tri === 'recents' ? 'selected' : '' ?>>
                                Mises à jour récentes
                            </option>
                            <option value="<?= url_recherche(['tri' => 'note']) ?>" <?= $tri === 'note' ? 'selected' : '' ?>>
                                Mieux notés
                            </option>
                        </select>
                    </div>
                </div>

                <!-- GRILLE DES PROFILS OU MESSAGE QUAND AUCUN RÉSULTAT -->
                <?php if (!empty($profils)): ?>
                    
                    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3 g-xl-4 mb-5">
                        <?php foreach ($profils as $profil): ?>
                            <div class="col">
                                <?php afficher_carte_profil($profil); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- PAGINATION (12 PAR PAGE) -->
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Pagination des professionnels" class="d-flex justify-content-center mt-4">
                            <ul class="pagination pagination-sm shadow-2xs mb-0">
                                
                                <!-- Bouton Précédent -->
                                <li class="page-item <?= ($pageCourante <= 1) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= url_recherche(['page' => $pageCourante - 1]) ?>" aria-label="Page précédente">
                                        <i class="bi bi-chevron-left" aria-hidden="true"></i>
                                    </a>
                                </li>

                                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                    <?php if ($p === 1 || $p === $totalPages || abs($p - $pageCourante) <= 2): ?>
                                        <li class="page-item <?= ($p === $pageCourante) ? 'active' : '' ?>">
                                            <a class="page-link" 
                                               href="<?= url_recherche(['page' => $p]) ?>" 
                                               <?= ($p === $pageCourante) ? 'aria-current="page"' : '' ?>>
                                                <?= $p ?>
                                            </a>
                                        </li>
                                    <?php elseif ($p === 2 && $pageCourante > 4): ?>
                                        <li class="page-item disabled"><span class="page-link">…</span></li>
                                    <?php elseif ($p === $totalPages - 1 && $pageCourante < $totalPages - 3): ?>
                                        <li class="page-item disabled"><span class="page-link">…</span></li>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <!-- Bouton Suivant -->
                                <li class="page-item <?= ($pageCourante >= $totalPages) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= url_recherche(['page' => $pageCourante + 1]) ?>" aria-label="Page suivante">
                                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                                    </a>
                                </li>

                            </ul>
                        </nav>
                    <?php endif; ?>

                <?php else: ?>

                    <!-- MESSAGE UTILE ET GUIDAGE EN CAS D'ABSENCE DE RÉSULTAT -->
                    <div class="card shadow-2xs border text-center p-4 p-md-5 my-3">
                        <div class="mb-3">
                            <div class="rounded-circle bg-light border d-inline-flex align-items-center justify-content-center text-secondary" style="width: 72px; height: 72px;">
                                <i class="bi bi-search fs-2" aria-hidden="true"></i>
                            </div>
                        </div>
                        
                        <h2 class="h4 fw-bold text-dark mb-2">
                            Aucun professionnel ne correspond à vos critères
                        </h2>
                        
                        <p class="text-secondary mx-auto mb-4" style="max-width: 580px;">
                            Nous n'avons trouvé aucun profil professionnel approuvé correspondant exactement à votre sélection.
                        </p>

                        <div class="alert alert-warning text-start mx-auto p-3 mb-4" style="max-width: 620px;">
                            <span class="fw-bold d-block mb-1.5 text-warning-emphasis">
                                <i class="bi bi-lightbulb me-1.5" aria-hidden="true"></i>Conseils pour trouver un professionnel :
                            </span>
                            <ul class="mb-0 ps-3 small text-secondary">
                                <li>Vérifiez l'orthographe de vos mots-clés dans la barre de recherche.</li>
                                <li>Essayez des termes plus larges ou un métier général (ex. : <em>« PHP »</em>, <em>« Montage »</em>, <em>« Audit »</em>).</li>
                                <li>Assouplissez vos filtres : retirez la restriction de commune ou de disponibilité pour voir tous les professionnels.</li>
                                <li>Consultez directement une catégorie principale pour découvrir l'ensemble des experts disponibles.</li>
                            </ul>
                        </div>

                        <div class="d-flex flex-wrap justify-content-center gap-2">
                            <a href="<?= url('search.php') ?>" class="btn btn-primary d-inline-flex align-items-center gap-1.5 shadow-2xs">
                                <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                                <span>Réinitialiser tous les filtres</span>
                            </a>
                            <a href="<?= url('') ?>" class="btn btn-outline-primary d-inline-flex align-items-center gap-1.5 shadow-2xs">
                                <i class="bi bi-house" aria-hidden="true"></i>
                                <span>Retour à l'accueil</span>
                            </a>
                        </div>
                    </div>

                <?php endif; ?>

            </main>

        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
