<?php
/**
 * Modération et validation des profils professionnels — FindPro.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';

demarrer_session();
$utilisateur = exiger_role('admin');

$pdo = db();

// Récupération de tous les profils soumis ou en cours de vérification
$stmtProfils = $pdo->query('
    SELECT 
        p.*,
        u.nom,
        u.prenom,
        u.email,
        u.telephone,
        c.libelle AS categorie_libelle,
        c.slug AS categorie_slug
    FROM profil_professionnel p
    JOIN utilisateur u ON p.id_utilisateur = u.id_utilisateur
    LEFT JOIN categorie c ON p.id_categorie = c.id_categorie
    WHERE p.statut_validation IN (\'soumis\', \'en_verification\')
    ORDER BY p.date_soumission DESC, p.id_profil DESC
');
$profilsEnAttente = $stmtProfils->fetchAll();

$titre_page = 'Validation des profils professionnels';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4 py-lg-5">
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <div>
            <h1 class="h3 fw-bold mb-1">Validation des profils professionnels</h1>
            <p class="text-secondary small mb-0">File d'arbitrage administratif des candidatures de prestataires.</p>
        </div>
        <a href="<?= url('admin/index.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Retour au tableau de bord
        </a>
    </div>

    <?php if (empty($profilsEnAttente)): ?>
        <div class="card p-5 text-center shadow-sm border bg-light">
            <i class="bi bi-check2-circle fs-1 text-success opacity-75 d-block mb-2"></i>
            <h2 class="h5 fw-bold mb-1">File de validation vide</h2>
            <p class="text-secondary small mb-0">Tous les dossiers soumis ont été traités ou aucun profil n'est en attente.</p>
        </div>
    <?php else: ?>
        <div class="card shadow-sm border">
            <div class="card-header bg-white py-3 border-bottom">
                <span class="fw-semibold small text-uppercase text-secondary">
                    Dossiers en attente (<?= count($profilsEnAttente) ?>)
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-secondary">
                        <tr>
                            <th scope="col" class="ps-3">Professionnel</th>
                            <th scope="col">Métier</th>
                            <th scope="col">Titre</th>
                            <th scope="col">Date de soumission</th>
                            <th scope="col">Statut</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php foreach ($profilsEnAttente as $p): ?>
                            <tr>
                                <td class="ps-3 py-3">
                                    <span class="fw-semibold text-dark d-block"><?= e($p['prenom'] . ' ' . $p['nom']) ?></span>
                                    <small class="text-secondary"><?= e($p['email']) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= e($p['categorie_libelle'] ?? 'Non défini') ?></span>
                                    <?php if ($p['categorie_slug'] === 'cybersecurite'): ?>
                                        <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-50 small ms-1">
                                            <i class="bi bi-shield-check text-warning me-0.5"></i>Cyber
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($p['titre']) ?></td>
                                <td><?= !empty($p['date_soumission']) ? e(date('d/m/Y à H:i', strtotime($p['date_soumission']))) : 'N/C' ?></td>
                                <td>
                                    <span class="badge bg-info text-dark">
                                        <i class="bi bi-hourglass-split me-1"></i><?= e($p['statut_validation']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
