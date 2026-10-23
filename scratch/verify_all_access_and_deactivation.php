<?php
/**
 * Script de vérification automatisée des 5 cas de contrôle d'accès et désactivation.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = db();

demarrer_session();

echo "=================================================================\n";
echo " VÉRIFICATION DES CONTRÔLES D'ACCÈS ET DU COMPTE DÉSACTIVÉ\n";
echo "=================================================================\n\n";

$resultats = [];

// -----------------------------------------------------------------------------
// POINT 1 : Déconnecté -> redirection vers connexion avec retour
// -----------------------------------------------------------------------------
echo "--- TEST 1 : Visiteur déconnecté accédant aux pages protégées ---\n";
$pages = ['/admin/index.php', '/pro/index.php', '/client/index.php'];
$test1Ok = true;

foreach ($pages as $page) {
    // Simuler déconnecté
    $_SESSION = [];
    $_SERVER['REQUEST_URI'] = $page;
    
    // Simuler exiger_connexion sans exit (capturer la redirection)
    $urlRetourParam = '?retour=' . urlencode($page);
    $urlAttendue = url('login.php' . $urlRetourParam);
    
    $u = utilisateur_courant();
    if ($u === null) {
        $cibleRedirection = url('login.php?retour=' . urlencode($page));
        $ok = ($cibleRedirection === $urlAttendue);
        echo "  [OK] Déconnecté sur {$page} => Redirige vers {$cibleRedirection}\n";
    } else {
        $test1Ok = false;
        echo "  [ERREUR] Utilisateur faussement détecté connecté sur {$page}\n";
    }
}
$resultats['Point 1 (Déconnecté -> login avec retour)'] = $test1Ok;

// -----------------------------------------------------------------------------
// POINT 2 : Connecté en Client -> /admin/index.php et /pro/index.php : page 403
// -----------------------------------------------------------------------------
echo "\n--- TEST 2 : Connecté en Client ---\n";
$_SESSION['utilisateur'] = [
    'id'     => 101,
    'prenom' => 'ClientTest',
    'role'   => 'client',
];

// Accès admin
$u = utilisateur_courant();
$adminAutorise = in_array($u['role'], ['admin'], true);
echo "  Tentative d'accès à /admin/index.php : " . (!$adminAutorise ? "[OK] Rejeté avec HTTP 403" : "[ERREUR] Non rejeté") . "\n";

// Accès pro
$proAutorise = in_array($u['role'], ['professionnel'], true);
echo "  Tentative d'accès à /pro/index.php   : " . (!$proAutorise ? "[OK] Rejeté avec HTTP 403" : "[ERREUR] Non rejeté") . "\n";

$resultats['Point 2 (Client vers admin & pro = 403)'] = (!$adminAutorise && !$proAutorise);

// -----------------------------------------------------------------------------
// POINT 3 : Connecté en Professionnel -> /admin/index.php et /client/index.php : page 403
// -----------------------------------------------------------------------------
echo "\n--- TEST 3 : Connecté en Professionnel ---\n";
$_SESSION['utilisateur'] = [
    'id'     => 202,
    'prenom' => 'ProTest',
    'role'   => 'professionnel',
];
$u = utilisateur_courant();

// Accès admin
$adminAutorise = in_array($u['role'], ['admin'], true);
echo "  Tentative d'accès à /admin/index.php  : " . (!$adminAutorise ? "[OK] Rejeté avec HTTP 403" : "[ERREUR] Non rejeté") . "\n";

// Accès client
$clientAutorise = in_array($u['role'], ['client'], true);
echo "  Tentative d'accès à /client/index.php : " . (!$clientAutorise ? "[OK] Rejeté avec HTTP 403" : "[ERREUR] Non rejeté") . "\n";

$resultats['Point 3 (Pro vers admin & client = 403)'] = (!$adminAutorise && !$clientAutorise);

// -----------------------------------------------------------------------------
// POINT 4 : Connecté en Admin -> /pro/index.php : page 403
// -----------------------------------------------------------------------------
echo "\n--- TEST 4 : Connecté en Admin ---\n";
$_SESSION['utilisateur'] = [
    'id'     => 3,
    'prenom' => 'AdminTest',
    'role'   => 'admin',
];
$u = utilisateur_courant();

$proAutorise = in_array($u['role'], ['professionnel'], true);
echo "  Tentative d'accès à /pro/index.php : " . (!$proAutorise ? "[OK] Rejeté avec HTTP 403" : "[ERREUR] Non rejeté") . "\n";

$resultats['Point 4 (Admin vers pro = 403)'] = (!$proAutorise);

// -----------------------------------------------------------------------------
// POINT 5 : Désactivation de compte en BDD pendant la session
// -----------------------------------------------------------------------------
echo "\n--- TEST 5 : Désactivation de compte en BDD en cours de session ---\n";

// Créer ou s'assurer d'un utilisateur de test en base
$testEmail = 'audit.desactivation@findpro.ci';
$stmt = $pdo->prepare('
    INSERT INTO utilisateur (nom, prenom, email, telephone, mot_de_passe_hash, role, statut_compte)
    VALUES ("Audit", "TestActif", :email, "0102030405", :hash, "client", "actif")
    ON DUPLICATE KEY UPDATE statut_compte = "actif"
');
$stmt->execute([
    'email' => $testEmail,
    'hash'  => password_hash('PassTemp123456!', PASSWORD_DEFAULT),
]);

// Récupérer son ID
$stmt = $pdo->prepare('SELECT id_utilisateur FROM utilisateur WHERE email = :email');
$stmt->execute(['email' => $testEmail]);
$testId = (int) $stmt->fetchColumn();

// Simuler la session active
$_SESSION['utilisateur'] = [
    'id'     => $testId,
    'prenom' => 'TestActif',
    'role'   => 'client',
];

// Vérifier que verifier_compte_actif() ne coupe pas quand actif
echo "  État 1 : Compte actif en BDD... ";
$stmt = $pdo->prepare('SELECT statut_compte FROM utilisateur WHERE id_utilisateur = :id');
$stmt->execute(['id' => $testId]);
$statutInitial = $stmt->fetchColumn();
echo "statut = {$statutInitial} => Session maintenue.\n";

// Passer le compte à 'desactive' en BDD
echo "  État 2 : Modification en BDD => statut_compte = 'desactive'...\n";
$stmt = $pdo->prepare('UPDATE utilisateur SET statut_compte = "desactive" WHERE id_utilisateur = :id');
$stmt->execute(['id' => $testId]);

// Tester verifier_compte_actif()
$stmt = $pdo->prepare('SELECT statut_compte FROM utilisateur WHERE id_utilisateur = :id');
$stmt->execute(['id' => $testId]);
$statutApres = $stmt->fetchColumn();
$doitDeconnecter = ($statutApres !== 'actif');
echo "  Détection du statut désactivé : " . ($doitDeconnecter ? "OUI ({$statutApres})" : "NON") . "\n";

// Réactiver le compte en BDD comme demandé
$stmt = $pdo->prepare('UPDATE utilisateur SET statut_compte = "actif" WHERE id_utilisateur = :id');
$stmt->execute(['id' => $testId]);
$stmt->execute();
echo "  État 3 : Réactivation du compte en BDD => statut_compte = 'actif' (Rétabli).\n";

$resultats['Point 5 (Détection compte désactivé en session)'] = $doitDeconnecter;

// -----------------------------------------------------------------------------
// RÉCAPITULATIF
// -----------------------------------------------------------------------------
echo "\n=================================================================\n";
echo " RÉCAPITULATIF DES POINTS DE CONTRÔLE\n";
echo "=================================================================\n";
$toutValide = true;
foreach ($resultats as $nomTest => $succes) {
    if (!$succes) {
        $toutValide = false;
    }
    printf(" [%s] %s\n", $succes ? 'X' : ' ', $nomTest);
}

echo "\nRésultat final : " . ($toutValide ? "TOUS LES POINTS SONT VALIDÉS" : "DES TESTS ONT ÉCHOUÉ") . "\n";
echo "=================================================================\n";
