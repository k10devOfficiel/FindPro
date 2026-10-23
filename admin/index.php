<?php
/**
 * Tableau de bord d'Administration — FindPro.
 * 
 * Accès strictement réservé aux utilisateurs ayant le rôle "admin".
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';

// RÈGLE OBLIGATOIRE : exiger_role('admin') avant toute autre action
demarrer_session();
$utilisateur = exiger_role('admin');

$titre_page = 'Tableau de bord Administrateur';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4 py-lg-5">
    
    <!-- En-tête principal du tableau de bord -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-2 border-bottom">
        <div>
            <h1 class="h3 fw-bold mb-1">
                Tableau de bord Administrateur — <?= e($utilisateur['prenom']) ?>
            </h1>
            <p class="text-secondary small mb-0">
                Supervision générale, contrôle de conformité et modération de la plateforme FindPro.
            </p>
        </div>
        <div>
            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3 py-2 rounded-pill font-monospace small">
                <i class="bi bi-shield-check me-1"></i>Accès Administrateur
            </span>
        </div>
    </div>

    <!-- Navigation mobile : Onglets défilants horizontaux (affichés uniquement < lg) -->
    <div class="d-lg-none mb-4">
        <ul class="nav nav-pills flex-nowrap overflow-x-auto pb-2 gap-2 text-nowrap" style="scrollbar-width: thin;">
            <li class="nav-item">
                <a class="nav-link active btn-sm" href="<?= url('admin/index.php') ?>">
                    <i class="bi bi-speedometer2 me-1"></i>Vue d'ensemble
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary btn-sm" href="#" onclick="return false;">
                    <i class="bi bi-patch-check me-1"></i>Validation
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary btn-sm" href="#" onclick="return false;">
                    <i class="bi bi-people me-1"></i>Comptes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary btn-sm" href="#" onclick="return false;">
                    <i class="bi bi-tags me-1"></i>Catégories
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary btn-sm" href="#" onclick="return false;">
                    <i class="bi bi-flag me-1"></i>Signalements
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary btn-sm" href="#" onclick="return false;">
                    <i class="bi bi-graph-up me-1"></i>Statistiques
                </a>
            </li>
        </ul>
    </div>

    <div class="row g-4">
        
        <!-- Navigation latérale Desktop (affichée uniquement >= lg) -->
        <aside class="col-lg-3 d-none d-lg-block">
            <div class="card shadow-sm border">
                <div class="card-header bg-white py-3 border-bottom">
                    <span class="fw-semibold small text-uppercase text-secondary tracking-wider">Administration</span>
                </div>
                <div class="list-group list-group-flush small">
                    <a href="<?= url('admin/index.php') ?>" class="list-group-item list-group-item-action active d-flex align-items-center justify-content-between py-2 fw-medium">
                        <span><i class="bi bi-speedometer2 me-2"></i>Vue d'ensemble</span>
                        <i class="bi bi-chevron-right small"></i>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action text-secondary d-flex align-items-center justify-content-between py-2" onclick="return false;">
                        <span><i class="bi bi-patch-check me-2"></i>Validation</span>
                        <span class="badge bg-light text-secondary border">Bientôt</span>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action text-secondary d-flex align-items-center justify-content-between py-2" onclick="return false;">
                        <span><i class="bi bi-people me-2"></i>Comptes</span>
                        <span class="badge bg-light text-secondary border">Bientôt</span>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action text-secondary d-flex align-items-center justify-content-between py-2" onclick="return false;">
                        <span><i class="bi bi-tags me-2"></i>Catégories</span>
                        <span class="badge bg-light text-secondary border">Bientôt</span>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action text-secondary d-flex align-items-center justify-content-between py-2" onclick="return false;">
                        <span><i class="bi bi-flag me-2"></i>Signalements</span>
                        <span class="badge bg-light text-secondary border">Bientôt</span>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action text-secondary d-flex align-items-center justify-content-between py-2" onclick="return false;">
                        <span><i class="bi bi-graph-up me-2"></i>Statistiques</span>
                        <span class="badge bg-light text-secondary border">Bientôt</span>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Contenu principal -->
        <main class="col-12 col-lg-9">
            
            <!-- Cartes récapitulatives d'indicateurs -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-md-3">
                    <div class="card p-3 shadow-sm border text-center h-100">
                        <small class="text-secondary mb-1">Dossiers en attente</small>
                        <span class="h4 fw-bold mb-0 text-primary">0</span>
                    </div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="card p-3 shadow-sm border text-center h-100">
                        <small class="text-secondary mb-1">Professionnels actifs</small>
                        <span class="h4 fw-bold mb-0 text-dark">0</span>
                    </div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="card p-3 shadow-sm border text-center h-100">
                        <small class="text-secondary mb-1">Clients inscrits</small>
                        <span class="h4 fw-bold mb-0 text-dark">0</span>
                    </div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="card p-3 shadow-sm border text-center h-100">
                        <small class="text-secondary mb-1">Signalements ouverts</small>
                        <span class="h4 fw-bold mb-0 text-danger">0</span>
                    </div>
                </div>
            </div>

            <!-- Espace réservé gris pour graphiques et statistiques (visuel manquant) -->
            <div class="card shadow-sm border mb-4">
                <div class="card-header bg-white py-3">
                    <h2 class="h6 fw-semibold mb-0">Graphique d'activité de la plateforme</h2>
                </div>
                <div class="card-body p-5 text-center bg-light" style="min-height: 240px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                    <div class="text-secondary opacity-50 mb-2">
                        <i class="bi bi-bar-chart-line fs-1"></i>
                    </div>
                    <p class="text-secondary fw-medium mb-1">Espace réservé — Graphiques et statistiques d'activité</p>
                    <small class="text-muted">Visuel non présent dans les références — Espace réservé gris appliqué</small>
                </div>
            </div>

            <div class="alert alert-info d-flex align-items-center" role="alert">
                <i class="bi bi-info-circle-fill me-2 fs-5 flex-shrink-0"></i>
                <div>
                    Les modules d'administration (Validation, Comptes, Catégories, Signalements, Statistiques) seront branchés aux prochaines étapes du développement.
                </div>
            </div>

        </main>
    </div>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
