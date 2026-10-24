<?php
/**
 * Modération des signalements d'abus et litiges — FindPro.
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

$stmt = $pdo->query('
    SELECT 
        s.*,
        u.nom AS auteur_nom,
        u.prenom AS auteur_prenom,
        u.email AS auteur_email
    FROM signalement s
    JOIN utilisateur u ON s.id_auteur = u.id_utilisateur
    ORDER BY s.date_creation DESC
');
$signalements = $stmt->fetchAll();

$titre_page = 'Modération des signalements';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4 py-lg-5">
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <div>
            <h1 class="h3 fw-bold mb-1">Modération des signalements</h1>
            <p class="text-secondary small mb-0">Gestion des signalements d'abus, fraudes ou comportements suspects.</p>
        </div>
        <a href="<?= url('admin/index.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Tableau de bord
        </a>
    </div>

    <?php if (empty($signalements)): ?>
        <div class="card p-5 text-center shadow-sm border bg-light">
            <i class="bi bi-shield-check fs-1 text-success opacity-75 d-block mb-2"></i>
            <h2 class="h5 fw-bold mb-1">Aucun signalement en cours</h2>
            <p class="text-secondary small mb-0">Tous les signalements ont été résolus ou aucun abus n'a été signalé.</p>
        </div>
    <?php else: ?>
        <div class="card shadow-sm border">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-secondary">
                        <tr>
                            <th scope="col" class="ps-3">Signalé par</th>
                            <th scope="col">Type de cible</th>
                            <th scope="col">Motif</th>
                            <th scope="col">Statut</th>
                            <th scope="col">Date</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php foreach ($signalements as $s): ?>
                            <tr>
                                <td class="ps-3 py-3">
                                    <span class="fw-semibold text-dark d-block"><?= e($s['auteur_prenom'] . ' ' . $s['auteur_nom']) ?></span>
                                    <small class="text-secondary"><?= e($s['auteur_email']) ?></small>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= e($s['type_cible']) ?> (ID: <?= (int) $s['id_cible'] ?>)</span></td>
                                <td><span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25"><?= e($s['motif']) ?></span></td>
                                <td>
                                    <span class="badge <?= $s['statut'] === 'nouveau' ? 'bg-danger' : 'bg-secondary' ?>">
                                        <?= e($s['statut']) ?>
                                    </span>
                                </td>
                                <td class="text-secondary"><?= e(date('d/m/Y à H:i', strtotime($s['date_creation']))) ?></td>
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
