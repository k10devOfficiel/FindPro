<?php
require_once __DIR__ . '/../includes/db.php';
try {
    $cols = db()->query("DESCRIBE projet_portfolio")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo $c['Field'] . ' | ' . $c['Type'] . ' | ' . $c['Null'] . ' | ' . ($c['Default'] ?? 'NULL') . PHP_EOL;
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}
