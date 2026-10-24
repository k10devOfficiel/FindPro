<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db();

echo "Test 1: profils_publics with invalid tri:\n";
try {
    $res = profils_publics($pdo, ['tri' => 'INVALIDE_HACK', 'q' => 'test']);
    echo "OK (count: " . count($res) . ")\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "Test 2: profils_publics with %:\n";
try {
    $res = profils_publics($pdo, ['q' => '%']);
    echo "OK (count: " . count($res) . ")\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "Test 3: profils_publics with _:\n";
try {
    $res = profils_publics($pdo, ['q' => '_']);
    echo "OK (count: " . count($res) . ")\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "Test 4: compter_profils_publics with % and _:\n";
try {
    $count = compter_profils_publics($pdo, ['q' => '%']);
    echo "OK % (count: " . $count . ")\n";
    $count2 = compter_profils_publics($pdo, ['q' => '_']);
    echo "OK _ (count: " . $count2 . ")\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
