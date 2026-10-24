<?php
/**
 * File d'attente et arbitrage des profils professionnels — FindPro.
 * 
 * - Accès strictement réservé aux administrateurs (exiger_role('admin')).
 * - Liste des profils dont le statut est 'soumis' ou 'en_verification'.
 * - Triés par date_soumission croissante (ordre chronologique strict / SLA de modération).
 * - Affiche : photo, nom complet, catégorie, titre professionnel, date de soumission et bouton "Examiner".
 * - Filtres dynamiques par statut ('soumis', 'en_verification') et par catégorie de métier.
 * - Pagination stricte de 20 profils par page avec préservation des filtres.
 * - Échappement complet de toutes les sorties (protection XSS via e()).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';

// 1. Authentification stricte de l'administrateur
demarrer_session();
$utilisateur = exiger_role('admin');

$pdo = db();

// 2. Chargement de la liste des catégories pour le filtre
$categories = $pdo->query('SELECT id_categorie, libelle, slug FROM categorie ORDER BY libelle ASC')->fetchAll();

// 3. Récupération et validation des filtres GET
$filtreStatut = trim((string) ($_GET['statut'] ?? ''));
if (!in_array($filtreStatut, ['soumis', 'en_verification'], true)) {
    $filtreStatut = ''; // 'Tous' par défaut
}

$idCategorie = (int) ($_GET['categorie'] ?? 0);
if ($idCategorie > 0) {
    // Vérifier l'existence en base
    $stmtVerifCat = $pdo->prepare('SELECT COUNT(*) FROM categorie WHERE id_categorie = :id');
    $stmtVerifCat->execute(['id' => $idCategorie]);
    if ((int) $stmtVerifCat->fetchColumn() === 0) {
        $idCategorie = 0;
    }
}

// 4. Compteurs d'en-tête pour les onglets de statut
$nbTotalAttente = (int) $pdo->query("SELECT COUNT(*) FROM profil_professionnel WHERE statut_validation IN ('soumis', 'en_verification')")->fetchColumn();
$nbSoumis = (int) $pdo->query("SELECT COUNT(*) FROM profil_professionnel WHERE statut_validation = 'soumis'")->fetchColumn();
$nbEnVerification = (int) $pdo->query("SELECT COUNT(*) FROM profil_professionnel WHERE statut_validation = 'en_verification'")->fetchColumn();

// 5. Construction de la requête avec filtres
$whereClauses = [];
$params = [];

if ($filtreStatut !== '') {
    $whereClauses[] = 'p.statut_validation = :statut';
    $params['statut'] = $filtreStatut;
} else {
    $whereClauses[] = 'p.statut_validation IN (\'soumis\', \'en_verification\')';
}

if ($idCategorie > 0) {
    $whereClauses[] = 'p.id_categorie = :id_categorie';
    $params['id_categorie'] = $idCategorie;
}

$whereSql = implode(' AND ', $whereClauses);

// 6. Pagination (20 éléments par page)
$parPage = 20;

// Requête de comptage total pour la pagination
$sqlCount = "
    SELECT COUNT(*) 
    FROM profil_professionnel p
    JOIN utilisateur u ON p.id_utilisateur = u.id_utilisateur
    WHERE {$whereSql}
";
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalProfils = (int) $stmtCount->fetchColumn();

$totalPages = max(1, (int) ceil($totalProfils / $parPage));
$page = max(1, (int) ($_GET['page'] ?? 1));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $parPage;

// 7. Requête principale ordonnée par date_soumission CROISSANTE (date_soumission ASC)
$sqlProfils = "
    SELECT 
        p.id_profil,
        p.titre,
        p.statut_validation,
        p.date_soumission,
        p.photo,
        p.zone,
        p.engagement_cyber,
        p.date_engagement_cyber,
        u.id_utilisateur,
        u.nom,
        u.prenom,
        u.email,
        u.telephone,
        c.id_categorie,
        c.libelle AS categorie_libelle,
        c.slug AS categorie_slug
    FROM profil_professionnel p
    JOIN utilisateur u ON p.id_utilisateur = u.id_utilisateur
    LEFT JOIN categorie c ON p.id_categorie = c.id_categorie
    WHERE {$whereSql}
    ORDER BY p.date_soumission ASC, p.id_profil ASC
    LIMIT {$parPage} OFFSET {$offset}
";

$stmtProfils = $pdo->prepare($sqlProfils);
$stmtProfils->execute($params);
$profils = $stmtProfils->fetchAll();

// Index de pagination affiché
$debutAffiche = $totalProfils > 0 ? $offset + 1 : 0;
$finAffiche = min($offset + $parPage, $totalProfils);

// Helper pour préserver les filtres dans les liens de pagination
function build_url_params(array $override = []): string {
    $queryParams = $_GET;
    foreach ($override as $key => $val) {
        if ($val === null || $val === '') {
            unset($queryParams[$key]);
        } else {
            $queryParams[$key] = (string) $val;
        }
    }
    return http_build_query($queryParams);
}

// Vérification de la présence de l'avatar par défaut
$avatarDefautRel = 'assets/img/avatar-defaut.svg';
$avatarDefautExiste = file_exists(__DIR__ . '/../' . $avatarDefautRel);

$titre_page = 'Validation des profils professionnels';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4 py-lg-5">
    
    <!-- En-tête principal -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-2 border-bottom">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                <h1 class="h3 fw-bold mb-0">Validation des profils</h1>
                <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-50 px-2.5 py-1.5 rounded-pill small font-monospace">
                    <i class="bi bi-hourglass-split me-1"></i><?= $totalProfils ?> dossier(s) en attente
                </span>
            </div>
            <p class="text-secondary small mb-0">
                File d'arbitrage chronologique (date de soumission croissante). Traitement selon la règle SLA premier soumis, premier examiné.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= url('admin/index.php') ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Tableau de bord
            </a>
        </div>
    </div>

    <!-- Barre d'outils et de filtres (Statut + Catégorie) -->
    <div class="card p-3 shadow-sm border mb-4 bg-white">
        <form method="GET" action="<?= url('admin/validation.php') ?>" class="row g-3 align-items-center">
            
            <!-- Onglets de filtre par statut -->
            <div class="col-12 col-md-6 col-lg-5">
                <label class="form-label small fw-semibold text-secondary mb-1 d-block">Statut de validation :</label>
                <div class="btn-group w-100" role="group" aria-label="Filtre par statut">
                    <a href="<?= url('admin/validation.php?' . build_url_params(['statut' => '', 'page' => 1])) ?>" class="btn btn-sm <?= $filtreStatut === '' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                        Tous <span class="badge bg-light text-dark ms-1"><?= $nbTotalAttente ?></span>
                    </a>
                    <a href="<?= url('admin/validation.php?' . build_url_params(['statut' => 'soumis', 'page' => 1])) ?>" class="btn btn-sm <?= $filtreStatut === 'soumis' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                        Soumis <span class="badge bg-info text-dark ms-1"><?= $nbSoumis ?></span>
                    </a>
                    <a href="<?= url('admin/validation.php?' . build_url_params(['statut' => 'en_verification', 'page' => 1])) ?>" class="btn btn-sm <?= $filtreStatut === 'en_verification' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                        En examen <span class="badge bg-warning text-dark ms-1"><?= $nbEnVerification ?></span>
                    </a>
                </div>
            </div>

            <!-- Filtre par catégorie -->
            <div class="col-12 col-sm-8 col-md-4 col-lg-5">
                <label for="categorie" class="form-label small fw-semibold text-secondary mb-1">Corps de métier :</label>
                <input type="hidden" name="statut" value="<?= e($filtreStatut) ?>">
                <select name="categorie" id="categorie" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">Toutes les catégories (<?= $nbTotalAttente ?>)</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int) $cat['id_categorie'] ?>" <?= $idCategorie === (int) $cat['id_categorie'] ? 'selected' : '' ?>>
                            <?= e($cat['libelle']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Bouton Réinitialiser si filtre actif -->
            <div class="col-12 col-sm-4 col-md-2 col-lg-2 d-flex align-items-end">
                <?php if ($filtreStatut !== '' || $idCategorie > 0): ?>
                    <a href="<?= url('admin/validation.php') ?>" class="btn btn-outline-danger btn-sm w-100" title="Réinitialiser les filtres">
                        <i class="bi bi-x-circle me-1"></i>Effacer
                    </a>
                <?php else: ?>
                    <button type="submit" class="btn btn-light border btn-sm w-100 text-secondary">
                        <i class="bi bi-filter me-1"></i>Filtrer
                    </button>
                <?php endif; ?>
            </div>

        </form>
    </div>

    <!-- Conteneur principal : Liste ou état vide -->
    <?php if (empty($profils)): ?>
        <div class="card p-5 text-center shadow-sm border bg-light">
            <div class="text-success mb-2">
                <i class="bi bi-check2-circle fs-1 text-success opacity-75"></i>
            </div>
            <h2 class="h5 fw-bold mb-1 text-dark">Aucun dossier en attente pour ces critères</h2>
            <p class="text-secondary small mb-3">
                <?= ($filtreStatut !== '' || $idCategorie > 0) ? 'Aucun profil soumis ne correspond aux filtres sélectionnés.' : 'Tous les dossiers soumis par les professionnels ont été examinés avec succès.' ?>
            </p>
            <?php if ($filtreStatut !== '' || $idCategorie > 0): ?>
                <div>
                    <a href="<?= url('admin/validation.php') ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Afficher tous les dossiers
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>

        <div class="card shadow-sm border">
            
            <div class="card-header bg-white py-3 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 border-bottom">
                <span class="small fw-semibold text-secondary text-uppercase tracking-wider">
                    Dossiers classés par ancienneté croissante (<?= $debutAffiche ?> à <?= $finAffiche ?> sur <?= $totalProfils ?>)
                </span>
                <span class="badge bg-light text-secondary border small">
                    Page <?= $page ?> sur <?= $totalPages ?>
                </span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-secondary">
                        <tr>
                            <th scope="col" class="ps-3" style="width: 280px;">Professionnel</th>
                            <th scope="col">Catégorie</th>
                            <th scope="col">Titre professionnel</th>
                            <th scope="col">Date de soumission</th>
                            <th scope="col">Statut</th>
                            <th scope="col" class="text-end pe-3" style="width: 130px;">Action</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php foreach ($profils as $p): 
                            $dateSoum = !empty($p['date_soumission']) ? date('d/m/Y à H:i', strtotime($p['date_soumission'])) : 'Non renseignée';
                            $isCyber = ($p['categorie_slug'] === 'cybersecurite');
                            $photoUrl = !empty($p['photo']) && file_exists(__DIR__ . '/../' . $p['photo']) ? url($p['photo']) : null;
                            $avatarDefaut = $avatarDefautExiste ? url($avatarDefautRel) : null;
                        ?>
                            <tr>
                                
                                <!-- Photo & Nom -->
                                <td class="ps-3 py-3">
                                    <div class="d-flex align-items-center gap-2.5">
                                        <div class="position-relative flex-shrink-0">
                                            <?php if ($photoUrl !== null): ?>
                                                <img src="<?= $photoUrl ?>" alt="Photo de <?= e($p['prenom']) ?>" class="rounded-circle object-fit-cover border shadow-sm" style="width: 44px; height: 44px;">
                                            <?php elseif ($avatarDefaut !== null): ?>
                                                <img src="<?= $avatarDefaut ?>" alt="Avatar par défaut" class="rounded-circle object-fit-cover border shadow-sm" style="width: 44px; height: 44px;">
                                            <?php else: ?>
                                                <!-- Espace réservé gris si aucun visuel disponible -->
                                                <div class="rounded-circle bg-secondary bg-opacity-25 d-flex align-items-center justify-content-center border shadow-sm text-secondary" style="width: 44px; height: 44px;" title="Espace réservé gris">
                                                    <i class="bi bi-person fs-5"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <span class="fw-bold text-dark d-block">
                                                <?= e($p['prenom'] . ' ' . $p['nom']) ?>
                                            </span>
                                            <small class="text-secondary d-block"><?= e($p['email']) ?></small>
                                            <?php if (!empty($p['telephone'])): ?>
                                                <small class="text-muted font-monospace" style="font-size: 0.72rem;"><?= e($p['telephone']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>

                                <!-- Catégorie -->
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?= e($p['categorie_libelle'] ?? 'Non assignée') ?>
                                    </span>
                                    <?php if ($isCyber): ?>
                                        <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-50 small ms-1" title="Engagement déontologique requis">
                                            <i class="bi bi-shield-check text-warning me-0.5"></i>Cyber
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Titre professionnel -->
                                <td>
                                    <span class="fw-medium text-dark d-block text-truncate" style="max-width: 240px;" title="<?= e($p['titre']) ?>">
                                        <?= e($p['titre']) ?>
                                    </span>
                                    <?php if (!empty($p['zone'])): ?>
                                        <small class="text-secondary"><i class="bi bi-geo-alt me-0.5"></i><?= e($p['zone']) ?></small>
                                    <?php endif; ?>
                                </td>

                                <!-- Date de soumission (triée croissante) -->
                                <td>
                                    <span class="text-dark fw-medium d-block"><?= e($dateSoum) ?></span>
                                    <small class="text-secondary font-monospace" style="font-size: 0.72rem;">
                                        ID profil #<?= (int) $p['id_profil'] ?>
                                    </small>
                                </td>

                                <!-- Statut -->
                                <td>
                                    <?php if ($p['statut_validation'] === 'soumis'): ?>
                                        <span class="badge bg-info text-dark">
                                            <i class="bi bi-hourglass-split me-1"></i>En attente
                                        </span>
                                    <?php elseif ($p['statut_validation'] === 'en_verification'): ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="bi bi-search me-1"></i>En cours d'examen
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary text-white"><?= e($p['statut_validation']) ?></span>
                                    <?php endif; ?>
                                </td>

                                <!-- Bouton Examiner -->
                                <td class="text-end pe-3">
                                    <a href="<?= url('admin/validation-detail.php?id=' . (int) $p['id_profil']) ?>" class="btn btn-sm btn-brand d-inline-flex align-items-center gap-1 shadow-sm">
                                        <i class="bi bi-patch-check"></i>
                                        <span>Examiner</span>
                                    </a>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pied de carte et pagination (20 par page) -->
            <?php if ($totalPages > 1): ?>
                <div class="card-footer bg-white py-3 d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 border-top">
                    <span class="small text-secondary">
                        Affichage des dossiers <strong><?= $debutAffiche ?></strong> à <strong><?= $finAffiche ?></strong> sur un total de <strong><?= $totalProfils ?></strong>
                    </span>

                    <nav aria-label="Pagination des dossiers de validation">
                        <ul class="pagination pagination-sm mb-0">
                            
                            <!-- Précédent -->
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= url('admin/validation.php?' . build_url_params(['page' => $page - 1])) ?>" aria-label="Précédent">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>

                            <!-- Numéros de page -->
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <?php if ($totalPages > 7 && abs($i - $page) > 2 && $i !== 1 && $i !== $totalPages): ?>
                                    <?php if ($i === 2 || $i === $totalPages - 1): ?>
                                        <li class="page-item disabled"><span class="page-link">…</span></li>
                                    <?php endif; ?>
                                    <?php continue; ?>
                                <?php endif; ?>

                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= url('admin/validation.php?' . build_url_params(['page' => $i])) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <!-- Suivant -->
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= url('admin/validation.php?' . build_url_params(['page' => $page + 1])) ?>" aria-label="Suivant">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>

                        </ul>
                    </nav>
                </div>
            <?php endif; ?>

        </div>

    <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
