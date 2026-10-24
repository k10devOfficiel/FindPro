<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = db();
foreach (['profil_professionnel', 'utilisateur', 'projet_portfolio', 'profil_competence', 'competence', 'avis', 'demande_service'] as $tbl) {
    echo "=== $tbl ===\n";
    $cols = $pdo->query("SHOW COLUMNS FROM $tbl")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo "  {$c['Field']} ({$c['Type']})\n";
    }
}
