<?php
/**
 * Page d'erreur HTTP 403 — Accès refusé.
 * 
 * Affichée lorsqu'un utilisateur authentifié tente d'accéder à une zone
 * pour laquelle son rôle ne dispose pas des privilèges suffisants.
 */

declare(strict_types=1);

http_response_code(403);

$titre_page = '403 — Accès refusé';
require_once __DIR__ . '/header.php';
?>

<div class="container py-5 my-md-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6 col-xl-5 text-center">
            
            <div class="card p-4 p-md-5 shadow-sm border">
                <div class="mb-4">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger bg-opacity-10 text-danger p-3" style="width: 72px; height: 72px;">
                        <i class="bi bi-shield-lock-fill fs-1"></i>
                    </span>
                </div>

                <h1 class="h3 fw-bold mb-2"><?= isset($titre403) && $titre403 !== '' ? e($titre403) : 'Accès refusé (403)' ?></h1>
                <p class="text-secondary small mb-4">
                    <?= isset($message403) && $message403 !== '' ? e($message403) : 'Vous ne disposez pas des autorisations nécessaires pour accéder à cet espace. Veuillez vérifier votre rôle ou contacter l\'administration de FindPro.' ?>
                </p>

                <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
                    <a href="<?= url('') ?>" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-house-door me-1"></i>Retour à l'accueil
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
                        <a href="<?= $destination ?>" class="btn btn-brand btn-sm">
                            <i class="bi bi-speedometer2 me-1"></i>Mon tableau de bord
                        </a>
                    <?php else: ?>
                        <a href="<?= url('login.php') ?>" class="btn btn-brand btn-sm">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Se connecter
                        </a>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/footer.php';
exit;
