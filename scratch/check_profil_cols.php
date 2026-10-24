<?php
require_once __DIR__ . '/../includes/db.php';
$cols = array_column(db()->query('SHOW COLUMNS FROM profil_professionnel')->fetchAll(PDO::FETCH_ASSOC), 'Field');
print_r($cols);
