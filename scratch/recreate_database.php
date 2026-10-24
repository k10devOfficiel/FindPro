<?php
/**
 * Recréation complète et propre de la base de données findpro
 * à partir de sql/schema.sql et sql/seed.sql, puis réinitialisation des comptes.
 */

declare(strict_types=1);

$config = require __DIR__ . '/../config/config.local.php';

$host = $config['db_host'] ?? 'localhost';
$user = $config['db_user'] ?? 'root';
$pass = $config['db_pass'] ?? '';

echo "1. Connexion au serveur MySQL ({$host})...\n";
$pdo = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

echo "2. Exécution de sql/schema.sql...\n";
$schemaSql = file_get_contents(__DIR__ . '/../sql/schema.sql');
$pdo->exec($schemaSql);
echo "   [✓] Schéma SQL recréé avec succès.\n";

echo "3. Exécution de sql/seed.sql...\n";
$seedSql = file_get_contents(__DIR__ . '/../sql/seed.sql');
$pdo->exec($seedSql);
echo "   [✓] Données de référence (catégories, compétences) insérées avec succès.\n";

echo "4. Initialisation des comptes de test...\n";
require __DIR__ . '/../scripts/preparer_tests.php';
echo "   [✓] Comptes réinitialisés avec succès.\n";

echo "\nBase de données findpro recréée et prête !\n";
