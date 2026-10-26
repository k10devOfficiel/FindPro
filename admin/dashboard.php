<?php
/**
 * Alias de compatibilité vers admin/index.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';

demarrer_session();
exiger_role('admin');

redirect(url('admin/index.php'));
