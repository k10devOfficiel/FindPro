<?php
/**
 * Gestion des comptes utilisateurs — FindPro.
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

$roleFiltre = (string) ($_GET['role'] ?? '');
if (!in_array($roleFiltre, ['client', 'professionnel', 'admin'], true)) {
    $roleFiltre = '';
}

$sql = '
    SELECT 
        u.id_utilisateur, u.nom, u.prenom, u.email, u.telephone, u.role, u.statut_compte, u.date_inscription,
        p.id_profil, p.statut_validation
    FROM utilisateur u
    LEFT JOIN profil_professionnel p ON u.id_utilisateur = p.id_utilisateur
';
$params = [];
if ($roleFiltre !== '') {
    $sql .= ' WHERE u.role = :role';
    $params['role'] = $roleFiltre;
}
$sql .= ' ORDER BY u.date_inscription DESC, u.id_utilisateur DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$utilisateurs = $stmt->fetchAll();

$titre_page = 'Gestion des utilisateurs';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4 py-lg-5">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4 pb-2 border-bottom">
        <div>
            <h1 class="h3 fw-bold mb-1">Comptes et Utilisateurs</h1>
            <p class="text-secondary small mb-0">Gestion du registre des membres inscrits sur FindPro.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= url('admin/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Tableau de bord
            </a>
        </div>
    </div>

    <!-- Filtres rapides -->
    <div class="d-flex gap-2 mb-3">
        <a href="<?= url('admin/utilisateurs.php') ?>" class="btn btn-sm <?= $roleFiltre === '' ? 'btn-primary' : 'btn-outline-secondary' ?>">
            Tous (<?= count($utilisateurs) ?>)
        </a>
        <a href="<?= url('admin/utilisateurs.php?role=client') ?>" class="btn btn-sm <?= $roleFiltre === 'client' ? 'btn-primary' : 'btn-outline-secondary' ?>">
            Clients
        </a>
        <a href="<?= url('admin/utilisateurs.php?role=professionnel') ?>" class="btn btn-sm <?= $roleFiltre === 'professionnel' ? 'btn-primary' : 'btn-outline-secondary' ?>">
            Professionnels
        </a>
    </div>

    <div class="card shadow-sm border">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-secondary">
                    <tr>
                        <th scope="col" class="ps-3">Nom &amp; Prénom</th>
                        <th scope="col">Email</th>
                        <th scope="col">Rôle</th>
                        <th scope="col">Statut</th>
                        <th scope="col">Date d'inscription</th>
                        <th scope="col" class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody class="small">
                    <?php foreach ($utilisateurs as $u): ?>
                        <tr>
                            <td class="ps-3 py-3 fw-semibold text-dark">
                                <?= e($u['prenom'] . ' ' . $u['nom']) ?>
                            </td>
                            <td><?= e($u['email']) ?></td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?= e($u['role']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $u['statut_compte'] === 'actif' ? 'bg-success' : 'bg-danger' ?>">
                                    <?= e($u['statut_compte']) ?>
                                </span>
                            </td>
                            <td class="text-secondary"><?= e(date('d/m/Y', strtotime($u['date_inscription']))) ?></td>
                            <td class="text-end pe-3">
                                <?php if ($u['role'] === 'professionnel' && !empty($u['id_profil'])): ?>
                                    <a href="<?= url('admin/profil_detail.php?id=' . (int) $u['id_profil']) ?>" class="btn btn-sm <?= $u['statut_validation'] === 'suspendu' ? 'btn-danger' : ($u['statut_validation'] === 'approuve' ? 'btn-outline-primary' : 'btn-brand') ?> py-1 px-2.5">
                                        <i class="bi bi-shield-check me-1"></i>Arbitrage (<?= e($u['statut_validation']) ?>)
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
