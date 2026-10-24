<?php
/**
 * Redirection vers la vue unifiée demande_detail.php — Espace Client FindPro
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($id && (int) $id > 0) {
    redirect(url('demande_detail.php?id=' . (int) $id));
}

redirect(url('client/demandes.php'));
