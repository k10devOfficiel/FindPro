<?php
/**
 * Page d'erreur HTTP 403 — Accès refusé — FindPro.
 * 
 * Présentation sobre, professionnelle et respectueuse de la charte graphique.
 * Ne divulgue AUCUN détail technique, chemin système ou information sensible.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/errors.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/session.php';

demarrer_session();

if (!headers_sent()) {
    http_response_code(403);
}

$titre_page = 'Accès refusé — Erreur 403';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5 my-md-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-8 col-xl-6 text-center">

            <!-- Carte principale 403 -->
            <div class="card p-4 p-sm-5 border rounded-3 shadow-sm bg-white overflow-hidden">
                
                <!-- Icône et badge 403 -->
                <div class="mb-3">
                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3 py-1.5 font-monospace small fw-bold text-uppercase mb-2">
                        Erreur 403
                    </span>
                    <div class="display-3 fw-bold text-dark font-oxanium lh-1 my-2" style="letter-spacing: -0.04em;">
                        <i class="bi bi-shield-lock text-danger"></i>
                    </div>
                </div>

                <!-- Titre de l'erreur -->
                <h1 class="h3 fw-bold text-dark mb-3">
                    Accès non autorisé
                </h1>

                <!-- Message explicatif épuré sans détail technique -->
                <p class="text-secondary mb-4 mx-auto leading-relaxed" style="max-width: 440px;">
                    Vous ne disposez pas des autorisations nécessaires pour accéder à cette ressource. Veuillez vous assurer d'être connecté avec le compte approprié.
                </p>

                <!-- Boutons d'action -->
                <div class="d-flex flex-column flex-sm-row justify-content-center gap-2 mb-3">
                    <a href="<?= url('') ?>" class="btn btn-outline-primary px-4 py-2 d-inline-flex align-items-center justify-content-center gap-2 shadow-2xs">
                        <i class="bi bi-house-door" aria-hidden="true"></i>
                        <span>Retour à l'accueil</span>
                    </a>

                    <?php 
                    $u = utilisateur_courant();
                    if ($u !== null):
                        $destination = match ($u['role']) {
                            'admin'         => url('admin/index.php'),
                            'professionnel' => url('pro/index.php'),
                            default         => url('client/index.php'),
                        };
                    ?>
                        <a href="<?= $destination ?>" class="btn btn-brand px-4 py-2 d-inline-flex align-items-center justify-content-center gap-2 shadow-sm">
                            <i class="bi bi-speedometer2" aria-hidden="true"></i>
                            <span>Mon tableau de bord</span>
                        </a>
                    <?php else: ?>
                        <a href="<?= url('login.php') ?>" class="btn btn-brand px-4 py-2 d-inline-flex align-items-center justify-content-center gap-2 shadow-sm">
                            <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                            <span>Se connecter</span>
                        </a>
                    <?php endif; ?>
                </div>

            </div>

        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
