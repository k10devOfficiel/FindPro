<?php
/**
 * Suivi et supervision des demandes de service — FindPro.
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
        d.*,
        c.nom AS client_nom,
        c.prenom AS client_prenom,
        c.email AS client_email,
        cat.libelle AS categorie_libelle
    FROM demande_service d
    JOIN utilisateur c ON d.id_client = c.id_utilisateur
    LEFT JOIN categorie cat ON d.id_categorie = cat.id_categorie
    ORDER BY d.date_creation DESC
');
$demandes = $stmt->fetchAll();

$titre_page = 'Demandes de service';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4 py-lg-5">
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <div>
            <h1 class="h3 fw-bold mb-1">Demandes de service</h1>
            <p class="text-secondary small mb-0">Supervision de l'ensemble des prestations sollicitées sur la plateforme.</p>
        </div>
        <a href="<?= url('admin/index.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Tableau de bord
        </a>
    </div>

    <?php if (empty($demandes)): ?>
        <div class="card p-5 text-center shadow-sm border bg-light">
            <i class="bi bi-chat-square-text fs-1 text-secondary opacity-50 d-block mb-2"></i>
            <h2 class="h5 fw-bold mb-1">Aucune demande enregistrée</h2>
            <p class="text-secondary small mb-0">Les demandes de prestations créées par les clients apparaîtront ici.</p>
        </div>
    <?php else: ?>
        <div class="card shadow-sm border">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-secondary">
                        <tr>
                            <th scope="col" class="ps-3">Titre de la demande</th>
                            <th scope="col">Client</th>
                            <th scope="col">Catégorie</th>
                            <th scope="col">Statut</th>
                            <th scope="col">Date de création</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php foreach ($demandes as $d): ?>
                            <tr>
                                <td class="ps-3 py-3 fw-semibold text-dark"><?= e($d['titre']) ?></td>
                                <td><?= e($d['client_prenom'] . ' ' . $d['client_nom']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= e($d['categorie_libelle'] ?? 'Non assigné') ?></span></td>
                                <td><span class="badge bg-info text-dark"><?= e($d['statut']) ?></span></td>
                                <td class="text-secondary"><?= e(date('d/m/Y', strtotime($d['date_creation']))) ?></td>
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
