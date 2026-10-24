<?php
/**
 * Gestion et suivi des demandes de service reçues — Espace Professionnel FindPro
 *
 * Règles d'accès et d'isolation stricte (RG01, RG03, RG04) :
 * - Accès strictement réservé aux professionnels authentifiés via exiger_role('professionnel').
 * - Un professionnel ne voit JAMAIS les demandes adressées à un autre professionnel.
 * - Filtre par statut, pagination et tri antéchronologique.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';

demarrer_session();
$utilisateur = exiger_role('professionnel');
$idUtilisateurPro = (int) ($utilisateur['id'] ?? $utilisateur['id_utilisateur']);

$pdo = db();

// ============================================================================
// 1. RÉSOLUTION DU PROFIL PROFESSIONNEL CONNECTÉ
// ============================================================================

$stmtProfil = $pdo->prepare('
    SELECT id_profil, titre, statut_validation, disponibilite
    FROM profil_professionnel
    WHERE id_utilisateur = :uid
    LIMIT 1
');
$stmtProfil->execute(['uid' => $idUtilisateurPro]);
$profilPro = $stmtProfil->fetch(PDO::FETCH_ASSOC);

$idProfil = (int) ($profilPro['id_profil'] ?? 0);

// ============================================================================
// 2. FILTRE PAR STATUT ET VALIDATION
// ============================================================================

$statutsValides = ['tous', 'envoyee', 'acceptee', 'refusee', 'en_cours', 'a_confirmer', 'terminee', 'annulee'];
$statutFiltre = (string) ($_GET['statut'] ?? 'tous');
if (!in_array($statutFiltre, $statutsValides, true)) {
    $statutFiltre = 'tous';
}

// ============================================================================
// 3. COMPTEURS PAR STATUT POUR CE PROFESSIONNEL SPÉCIFIQUE
// ============================================================================

$countsParStatut = [];
$totalDemandes = 0;

if ($idProfil > 0) {
    $stmtCounts = $pdo->prepare('
        SELECT statut, COUNT(*) AS nb
        FROM demande_service
        WHERE id_profil = :pid
        GROUP BY statut
    ');
    $stmtCounts->execute(['pid' => $idProfil]);
    foreach ($stmtCounts->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $countsParStatut[$row['statut']] = (int) $row['nb'];
        $totalDemandes += (int) $row['nb'];
    }
}

// ============================================================================
// 4. PAGINATION
// ============================================================================

$parPage = 10;
$pageParam = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$pageCourante = ($pageParam !== false && $pageParam > 0) ? (int) $pageParam : 1;

$totalFiltre = 0;
if ($idProfil > 0) {
    $sqlCount = 'SELECT COUNT(*) FROM demande_service WHERE id_profil = :pid';
    $paramsCount = ['pid' => $idProfil];

    if ($statutFiltre !== 'tous') {
        $sqlCount .= ' AND statut = :statut';
        $paramsCount['statut'] = $statutFiltre;
    }

    $stmtTotal = $pdo->prepare($sqlCount);
    $stmtTotal->execute($paramsCount);
    $totalFiltre = (int) $stmtTotal->fetchColumn();
}

$nbPages = (int) ceil($totalFiltre / $parPage);
if ($nbPages < 1) {
    $nbPages = 1;
}
if ($pageCourante > $nbPages) {
    $pageCourante = $nbPages;
}
$offset = ($pageCourante - 1) * $parPage;

// ============================================================================
// 5. RÉCUPÉRATION DES DEMANDES (ISOLATION STRICTE AU PROFIL DU PRO)
// ============================================================================

$demandes = [];
if ($idProfil > 0) {
    $sqlDemandes = '
        SELECT 
            d.id_demande,
            d.id_client,
            d.id_profil,
            d.id_categorie,
            d.titre,
            d.description,
            d.budget_indicatif,
            d.delai_souhaite,
            d.statut,
            d.date_creation,
            d.date_cloture,
            d.date_confirmation,
            cat.libelle AS categorie_libelle,
            c.nom AS client_nom,
            c.prenom AS client_prenom,
            c.email AS client_email,
            c.telephone AS client_telephone,
            conv.id_conversation
        FROM demande_service d
        JOIN utilisateur c ON d.id_client = c.id_utilisateur
        LEFT JOIN categorie cat ON d.id_categorie = cat.id_categorie
        LEFT JOIN conversation conv ON d.id_demande = conv.id_demande
        WHERE d.id_profil = :pid
    ';

    $paramsDemandes = ['pid' => $idProfil];

    if ($statutFiltre !== 'tous') {
        $sqlDemandes .= ' AND d.statut = :statut';
        $paramsDemandes['statut'] = $statutFiltre;
    }

    $sqlDemandes .= ' ORDER BY d.date_creation DESC LIMIT :limit OFFSET :offset';

    $stmtDemandes = $pdo->prepare($sqlDemandes);
    foreach ($paramsDemandes as $cle => $val) {
        $stmtDemandes->bindValue(':' . $cle, $val);
    }
    $stmtDemandes->bindValue(':limit', $parPage, PDO::PARAM_INT);
    $stmtDemandes->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtDemandes->execute();
    $demandes = $stmtDemandes->fetchAll(PDO::FETCH_ASSOC);
}

// Configuration des badges de statuts (Charte FindPro)
$statutClasses = [
    'envoyee'     => ['badge' => 'bg-info bg-opacity-10 text-info border border-info border-opacity-25', 'label' => 'Nouvelle demande', 'icon' => 'bi-bell-fill'],
    'acceptee'    => ['badge' => 'bg-success bg-opacity-10 text-success border border-success border-opacity-25', 'label' => 'Acceptée', 'icon' => 'bi-check-circle'],
    'refusee'     => ['badge' => 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25', 'label' => 'Refusée', 'icon' => 'bi-x-circle'],
    'en_cours'    => ['badge' => 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25', 'label' => 'En cours', 'icon' => 'bi-play-circle'],
    'a_confirmer' => ['badge' => 'bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25', 'label' => 'Attente confirmation client', 'icon' => 'bi-hourglass-split'],
    'terminee'    => ['badge' => 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25', 'label' => 'Terminée', 'icon' => 'bi-flag'],
    'annulee'     => ['badge' => 'bg-dark bg-opacity-10 text-dark border border-dark border-opacity-25', 'label' => 'Annulée', 'icon' => 'bi-dash-circle'],
];

$titre_page = 'Demandes reçues — Espace Pro FindPro';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Fil d'Ariane -->
<nav class="py-3 bg-light border-bottom" aria-label="Fil d'Ariane">
    <div class="container">
        <ol class="breadcrumb small mb-0">
            <li class="breadcrumb-item">
                <a href="<?= url('') ?>" class="text-secondary text-decoration-none">Accueil</a>
            </li>
            <li class="breadcrumb-item">
                <a href="<?= url('pro/index.php') ?>" class="text-secondary text-decoration-none">Espace Pro</a>
            </li>
            <li class="breadcrumb-item active text-dark fw-medium" aria-current="page">
                Demandes reçues
            </li>
        </ol>
    </div>
</nav>

<div class="container py-4 py-lg-5">

    <!-- En-tête principal -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-2 border-bottom">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark">
                Demandes de prestations reçues
            </h1>
            <p class="text-secondary small mb-0">
                Gérez les opportunités de missions envoyées par les clients FindPro.
            </p>
        </div>
        <div>
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2 rounded-pill font-monospace small">
                <i class="bi bi-briefcase me-1"></i>Espace Professionnel
            </span>
        </div>
    </div>

    <!-- Navigation mobile : Onglets défilants (< lg) -->
    <div class="d-lg-none mb-4">
        <ul class="nav nav-pills flex-nowrap overflow-x-auto pb-2 gap-2 text-nowrap" style="scrollbar-width: thin;">
            <li class="nav-item">
                <a class="nav-link text-secondary btn-sm" href="<?= url('pro/index.php') ?>">
                    <i class="bi bi-speedometer2 me-1"></i>Vue d'ensemble
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active btn-sm" href="<?= url('pro/demandes.php') ?>">
                    <i class="bi bi-inbox me-1"></i>Demandes reçues (<?= $totalDemandes ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary btn-sm" href="<?= url('pro/profil.php') ?>">
                    <i class="bi bi-person-lines-fill me-1"></i>Mon profil
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary btn-sm" href="<?= url('pro/competences.php') ?>">
                    <i class="bi bi-award me-1"></i>Compétences
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary btn-sm" href="<?= url('pro/portfolio.php') ?>">
                    <i class="bi bi-images me-1"></i>Portfolio
                </a>
            </li>
        </ul>
    </div>

    <div class="row g-4">
        
        <!-- Navigation latérale Desktop (>= lg) -->
        <aside class="col-lg-3 d-none d-lg-block">
            <div class="card shadow-sm border mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <span class="fw-semibold small text-uppercase text-secondary tracking-wider">Espace Pro</span>
                </div>
                <div class="list-group list-group-flush small">
                    <a href="<?= url('pro/index.php') ?>" class="list-group-item list-group-item-action text-secondary d-flex align-items-center justify-content-between py-2">
                        <span><i class="bi bi-speedometer2 me-2"></i>Vue d'ensemble</span>
                        <i class="bi bi-chevron-right small"></i>
                    </a>
                    <a href="<?= url('pro/demandes.php') ?>" class="list-group-item list-group-item-action active d-flex align-items-center justify-content-between py-2 fw-medium">
                        <span><i class="bi bi-inbox me-2"></i>Demandes reçues</span>
                        <span class="badge bg-white text-primary fw-bold rounded-pill"><?= $totalDemandes ?></span>
                    </a>
                    <a href="<?= url('pro/profil.php') ?>" class="list-group-item list-group-item-action text-secondary d-flex align-items-center justify-content-between py-2">
                        <span><i class="bi bi-person-lines-fill me-2"></i>Mon profil</span>
                        <i class="bi bi-chevron-right small"></i>
                    </a>
                    <a href="<?= url('pro/competences.php') ?>" class="list-group-item list-group-item-action text-secondary d-flex align-items-center justify-content-between py-2">
                        <span><i class="bi bi-award me-2"></i>Compétences</span>
                        <i class="bi bi-chevron-right small"></i>
                    </a>
                    <a href="<?= url('pro/portfolio.php') ?>" class="list-group-item list-group-item-action text-secondary d-flex align-items-center justify-content-between py-2">
                        <span><i class="bi bi-images me-2"></i>Portfolio</span>
                        <i class="bi bi-chevron-right small"></i>
                    </a>
                </div>
            </div>

            <!-- Carte filtres par statut -->
            <div class="card shadow-sm border p-3 small">
                <span class="fw-semibold text-secondary text-uppercase mb-2 d-block" style="font-size: 0.72rem;">Filtrer par statut</span>
                <div class="list-group list-group-flush">
                    <a href="<?= url('pro/demandes.php?statut=tous') ?>" class="list-group-item list-group-item-action px-2 py-1.5 rounded-2 d-flex justify-content-between align-items-center <?= $statutFiltre === 'tous' ? 'active fw-semibold' : 'text-secondary' ?>">
                        <span>Toutes les demandes</span>
                        <span class="badge <?= $statutFiltre === 'tous' ? 'bg-white text-primary' : 'bg-light text-secondary border' ?> rounded-pill"><?= $totalDemandes ?></span>
                    </a>
                    <?php foreach ($statutClasses as $stKey => $stCfg): 
                        $nbSt = $countsParStatut[$stKey] ?? 0;
                    ?>
                        <a href="<?= url('pro/demandes.php?statut=' . $stKey) ?>" class="list-group-item list-group-item-action px-2 py-1.5 rounded-2 d-flex justify-content-between align-items-center <?= $statutFiltre === $stKey ? 'active fw-semibold' : 'text-secondary' ?>">
                            <span><?= e($stCfg['label']) ?></span>
                            <span class="badge <?= $statutFiltre === $stKey ? 'bg-white text-primary' : 'bg-light text-secondary border' ?> rounded-pill"><?= $nbSt ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>

        <!-- Contenu principal -->
        <main class="col-12 col-lg-9">

            <!-- Alerte si le professionnel n'a pas encore créé son profil -->
            <?php if (!$profilPro): ?>
                <div class="alert alert-warning d-flex align-items-center gap-3 shadow-2xs mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-3 text-warning flex-shrink-0" aria-hidden="true"></i>
                    <div>
                        <strong class="d-block mb-1">Profil professionnel non configuré</strong>
                        <span>Vous devez configurer votre vitrine professionnelle pour pouvoir recevoir et traiter des demandes de clients.</span>
                        <div class="mt-2">
                            <a href="<?= url('pro/profil.php') ?>" class="btn btn-sm btn-outline-warning">
                                Configurer mon profil
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Filtres rapides sur mobile -->
            <div class="d-lg-none mb-3">
                <div class="d-flex gap-2 overflow-x-auto pb-2" style="scrollbar-width: thin;">
                    <a href="<?= url('pro/demandes.php?statut=tous') ?>" class="btn btn-sm text-nowrap <?= $statutFiltre === 'tous' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                        Toutes (<?= $totalDemandes ?>)
                    </a>
                    <?php foreach ($statutClasses as $stKey => $stCfg): 
                        $nbSt = $countsParStatut[$stKey] ?? 0;
                        if ($nbSt === 0 && $statutFiltre !== $stKey) continue;
                    ?>
                        <a href="<?= url('pro/demandes.php?statut=' . $stKey) ?>" class="btn btn-sm text-nowrap <?= $statutFiltre === $stKey ? 'btn-primary' : 'btn-outline-secondary' ?>">
                            <?= e($stCfg['label']) ?> (<?= $nbSt ?>)
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Liste des demandes reçues -->
            <?php if (empty($demandes)): ?>
                <div class="card p-5 text-center shadow-sm border bg-light">
                    <div class="text-secondary opacity-50 mb-2">
                        <i class="bi bi-inbox fs-1"></i>
                    </div>
                    <h2 class="h5 fw-bold mb-1 text-dark">Aucune demande reçue</h2>
                    <p class="text-secondary small mb-0">
                        <?php if ($statutFiltre !== 'tous'): ?>
                            Aucune demande ne correspond au statut « <?= e($statutClasses[$statutFiltre]['label'] ?? $statutFiltre) ?> ».
                        <?php else: ?>
                            Vous n'avez pas encore reçu de demande de prestation de la part de clients FindPro.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3 mb-4">
                    <?php foreach ($demandes as $d): 
                        $st = $statutClasses[$d['statut']] ?? ['badge' => 'bg-light text-dark border', 'label' => ucfirst($d['statut']), 'icon' => 'bi-question-circle'];
                        $clientNomComplet = trim($d['client_prenom'] . ' ' . $d['client_nom']);
                    ?>
                        <div class="card shadow-sm border hover-shadow transition">
                            <div class="card-body p-3 p-md-4">
                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                                    
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                            <span class="badge <?= $st['badge'] ?> px-2.5 py-1 rounded-pill small fw-semibold font-monospace">
                                                <i class="bi <?= $st['icon'] ?> me-1"></i><?= e($st['label']) ?>
                                            </span>
                                            <span class="badge bg-light text-secondary border small">
                                                <?= e($d['categorie_libelle'] ?? 'Service') ?>
                                            </span>
                                        </div>

                                        <h2 class="h6 fw-bold mb-1 text-dark text-break">
                                            <a href="<?= url('demande_detail.php?id=' . $d['id_demande']) ?>" class="text-dark text-decoration-none hover-primary">
                                                <?= e($d['titre']) ?>
                                            </a>
                                        </h2>

                                        <p class="text-secondary small mb-2 text-break">
                                            Client demandeur : <strong class="text-dark"><i class="bi bi-person me-1"></i><?= e($clientNomComplet) ?></strong>
                                        </p>

                                        <!-- Métadonnées : date, budget, délai -->
                                        <div class="d-flex flex-wrap gap-3 text-secondary small pt-1 border-top border-light">
                                            <span>
                                                <i class="bi bi-calendar3 me-1 text-primary"></i>
                                                Reçue le <?= date('d/m/Y à H:i', strtotime($d['date_creation'])) ?>
                                            </span>
                                            <?php if ($d['budget_indicatif'] !== null): ?>
                                                <span>
                                                    <i class="bi bi-cash me-1 text-success"></i>
                                                    Budget indicatif : <?= number_format((float) $d['budget_indicatif'], 0, ',', ' ') ?> FCFA
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">
                                                    <i class="bi bi-cash me-1 text-muted"></i>
                                                    Budget : Non précisé
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($d['delai_souhaite'])): ?>
                                                <span>
                                                    <i class="bi bi-clock me-1 text-info"></i>
                                                    Délai souhaité : <?= date('d/m/Y', strtotime($d['delai_souhaite'])) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Action principale -->
                                    <div class="flex-shrink-0 align-self-md-center w-100 w-md-auto text-end text-md-start mt-2 mt-md-0">
                                        <a href="<?= url('demande_detail.php?id=' . $d['id_demande']) ?>" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center justify-content-center gap-1 shadow-2xs w-100 w-md-auto">
                                            <span>Examiner & Répondre</span>
                                            <i class="bi bi-chevron-right small"></i>
                                        </a>
                                    </div>

                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination accessible -->
                <?php if ($nbPages > 1): ?>
                    <nav aria-label="Pagination des demandes reçues" class="mt-4">
                        <ul class="pagination pagination-sm justify-content-center mb-0">
                            <!-- Page précédente -->
                            <li class="page-item <?= $pageCourante <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= url('pro/demandes.php?statut=' . urlencode($statutFiltre) . '&page=' . ($pageCourante - 1)) ?>" aria-label="Précédent">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>

                            <?php for ($p = 1; $p <= $nbPages; $p++): ?>
                                <li class="page-item <?= $p === $pageCourante ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= url('pro/demandes.php?statut=' . urlencode($statutFiltre) . '&page=' . $p) ?>">
                                        <?= $p ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <!-- Page suivante -->
                            <li class="page-item <?= $pageCourante >= $nbPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= url('pro/demandes.php?statut=' . urlencode($statutFiltre) . '&page=' . ($pageCourante + 1)) ?>" aria-label="Suivant">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php endif; ?>

        </main>
    </div>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
