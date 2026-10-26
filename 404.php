<?php
/**
 * Page d'erreur HTTP 404 — Page introuvable — FindPro
 * 
 * Accessible directement ou incluse par la fonction page_introuvable().
 * Respecte rigoureusement la charte graphique FindPro et le design mobile (360px).
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/errors.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/session.php';

demarrer_session();

// Envoi systématique du code HTTP 404
if (http_response_code() !== 404) {
    http_response_code(404);
}

$titre_page = 'Page introuvable — Erreur 404';
$titreAffichage = $titre404 ?? 'Page introuvable';
$messageAffichage = $message404 ?? "La ressource que vous recherchez n'existe pas, a été supprimée ou son identifiant est invalide.";

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5 my-md-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-8 col-xl-7 text-center">

            <!-- Carte principale 404 -->
            <div class="card p-4 p-sm-5 border rounded-3 shadow-sm bg-white overflow-hidden">
                
                <!-- Grand indicateur 404 stylisé -->
                <div class="mb-3">
                    <span class="badge bg-light text-primary border px-3 py-1.5 font-monospace small fw-bold text-uppercase mb-2">
                        Erreur 404
                    </span>
                    <div class="display-2 fw-bold text-dark font-oxanium lh-1 my-2" style="letter-spacing: -0.04em;">
                        404
                    </div>
                </div>

                <!-- Titre de l'erreur -->
                <h1 class="h3 fw-bold text-dark mb-3">
                    <?= e($titreAffichage) ?>
                </h1>

                <!-- Message explicatif -->
                <p class="text-secondary mb-4 mx-auto leading-relaxed" style="max-width: 480px;">
                    <?= e($messageAffichage) ?>
                </p>

                <!-- Boutons d'action -->
                <div class="d-flex flex-column flex-sm-row justify-content-center gap-2 mb-4">
                    <a href="<?= url('') ?>" class="btn btn-brand px-4 py-2 d-inline-flex align-items-center justify-content-center gap-2 shadow-sm">
                        <i class="bi bi-house-door" aria-hidden="true"></i>
                        <span>Retour à l'accueil</span>
                    </a>
                    <a href="<?= url('search.php') ?>" class="btn btn-outline-primary px-4 py-2 d-inline-flex align-items-center justify-content-center gap-2 shadow-sm">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <span>Explorer les professionnels</span>
                    </a>
                </div>

                <!-- Suggestions de navigation utiles -->
                <div class="pt-4 border-top">
                    <span class="text-secondary small fw-semibold text-uppercase d-block mb-2" style="font-size: 0.75rem; letter-spacing: 0.05em;">
                        Découvrir nos catégories de métiers :
                    </span>
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <a href="<?= url('category.php?slug=developpement') ?>" class="badge bg-light text-secondary border text-decoration-none py-1.5 px-2.5 hover-primary">
                            Développement informatique
                        </a>
                        <a href="<?= url('category.php?slug=montage-video') ?>" class="badge bg-light text-secondary border text-decoration-none py-1.5 px-2.5 hover-primary">
                            Montage vidéo
                        </a>
                        <a href="<?= url('category.php?slug=cybersecurite') ?>" class="badge bg-light text-secondary border text-decoration-none py-1.5 px-2.5 hover-primary">
                            Cybersécurité
                        </a>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
