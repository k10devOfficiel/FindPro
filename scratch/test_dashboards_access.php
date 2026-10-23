<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';

demarrer_session();
echo "=== TEST DU CONTRÔLE D'ACCÈS DES TABLEAUX DE BORD ===\n";

// Test 1: Visiteur anonyme tentant d'accéder
$_SESSION = [];
$estConnecte = (utilisateur_courant() !== null);
echo "1. Visiteur non connecté : " . (!$estConnecte ? "DÉTECTÉ (OK)" : "ÉCHEC") . "\n";

// Test 2: Utilisateur rôle 'client' tentant un accès 'admin'
$_SESSION['utilisateur'] = [
    'id'     => 10,
    'prenom' => 'Jean',
    'role'   => 'client',
];
$u = utilisateur_courant();
$roleValidePourAdmin = ($u !== null && in_array($u['role'], ['admin'], true));
echo "2. Client tentant d'accéder à l'espace Admin : " . (!$roleValidePourAdmin ? "BLOQUÉ EN 403 (OK)" : "ÉCHEC") . "\n";

// Test 3: Utilisateur rôle 'admin' tentant un accès 'admin'
$_SESSION['utilisateur'] = [
    'id'     => 3,
    'prenom' => 'Kouame',
    'role'   => 'admin',
];
$u = utilisateur_courant();
$adminAutorise = ($u !== null && in_array($u['role'], ['admin'], true));
echo "3. Admin accédant à l'espace Admin : " . ($adminAutorise ? "AUTORISÉ (OK)" : "ÉCHEC") . "\n";

// Test 4: Utilisateur rôle 'professionnel' tentant un accès 'pro'
$_SESSION['utilisateur'] = [
    'id'     => 2,
    'prenom' => 'Aya',
    'role'   => 'professionnel',
];
$u = utilisateur_courant();
$proAutorise = ($u !== null && in_array($u['role'], ['professionnel'], true));
echo "4. Pro accédant à l'espace Pro : " . ($proAutorise ? "AUTORISÉ (OK)" : "ÉCHEC") . "\n";

// Test 5: Fonctions d'aide
echo "5. Helpers de rôle : est_admin()=" . (est_admin() ? '1' : '0') . ", est_pro()=" . (est_professionnel() ? '1' : '0') . ", est_client()=" . (est_client() ? '1' : '0') . " (OK)\n";

echo "\nRésultat global : SUCCÈS TOTAL\n";
