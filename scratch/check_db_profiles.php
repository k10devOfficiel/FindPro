<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = db();
$profils = $pdo->query("
    SELECT 
        p.id_profil,
        p.id_utilisateur,
        u.nom,
        u.prenom,
        u.email,
        p.titre,
        p.statut_validation,
        p.date_soumission,
        c.libelle AS categorie
    FROM profil_professionnel p
    JOIN utilisateur u ON p.id_utilisateur = u.id_utilisateur
    LEFT JOIN categorie c ON p.id_categorie = c.id_categorie
")->fetchAll(PDO::FETCH_ASSOC);

$users = $pdo->query("SELECT id_utilisateur, prenom, nom, email, role, statut_compte FROM utilisateur")->fetchAll(PDO::FETCH_ASSOC);
echo "Nombre total d'utilisateurs : " . count($users) . "\n";
foreach ($users as $u) {
    echo "ID: {$u['id_utilisateur']} | {$u['prenom']} {$u['nom']} ({$u['email']}) | Role: {$u['role']} | Statut: {$u['statut_compte']}\n";
}

