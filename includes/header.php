<?php
/**
 * En-tête HTML5 et navigation principale de FindPro.
 * 
 * Variables attendues :
 * - $titre_page (string, optionnelle) : Titre spécifique de la page en cours.
 */

declare(strict_types=1);

require_once __DIR__ . '/securite.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';

demarrer_session();

$utilisateurCourant = utilisateur_courant();
$estConnecte = $utilisateurCourant !== null;
$roleUtilisateur = $estConnecte ? $utilisateurCourant['role'] : null;
$nomUtilisateur = $estConnecte && $utilisateurCourant['prenom'] !== '' ? $utilisateurCourant['prenom'] : 'Mon compte';

// Préparation des métadonnées SEO
$titreDefaut = 'FindPro — Trouvez un Développeur Web, Mobile & Expert du Numérique';
if (isset($titre_page) && $titre_page !== '') {
    $titreFinal = (str_contains($titre_page, 'FindPro')) ? $titre_page : $titre_page . ' — FindPro';
} else {
    $titreFinal = $titreDefaut;
}

$descParDefaut = "Trouvez et engagez un développeur web, mobile ou logiciel freelance vérifié en Côte d'Ivoire. Création de site internet, applications sur mesure, e-commerce, montage vidéo et cybersécurité sur FindPro. Contact direct et sans intermédiaire.";
$metaDesc = isset($meta_description) && $meta_description !== '' ? $meta_description : $descParDefaut;

$keywordsParDefaut = "développeur web, développeur mobile, développeur freelance, trouver un développeur, développeur PHP, React, WordPress, Flutter, Côte d'Ivoire, Abidjan, création site internet, application mobile, montage vidéo, cybersécurité, FindPro";
$metaKeys = isset($meta_keywords) && $meta_keywords !== '' ? $meta_keywords : $keywordsParDefaut;

$urlCourante = url(ltrim((string) ($_SERVER['REQUEST_URI'] ?? ''), '/'));
$urlCanonique = isset($canonical_url) && $canonical_url !== '' ? $canonical_url : $urlCourante;
$ogImg = isset($og_image) && $og_image !== '' ? $og_image : url('assets/img/favicon-512.png');
$ogType = isset($og_type) && $og_type !== '' ? $og_type : 'website';

