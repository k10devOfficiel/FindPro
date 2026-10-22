<?php
/**
 * Page de test visuel et fonctionnel pour le navigateur.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

// Définition d'un message flash de démonstration
flash_set('success', 'Bienvenue sur FindPro ! Les composants d’en-tête, de navigation et de messages flash sont opérationnels.');

$titre_page = 'Démonstration Header & Footer';

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 text-center mb-5">
            <h1 class="display-5 fw-bold mb-3">Plateforme FindPro</h1>
            <p class="lead text-secondary">
                Trouvez le bon professionnel du numérique en toute confiance : développeurs, monteurs vidéo et spécialistes en cybersécurité.
            </p>
            <div class="d-flex justify-content-center gap-3 mt-4">
                <a href="<?= url('recherche.php') ?>" class="btn btn-brand btn-lg">
                    <i class="bi bi-search me-2"></i>Rechercher un pro
                </a>
                <a href="<?= url('inscription.php') ?>" class="btn btn-primary btn-lg">
                    Devenir prestataire
                </a>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card h-100 p-4">
                <div class="card-body">
                    <div class="d-inline-flex p-3 rounded-3 bg-light text-primary mb-3">
                        <i class="bi bi-code-slash fs-3"></i>
                    </div>
                    <h3 class="h5 card-title">Développement</h3>
                    <p class="card-text text-secondary">
                        Création d'applications web, sites vitrines, e-commerce et maintenance applicative.
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 p-4">
                <div class="card-body">
                    <div class="d-inline-flex p-3 rounded-3 bg-light text-primary mb-3">
                        <i class="bi bi-film fs-3"></i>
                    </div>
                    <h3 class="h5 card-title">Montage vidéo</h3>
                    <p class="card-text text-secondary">
                        Post-production, clips promotionnels, animations graphiques et étalonnage couleur.
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 p-4">
                <div class="card-body">
                    <div class="d-inline-flex p-3 rounded-3 bg-light text-primary mb-3">
                        <i class="bi bi-shield-check fs-3"></i>
                    </div>
                    <h3 class="h5 card-title">Cybersécurité</h3>
                    <p class="card-text text-secondary">
                        Audits de sécurité, tests de résistance, sécurisation de serveurs et conformité.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Démonstration d'espace réservé gris pour visuel -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="img-placeholder">
                <i class="bi bi-image"></i>
                <span class="fw-medium">Espace réservé pour image ou illustration de démonstration</span>
                <span class="small text-muted">Conforme à la charte graphique FindPro (aucun visuel non fourni n'est inventé)</span>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
