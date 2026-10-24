<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

// Get profile 65 (soumis) owner id
$p65 = $pdo->query("SELECT id_profil, id_utilisateur, titre FROM profil_professionnel WHERE id_profil = 65")->fetch(PDO::FETCH_ASSOC);
$ownerId = (int) $p65['id_utilisateur'];

// Get an admin id
$admin = $pdo->query("SELECT id_utilisateur FROM utilisateur WHERE role = 'admin' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$admin) {
    // Create an admin for test
    $pdo->exec("INSERT INTO utilisateur (email, mot_de_passe_hash, nom, prenom, role, statut_compte) VALUES ('tmp.admin@test.ci', 'hash', 'Admin', 'Test', 'admin', 'actif')");
    $adminId = (int) $pdo->lastInsertId();
} else {
    $adminId = (int) $admin['id_utilisateur'];
}

// Get a non-owner, non-admin user
$other = $pdo->query("SELECT id_utilisateur FROM utilisateur WHERE role != 'admin' AND id_utilisateur != $ownerId LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$otherId = (int) $other['id_utilisateur'];

function runCase(string $scriptCode): string {
    $tmpFile = __DIR__ . '/tmp_runner_' . bin2hex(random_bytes(4)) . '.php';
    file_put_contents($tmpFile, "<?php\n" . $scriptCode);
    $output = shell_exec('"C:\wamp64\bin\php\php8.2.29\php.exe" ' . escapeshellarg($tmpFile));
    unlink($tmpFile);
    return (string) $output;
}

echo "=== TEST DU BANDEAU APERÇU ET EXCEPTION (PROPRIÉTAIRE & ADMIN) ===\n\n";

// Case 1: Owner accessing their unapproved profile #65
$codeOwner = <<<PHP
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
demarrer_session();
\$_SESSION['utilisateur'] = [
    'id_utilisateur' => $ownerId,
    'nom' => 'Traore',
    'prenom' => 'Ibrahim',
    'role' => 'professionnel',
    'statut_compte' => 'actif'
];
\$_GET['id'] = '65';
ob_start();
require __DIR__ . '/../profile.php';
\$html = ob_get_clean();
echo "OWNER_SEES_TITLE: " . (str_contains(\$html, 'Développeur Fullstack PHP') ? 'OUI' : 'NON') . PHP_EOL;
echo "OWNER_SEES_PREVIEW_BANNER: " . (str_contains(\$html, 'Aperçu - non public') ? 'OUI' : 'NON') . PHP_EOL;
echo "OWNER_HTTP_CODE: " . http_response_code() . PHP_EOL;
PHP;
$outOwner = runCase($codeOwner);
foreach (explode("\n", $outOwner) as $l) {
    if (str_contains($l, 'OWNER_')) echo $l . "\n";
}

// Case 2: Admin accessing unapproved profile #65
$codeAdmin = <<<PHP
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
demarrer_session();
\$_SESSION['utilisateur'] = [
    'id_utilisateur' => $adminId,
    'nom' => 'Admin',
    'prenom' => 'Test',
    'role' => 'admin',
    'statut_compte' => 'actif'
];
\$_GET['id'] = '65';
ob_start();
require __DIR__ . '/../profile.php';
\$html = ob_get_clean();
echo "ADMIN_SEES_TITLE: " . (str_contains(\$html, 'Développeur Fullstack PHP') ? 'OUI' : 'NON') . PHP_EOL;
echo "ADMIN_SEES_PREVIEW_BANNER: " . (str_contains(\$html, 'Aperçu - non public') ? 'OUI' : 'NON') . PHP_EOL;
echo "ADMIN_HTTP_CODE: " . http_response_code() . PHP_EOL;
PHP;
$outAdmin = runCase($codeAdmin);
foreach (explode("\n", $outAdmin) as $l) {
    if (str_contains($l, 'ADMIN_')) echo $l . "\n";
}

// Case 3: Other user (neither owner nor admin) accessing unapproved profile #65 -> MUST GET 404
$codeOther = <<<PHP
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
demarrer_session();
\$_SESSION['utilisateur'] = [
    'id_utilisateur' => $otherId,
    'nom' => 'Autre',
    'prenom' => 'User',
    'role' => 'client',
    'statut_compte' => 'actif'
];
\$_GET['id'] = '65';
ob_start();
require __DIR__ . '/../profile.php';
\$html = ob_get_clean();
echo "OTHER_SEES_404: " . (str_contains(\$html, 'Profil introuvable') ? 'OUI' : 'NON') . PHP_EOL;
echo "OTHER_HTTP_CODE: " . http_response_code() . PHP_EOL;
PHP;
$outOther = runCase($codeOther);
$is404 = str_contains($outOther, 'Profil introuvable') || str_contains($outOther, 'Erreur 404');
echo "OTHER_SEES_404: " . ($is404 ? 'OUI' : 'NON') . PHP_EOL;
echo "OTHER_LEAKS_DATA: " . (str_contains($outOther, 'Traoré') ? 'OUI (BUG)' : 'NON (SECURISE)') . PHP_EOL;
