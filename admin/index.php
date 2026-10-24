<?php
/**
 * Tableau de bord d'Administration — FindPro.
 * 
 * - Accès strictement réservé aux utilisateurs ayant le rôle "admin".
 * - Indicateurs clés préparés ou sans paramètres avec COUNT :
 *     * Nombre de clients
 *     * Nombre de professionnels
 *     * Profils en attente (soumis + en_verification)
 *     * Nombre de demandes de service
 *     * Nouveaux signalements
 * - Chaque carte renvoie vers sa page de gestion correspondante.
 * - Liste des 5 derniers profils soumis pour validation.
 * - Respect strict de docs/CHARTE_GRAPHIQUE_FINDPRO.md et des assets existants.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';

// RÈGLE OBLIGATOIRE : exiger_role('admin') en tête
demarrer_session();
$utilisateur = exiger_role('admin');

$pdo = db();

// -----------------------------------------------------------------------------
// Indicateurs statistiques (Toutes requêtes sans paramètres, avec COUNT)
// -----------------------------------------------------------------------------
$nbClients = (int) $pdo->query("SELECT COUNT(*) FROM utilisateur WHERE role = 'client'")->fetchColumn();
$nbProfessionnels = (int) $pdo->query("SELECT COUNT(*) FROM utilisateur WHERE role = 'professionnel'")->fetchColumn();
$nbProfilsEnAttente = (int) $pdo->query("SELECT COUNT(*) FROM profil_professionnel WHERE statut_validation IN ('soumis', 'en_verification')")->fetchColumn();
$nbDemandes = (int) $pdo->query("SELECT COUNT(*) FROM demande_service")->fetchColumn();
$nbSignalementsNouveaux = (int) $pdo->query("SELECT COUNT(*) FROM signalement WHERE statut = 'nouveau'")->fetchColumn();

// -----------------------------------------------------------------------------
// Récupération des 5 derniers profils soumis (statut 'soumis' ou 'en_verification')
// -----------------------------------------------------------------------------
$stmtDerniersSoumis = $pdo->query('
    SELECT 
        p.id_profil,
        p.titre,
        p.statut_validation,
        p.date_soumission,
        p.photo,
        p.engagement_cyber,
        p.zone,
        u.id_utilisateur,
        u.nom,
        u.prenom,
        u.email,
        c.libelle AS categorie_libelle,
        c.slug AS categorie_slug
    FROM profil_professionnel p
    JOIN utilisateur u ON p.id_utilisateur = u.id_utilisateur
    LEFT JOIN categorie c ON p.id_categorie = c.id_categorie
    WHERE p.statut_validation IN (\'soumis\', \'en_verification\')
    ORDER BY p.date_soumission DESC, p.id_profil DESC
    LIMIT 5
');
$derniersSoumis = $stmtDerniersSoumis->fetchAll();

$titre_page = 'Tableau de bord Administrateur';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4 py-lg-5">
    
    <!-- En-tête principal du tableau de bord -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-2 border-bottom">
        <div>
            <h1 class="h3 fw-bold mb-1">
                Tableau de bord Administrateur — <?= e($utilisateur['prenom'] . ' ' . $utilisateur['nom']) ?>
            </h1>
            <p class="text-secondary small mb-0">
                Supervision générale, arbitrage des profils professionnels et contrôle de conformité FindPro.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
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
                <a class="nav-link text-secondary btn-sm" href="<?= url('admin/validations.php') ?>">
                    <i class="bi bi-patch-check me-1"></i>Validation (<?= $nbProfilsEnAttente ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary btn-sm" href="<?= url('admin/utilisateurs.php') ?>">
                    <i class="bi bi-people me-1"></i>Comptes (<?= $nbClients + $nbProfessionnels ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary btn-sm" href="<?= url('admin/demandes.php') ?>">
                    <i class="bi bi-chat-square-text me-1"></i>Demandes (<?= $nbDemandes ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary btn-sm" href="<?= url('admin/signalements.php') ?>">
                    <i class="bi bi-flag me-1"></i>Signalements (<?= $nbSignalementsNouveaux ?>)
                </a>
            </li>
        </ul>
    </div>

    <div class="row g-4">
        
        <!-- Navigation latérale Desktop (affichée uniquement >= lg) -->
        <aside class="col-lg-3 d-none d-lg-block">
            <div class="card shadow-sm border mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <span class="fw-semibold small text-uppercase text-secondary tracking-wider">Administration</span>
                </div>
                <div class="list-group list-group-flush small">
                    <a href="<?= url('admin/index.php') ?>" class="list-group-item list-group-item-action active d-flex align-items-center justify-content-between py-2 fw-medium">
                        <span><i class="bi bi-speedometer2 me-2"></i>Vue d'ensemble</span>
                        <i class="bi bi-chevron-right small"></i>
                    </a>
                    <a href="<?= url('admin/validations.php') ?>" class="list-group-item list-group-item-action text-secondary d-flex align-items-center justify-content-between py-2">
                        <span><i class="bi bi-patch-check me-2 text-primary"></i>Validation des profils</span>
                        <?php if ($nbProfilsEnAttente > 0): ?>
                            <span class="badge bg-warning text-dark rounded-pill"><?= $nbProfilsEnAttente ?></span>
                        <?php else: ?>
                            <span class="badge bg-light text-secondary border">0</span>
                        <?php endif; ?>
                    </a>
                    <a href="<?= url('admin/utilisateurs.php') ?>" class="list-group-item list-group-item-action text-secondary d-flex align-items-center justify-content-between py-2">
                        <span><i class="bi bi-people me-2"></i>Comptes & Utilisateurs</span>
                        <span class="badge bg-light text-secondary border"><?= $nbClients + $nbProfessionnels ?></span>
                    </a>
                    <a href="<?= url('admin/demandes.php') ?>" class="list-group-item list-group-item-action text-secondary d-flex align-items-center justify-content-between py-2">
                        <span><i class="bi bi-chat-square-text me-2"></i>Demandes de service</span>
                        <span class="badge bg-light text-secondary border"><?= $nbDemandes ?></span>
                    </a>
                    <a href="<?= url('admin/signalements.php') ?>" class="list-group-item list-group-item-action text-secondary d-flex align-items-center justify-content-between py-2">
                        <span><i class="bi bi-flag me-2 text-danger"></i>Signalements</span>
                        <?php if ($nbSignalementsNouveaux > 0): ?>
                            <span class="badge bg-danger rounded-pill"><?= $nbSignalementsNouveaux ?></span>
                        <?php else: ?>
                            <span class="badge bg-light text-secondary border">0</span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>

            <!-- Espace réservé gris : module d'audit ou graphiques latéraux -->
            <div class="card shadow-sm border p-3 bg-light text-center">
                <div class="text-secondary opacity-50 mb-1">
                    <i class="bi bi-shield-lock fs-2"></i>
                </div>
                <span class="fw-semibold text-secondary small d-block">Espace réservé — Audit SOC2</span>
                <small class="text-muted" style="font-size: 0.72rem;">Registre de conformité administrative</small>
            </div>
        </aside>

        <!-- Contenu principal -->
        <main class="col-12 col-lg-9">
            
            <!-- 5 Cartes d'indicateurs (renvoyant chacune vers la page de gestion correspondante) -->
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-5 g-3 mb-4">
                
                <!-- Carte 1 : Profils en attente (Crucial) -->
                <div class="col">
                    <a href="<?= url('admin/validations.php') ?>" class="text-decoration-none h-100 d-block">
                        <div class="card p-3 shadow-sm border h-100 hover-shadow transition-all <?= $nbProfilsEnAttente > 0 ? 'border-warning bg-warning bg-opacity-10' : 'bg-white' ?>">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="small fw-semibold <?= $nbProfilsEnAttente > 0 ? 'text-warning-emphasis' : 'text-secondary' ?> text-uppercase tracking-wider" style="font-size: 0.72rem;">
                                    En attente
                                </span>
                                <div class="w-8 h-8 rounded p-1 bg-white border d-flex align-items-center justify-content-center text-warning shadow-sm">
                                    <i class="bi bi-hourglass-split"></i>
                                </div>
                            </div>
                            <div class="h3 fw-bold mb-1 text-dark">
                                <?= $nbProfilsEnAttente ?>
                            </div>
                            <small class="text-secondary" style="font-size: 0.75rem;">
                                <?= $nbProfilsEnAttente > 0 ? "Dossiers à arbitrer" : "Aucun dossier en attente" ?>
                            </small>
                        </div>
                    </a>
                </div>

                <!-- Carte 2 : Professionnels -->
                <div class="col">
                    <a href="<?= url('admin/utilisateurs.php?role=professionnel') ?>" class="text-decoration-none h-100 d-block">
                        <div class="card p-3 shadow-sm border h-100 hover-shadow transition-all bg-white">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="small fw-semibold text-secondary text-uppercase tracking-wider" style="font-size: 0.72rem;">
                                    Professionnels
                                </span>
                                <div class="w-8 h-8 rounded p-1 bg-light border d-flex align-items-center justify-content-center text-primary">
                                    <i class="bi bi-briefcase"></i>
                                </div>
                            </div>
                            <div class="h3 fw-bold mb-1 text-dark">
                                <?= $nbProfessionnels ?>
                            </div>
                            <small class="text-secondary" style="font-size: 0.75rem;">Prestataires inscrits</small>
                        </div>
                    </a>
                </div>

                <!-- Carte 3 : Clients inscrits -->
                <div class="col">
                    <a href="<?= url('admin/utilisateurs.php?role=client') ?>" class="text-decoration-none h-100 d-block">
                        <div class="card p-3 shadow-sm border h-100 hover-shadow transition-all bg-white">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="small fw-semibold text-secondary text-uppercase tracking-wider" style="font-size: 0.72rem;">
                                    Clients
                                </span>
                                <div class="w-8 h-8 rounded p-1 bg-light border d-flex align-items-center justify-content-center text-info">
                                    <i class="bi bi-people"></i>
                                </div>
                            </div>
                            <div class="h3 fw-bold mb-1 text-dark">
                                <?= $nbClients ?>
                            </div>
                            <small class="text-secondary" style="font-size: 0.75rem;">Donneurs d'ordres</small>
                        </div>
                    </a>
                </div>

                <!-- Carte 4 : Demandes de service -->
                <div class="col">
                    <a href="<?= url('admin/demandes.php') ?>" class="text-decoration-none h-100 d-block">
                        <div class="card p-3 shadow-sm border h-100 hover-shadow transition-all bg-white">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="small fw-semibold text-secondary text-uppercase tracking-wider" style="font-size: 0.72rem;">
                                    Demandes
                                </span>
                                <div class="w-8 h-8 rounded p-1 bg-light border d-flex align-items-center justify-content-center text-success">
                                    <i class="bi bi-chat-square-text"></i>
                                </div>
                            </div>
                            <div class="h3 fw-bold mb-1 text-dark">
                                <?= $nbDemandes ?>
                            </div>
                            <small class="text-secondary" style="font-size: 0.75rem;">Prestations créées</small>
                        </div>
                    </a>
                </div>

                <!-- Carte 5 : Nouveaux signalements -->
                <div class="col">
                    <a href="<?= url('admin/signalements.php') ?>" class="text-decoration-none h-100 d-block">
                        <div class="card p-3 shadow-sm border h-100 hover-shadow transition-all <?= $nbSignalementsNouveaux > 0 ? 'border-danger bg-danger bg-opacity-10' : 'bg-white' ?>">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="small fw-semibold <?= $nbSignalementsNouveaux > 0 ? 'text-danger' : 'text-secondary' ?> text-uppercase tracking-wider" style="font-size: 0.72rem;">
                                    Signalements
                                </span>
                                <div class="w-8 h-8 rounded p-1 bg-white border d-flex align-items-center justify-content-center text-danger shadow-sm">
                                    <i class="bi bi-flag-fill"></i>
                                </div>
                            </div>
                            <div class="h3 fw-bold mb-1 <?= $nbSignalementsNouveaux > 0 ? 'text-danger' : 'text-dark' ?>">
                                <?= $nbSignalementsNouveaux ?>
                            </div>
                            <small class="<?= $nbSignalementsNouveaux > 0 ? 'text-danger fw-semibold' : 'text-secondary' ?>" style="font-size: 0.75rem;">
                                <?= $nbSignalementsNouveaux > 0 ? "Nouveaux signalements" : "Aucun abus ouvert" ?>
                            </small>
                        </div>
                    </a>
                </div>

            </div>

            <!-- Tableau : 5 derniers profils soumis pour validation -->
            <div class="card shadow-sm border mb-4">
                <div class="card-header bg-white py-3 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 border-bottom">
                    <div>
                        <h2 class="h6 fw-bold mb-0 text-dark">
                            <i class="bi bi-clock-history text-primary me-2"></i>Derniers profils soumis pour validation
                        </h2>
                        <span class="text-secondary small">Les 5 dernières candidatures en attente de vérification administrative</span>
                    </div>
                    <div>
                        <a href="<?= url('admin/validations.php') ?>" class="btn btn-outline-primary btn-sm">
                            Voir toute la file (<?= $nbProfilsEnAttente ?>) <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>

                <?php if (empty($derniersSoumis)): ?>
                    <div class="p-5 text-center bg-light">
                        <div class="text-success mb-2">
                            <i class="bi bi-check-circle-fill fs-1 text-success opacity-75"></i>
                        </div>
                        <p class="fw-semibold text-dark mb-1">Aucun profil en attente de validation</p>
                        <small class="text-secondary">Tous les dossiers soumis par les professionnels ont été examinés ou aucun profil n'est actuellement en file d'attente.</small>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-secondary">
                                <tr>
                                    <th scope="col" class="ps-3">Professionnel</th>
                                    <th scope="col">Métier</th>
                                    <th scope="col">Titre</th>
                                    <th scope="col">Date de soumission</th>
                                    <th scope="col">Statut</th>
                                    <th scope="col" class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php foreach ($derniersSoumis as $p): 
                                    $dateSoum = !empty($p['date_soumission']) ? date('d/m/Y à H:i', strtotime($p['date_soumission'])) : 'N/C';
                                    $isCyber = ($p['categorie_slug'] === 'cybersecurite');
                                    $photoUrl = !empty($p['photo']) && file_exists(__DIR__ . '/../' . $p['photo']) ? url($p['photo']) : null;
                                    $avatarDefaut = file_exists(__DIR__ . '/../assets/img/avatar-defaut.svg') ? url('assets/img/avatar-defaut.svg') : null;
                                ?>
                                    <tr>
                                        <td class="ps-3 py-3">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="flex-shrink-0">
                                                    <?php if ($photoUrl !== null): ?>
                                                        <img src="<?= $photoUrl ?>" alt="Photo" class="rounded-circle object-fit-cover border shadow-sm" style="width: 38px; height: 38px;">
                                                    <?php elseif ($avatarDefaut !== null): ?>
                                                        <img src="<?= $avatarDefaut ?>" alt="Avatar" class="rounded-circle object-fit-cover border shadow-sm" style="width: 38px; height: 38px;">
                                                    <?php else: ?>
                                                        <!-- Espace réservé gris si avatar manquant -->
                                                        <div class="rounded-circle bg-secondary bg-opacity-25 d-flex align-items-center justify-content-center border shadow-sm" style="width: 38px; height: 38px;" title="Espace réservé gris">
                                                            <i class="bi bi-person text-secondary"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <span class="fw-semibold text-dark d-block">
                                                        <?= e($p['prenom'] . ' ' . $p['nom']) ?>
                                                    </span>
                                                    <small class="text-secondary"><?= e($p['email']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?= e($p['categorie_libelle'] ?? 'Non assigné') ?>
                                            </span>
                                            <?php if ($isCyber): ?>
                                                <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-50 small ms-1" title="Engagement cybersécurité requis">
                                                    <i class="bi bi-shield-check text-warning me-0.5"></i>Cyber
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-truncate" style="max-width: 220px;" title="<?= e($p['titre']) ?>">
                                            <?= e($p['titre']) ?>
                                        </td>
                                        <td>
                                            <span class="text-secondary"><?= e($dateSoum) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($p['statut_validation'] === 'soumis'): ?>
                                                <span class="badge bg-info text-dark">
                                                    <i class="bi bi-hourglass-split me-1"></i>En attente
                                                </span>
                                            <?php elseif ($p['statut_validation'] === 'en_verification'): ?>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="bi bi-search me-1"></i>En examen
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary text-white"><?= e($p['statut_validation']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-3">
                                            <a href="<?= url('admin/validations.php?id=' . (int) $p['id_profil']) ?>" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1">
                                                <i class="bi bi-patch-check"></i>
                                                <span>Examiner</span>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Espace réservé gris pour graphiques et statistiques (visuel manquant dans les références) -->
            <div class="card shadow-sm border mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h2 class="h6 fw-semibold mb-0 text-dark">Graphique d'activité de la plateforme</h2>
                </div>
                <div class="card-body p-5 text-center bg-light" style="min-height: 220px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                    <div class="text-secondary opacity-50 mb-2">
                        <i class="bi bi-bar-chart-line fs-1"></i>
                    </div>
                    <p class="text-secondary fw-medium mb-1">Espace réservé — Graphiques et statistiques d'activité</p>
                    <small class="text-muted">Visuel non présent dans les références — Espace réservé gris appliqué</small>
                </div>
            </div>

        </main>
    </div>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
