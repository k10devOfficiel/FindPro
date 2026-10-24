<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = db();
$cols = array_column($pdo->query('SHOW COLUMNS FROM profil_professionnel')->fetchAll(PDO::FETCH_ASSOC), 'Field');

if (!in_array('engagement_cyber', $cols, true)) {
    $pdo->exec('ALTER TABLE profil_professionnel ADD COLUMN engagement_cyber TINYINT(1) NOT NULL DEFAULT 0 AFTER statut_validation');
    echo "Added engagement_cyber\n";
}

if (!in_array('date_engagement_cyber', $cols, true)) {
    $pdo->exec('ALTER TABLE profil_professionnel ADD COLUMN date_engagement_cyber DATETIME NULL AFTER engagement_cyber');
    echo "Added date_engagement_cyber\n";
}

echo "profil_professionnel schema update OK\n";