// Schémas de données structurées Schema.org JSON-LD
$schemasSite = [
    '@context' => 'https://schema.org',
    '@graph'   => [
        [
            '@type'        => 'WebSite',
            '@id'          => url('') . '/#website',
            'url'          => url(''),
            'name'         => 'FindPro',
            'description'  => 'Plateforme de mise en relation directe avec des développeurs informatiques, monteurs vidéo et experts en cybersécurité vérifiés.',
            'inLanguage'   => 'fr-FR',
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => [
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => url('search.php') . '?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ],
        [
            '@type'        => 'Organization',
            '@id'          => url('') . '/#organization',
            'name'         => 'FindPro',
            'url'          => url(''),
            'logo'         => url('assets/img/logo.svg'),
            'description'  => 'Annuaire et plateforme de freelances du numérique en Côte d\'Ivoire.',
            'areaServed'   => [
                '@type' => 'Country',
                'name'  => 'Côte d\'Ivoire',
            ],
        ],
    ],
];

if (isset($schema_supplementaire) && is_array($schema_supplementaire)) {
    if (isset($schema_supplementaire['@context'])) {
        unset($schema_supplementaire['@context']);
    }
    $schemasSite['@graph'][] = $schema_supplementaire;
}
?>
<!DOCTYPE html>
<html lang="fr" prefix="og: https://ogp.me/ns#">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titreFinal) ?></title>

    <!-- Balises Meta SEO Essentielles -->
    <meta name="description" content="<?= e($metaDesc) ?>">
    <meta name="keywords" content="<?= e($metaKeys) ?>">
    <meta name="author" content="FindPro">
    <meta name="robots" content="<?= isset($meta_robots) ? e($meta_robots) : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1' ?>">
    <link rel="canonical" href="<?= e($urlCanonique) ?>">

    <!-- Géolocalisation pour le référencement local (Côte d'Ivoire & Afrique de l'Ouest) -->
    <meta name="geo.region" content="CI">
    <meta name="geo.placename" content="Abidjan">
    <meta name="geo.position" content="5.359952;-4.008256">
    <meta name="ICBM" content="5.359952, -4.008256">

    <!-- Open Graph (Facebook, WhatsApp, LinkedIn) -->
    <meta property="og:locale" content="fr_FR">
    <meta property="og:type" content="<?= e($ogType) ?>">
    <meta property="og:title" content="<?= e($titreFinal) ?>">
    <meta property="og:description" content="<?= e($metaDesc) ?>">
    <meta property="og:url" content="<?= e($urlCanonique) ?>">
    <meta property="og:site_name" content="FindPro">
    <meta property="og:image" content="<?= e($ogImg) ?>">
    <meta property="og:image:width" content="512">
    <meta property="og:image:height" content="512">
    <meta property="og:image:alt" content="FindPro — Plateforme de freelances du numérique">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($titreFinal) ?>">
    <meta name="twitter:description" content="<?= e($metaDesc) ?>">
    <meta name="twitter:image" content="<?= e($ogImg) ?>">

    <!-- Données structurées Schema.org JSON-LD pour Google -->
    <script type="application/ld+json">
    <?= json_encode($schemasSite, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>
    </script>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= url('assets/img/favicon.png') ?>">

    <!-- Polices Google : Oxanium (titres) & Inter (texte courant) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oxanium:wght@600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- Bootstrap Icons CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Feuille de style personnalisée FindPro avec anti-cache -->
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>?v=<?= file_exists(__DIR__ . '/../assets/css/style.css') ? filemtime(__DIR__ . '/../assets/css/style.css') : '2.0' ?>">
</head>
<body>

    <!-- Barre de navigation responsive -->
    <nav class="navbar navbar-expand-lg sticky-top shadow-sm" aria-label="Navigation principale">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="<?= url('') ?>">
                <img src="<?= url('assets/img/logo.svg') ?>" alt="FindPro" height="40" class="d-inline-block">
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarFindPro" aria-controls="navbarFindPro" aria-expanded="false" aria-label="Basculer le menu">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarFindPro">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= url('') ?>">Accueil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= (($_GET['slug'] ?? '') === 'developpement') ? 'active' : '' ?>" href="<?= url('category.php?slug=developpement') ?>">Développement informatique</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= (($_GET['slug'] ?? '') === 'montage-video') ? 'active' : '' ?>" href="<?= url('category.php?slug=montage-video') ?>">Montage vidéo</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= (($_GET['slug'] ?? '') === 'cybersecurite') ? 'active' : '' ?>" href="<?= url('category.php?slug=cybersecurite') ?>">Cybersécurité</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= url('search.php') ?>">
                            <i class="bi bi-search me-1" aria-hidden="true"></i>Recherche
                        </a>
                    </li>
                </ul>

                <div class="d-flex align-items-center gap-2 pt-2 pt-lg-0">
                    <?php if (!$estConnecte): ?>
                        <!-- Emplacements pour visiteur non connecté -->
                        <a href="<?= url('login.php') ?>" class="btn btn-outline-primary btn-sm" style="color: #111827 !important; border: 1.5px solid #CBD5E1 !important; background-color: #FFFFFF !important; font-weight: 600 !important;">Connexion</a>
                        <a href="<?= url('register.php') ?>" class="btn btn-brand btn-sm" style="background: linear-gradient(135deg, #E11D2E 0%, #F97316 100%) !important; color: #FFFFFF !important; border: none !important; font-weight: 600 !important;">Inscription</a>
                    <?php else: ?>
                        <!-- Emplacement pour utilisateur connecté selon le rôle -->
                        <div class="dropdown">
                            <button class="btn btn-outline-primary btn-sm dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle"></i>
                                <span><?= e($nomUtilisateur) ?></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <?php if ($roleUtilisateur === 'admin'): ?>
                                    <li><h6 class="dropdown-header">Administration</h6></li>
                                    <li><a class="dropdown-item" href="<?= url('admin/index.php') ?>"><i class="bi bi-speedometer2 me-2"></i>Tableau de bord</a></li>
                                <?php elseif ($roleUtilisateur === 'professionnel'): ?>
                                    <li><h6 class="dropdown-header">Espace Professionnel</h6></li>
                                    <li><a class="dropdown-item" href="<?= url('pro/index.php') ?>"><i class="bi bi-briefcase me-2"></i>Tableau de bord</a></li>
                                    <li><a class="dropdown-item" href="<?= url('pro/demandes.php') ?>"><i class="bi bi-inbox me-2"></i>Demandes reçues</a></li>
                                    <li><a class="dropdown-item" href="<?= url('pro/profil.php') ?>"><i class="bi bi-person-lines-fill me-2"></i>Mon profil</a></li>
                                    <li><a class="dropdown-item" href="<?= url('pro/profil.php#photo') ?>"><i class="bi bi-camera me-2"></i>Changer ma photo</a></li>
                                    <li><a class="dropdown-item" href="<?= url('pro/competences.php') ?>"><i class="bi bi-award me-2"></i>Mes compétences</a></li>
                                    <li><a class="dropdown-item" href="<?= url('pro/portfolio.php') ?>"><i class="bi bi-images me-2"></i>Portfolio</a></li>
                                <?php else: ?>
                                    <li><h6 class="dropdown-header">Espace Client</h6></li>
                                    <li><a class="dropdown-item" href="<?= url('client/index.php') ?>"><i class="bi bi-speedometer2 me-2"></i>Tableau de bord</a></li>
                                    <li><a class="dropdown-item" href="<?= url('client/demandes.php') ?>"><i class="bi bi-grid me-2"></i>Mes demandes</a></li>
                                    <li><a class="dropdown-item" href="<?= url('client/profil.php') ?>"><i class="bi bi-person-gear me-2"></i>Mon profil &amp; Paramètres</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form action="<?= url('logout.php') ?>" method="POST" class="m-0 p-0">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="dropdown-item text-danger d-flex align-items-center">
                                            <i class="bi bi-box-arrow-right me-2"></i>Déconnexion
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Zone d'affichage des messages flash -->
    <?php
    $messagesFlash = flash_get();
    if (!empty($messagesFlash)): ?>
        <div class="container mt-3" aria-live="polite">
            <?php foreach ($messagesFlash as $flash):
                $type = match ($flash['type'] ?? 'info') {
                    'erreur', 'danger', 'error' => 'danger',
                    'avertissement', 'warning'  => 'warning',
                    'succes', 'success'         => 'success',
                    default                     => 'info',
                };
            ?>
                <div class="alert alert-<?= e($type) ?> alert-dismissible fade show d-flex align-items-center" role="alert">
                    <div><?= e($flash['message'] ?? '') ?></div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Conteneur principal de la page -->
    <main>
