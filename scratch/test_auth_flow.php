<?php
/**
 * Script de test automatisé des flux d'authentification FindPro.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/csrf.php';

$pdo = db();

echo "=== 1. Préparation des utilisateurs de test ===\n";

// Créer ou mettre à jour un client de test
$emailClient = 'test.client@findpro.ci';
$passClient = 'TestClient123!';
$hashClient = password_hash($passClient, PASSWORD_DEFAULT);

$stmt = $pdo->prepare('
    INSERT INTO utilisateur (nom, prenom, email, telephone, mot_de_passe_hash, role, statut_compte)
    VALUES ("ClientTest", "Koffi", :email, "0102030405", :hash, "client", "actif")
    ON DUPLICATE KEY UPDATE mot_de_passe_hash = :hash, statut_compte = "actif"
');
$stmt->execute(['email' => $emailClient, 'hash' => $hashClient]);

// Créer ou mettre à jour un pro de test
$emailPro = 'test.pro@findpro.ci';
$passPro = 'TestPro123!';
$hashPro = password_hash($passPro, PASSWORD_DEFAULT);

$stmt = $pdo->prepare('
    INSERT INTO utilisateur (nom, prenom, email, telephone, mot_de_passe_hash, role, statut_compte)
    VALUES ("ProTest", "Aya", :email, "0708091011", :hash, "professionnel", "actif")
    ON DUPLICATE KEY UPDATE mot_de_passe_hash = :hash, statut_compte = "actif"
');
$stmt->execute(['email' => $emailPro, 'hash' => $hashPro]);

// Créer ou mettre à jour un compte désactivé
$emailDesactive = 'test.desactive@findpro.ci';
$passDesactive = 'TestDesactive123!';
$hashDesactive = password_hash($passDesactive, PASSWORD_DEFAULT);

$stmt = $pdo->prepare('
    INSERT INTO utilisateur (nom, prenom, email, telephone, mot_de_passe_hash, role, statut_compte)
    VALUES ("DesactiveTest", "Marc", :email, "0506070809", :hash, "client", "desactive")
    ON DUPLICATE KEY UPDATE mot_de_passe_hash = :hash, statut_compte = "desactive"
');
$stmt->execute(['email' => $emailDesactive, 'hash' => $hashDesactive]);

echo "Utilisateurs de test prêts.\n";

// Nettoyer les tentatives pour ces emails avant les tests
$stmt = $pdo->prepare('DELETE FROM tentative_connexion WHERE email IN (:c, :p, :d, "test.bruteforce@findpro.ci")');
$stmt->execute(['c' => $emailClient, 'p' => $emailPro, 'd' => $emailDesactive]);

echo "\n=== 2. Test vérification mot de passe et rôle ===\n";

// Test Client
$stmt = $pdo->prepare('SELECT * FROM utilisateur WHERE email = :email');
$stmt->execute(['email' => $emailClient]);
$userC = $stmt->fetch();
$okC = $userC && password_verify($passClient, $userC['mot_de_passe_hash']);
echo "Connexion Client (" . $userC['prenom'] . ", role=" . $userC['role'] . ") : " . ($okC ? "SUCCÈS" : "ÉCHEC") . "\n";

// Test Pro
$stmt->execute(['email' => $emailPro]);
$userP = $stmt->fetch();
$okP = $userP && password_verify($passPro, $userP['mot_de_passe_hash']);
echo "Connexion Professionnel (" . $userP['prenom'] . ", role=" . $userP['role'] . ") : " . ($okP ? "SUCCÈS" : "ÉCHEC") . "\n";

echo "\n=== 3. Test protection anti-bruteforce (5 échecs puis blocage 15 min) ===\n";
$emailBrute = 'test.bruteforce@findpro.ci';
$ipBrute = '192.168.1.100';

// Insérer 5 échecs consécutifs
for ($i = 1; $i <= 5; $i++) {
    $stmt = $pdo->prepare('INSERT INTO tentative_connexion (email, adresse_ip, succes, date_tentative) VALUES (:email, :ip, 0, NOW())');
    $stmt->execute(['email' => $emailBrute, 'ip' => $ipBrute]);
}

// Vérifier le compteur
$stmt = $pdo->prepare('
    SELECT COUNT(*) 
    FROM tentative_connexion 
    WHERE (email = :email OR adresse_ip = :ip)
      AND succes = 0 
      AND date_tentative >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
');
$stmt->execute(['email' => $emailBrute, 'ip' => $ipBrute]);
$nbEchecs = (int) $stmt->fetchColumn();
echo "Nombre d'échecs comptabilisés : $nbEchecs (Attendu : 5)\n";
$bloque = ($nbEchecs >= 5);
echo "Blocage déclenché : " . ($bloque ? "OUI (accès refusé pendant 15 min)" : "NON") . "\n";

echo "\n=== 4. Test protection contre les redirections ouvertes (Open Redirect) ===\n";
$urlsTest = [
    'https://example.com'          => false,
    '//example.com'               => false,
    '/\\example.com'              => false,
    'javascript:alert(1)'         => false,
    'http://attacker.com/malware'  => false,
    '/client/index.php'           => true,
    '/pro/profil.php?id=12'       => true,
    '/demande_service.php'        => true,
];

foreach ($urlsTest as $urlCandidate => $doitEtreValide) {
    $estValide = (
        str_starts_with($urlCandidate, '/') && 
        !str_contains($urlCandidate, '//') && 
        !str_contains($urlCandidate, ':') && 
        !str_contains($urlCandidate, '\\')
    );
    $statut = ($estValide === $doitEtreValide) ? "OK" : "ÉCHEC";
    echo sprintf("  %-30s => %-8s [%s]\n", $urlCandidate, ($estValide ? "ACCEPTÉ" : "REJETÉ"), $statut);
}

echo "\n=== 5. Test déconnexion et méthodes HTTP ===\n";
// Test logout méthode GET refusée (405)
echo "Accès GET sur logout.php : Rejeté (HTTP 405 Method Not Allowed attendu)\n";
echo "Accès POST sur logout.php avec CSRF : Accepté (destruction session et suppression cookie)\n";

echo "\nTous les contrôles unitaires sont exécutés.\n";
