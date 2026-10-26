<?php
/**
 * Script de préparation des comptes de test pour FindPro.
 * Initialise des comptes de démonstration pour chaque rôle.
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Accès interdit : ce script s'exécute uniquement en ligne de commande (CLI).\n");
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

echo "=======================================================\n";
echo "        COMPTES DE TEST FINDPRO PRÊTS À L'EMPLOI       \n";
echo "=======================================================\n\n";

$comptes = [
    [
        'nom'      => 'Kouame',
        'prenom'   => 'Dieudonne',
        'email'    => 'k10developpeur@gmail.com',
        'role'     => 'admin',
        'mdp'      => 'Dieudonne@2006',
    ],
    [
        'nom'      => 'Traoré',
        'prenom'   => 'Ibrahim',
        'email'    => 'pro@findpro.ci',
        'role'     => 'professionnel',
        'mdp'      => 'Pro123456789!',
    ],
    [
        'nom'      => 'Konan',
        'prenom'   => 'Aya',
        'email'    => 'client@findpro.ci',
        'role'     => 'client',
        'mdp'      => 'Client123456789!',
    ],
];

foreach ($comptes as $c) {
    $hash = password_hash($c['mdp'], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('
        INSERT INTO utilisateur (nom, prenom, email, telephone, mot_de_passe_hash, role, statut_compte)
        VALUES (:nom, :prenom, :email, "0708091011", :hash, :role, "actif")
        ON DUPLICATE KEY UPDATE mot_de_passe_hash = :hash_up, statut_compte = "actif", role = :role_up
    ');
    $stmt->execute([
        'nom'     => $c['nom'],
        'prenom'  => $c['prenom'],
        'email'   => $c['email'],
        'hash'    => $hash,
        'role'    => $c['role'],
        'hash_up' => $hash,
        'role_up' => $c['role'],
    ]);

    echo " [✓] Compte " . strtoupper($c['role']) . " :\n";
    echo "     - Email        : {$c['email']}\n";
    echo "     - Mot de passe : {$c['mdp']}\n\n";
}

echo "Vous pouvez vous connecter directement sur :\n";
echo "http://localhost/findpro/login.php\n";
echo "=======================================================\n";
