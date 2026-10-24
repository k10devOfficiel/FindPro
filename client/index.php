<?php
/**
 * Tableau de bord Client — FindPro.
 * 
 * Accès strictement réservé aux utilisateurs ayant le rôle "client".
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';

// RÈGLE OBLIGATOIRE : exiger_role('client') avant toute autre action
demarrer_session();
$utilisateur = exiger_role('client');

$titre_page = 'Tableau de bord Client';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4 py-lg-5">
    
    <!-- En-tête principal du tableau de bord -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-2 border-bottom">
        <div>
            <h1 class="h3 fw-bold mb-1">
                Tableau de bord Client — <?= e($utilisateur['prenom']) ?>
            </h1>
            <p class="text-secondary small mb-0">
                Suivez vos demandes de services numériques, échangez avec vos prestataires et gérez votre compte.
            </p>
        </div>
        <div>
            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 rounded-pill font-monospace small">
                <i class="bi bi-person-check me-1"></i>Espace Particulier & Client
            </span>
        </div>
    </div>

    <!-- Navigation mobile : Onglets défilants horizontaux (affichés uniquement < lg) -->
    <div class="d-lg-none mb-4">
        <ul class="nav nav-pills flex-nowrap overflow-x-auto pb-2 gap-2 text-nowrap" style="scrollbar-width: thin;">
            <li class="nav-item">
                <a class="nav-link active btn-sm" href="<?= url('client/index.php') ?>">
                    <i class="bi bi-speedometer2 me-1"></i>Vue d'ensemble
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary btn-sm" href="<?= url('client/demandes.php') ?>">
                    <i class="bi bi-grid me-1"></i>Mes demandes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary btn-sm" href="#" onclick="return false;">
                    <i class="bi bi-chat-dots me-1"></i>Messages
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary btn-sm" href="#" onclick="return false;">
                    <i class="bi bi-person-gear me-1"></i>Mon compte
                </a>
            </li>
        </ul>
    </div>

    <div class="row g-4">
        
        <!-- Navigation latérale Desktop (affichée uniquement >= lg) -->
        <aside class="col-lg-3 d-none d-lg-block">
            <div class="card shadow-sm border">
                <div class="card-header bg-white py-3 border-bottom">
                    <span class="fw-semibold small text-uppercase text-secondary tracking-wider">Mon Compte</span>
                </div>
                <div class="list-group list-group-flush small">
                    <a href="<?= url('client/index.php') ?>" class="list-group-item list-group-item-action active d-flex align-items-center justify-content-between py-2 fw-medium">
                        <span><i class="bi bi-speedometer2 me-2"></i>Vue d'ensemble</span>
                        <i class="bi bi-chevron-right small"></i>
                    </a>
                    <a href="<?= url('client/demandes.php') ?>" class="list-group-item list-group-item-action text-secondary d-flex align-items-center justify-content-between py-2">
                        <span><i class="bi bi-grid me-2"></i>Mes demandes</span>
                        <i class="bi bi-chevron-right small"></i>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action text-secondary d-flex align-items-center justify-content-between py-2" onclick="return false;">
                        <span><i class="bi bi-chat-dots me-2"></i>Messages</span>
                        <span class="badge bg-light text-secondary border">Bientôt</span>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action text-secondary d-flex align-items-center justify-content-between py-2" onclick="return false;">
                        <span><i class="bi bi-person-gear me-2"></i>Mon compte</span>
                        <span class="badge bg-light text-secondary border">Bientôt</span>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Contenu principal -->
        <main class="col-12 col-lg-9">
            
            <!-- Cartes récapitulatives client -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-md-4">
                    <div class="card p-3 shadow-sm border text-center h-100">
                        <small class="text-secondary mb-1">Demandes actives</small>
                        <span class="h4 fw-bold mb-0 text-primary">0</span>
                    </div>
                </div>
                <div class="col-sm-6 col-md-4">
                    <div class="card p-3 shadow-sm border text-center h-100">
                        <small class="text-secondary mb-1">Prestations terminées</small>
                        <span class="h4 fw-bold mb-0 text-dark">0</span>
                    </div>
                </div>
                <div class="col-sm-6 col-md-4">
                    <div class="card p-3 shadow-sm border text-center h-100">
                        <small class="text-secondary mb-1">Messages non lus</small>
                        <span class="h4 fw-bold mb-0 text-dark">0</span>
                    </div>
                </div>
            </div>

            <!-- Espace réservé gris pour visuel / illustration de recherche (visuel manquant) -->
            <div class="card shadow-sm border mb-4">
                <div class="card-header bg-white py-3">
                    <h2 class="h6 fw-semibold mb-0">Trouver un nouveau professionnel</h2>
                </div>
                <div class="card-body p-5 text-center bg-light" style="min-height: 220px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                    <div class="text-secondary opacity-50 mb-2">
                        <i class="bi bi-image fs-1"></i>
                    </div>
                    <p class="text-secondary fw-medium mb-1">Espace réservé — Visuel de mise en relation</p>
                    <small class="text-muted">Visuel non présent dans les références — Espace réservé gris appliqué</small>
                    <div class="mt-3">
                        <a href="<?= url('recherche.php') ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-search me-1"></i>Explorer les professionnels
                        </a>
                    </div>
                </div>
            </div>

            <div class="alert alert-info d-flex align-items-center" role="alert">
                <i class="bi bi-info-circle-fill me-2 fs-5 flex-shrink-0"></i>
                <div>
                    Vos fonctionnalités de suivi (Mes demandes, Messages, Mon compte) seront connectées aux étapes suivantes du développement.
                </div>
            </div>

        </main>
    </div>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
