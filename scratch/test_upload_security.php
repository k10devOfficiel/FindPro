<?php
/**
 * Test unitaire et de sécurité du module d'upload (includes/upload.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/upload.php';

echo "=================================================================\n";
echo " VÉRIFICATION DU MODULE D'UPLOAD ET DE LA SÉCURITÉ\n";
echo "=================================================================\n\n";

$resultats = [];

// -----------------------------------------------------------------------------
// POINT 1 : Vérification de l'existence de uploads/.htaccess
// -----------------------------------------------------------------------------
echo "--- 1. TEST : Vérification de l'existence de uploads/.htaccess ---\n";
$htaccessPath = __DIR__ . '/../uploads/.htaccess';
$htaccessExiste = file_exists($htaccessPath);
$htaccessTaille = $htaccessExiste ? filesize($htaccessPath) : 0;
$contenu = $htaccessExiste ? (string) file_get_contents($htaccessPath) : '';
$contientSecurite = str_contains($contenu, 'engine off') && str_contains($contenu, 'Require all denied');

$p1Ok = ($htaccessExiste && $htaccessTaille > 0 && $contientSecurite);
echo "Fichier uploads/.htaccess présent : " . ($htaccessExiste ? "OUI ({$htaccessTaille} octets)" : "NON") . "\n";
echo "Directives de sécurité PHP désactivé & liste blanche : " . ($contientSecurite ? "OUI" : "NON") . "\n";
echo "Résultat Point 1 : " . ($p1Ok ? "[SUCCÈS]" : "[ÉCHEC]") . "\n\n";
$resultats['uploads/.htaccess existe et est configuré'] = $p1Ok;

// -----------------------------------------------------------------------------
// POINT 2 : Un fichier .php renommé en .jpg est refusé
// -----------------------------------------------------------------------------
echo "--- 2. TEST : Un fichier .php renommé en .jpg est refusé ---\n";
$fakeJpg = tempnam(sys_get_temp_dir(), 'fake_') . '.jpg';
file_put_contents($fakeJpg, "<?php echo 'Attaque de test par fichier php renomme en jpg'; ?>");

$refuseFauxJpg = false;
$messageErreurFauxJpg = '';

try {
    $fichierSimule = [
        'name'     => 'photo_vacances.jpg',
        'type'     => 'image/jpeg', // Faux en-tête envoyé par un navigateur pirate
        'tmp_name' => $fakeJpg,
        'error'    => UPLOAD_ERR_OK,
        'size'     => filesize($fakeJpg),
    ];

    televerser_image($fichierSimule, 'avatars');
} catch (RuntimeException $e) {
    $refuseFauxJpg = true;
    $messageErreurFauxJpg = $e->getMessage();
} finally {
    if (file_exists($fakeJpg)) {
        @unlink($fakeJpg);
    }
}

echo "Détection de la fausse image par finfo/magic bytes : " . ($refuseFauxJpg ? "OUI (Bloqué)" : "NON (Vulnérabilité !)") . "\n";
echo "Message retourné à l'utilisateur : \"{$messageErreurFauxJpg}\"\n";
$p2Ok = $refuseFauxJpg;
echo "Résultat Point 2 : " . ($p2Ok ? "[SUCCÈS]" : "[ÉCHEC]") . "\n\n";
$resultats['Un fichier .php renommé en .jpg est refusé'] = $p2Ok;

// -----------------------------------------------------------------------------
// POINT 3 : Un fichier de 5 Mo est refusé (limite 2 Mo)
// -----------------------------------------------------------------------------
echo "--- 3. TEST : Un fichier de 5 Mo est refusé ---\n";
$bigFile = tempnam(sys_get_temp_dir(), 'big_');
// Générer un fichier factice de 5 Mo (5 * 1024 * 1024 octets)
$taille5Mo = 5 * 1024 * 1024;
$fp = fopen($bigFile, 'wb');
fseek($fp, $taille5Mo - 1);
fwrite($fp, "\0");
fclose($fp);

$refuse5Mo = false;
$messageErreur5Mo = '';

try {
    $fichierGros = [
        'name'     => 'gros_fichier.png',
        'type'     => 'image/png',
        'tmp_name' => $bigFile,
        'error'    => UPLOAD_ERR_OK,
        'size'     => filesize($bigFile),
    ];

    televerser_image($fichierGros, 'avatars');
} catch (RuntimeException $e) {
    $refuse5Mo = true;
    $messageErreur5Mo = $e->getMessage();
} finally {
    if (file_exists($bigFile)) {
        @unlink($bigFile);
    }
}

echo "Contrôle de taille sur fichier de 5 Mo : " . ($refuse5Mo ? "OUI (Refusé)" : "NON (Accepté à tort)") . "\n";
echo "Message retourné à l'utilisateur : \"{$messageErreur5Mo}\"\n";
$p3Ok = $refuse5Mo;
echo "Résultat Point 3 : " . ($p3Ok ? "[SUCCÈS]" : "[ÉCHEC]") . "\n\n";
$resultats['Un fichier de 5 Mo est refusé'] = $p3Ok;

// -----------------------------------------------------------------------------
// POINT 4 : Test positif avec une vraie image valide (PNG 200x200)
// -----------------------------------------------------------------------------
echo "--- 4. TEST BONUS : Téléversement réussi d'une vraie image PNG valide ---\n";
$vraiPng = tempnam(sys_get_temp_dir(), 'valid_') . '.png';

// Créer une vraie image avec GD si disponible ou générer les octets PNG minimaux
if (function_exists('imagecreatetruecolor')) {
    $img = imagecreatetruecolor(200, 200);
    $couleur = imagecolorallocate($img, 225, 29, 46); // Rouge FindPro
    imagefill($img, 0, 0, $couleur);
    imagepng($img, $vraiPng);
    imagedestroy($img);
} else {
    // 1x1 pixel PNG minimal
    file_put_contents($vraiPng, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
}

$cheminEnregistre = null;
try {
    $fichierValide = [
        'name'     => 'mon_avatar.png',
        'type'     => 'image/png',
        'tmp_name' => $vraiPng,
        'error'    => UPLOAD_ERR_OK,
        'size'     => filesize($vraiPng),
    ];

    $cheminEnregistre = televerser_image($fichierValide, 'avatars');
    echo "Fichier enregistré avec succès sous : {$cheminEnregistre}\n";
    echo "Existence du fichier final : " . (file_exists(__DIR__ . '/../' . $cheminEnregistre) ? "OUI" : "NON") . "\n";
} catch (Exception $e) {
    echo "Erreur inattendue : " . $e->getMessage() . "\n";
} finally {
    if (file_exists($vraiPng)) {
        @unlink($vraiPng);
    }
    if ($cheminEnregistre !== null && file_exists(__DIR__ . '/../' . $cheminEnregistre)) {
        @unlink(__DIR__ . '/../' . $cheminEnregistre); // Nettoyage de test
    }
}
$p4Ok = ($cheminEnregistre !== null);
echo "Résultat Test Bonus : " . ($p4Ok ? "[SUCCÈS]" : "[ÉCHEC]") . "\n\n";

// -----------------------------------------------------------------------------
// RÉCAPITULATIF
// -----------------------------------------------------------------------------
echo "=================================================================\n";
echo " RÉCAPITULATIF DES POINTS DE CONTRÔLE UPLOAD\n";
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
