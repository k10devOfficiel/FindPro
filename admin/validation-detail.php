<?php
/**
 * Alias de redirection vers admin/profil_detail.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
redirect(url('admin/profil_detail.php' . $query));
