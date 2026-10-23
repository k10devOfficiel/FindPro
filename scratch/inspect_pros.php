<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = db();
$users = $pdo->query('SELECT id_utilisateur, email, role, statut_compte FROM utilisateur')->fetchAll(PDO::FETCH_ASSOC);
print_r($users);

$pros = $pdo->query('SELECT * FROM profil_professionnel')->fetchAll(PDO::FETCH_ASSOC);
print_r($pros);
