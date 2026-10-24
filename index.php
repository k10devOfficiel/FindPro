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
$profilsRecents = profils_publics($pdo, [], 6, 0);

$titre_page = 'Accueil — Trouvez le bon professionnel du numérique';
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
                            <button type="submit" class="btn btn-brand btn-lg w-100 d-inline-flex align-items-center justify-content-center gap-2 shadow-sm">
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
                <a href="<?= url('search.php') ?>" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1.5 shadow-sm">
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
                    <a href="<?= url('register.php') ?>" class="btn btn-brand px-4 py-2 shadow-sm">
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
<!-- 5. SECTION APPEL À L'ACTION : RECRUTEMENT DE PROFESSIONNELS              -->
<!-- ========================================================================= -->
<section class="py-5 py-lg-6 bg-light border-top" aria-labelledby="cta-pro-title">
    <div class="container py-2">
        <div class="card bg-dark text-white border-0 rounded-4 overflow-hidden shadow-lg p-4 p-md-5 position-relative" style="background-color: var(--color-primary) !important;">
            
            <div class="row align-items-center gy-4 position-relative z-1">
                <div class="col-12 col-lg-8">
                    <h2 id="cta-pro-title" class="h2 fw-bold text-white mb-3">
                        Vous êtes un professionnel du numérique ?
                    </h2>
                    <p class="text-light opacity-75 lead mb-0" style="font-size: 1.1rem; max-width: 680px;">
                        Rejoignez le réseau FindPro. Valorisez votre portfolio, obtenez votre badge de profil approuvé et recevez des demandes de service ciblées sans commission cachée.
                    </p>
                </div>
                <div class="col-12 col-lg-4 text-lg-end">
                    <div class="d-flex flex-column flex-sm-row flex-lg-column gap-2 justify-content-lg-end">
                        <a href="<?= url('register.php') ?>" class="btn btn-brand btn-lg shadow-sm">
                            <span>Créer mon profil professionnel</span>
                        </a>
                        <a href="<?= url('login.php') ?>" class="btn btn-outline-light btn-lg">
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
