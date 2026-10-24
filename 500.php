<?php
/**
 * Page d'erreur HTTP 500 — Erreur interne du serveur — FindPro.
 * 
 * Présentation sobre, professionnelle et respectueuse de la charte graphique.
 * Ne divulgue AUCUN détail technique, trace d'exécution ou message de base de données.
 */

declare(strict_types=1);

if (!headers_sent()) {
    http_response_code(500);
}

// Tentative d'inclusion sécurisée de l'environnement FindPro
$findproRoot = __DIR__;
$configLoaded = false;

if (file_exists($findproRoot . '/config/config.php') && file_exists($findproRoot . '/includes/functions.php')) {
    try {
        if (file_exists($findproRoot . '/includes/securite.php')) {
            require_once $findproRoot . '/includes/securite.php';
        }
        require_once $findproRoot . '/config/config.php';
        require_once $findproRoot . '/includes/functions.php';
        require_once $findproRoot . '/includes/session.php';
        demarrer_session();
        $configLoaded = true;
    } catch (Throwable $t) {
        $configLoaded = false;
    }
}

$titre_page = 'Erreur technique — Erreur 500';

if ($configLoaded && file_exists($findproRoot . '/includes/header.php')) {
    require_once $findproRoot . '/includes/header.php';
} else {
    // Rendu autonome de secours au cas où le système PHP/BDD est complètement indisponible
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Erreur technique — FindPro</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <style>
            body { font-family: system-ui, -apple-system, sans-serif; background-color: #F8FAFC; color: #1F2937; }
            .btn-brand { background: linear-gradient(135deg, #E11D2E 0%, #F97316 100%); color: #fff; font-weight: 600; border: none; }
            .btn-brand:hover { opacity: 0.92; color: #fff; }
        </style>
    </head>
    <body class="d-flex flex-column min-vh-100">
    <main class="flex-grow-1">
    <?php
}
?>

<div class="container py-5 my-md-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-8 col-xl-6 text-center">

            <!-- Carte principale 500 -->
            <div class="card p-4 p-sm-5 border rounded-3 shadow-sm bg-white overflow-hidden">
                
                <!-- Icône et badge 500 -->
                <div class="mb-3">
                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3 py-1.5 font-monospace small fw-bold text-uppercase mb-2">
                        Erreur 500
                    </span>
                    <div class="display-3 fw-bold text-dark font-oxanium lh-1 my-2" style="letter-spacing: -0.04em;">
                        <i class="bi bi-exclamation-triangle text-warning"></i>
                    </div>
                </div>

                <!-- Titre de l'erreur -->
                <h1 class="h3 fw-bold text-dark mb-3">
                    Une erreur inattendue est survenue
                </h1>

                <!-- Message sobre sans fuite d'information technique -->
                <p class="text-secondary mb-4 mx-auto leading-relaxed" style="max-width: 440px;">
                    Notre serveur rencontre une difficulté temporaire pour traiter cette requête. L'incident a été consigné pour analyse par notre équipe technique.
                </p>

                <!-- Actions recommandées -->
                <div class="d-flex flex-column flex-sm-row justify-content-center gap-2 mb-3">
                    <button type="button" onclick="window.location.reload();" class="btn btn-brand px-4 py-2 d-inline-flex align-items-center justify-content-center gap-2 shadow-sm">
                        <i class="bi bi-arrow-clockwise" aria-hidden="true"></i>
                        <span>Réessayer</span>
                    </button>
                    <a href="<?= function_exists('url') ? url('') : '/findpro/' ?>" class="btn btn-outline-primary px-4 py-2 d-inline-flex align-items-center justify-content-center gap-2 shadow-2xs">
                        <i class="bi bi-house-door" aria-hidden="true"></i>
                        <span>Retour à l'accueil</span>
                    </a>
                </div>

                <div class="pt-3 border-top mt-3">
                    <small class="text-muted">
                        Si le problème persiste, merci de retenter votre démarche dans quelques instants.
                    </small>
                </div>

            </div>

        </div>
    </div>
</div>

<?php
if ($configLoaded && file_exists($findproRoot . '/includes/footer.php')) {
    require_once $findproRoot . '/includes/footer.php';
} else {
    ?>
    </main>
    <footer class="py-3 text-center text-muted small bg-white border-top">
        &copy; <?= date('Y') ?> FindPro. Tous droits réservés.
    </footer>
    </body>
    </html>
    <?php
}
