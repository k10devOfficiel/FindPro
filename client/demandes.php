<?php
/**
 * Suivi des demandes de service émises — Espace Client FindPro
 *
 * Règles d'accès (RG01, RG02, RG03) :
 * - Accès strictement réservé aux clients authentifiés via exiger_role('client').
 * - Un client ne voit QUE ses propres demandes (id_client = utilisateur connecté).
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
$utilisateur = exiger_role('client');
$idClient = (int) ($utilisateur['id'] ?? $utilisateur['id_utilisateur']);

$pdo = db();

// ============================================================================
// 1. FILTRE PAR STATUT ET VALIDATION
// ============================================================================

$statutsValides = ['tous', 'envoyee', 'acceptee', 'refusee', 'en_cours', 'a_confirmer', 'terminee', 'annulee'];
$statutFiltre = (string) ($_GET['statut'] ?? 'tous');
if (!in_array($statutFiltre, $statutsValides, true)) {
    $statutFiltre = 'tous';
}

// ============================================================================
// 2. COMPTEURS PAR STATUT POUR LE CLIENT
// ============================================================================

$stmtCounts = $pdo->prepare('
    SELECT statut, COUNT(*) AS nb
    FROM demande_service
    WHERE id_client = :cid
    GROUP BY statut
');
$stmtCounts->execute(['cid' => $idClient]);
$countsParStatut = [];
$totalDemandes = 0;
foreach ($stmtCounts->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $countsParStatut[$row['statut']] = (int) $row['nb'];
    $totalDemandes += (int) $row['nb'];
}

// ============================================================================
// 3. PAGINATION
// ============================================================================

$parPage = 10;
$pageParam = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$pageCourante = ($pageParam !== false && $pageParam > 0) ? (int) $pageParam : 1;

// Calcul du total d'enregistrements pour le filtre actuel
$sqlCount = 'SELECT COUNT(*) FROM demande_service WHERE id_client = :cid';
$paramsCount = ['cid' => $idClient];

if ($statutFiltre !== 'tous') {
    $sqlCount .= ' AND statut = :statut';
    $paramsCount['statut'] = $statutFiltre;
}

$stmtTotal = $pdo->prepare($sqlCount);
$stmtTotal->execute($paramsCount);
$totalFiltre = (int) $stmtTotal->fetchColumn();

$nbPages = (int) ceil($totalFiltre / $parPage);
if ($nbPages < 1) {
    $nbPages = 1;
}
if ($pageCourante > $nbPages) {
    $pageCourante = $nbPages;
}
$offset = ($pageCourante - 1) * $parPage;

// ============================================================================
// 4. RÉCUPÉRATION DES DEMANDES AVEC LES INFOS DU PROFESSIONNEL
// ============================================================================

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
        p.titre AS pro_titre,
        p.photo AS pro_photo,
        p.zone AS pro_zone,
        u_pro.nom AS pro_nom,
        u_pro.prenom AS pro_prenom,
        conv.id_conversation,
        a.id_avis,
        a.note AS avis_note
    FROM demande_service d
    JOIN profil_professionnel p ON d.id_profil = p.id_profil
    JOIN utilisateur u_pro ON p.id_utilisateur = u_pro.id_utilisateur
    LEFT JOIN categorie cat ON d.id_categorie = cat.id_categorie
    LEFT JOIN conversation conv ON d.id_demande = conv.id_demande
    LEFT JOIN avis a ON d.id_demande = a.id_demande
    WHERE d.id_client = :cid
';

$paramsDemandes = ['cid' => $idClient];

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

// Configuration des badges de statuts (Charte FindPro)
$statutClasses = [
    'envoyee'     => ['badge' => 'bg-info bg-opacity-10 text-info border border-info border-opacity-25', 'label' => 'Envoyée', 'icon' => 'bi-clock-history'],
    'acceptee'    => ['badge' => 'bg-success bg-opacity-10 text-success border border-success border-opacity-25', 'label' => 'Acceptée', 'icon' => 'bi-check-circle'],
    'refusee'     => ['badge' => 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25', 'label' => 'Refusée', 'icon' => 'bi-x-circle'],
    'en_cours'    => ['badge' => 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25', 'label' => 'En cours', 'icon' => 'bi-play-circle'],
    'a_confirmer' => ['badge' => 'bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25', 'label' => 'À confirmer', 'icon' => 'bi-exclamation-circle'],
    'terminee'    => ['badge' => 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25', 'label' => 'Terminée', 'icon' => 'bi-flag'],
    'annulee'     => ['badge' => 'bg-dark bg-opacity-10 text-dark border border-dark border-opacity-25', 'label' => 'Annulée', 'icon' => 'bi-dash-circle'],
];

$titre_page = 'Mes demandes de service — FindPro';
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
                <a href="<?= url('client/index.php') ?>" class="text-secondary text-decoration-none">Tableau de bord</a>
            </li>
            <li class="breadcrumb-item active text-dark fw-medium" aria-current="page">
                Mes demandes
            </li>
        </ol>
    </div>
</nav>

<div class="container py-4 py-lg-5">

    <!-- En-tête de section -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-2 border-bottom">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark">
                Mes demandes de service
            </h1>
            <p class="text-secondary small mb-0">
                Suivez l'état d'avancement de vos prestations et échangez avec vos professionnels.
            </p>
        </div>
        <div>
            <a href="<?= url('search.php') ?>" class="btn btn-brand btn-sm d-inline-flex align-items-center gap-1 shadow-2xs">
                <i class="bi bi-plus-lg"></i>
                <span>Nouvelle prestation</span>
            </a>
        </div>
    </div>

    <!-- Navigation mobile : Onglets défilants horizontaux (< lg) -->
    <div class="d-lg-none mb-4">
        <ul class="nav nav-pills flex-nowrap overflow-x-auto pb-2 gap-2 text-nowrap" style="scrollbar-width: thin;">
            <li class="nav-item">
                <a class="nav-link text-secondary btn-sm" href="<?= url('client/index.php') ?>">
                    <i class="bi bi-speedometer2 me-1"></i>Vue d'ensemble
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active btn-sm" href="<?= url('client/demandes.php') ?>">
                    <i class="bi bi-grid me-1"></i>Mes demandes (<?= $totalDemandes ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-secondary btn-sm" href="<?= url('client/profil.php') ?>">
                    <i class="bi bi-person-gear me-1"></i>Mon profil &amp; Paramètres
                </a>
            </li>
        </ul>
    </div>

    <div class="row g-4">
        
        <!-- Navigation latérale Desktop (>= lg) -->
        <aside class="col-lg-3 d-none d-lg-block">
            <div class="card shadow-sm border mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <span class="fw-semibold small text-uppercase text-secondary tracking-wider">Navigation Client</span>
                </div>
                <div class="list-group list-group-flush small">
                    <a href="<?= url('client/index.php') ?>" class="list-group-item list-group-item-action text-secondary d-flex align-items-center justify-content-between py-2">
                        <span><i class="bi bi-speedometer2 me-2"></i>Vue d'ensemble</span>
                        <i class="bi bi-chevron-right small"></i>
                    </a>
                    <a href="<?= url('client/demandes.php') ?>" class="list-group-item list-group-item-action active d-flex align-items-center justify-content-between py-2 fw-medium">
                        <span><i class="bi bi-grid me-2"></i>Mes demandes</span>
                        <span class="badge bg-white text-primary fw-bold rounded-pill"><?= $totalDemandes ?></span>
                    </a>
                    <a href="<?= url('client/profil.php') ?>" class="list-group-item list-group-item-action text-secondary d-flex align-items-center justify-content-between py-2">
                        <span><i class="bi bi-person-gear me-2"></i>Mon profil &amp; Paramètres</span>
                        <i class="bi bi-chevron-right small"></i>
                    </a>
                </div>
            </div>

            <!-- Carte filtres par statut -->
            <div class="card shadow-sm border p-3 small">
                <span class="fw-semibold text-secondary text-uppercase mb-2 d-block" style="font-size: 0.72rem;">Filtrer par statut</span>
                <div class="list-group list-group-flush">
                    <a href="<?= url('client/demandes.php?statut=tous') ?>" class="list-group-item list-group-item-action px-2 py-1.5 rounded-2 d-flex justify-content-between align-items-center <?= $statutFiltre === 'tous' ? 'active fw-semibold' : 'text-secondary' ?>">
                        <span>Tous les statuts</span>
                        <span class="badge <?= $statutFiltre === 'tous' ? 'bg-white text-primary' : 'bg-light text-secondary border' ?> rounded-pill"><?= $totalDemandes ?></span>
                    </a>
                    <?php foreach ($statutClasses as $stKey => $stCfg): 
                        $nbSt = $countsParStatut[$stKey] ?? 0;
                    ?>
                        <a href="<?= url('client/demandes.php?statut=' . $stKey) ?>" class="list-group-item list-group-item-action px-2 py-1.5 rounded-2 d-flex justify-content-between align-items-center <?= $statutFiltre === $stKey ? 'active fw-semibold' : 'text-secondary' ?>">
                            <span><?= e($stCfg['label']) ?></span>
                            <span class="badge <?= $statutFiltre === $stKey ? 'bg-white text-primary' : 'bg-light text-secondary border' ?> rounded-pill"><?= $nbSt ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>

        <!-- Contenu principal -->
        <main class="col-12 col-lg-9">

            <!-- Filtres rapides sous forme de pilules pour mobile / tablette -->
            <div class="d-lg-none mb-3">
                <div class="d-flex gap-2 overflow-x-auto pb-2" style="scrollbar-width: thin;">
                    <a href="<?= url('client/demandes.php?statut=tous') ?>" class="btn btn-sm text-nowrap <?= $statutFiltre === 'tous' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                        Tous (<?= $totalDemandes ?>)
                    </a>
                    <?php foreach ($statutClasses as $stKey => $stCfg): 
                        $nbSt = $countsParStatut[$stKey] ?? 0;
                        if ($nbSt === 0 && $statutFiltre !== $stKey) continue;
                    ?>
                        <a href="<?= url('client/demandes.php?statut=' . $stKey) ?>" class="btn btn-sm text-nowrap <?= $statutFiltre === $stKey ? 'btn-primary' : 'btn-outline-secondary' ?>">
                            <?= e($stCfg['label']) ?> (<?= $nbSt ?>)
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Liste des demandes -->
            <?php if (empty($demandes)): ?>
                <div class="card p-5 text-center shadow-sm border bg-light">
                    <div class="text-secondary opacity-50 mb-2">
                        <i class="bi bi-inbox fs-1"></i>
                    </div>
                    <h2 class="h5 fw-bold mb-1 text-dark">Aucune demande trouvée</h2>
                    <p class="text-secondary small mb-3">
                        <?php if ($statutFiltre !== 'tous'): ?>
                            Vous n'avez aucune demande au statut « <?= e($statutClasses[$statutFiltre]['label'] ?? $statutFiltre) ?> ».
                        <?php else: ?>
                            Vous n'avez pas encore envoyé de demande de prestation sur FindPro.
                        <?php endif; ?>
                    </p>
                    <div>
                        <a href="<?= url('search.php') ?>" class="btn btn-brand btn-sm">
                            <i class="bi bi-search me-1"></i>Trouver un professionnel
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3 mb-4">
                    <?php foreach ($demandes as $d): 
                        $st = $statutClasses[$d['statut']] ?? ['badge' => 'bg-light text-dark border', 'label' => ucfirst($d['statut']), 'icon' => 'bi-question-circle'];
                        
                        // Nom public pro (RG05)
                        $prenomP = trim((string) $d['pro_prenom']);
                        $nomP = trim((string) $d['pro_nom']);
                        $initP = $nomP !== '' ? mb_strtoupper(mb_substr($nomP, 0, 1, 'UTF-8'), 'UTF-8') . '.' : '';
                        $nomAfficheP = trim($prenomP . ' ' . $initP);
                        if ($nomAfficheP === '') $nomAfficheP = 'Professionnel';

                        // Avatar
                        $photoUrl = null;
                        if (!empty($d['pro_photo']) && file_exists(__DIR__ . '/../' . $d['pro_photo'])) {
                            $photoUrl = url($d['pro_photo']);
                        } else {
                            $photoUrl = url('assets/img/avatar-defaut.svg');
                        }
                    ?>
                        <div class="card shadow-sm border hover-shadow transition" data-demande-id="<?= (int) $d['id_demande'] ?>">
                            <div class="card-body p-3 p-md-4">
                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-stretch align-items-md-center gap-3">
                                    
                                    <div class="d-flex align-items-start gap-3 flex-grow-1">
                                        <!-- Photo du pro -->
                                        <div class="flex-shrink-0">
                                            <img src="<?= e($photoUrl) ?>" alt="Photo de <?= e($nomAfficheP) ?>" class="rounded-circle object-fit-cover shadow-2xs border" style="width: 52px; height: 52px;">
                                        </div>

                                        <!-- Détails de la demande -->
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
                                                Destinataire : <strong class="text-dark"><?= e($nomAfficheP) ?></strong> 
                                                <span class="text-muted">— <?= e($d['pro_titre']) ?></span>
                                            </p>

                                            <!-- Métadonnées : date, budget, délai -->
                                            <div class="d-flex flex-wrap gap-3 text-secondary small pt-1 border-top border-light">
                                                <span>
                                                    <i class="bi bi-calendar3 me-1 text-primary"></i>
                                                    <?= date('d/m/Y à H:i', strtotime($d['date_creation'])) ?>
                                                </span>
                                                <?php if ($d['budget_indicatif'] !== null): ?>
                                                    <span>
                                                        <i class="bi bi-cash me-1 text-success"></i>
                                                        <?= number_format((float) $d['budget_indicatif'], 0, ',', ' ') ?> FCFA
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">
                                                        <i class="bi bi-cash me-1 text-muted"></i>
                                                        Non précisé
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($d['delai_souhaite'])): ?>
                                                    <span>
                                                        <i class="bi bi-clock me-1 text-info"></i>
                                                        Souhaité le <?= date('d/m/Y', strtotime($d['delai_souhaite'])) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Séparateur discret sur mobile -->
                                    <hr class="d-md-none my-2.5 text-secondary opacity-25">

                                    <!-- Action principale et accès messagerie -->
                                    <div class="card-action-column flex-shrink-0 d-flex flex-column justify-content-center align-items-stretch gap-2">
                                        <a href="<?= url('demande_detail.php?id=' . $d['id_demande']) ?>" 
                                           class="btn btn-primary btn-sm d-inline-flex align-items-center justify-content-between gap-2 shadow-2xs px-3 py-2 fw-semibold w-100 text-nowrap">
                                            <span class="d-inline-flex align-items-center gap-1.5">
                                                <i class="bi bi-chat-dots-fill"></i>
                                                <span>Détails &amp; Échanges</span>
                                            </span>
                                            <i class="bi bi-arrow-right small"></i>
                                        </a>

                                        <?php if (!empty($d['id_conversation'])): ?>
                                            <a href="<?= url('chat/conversation.php?id=' . (int) $d['id_conversation']) ?>" 
                                               class="btn btn-outline-primary btn-sm d-inline-flex align-items-center justify-content-center gap-1.5 px-2.5 py-1.5 small text-nowrap w-100 fw-medium">
                                                <i class="bi bi-chat-text me-1"></i>Ouvrir le chat direct
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($d['statut'] === 'terminee'): ?>
                                            <?php if (empty($d['id_avis'])): ?>
                                                <a href="<?= url('demande_detail.php?id=' . $d['id_demande'] . '#evaluation') ?>" 
                                                   class="btn btn-warning btn-sm d-inline-flex align-items-center justify-content-center gap-1 shadow-2xs w-100 text-dark fw-semibold px-3 py-2">
                                                    <i class="bi bi-star-fill text-dark"></i>
                                                    <span>Laisser un avis</span>
                                                </a>
                                            <?php else: ?>
                                                <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-50 small py-1.5 px-2 text-center w-100">
                                                    <i class="bi bi-star-fill text-warning me-1"></i>Évalué <?= (int) $d['avis_note'] ?>/5
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>

                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination accessible -->
                <?php if ($nbPages > 1): ?>
                    <nav aria-label="Pagination des demandes" class="mt-4">
                        <ul class="pagination pagination-sm justify-content-center mb-0">
                            <!-- Page précédente -->
                            <li class="page-item <?= $pageCourante <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= url('client/demandes.php?statut=' . urlencode($statutFiltre) . '&page=' . ($pageCourante - 1)) ?>" aria-label="Précédent">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>

                            <?php for ($p = 1; $p <= $nbPages; $p++): ?>
                                <li class="page-item <?= $p === $pageCourante ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= url('client/demandes.php?statut=' . urlencode($statutFiltre) . '&page=' . $p) ?>">
                                        <?= $p ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <!-- Page suivante -->
                            <li class="page-item <?= $pageCourante >= $nbPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= url('client/demandes.php?statut=' . urlencode($statutFiltre) . '&page=' . ($pageCourante + 1)) ?>" aria-label="Suivant">
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
