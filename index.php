<?php
/**
 * Page d'accueil publique — FindPro
 * 
 * Sections :
 * 1. En-tête Hero avec slogan court, phrase d'explication et barre de recherche (mot-clé vers search.php).
 * 2. Les 3 catégories sous forme de grandes cartes cliquables (données lues dans la base).
 * 3. "Comment ça marche" en 4 étapes (Trouver un profil, Envoyer une demande, Discuter, Évaluer).
 * 4. 6 profils approuvés récents (via profils_publics) ou message d'invitation avec mention légale discrète.
 * 5. Appel à l'action (CTA) pour les professionnels.
 * 
 * Respect strict de la charte graphique FindPro (Oxanium, Inter, #111827, dégradé #E11D2E -> #F97316).
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

// 1. Récupération des 3 catégories de métiers actives depuis la base de données
$stmtCat = $pdo->query('
    SELECT id_categorie, libelle, slug, description, icone 
    FROM categorie 
    ORDER BY id_categorie ASC 
    LIMIT 3
');
$categories = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

// 2. Récupération des 6 derniers profils publics approuvés (RG05 via profils_publics)
// Règle RG05 absolue : Seuls les profils dont le statut_validation est 'approuve' et le compte 'actif' sont affichés
$profilsRecents = profils_publics($pdo, [], 6, 0);
// Filtrage défensif strict : exclusion formelle de tout profil suspendu, refusé ou inactif
$profilsRecents = array_values(array_filter($profilsRecents, static function (array $p): bool {
    return ($p['statut_validation'] ?? '') === 'approuve';
}));

$titre_page = 'Trouvez un Développeur Web & Mobile Freelance en Côte d\'Ivoire';
$meta_description = 'Besoin d\'un développeur pour créer votre site internet, application mobile, e-commerce ou logiciel sur mesure ? Trouvez des développeurs freelances vérifiés en Côte d\'Ivoire sur FindPro. Contact direct et sans commission.';
$meta_keywords = 'trouver un développeur, développeur web freelance, développeur abidjan, développeur mobile côte d\'ivoire, création site internet, développeur php, react, wordpress, flutter, monteur vidéo, cybersécurité, findpro';

// Schéma de données structurées FAQPage pour Google Rich Snippets
$schema_supplementaire = [
    '@type'      => 'FAQPage',
    'mainEntity' => [
        [
            '@type'          => 'Question',
            'name'           => 'Comment trouver et engager un bon développeur sur FindPro ?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => 'Sur FindPro, utilisez la barre de recherche ou explorez la catégorie Développement informatique pour filtrer les développeurs selon leurs compétences (PHP, React, WordPress, Flutter, etc.) et leur zone géographique. Consultez leurs portfolios vérifiés et envoyez directement votre demande de projet sans intermédiaire.',
            ],
        ],
        [
            '@type'          => 'Question',
            'name'           => 'Combien coûte la création d\'un site web ou d\'une application mobile ?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => 'Le tarif dépend de la complexité de votre projet (site vitrine, boutique e-commerce ou application mobile complète). Sur FindPro, vous négociez et convenez librement du tarif directement avec le professionnel, sans aucune commission prélevée par la plateforme.',
            ],
        ],
        [
            '@type'          => 'Question',
            'name'           => 'Quels types de besoins en développement informatique sont couverts ?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => 'Nos développeurs couvrent tous les besoins courants : création de sites vitrines et corporate, boutiques e-commerce avec paiement mobile (Wave, Orange Money), développement d\'applications mobiles Android et iOS, création d\'API, logiciels de gestion et refonte technique.',
            ],
        ],
        [
            '@type'          => 'Question',
            'name'           => 'Pourquoi choisir un développeur freelance avec profil approuvé sur FindPro ?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => 'Chaque profil arborant le badge « Profil approuvé » a fait l\'objet d\'une vérification manuelle d\'identité et de complétude administrative par l\'équipe FindPro. De plus, les avis clients sont authentifiés et ne peuvent être déposés qu\'après la clôture effective d\'une mission.',
            ],
        ],
    ],
];

require_once __DIR__ . '/includes/header.php';
?>

<!-- ========================================================================= -->
<!-- 1. SECTION HÉRO : SLOGAN, PHRASE D'EXPLICATION ET RECHERCHE RAPIDE       -->
<!-- ========================================================================= -->
<section class="hero-section py-5 py-lg-6 bg-light border-bottom position-relative overflow-hidden" aria-labelledby="hero-title">
    <div class="container position-relative py-3 py-md-4">
        <div class="row justify-content-center text-center">
            <div class="col-12 col-lg-10 col-xl-9">


                <!-- Slogan court -->
                <h1 id="hero-title" class="display-5 fw-bold mb-3 text-dark">
                    Trouvez le bon professionnel pour votre projet
                </h1>

                <!-- Phrase d'explication -->
                <p class="lead text-secondary mb-4 mb-lg-5 mx-auto" style="max-width: 760px;">
                    Découvrez des spécialistes en développement web, montage vidéo et cybersécurité. Consultez leurs réalisations vérifiées et échangez en direct, en toute sérénité.
                </p>

                <!-- Barre de recherche (mot-clé) menant à search.php -->
                <div class="search-box-card bg-white p-3 p-md-3.5 rounded-3 shadow-sm border mx-auto mb-4" style="max-width: 780px;">
                    <form action="<?= url('search.php') ?>" method="GET" class="row g-2 align-items-center" role="search" aria-label="Recherche de professionnels">
                        <div class="col-12 col-md-8 col-lg-9">
                            <div class="input-group input-group-lg border rounded-3 bg-light px-2">
                                <span class="input-group-text bg-transparent border-0 text-muted ps-1 pe-2">
                                    <i class="bi bi-search fs-5" aria-hidden="true"></i>
                                </span>
                                <input 
                                    type="search" 
                                    name="q" 
                                    id="search-input"
                                    class="form-control bg-transparent border-0 shadow-none ps-0" 
                                    placeholder="Ex : Développeur React, Montage YouTube, Audit..." 
                                    aria-label="Rechercher par mot-clé, compétence ou métier"
                                    required
                                >
                            </div>
                        </div>
                        <div class="col-12 col-md-4 col-lg-3">
                            <button type="submit" class="btn btn-brand btn-lg w-100 d-inline-flex align-items-center justify-content-center gap-2 shadow-sm" style="background: linear-gradient(135deg, #E11D2E 0%, #F97316 100%) !important; color: #FFFFFF !important; border: none !important; font-weight: 600 !important;">
                                <span>Rechercher</span>
                                <i class="bi bi-arrow-right" aria-hidden="true"></i>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Raccourcis de recherche populaires -->
                <div class="d-flex flex-wrap align-items-center justify-content-center gap-2 pt-2">
                    <span class="text-secondary small fw-semibold text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.05em;">Recherches courantes :</span>
                    <a href="<?= url('search.php?q=PHP') ?>" class="badge bg-white text-secondary border text-decoration-none py-1.5 px-2.5 hover-primary shadow-2xs">PHP & Laravel</a>
                    <a href="<?= url('search.php?q=React') ?>" class="badge bg-white text-secondary border text-decoration-none py-1.5 px-2.5 hover-primary shadow-2xs">React & Next.js</a>
                    <a href="<?= url('search.php?q=Montage') ?>" class="badge bg-white text-secondary border text-decoration-none py-1.5 px-2.5 hover-primary shadow-2xs">Montage vidéo</a>
                    <a href="<?= url('search.php?q=Audit') ?>" class="badge bg-white text-secondary border text-decoration-none py-1.5 px-2.5 hover-primary shadow-2xs">Audit & Sécurité</a>
                </div>

            </div>
        </div>
    </div>
</section>

<!-- ========================================================================= -->
<!-- 2. SECTION CATÉGORIES : 3 GRANDES CARTES CLIQUABLES (DONNÉES BDD)       -->
<!-- ========================================================================= -->
<section class="py-5 py-lg-6 bg-white" aria-labelledby="categories-title">
    <div class="container py-2">
        <div class="text-center mb-5">
            <h2 id="categories-title" class="h3 fw-bold text-dark mb-2">
                Explorez nos corps de métier
            </h2>
            <p class="text-secondary mb-0 mx-auto" style="max-width: 600px;">
                Des experts rigoureusement audités pour répondre à chaque étape clé de votre transformation numérique.
            </p>
        </div>

        <div class="row g-4">
            <?php foreach ($categories as $cat): 
                $icone = !empty($cat['icone']) ? $cat['icone'] : 'bi-briefcase';
                $urlCat = url('category.php?slug=' . urlencode((string) $cat['slug']));
            ?>
                <div class="col-12 col-md-4">
                    <a href="<?= $urlCat ?>" class="card h-100 p-4 border rounded-3 text-decoration-none transition-all shadow-sm hover-lift bg-white d-flex flex-column justify-content-between">
                        <div>
                            <!-- En-tête de carte : Icône stylisée dans la palette FindPro -->
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="rounded-3 bg-light border p-3 d-inline-flex align-items-center justify-content-center text-primary" style="width: 58px; height: 58px;">
                                    <i class="bi <?= e($icone) ?> fs-3" aria-hidden="true"></i>
                                </div>
                                <span class="badge bg-light text-secondary border small">
                                    Catégorie vérifiée
                                </span>
                            </div>

                            <!-- Titre de la catégorie -->
                            <h3 class="h5 fw-bold text-dark mb-2">
                                <?= e($cat['libelle']) ?>
                            </h3>

                            <!-- Description de la catégorie -->
                            <p class="text-secondary small mb-4 leading-relaxed">
                                <?= e($cat['description']) ?>
                            </p>
                        </div>

                        <!-- Lien d'action -->
                        <div class="pt-3 border-top d-flex align-items-center justify-content-between text-primary fw-medium small">
                            <span>Voir les spécialistes</span>
                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ========================================================================= -->
<!-- 3. SECTION BESOINS CONCRETS : TROUVER SELON VOTRE PROJET NUMÉRIQUE       -->
<!-- ========================================================================= -->
<section class="py-5 py-lg-6 bg-light border-top" aria-labelledby="needs-title">
    <div class="container py-2">
        <div class="text-center mb-5">
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-1.5 small fw-semibold text-uppercase mb-2">
                Projets & Besoins
            </span>
            <h2 id="needs-title" class="h3 fw-bold text-dark mb-2">
                Quel est votre projet ? Trouvez le profil idéal
            </h2>
            <p class="text-secondary mb-0 mx-auto" style="max-width: 650px;">
                Des compétences ciblées pour concrétiser vos ambitions numériques, du premier croquis au lancement en production.
            </p>
        </div>

        <div class="row g-3 g-lg-4">
            <!-- 1. Création de site web vitrine & corporate -->
            <div class="col-12 col-md-6 col-lg-4">
                <a href="<?= url('search.php?q=Web') ?>" class="card h-100 p-4 border rounded-3 bg-white text-decoration-none shadow-sm hover-lift d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="rounded-3 bg-primary bg-opacity-10 text-primary p-2.5 d-inline-flex align-items-center justify-content-center">
                                <i class="bi bi-window-fullscreen fs-4"></i>
                            </div>
                            <h3 class="h6 fw-bold text-dark mb-0">Création de Site Internet</h3>
                        </div>
                        <p class="text-secondary small mb-3">
                            Site vitrine pour entreprise, refonte moderne, blog professionnel ou portfolio. Technologies WordPress, HTML5/CSS3, PHP.
                        </p>
                    </div>
                    <div class="d-flex align-items-center justify-content-between text-primary small fw-semibold pt-2 border-top">
                        <span>Trouver un développeur web</span>
                        <i class="bi bi-arrow-right"></i>
                    </div>
                </a>
            </div>

            <!-- 2. Application mobile iOS & Android -->
            <div class="col-12 col-md-6 col-lg-4">
                <a href="<?= url('search.php?q=Mobile') ?>" class="card h-100 p-4 border rounded-3 bg-white text-decoration-none shadow-sm hover-lift d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="rounded-3 bg-primary bg-opacity-10 text-primary p-2.5 d-inline-flex align-items-center justify-content-center">
                                <i class="bi bi-phone fs-4"></i>
                            </div>
                            <h3 class="h6 fw-bold text-dark mb-0">Application Mobile (Android & iOS)</h3>
                        </div>
                        <p class="text-secondary small mb-3">
                            Conception d'applications smartphones performantes et intuitives avec Flutter, React Native ou Kotlin.
                        </p>
                    </div>
                    <div class="d-flex align-items-center justify-content-between text-primary small fw-semibold pt-2 border-top">
                        <span>Trouver un développeur mobile</span>
                        <i class="bi bi-arrow-right"></i>
                    </div>
                </a>
            </div>

            <!-- 3. E-commerce & Vente en ligne -->
            <div class="col-12 col-md-6 col-lg-4">
                <a href="<?= url('search.php?q=E-commerce') ?>" class="card h-100 p-4 border rounded-3 bg-white text-decoration-none shadow-sm hover-lift d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="rounded-3 bg-primary bg-opacity-10 text-primary p-2.5 d-inline-flex align-items-center justify-content-center">
                                <i class="bi bi-cart-check fs-4"></i>
                            </div>
                            <h3 class="h6 fw-bold text-dark mb-0">Boutique E-commerce</h3>
                        </div>
                        <p class="text-secondary small mb-3">
                            Plateforme de vente en ligne avec intégration de passerelles de paiement locales (Wave, Mobile Money, carte bancaire).
                        </p>
                    </div>
                    <div class="d-flex align-items-center justify-content-between text-primary small fw-semibold pt-2 border-top">
                        <span>Lancer ma boutique en ligne</span>
                        <i class="bi bi-arrow-right"></i>
                    </div>
                </a>
            </div>

            <!-- 4. Logiciel métier & API Backend -->
            <div class="col-12 col-md-6 col-lg-4">
                <a href="<?= url('search.php?q=PHP') ?>" class="card h-100 p-4 border rounded-3 bg-white text-decoration-none shadow-sm hover-lift d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="rounded-3 bg-primary bg-opacity-10 text-primary p-2.5 d-inline-flex align-items-center justify-content-center">
                                <i class="bi bi-hdd-network fs-4"></i>
                            </div>
                            <h3 class="h6 fw-bold text-dark mb-0">Logiciel Métier & API sur mesure</h3>
                        </div>
                        <p class="text-secondary small mb-3">
                            Système de gestion interne, automatisation de processus, tableau de bord administratif et APIs REST sécurisées.
                        </p>
                    </div>
                    <div class="d-flex align-items-center justify-content-between text-primary small fw-semibold pt-2 border-top">
                        <span>Créer un logiciel sur mesure</span>
                        <i class="bi bi-arrow-right"></i>
                    </div>
                </a>
            </div>

            <!-- 5. Vidéo YouTube, TikTok & Réseaux Sociaux -->
            <div class="col-12 col-md-6 col-lg-4">
                <a href="<?= url('category.php?slug=montage-video') ?>" class="card h-100 p-4 border rounded-3 bg-white text-decoration-none shadow-sm hover-lift d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="rounded-3 bg-primary bg-opacity-10 text-primary p-2.5 d-inline-flex align-items-center justify-content-center">
                                <i class="bi bi-camera-reels fs-4"></i>
                            </div>
                            <h3 class="h6 fw-bold text-dark mb-0">Montage Vidéo & Médias Sociaux</h3>
                        </div>
                        <p class="text-secondary small mb-3">
                            Montage dynamique, Reels, TikTok, vidéos YouTube, habillage sonore et animations graphiques professionnelles.
                        </p>
                    </div>
                    <div class="d-flex align-items-center justify-content-between text-primary small fw-semibold pt-2 border-top">
                        <span>Voir les monteurs vidéo</span>
                        <i class="bi bi-arrow-right"></i>
                    </div>
                </a>
            </div>

            <!-- 6. Audit & Sécurisation de site -->
            <div class="col-12 col-md-6 col-lg-4">
                <a href="<?= url('category.php?slug=cybersecurite') ?>" class="card h-100 p-4 border rounded-3 bg-white text-decoration-none shadow-sm hover-lift d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="rounded-3 bg-primary bg-opacity-10 text-primary p-2.5 d-inline-flex align-items-center justify-content-center">
                                <i class="bi bi-shield-check fs-4"></i>
                            </div>
                            <h3 class="h6 fw-bold text-dark mb-0">Audit & Sécurité Informatique</h3>
                        </div>
                        <p class="text-secondary small mb-3">
                            Détection préventive de vulnérabilités, protection contre les piratages et conformité des données sensibles.
                        </p>
                    </div>
                    <div class="d-flex align-items-center justify-content-between text-primary small fw-semibold pt-2 border-top">
                        <span>Sécuriser ma plateforme</span>
                        <i class="bi bi-arrow-right"></i>
                    </div>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ========================================================================= -->
<!-- 3. SECTION COMMENT ÇA MARCHE : 4 ÉTAPES CLAIRES                           -->
<!-- ========================================================================= -->
<section class="py-5 py-lg-6 bg-light border-top border-bottom" aria-labelledby="how-it-works-title">
    <div class="container py-2">
        <div class="text-center mb-5">
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-1.5 small fw-semibold text-uppercase mb-2">
                Simplicité & Transparence
            </span>
            <h2 id="how-it-works-title" class="h3 fw-bold text-dark mb-2">
                Comment ça marche ?
            </h2>
            <p class="text-secondary mb-0 mx-auto" style="max-width: 620px;">
                Un parcours fluide en 4 étapes pour collaborer en direct avec des professionnels de confiance.
            </p>
        </div>

        <div class="row g-4">
            <!-- Étape 1 : Trouver un profil -->
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card h-100 p-4 border rounded-3 bg-white shadow-sm text-center">
                    <div class="mx-auto rounded-circle bg-light border d-flex align-items-center justify-content-center mb-3 text-primary position-relative" style="width: 64px; height: 64px;">
                        <i class="bi bi-search fs-3" aria-hidden="true"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-dark text-white font-monospace" style="font-size: 0.72rem;">
                            1
                        </span>
                    </div>
                    <h3 class="h6 fw-bold text-dark mb-2">1. Trouver un profil</h3>
                    <p class="text-secondary small mb-0">
                        Explorez les profils vérifiés selon votre projet, vos technologies cibles et votre zone géographique.
                    </p>
                </div>
            </div>

            <!-- Étape 2 : Envoyer une demande -->
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card h-100 p-4 border rounded-3 bg-white shadow-sm text-center">
                    <div class="mx-auto rounded-circle bg-light border d-flex align-items-center justify-content-center mb-3 text-primary position-relative" style="width: 64px; height: 64px;">
                        <i class="bi bi-send-check fs-3" aria-hidden="true"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-dark text-white font-monospace" style="font-size: 0.72rem;">
                            2
                        </span>
                    </div>
                    <h3 class="h6 fw-bold text-dark mb-2">2. Envoyer une demande</h3>
                    <p class="text-secondary small mb-0">
                        Décrivez clairement votre besoin et vos contraintes directement au professionnel, sans intermédiaire.
                    </p>
                </div>
            </div>

            <!-- Étape 3 : Discuter -->
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card h-100 p-4 border rounded-3 bg-white shadow-sm text-center">
                    <div class="mx-auto rounded-circle bg-light border d-flex align-items-center justify-content-center mb-3 text-primary position-relative" style="width: 64px; height: 64px;">
                        <i class="bi bi-chat-dots fs-3" aria-hidden="true"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-dark text-white font-monospace" style="font-size: 0.72rem;">
                            3
                        </span>
                    </div>
                    <h3 class="h6 fw-bold text-dark mb-2">3. Discuter</h3>
                    <p class="text-secondary small mb-0">
                        Échangez en temps réel via notre messagerie sécurisée pour cadrer les détails techniques et les délais.
                    </p>
                </div>
            </div>

            <!-- Étape 4 : Évaluer -->
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card h-100 p-4 border rounded-3 bg-white shadow-sm text-center">
                    <div class="mx-auto rounded-circle bg-light border d-flex align-items-center justify-content-center mb-3 text-primary position-relative" style="width: 64px; height: 64px;">
                        <i class="bi bi-star fs-3" aria-hidden="true"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-dark text-white font-monospace" style="font-size: 0.72rem;">
                            4
                        </span>
                    </div>
                    <h3 class="h6 fw-bold text-dark mb-2">4. Évaluer</h3>
                    <p class="text-secondary small mb-0">
                        Une fois la mission achevée et validée, notez la prestation et partagez votre avis d'expérience.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ========================================================================= -->
<!-- 4. SECTION PROFILS APPROUVÉS RÉCENTS (6 PROFILS VIA PROFILS_PUBLICS)     -->
<!-- ========================================================================= -->
<section class="py-5 py-lg-6 bg-white" aria-labelledby="approved-profiles-title">
    <div class="container py-2">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4 gap-3">
            <div>
                <h2 id="approved-profiles-title" class="h3 fw-bold text-dark mb-1">
                    Professionnels récemment approuvés
                </h2>
                <p class="text-secondary mb-0">
                    Découvrez les derniers profils validés par notre équipe administrative.
                </p>
            </div>
            <div>
                <a href="<?= url('search.php') ?>" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1.5 shadow-sm" style="color: #111827 !important; border: 1.5px solid #CBD5E1 !important; background-color: #FFFFFF !important; font-weight: 600 !important;">
                    <span>Tous les professionnels</span>
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
        </div>

        <?php if (!empty($profilsRecents)): ?>
            <!-- Grille des profils approuvés (composant afficher_carte_profil) -->
            <div class="row g-4">
                <?php foreach ($profilsRecents as $p): ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <?php afficher_carte_profil($p); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Message invitant les professionnels à s'inscrire si aucun profil approuvé -->
            <div class="card p-5 border rounded-3 text-center bg-light shadow-sm my-4">
                <div class="rounded-circle bg-white border p-3 mx-auto mb-3 d-inline-flex align-items-center justify-content-center text-primary" style="width: 64px; height: 64px;">
                    <i class="bi bi-people fs-2" aria-hidden="true"></i>
                </div>
                <h3 class="h5 fw-bold text-dark mb-2">Aucun profil approuvé pour l'instant</h3>
                <p class="text-secondary mx-auto mb-4" style="max-width: 540px;">
                    Vous êtes développeur, monteur vidéo ou spécialiste en cybersécurité ? Soyez parmi les premiers professionnels à soumettre votre dossier et à valoriser votre expertise auprès de clients potentiels.
                </p>
                <div>
                    <a href="<?= url('register.php') ?>" class="btn btn-brand px-4 py-2 shadow-sm" style="background: linear-gradient(135deg, #E11D2E 0%, #F97316 100%) !important; color: #FFFFFF !important; border: none !important; font-weight: 600 !important;">
                        Rejoindre FindPro en tant que professionnel
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Mention légale obligatoire discrète en pied de section -->
        <div class="text-center mt-4 pt-2">
            <p class="text-secondary small mb-0" style="font-size: 0.80rem;">
                <i class="bi bi-info-circle me-1 text-muted" aria-hidden="true"></i>
                La mention « Profil approuvé par FindPro » atteste d'une vérification manuelle d'identité et de complétude administrative du dossier par notre équipe, et ne constitue pas une certification officielle de compétences.
            </p>
        </div>
    </div>
</section>

<!-- ========================================================================= -->
<!-- SECTION FAQ : QUESTIONS FRÉQUENTES SUR LES DÉVELOPPEURS ET PROJETS       -->
<!-- ========================================================================= -->
<section class="py-5 py-lg-6 bg-white border-top" aria-labelledby="faq-title">
    <div class="container py-2">
        <div class="text-center mb-5">
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-1.5 small fw-semibold text-uppercase mb-2">
                Foire Aux Questions
            </span>
            <h2 id="faq-title" class="h3 fw-bold text-dark mb-2">
                Tout savoir pour réussir votre projet sur FindPro
            </h2>
            <p class="text-secondary mb-0 mx-auto" style="max-width: 650px;">
                Des réponses claires à vos questions courantes sur le recrutement de développeurs et la collaboration directe en Côte d'Ivoire.
            </p>
        </div>

        <div class="row justify-content-center">
            <div class="col-12 col-lg-10 col-xl-9">
                <div class="accordion accordion-flush border rounded-3 shadow-sm overflow-hidden" id="accordionFaqHome">
                    
                    <!-- Question 1 -->
                    <div class="accordion-item">
                        <h3 class="accordion-header" id="headingOne">
                            <button class="accordion-button fw-bold text-dark collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                                <i class="bi bi-question-circle text-primary me-2"></i>
                                Comment trouver et engager un bon développeur sur FindPro ?
                            </button>
                        </h3>
                        <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#accordionFaqHome">
                            <div class="accordion-body text-secondary leading-relaxed">
                                Sur FindPro, utilisez la barre de recherche ou explorez la catégorie <a href="<?= url('category.php?slug=developpement') ?>" class="text-primary fw-medium text-decoration-none">Développement informatique</a> pour filtrer les profils selon leurs compétences précises (PHP, React, WordPress, Flutter, Python...) et leur zone géographique. Vous pouvez consulter directement leurs réalisations concrètes (portfolio vérifié), puis cliquer sur <strong>« Contacter »</strong> pour lui exposer votre besoin en direct.
                            </div>
                        </div>
                    </div>

                    <!-- Question 2 -->
                    <div class="accordion-item">
                        <h3 class="accordion-header" id="headingTwo">
                            <button class="accordion-button fw-bold text-dark collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                <i class="bi bi-cash-coin text-primary me-2"></i>
                                Combien coûte la création d'un site web ou d'une application mobile ?
                            </button>
                        </h3>
                        <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#accordionFaqHome">
                            <div class="accordion-body text-secondary leading-relaxed">
                                Le tarif varie selon la complexité : un site vitrine simple ne demande pas le même temps de travail qu'une boutique e-commerce ou une application mobile avec base de données complexe. Sur FindPro, vous échangez directement avec les spécialistes, sans intermédiaire : vous définissez ensemble un devis clair et transparent, sans aucune commission de plateforme.
                            </div>
                        </div>
                    </div>

                    <!-- Question 3 -->
                    <div class="accordion-item">
                        <h3 class="accordion-header" id="headingThree">
                            <button class="accordion-button fw-bold text-dark collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                <i class="bi bi-cpu text-primary me-2"></i>
                                Quels types de besoins en développement informatique sont couverts ?
                            </button>
                        </h3>
                        <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#accordionFaqHome">
                            <div class="accordion-body text-secondary leading-relaxed">
                                Nos développeurs indépendants couvrent un large panel de réalisations :
                                <ul class="mt-2 mb-0 ps-3">
                                    <li><strong>Sites web & vitrines :</strong> WordPress, Laravel, Symfony, React, Vue.js, TailwindCSS.</li>
                                    <li><strong>Applications mobiles :</strong> iOS et Android via Flutter, React Native ou natif.</li>
                                    <li><strong>E-commerce :</strong> intégration de passerelles Wave, Orange Money, MTN MoMo, Stripe.</li>
                                    <li><strong>Solutions sur mesure :</strong> APIs, logiciels de facturation/gestion, scripts d'automatisation.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Question 4 -->
                    <div class="accordion-item">
                        <h3 class="accordion-header" id="headingFour">
                            <button class="accordion-button fw-bold text-dark collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                                <i class="bi bi-patch-check text-primary me-2"></i>
                                Pourquoi choisir un développeur avec profil approuvé sur FindPro ?
                            </button>
                        </h3>
                        <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#accordionFaqHome">
                            <div class="accordion-body text-secondary leading-relaxed">
                                Le badge <strong>« Profil approuvé par FindPro »</strong> garantit qu'un administrateur a vérifié manuellement l'identité du professionnel, son dossier et son engagement éthique. De plus, les avis clients affichés sur les fiches de profil sont 100% authentiques : ils ne peuvent être rédigés qu'après la conclusion réelle d'une mission suivie sur la plateforme.
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>

<!-- ========================================================================= -->
<!-- 6. SECTION APPEL À L'ACTION : RECRUTEMENT DE PROFESSIONNELS              -->
<!-- ========================================================================= -->
<section class="py-5 py-lg-6 bg-light border-top" aria-labelledby="cta-pro-title">
    <div class="container py-2">
        <div class="card text-white border-0 rounded-4 overflow-hidden shadow-lg p-4 p-md-5 position-relative" style="background-color: #111827 !important; background: var(--color-primary, #111827) !important;">
            
            <div class="row align-items-center gy-4 position-relative z-1">
                <div class="col-12 col-lg-8">
                    <h2 id="cta-pro-title" class="h2 fw-bold text-white mb-3">
                        Vous êtes un professionnel du numérique ?
                    </h2>
                    <p class="text-light opacity-75 lead mb-0" style="font-size: 1.1rem; max-width: 680px; color: #e2e8f0 !important;">
                        Rejoignez le réseau FindPro. Valorisez votre portfolio, obtenez votre badge de profil approuvé et recevez des demandes de service ciblées sans commission cachée.
                    </p>
                </div>
                <div class="col-12 col-lg-4 text-lg-end">
                    <div class="d-flex flex-column flex-sm-row flex-lg-column gap-2 justify-content-lg-end">
                        <a href="<?= url('register.php') ?>" class="btn btn-brand btn-lg shadow-sm" style="background: linear-gradient(135deg, #E11D2E 0%, #F97316 100%) !important; color: #FFFFFF !important; border: none !important;">
                            <span>Créer mon profil professionnel</span>
                        </a>
                        <a href="<?= url('login.php') ?>" class="btn btn-outline-light btn-lg" style="border-color: rgba(255,255,255,0.4) !important; color: #FFFFFF !important;">
                            <span>Déjà inscrit ? Se connecter</span>
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
