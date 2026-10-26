<?php
/**
 * Redirection / alias de commodité vers admin/validation.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';

demarrer_session();
exiger_role('admin');

$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
redirect(url('admin/validation.php' . $query));
