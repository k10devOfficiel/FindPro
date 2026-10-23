<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = db();
$cols = array_column($pdo->query('SHOW COLUMNS FROM projet_portfolio')->fetchAll(PDO::FETCH_ASSOC), 'Field');

if (!in_array('lien_externe', $cols, true)) {
    $pdo->exec('ALTER TABLE projet_portfolio ADD COLUMN lien_externe VARCHAR(500) NULL AFTER description');
    echo "Added lien_externe\n";
}
if (!in_array('image', $cols, true)) {
    $pdo->exec('ALTER TABLE projet_portfolio ADD COLUMN image VARCHAR(255) NULL AFTER lien_externe');
    echo "Added image\n";
}
if (!in_array('date_projet', $cols, true)) {
    $pdo->exec('ALTER TABLE projet_portfolio ADD COLUMN date_projet DATE NULL AFTER image');
    echo "Added date_projet\n";
}

$pdo->exec('ALTER TABLE projet_portfolio MODIFY COLUMN url_media VARCHAR(500) NULL DEFAULT NULL');
$pdo->exec('ALTER TABLE projet_portfolio MODIFY COLUMN type_media ENUM(\'lien\', \'image\') NULL DEFAULT \'image\'');

echo "Table projet_portfolio updated successfully.\n";
