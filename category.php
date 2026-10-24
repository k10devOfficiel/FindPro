<?php
/**
 * Page de catégorie de métiers — FindPro
 * 
 * - Validation stricte du paramètre 'slug' (existence en base requise, sinon HTTP 404).
 * - Affiche le titre, la description et l'icône de la catégorie.
 * - Encadré déontologique obligatoire RG11 pour la catégorie 'cybersecurite'.
 * - Liste des profils approuvés de la catégorie via profils_publics() (RG05).
 * - Pagination de 12 par page et tri par défaut sur les mises à jour récentes (date_maj DESC).
 * - Respect strict de la charte graphique FindPro et des règles d'accessibilité.
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

// 1. Validation du paramètre slug
$slug = trim((string) ($_GET['slug'] ?? ''));

if ($slug === '') {
    page_introuvable('Aucun identifiant de catégorie n\'a été fourni dans votre requête.', 'Catégorie non spécifiée');
}

// 2. Recherche de la catégorie en base de données
$stmtCat = $pdo->prepare('
    SELECT id_categorie, libelle, slug, description, icone 
    FROM categorie 
    WHERE slug = :slug
    LIMIT 1
');
$stmtCat->execute(['slug' => $slug]);
$categorie = $stmtCat->fetch(PDO::FETCH_ASSOC);

// Si la catégorie n'existe pas en base : HTTP 404
if (!$categorie) {
    page_introuvable("La catégorie demandée « " . $slug . " » n'existe pas ou a été déplacée.", 'Catégorie introuvable');
}

// 3. Paramètres de pagination
$idCategorie = (int) $categorie['id_categorie'];
$limiteParPage = 12;
$pageCourante = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($pageCourante - 1) * $limiteParPage;

// 4. Récupération des données : total et profils approuvés (RG05)
$filtresRecherche = [
    'categorie' => $idCategorie,
];

// Nombre total de profils approuvés dans cette catégorie
$totalProfils = compter_profils_publics($pdo, $filtresRecherche);
$totalPages = max(1, (int) ceil($totalProfils / $limiteParPage));

// Ajustement si le numéro de page demandé excède le total
if ($pageCourante > $totalPages) {
    $pageCourante = $totalPages;
    $offset = ($pageCourante - 1) * $limiteParPage;
}

// Récupération des 12 profils approuvés triés par défaut sur date_maj DESC
$profils = profils_publics($pdo, $filtresRecherche, $limiteParPage, $offset);

$iconeCat = !empty($categorie['icone']) ? $categorie['icone'] : 'bi-briefcase';
$estCyber = ($categorie['slug'] === 'cybersecurite');

$titre_page = $categorie['libelle'] . ' — Professionnels FindPro';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ========================================================================= -->
<!-- EN-TÊTE DE LA CATÉGORIE : TITRE, DESCRIPTION ET FIL D'ARIANE             -->
<!-- ========================================================================= -->
<section class="py-4 py-lg-5 bg-light border-bottom" aria-labelledby="category-header-title">
    <div class="container">
        
        <!-- Fil d'Ariane accessible -->
        <nav aria-label="Fil d'Ariane" class="mb-3">
            <ol class="breadcrumb small mb-0">
                <li class="breadcrumb-item">
                    <a href="<?= url('') ?>" class="text-secondary text-decoration-none hover-primary">Accueil</a>
                </li>
                <li class="breadcrumb-item active text-dark fw-medium" aria-current="page">
                    <?= e($categorie['libelle']) ?>
                </li>
            </ol>
        </nav>

        <div class="row align-items-center gy-4">
            <div class="col-12 col-lg-8">
                <div class="d-flex align-items-start gap-3">
                    <div class="rounded-3 bg-white border p-3 d-inline-flex align-items-center justify-content-center text-primary shadow-sm flex-shrink-0" style="width: 64px; height: 64px;">
                        <i class="bi <?= e($iconeCat) ?> fs-2" aria-hidden="true"></i>
                    </div>
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <h1 id="category-header-title" class="h2 fw-bold text-dark mb-0">
                                <?= e($categorie['libelle']) ?>
                            </h1>
                            <span class="badge bg-white text-secondary border shadow-2xs small font-monospace">
                                <?= $totalProfils ?> professionnel<?= $totalProfils > 1 ? 's' : '' ?> approuvé<?= $totalProfils > 1 ? 's' : '' ?>
                            </span>
                        </div>
                        <p class="text-secondary mb-0 leading-relaxed" style="max-width: 720px;">
                            <?= e($categorie['description']) ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Raccourci vers recherche générale -->
            <div class="col-12 col-lg-4 text-lg-end">
                <a href="<?= url('search.php?categorie=' . urlencode((string) $categorie['slug'])) ?>" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1.5 shadow-sm">
                    <i class="bi bi-funnel me-1" aria-hidden="true"></i>
                    <span>Filtrer avec critères avancés</span>
                </a>
            </div>
        </div>

    </div>
</section>

<!-- ========================================================================= -->
<!-- CONTENU PRINCIPAL : AVERTISSEMENT CYBER & GRILLE DE PROFILS               -->
<!-- ========================================================================= -->
<section class="py-5 bg-white" aria-labelledby="profiles-list-title">
    <div class="container">

        <?php if ($estCyber): ?>
            <!-- Encadré spécifique Cybersécurité (RG11) -->
            <div class="alert alert-warning border-warning border-opacity-50 p-4 rounded-3 shadow-sm mb-5 d-flex gap-3 align-items-start" role="alert">
                <div class="flex-shrink-0 text-warning fs-3 mt-0.5">
                    <i class="bi bi-shield-exclamation" aria-hidden="true"></i>
                </div>
                <div>
                    <h2 class="h6 fw-bold text-dark mb-1 d-flex flex-wrap align-items-center gap-2">
                        <span>Cadre déontologique et légal strict (RG11)</span>
                        <span class="badge bg-warning bg-opacity-25 text-dark border border-warning border-opacity-50 small font-monospace">Obligatoire</span>
                    </h2>
                    <p class="mb-0 text-secondary small leading-relaxed">
                        Sur FindPro, seules les prestations de sécurité informatique <strong>expressément autorisées par le propriétaire légitime du système cible</strong> sont admises. Tout audit intrusif non mandaté, test d'intrusion non autorisé ou tentative d'accès frauduleux est formellement prohibé et fera l'objet de poursuites judiciaires immédiates.
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- En-tête de section des résultats -->
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mb-4 pb-2 border-bottom gap-2">
            <div>
                <h2 id="profiles-list-title" class="h5 fw-bold text-dark mb-0">
                    Spécialistes vérifiés
                </h2>
                <span class="text-secondary small">
                    Triés par défaut sur les mises à jour récentes
                </span>
            </div>

            <?php if ($totalProfils > 0): ?>
                <span class="text-secondary small">
                    Affichage de <strong><?= $offset + 1 ?></strong> à <strong><?= min($totalProfils, $offset + $limiteParPage) ?></strong> sur <strong><?= $totalProfils ?></strong> profil<?= $totalProfils > 1 ? 's' : '' ?>
                </span>
            <?php endif; ?>
        </div>

        <!-- ===================================================================== -->
        <!-- LISTE DES PROFILS APPROUVÉS (12 PAR PAGE)                            -->
        <!-- ===================================================================== -->
        <?php if (!empty($profils)): ?>
            <div class="row g-4 mb-5">
                <?php foreach ($profils as $p): ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <?php afficher_carte_profil($p); ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- ================================================================= -->
            <!-- PAGINATION BOOTSTRAP (12 PAR PAGE)                                -->
            <!-- ================================================================= -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Pagination des professionnels de <?= e($categorie['libelle']) ?>" class="mt-4 mb-3">
                    <ul class="pagination justify-content-center flex-wrap gap-1">
                        
                        <!-- Bouton Précédent -->
                        <?php if ($pageCourante > 1): ?>
                            <li class="page-item">
                                <a class="page-link shadow-2xs" href="<?= url('category.php?slug=' . urlencode($slug) . '&page=' . ($pageCourante - 1)) ?>" aria-label="Page précédente">
                                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                                    <span class="d-none d-sm-inline ms-1">Précédent</span>
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <span class="page-link shadow-2xs">
                                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                                    <span class="d-none d-sm-inline ms-1">Précédent</span>
                                </span>
                            </li>
                        <?php endif; ?>

                        <!-- Numéros de pages -->
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i === $pageCourante): ?>
                                <li class="page-item active" aria-current="page">
                                    <span class="page-link shadow-2xs fw-bold"><?= $i ?></span>
                                </li>
                            <?php else: ?>
                                <li class="page-item">
                                    <a class="page-link shadow-2xs" href="<?= url('category.php?slug=' . urlencode($slug) . '&page=' . $i) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <!-- Bouton Suivant -->
                        <?php if ($pageCourante < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link shadow-2xs" href="<?= url('category.php?slug=' . urlencode($slug) . '&page=' . ($pageCourante + 1)) ?>" aria-label="Page suivante">
                                    <span class="d-none d-sm-inline me-1">Suivant</span>
                                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <span class="page-link shadow-2xs">
                                    <span class="d-none d-sm-inline me-1">Suivant</span>
                                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                                </span>
                            </li>
                        <?php endif; ?>

                    </ul>
                </nav>
            <?php endif; ?>

        <?php else: ?>
            <!-- État vide : aucun profil approuvé dans cette catégorie -->
            <div class="card p-5 border rounded-3 text-center bg-light shadow-sm my-4">
                <div class="rounded-circle bg-white border p-3 mx-auto mb-3 d-inline-flex align-items-center justify-content-center text-primary" style="width: 64px; height: 64px;">
                    <i class="bi <?= e($iconeCat) ?> fs-2" aria-hidden="true"></i>
                </div>
                <h3 class="h5 fw-bold text-dark mb-2">Aucun professionnel approuvé pour l'instant</h3>
                <p class="text-secondary mx-auto mb-4" style="max-width: 520px;">
                    Vous exercez dans le domaine « <strong><?= e($categorie['libelle']) ?></strong> » ? Créez votre profil sur FindPro, faites vérifier vos compétences et recevez des demandes de clients.
                </p>
                <div>
                    <a href="<?= url('register.php') ?>" class="btn btn-brand px-4 py-2 shadow-sm">
                        Devenir professionnel référencé
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Mention légale obligatoire discrète en pied de section -->
        <div class="text-center mt-5 pt-3 border-top">
            <p class="text-secondary small mb-0" style="font-size: 0.80rem;">
                <i class="bi bi-info-circle me-1 text-muted" aria-hidden="true"></i>
                La mention « Profil approuvé par FindPro » atteste d'une vérification manuelle d'identité et de complétude administrative du dossier par notre équipe, et ne constitue pas une certification officielle de compétences.
            </p>
        </div>

    </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
