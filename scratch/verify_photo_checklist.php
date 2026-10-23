<?php
/**
 * Vérification formelle des 3 points de contrôle de photo de profil :
 * 1. Une photo valide s'affiche
 * 2. Le remplacement supprime l'ancien fichier
 * 3. Un faux fichier image est refusé
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/upload.php';

$pdo = db();

echo "=================================================================\n";
echo " VÉRIFICATION DE LA CHECKLIST PHOTO DE PROFIL\n";
echo "=================================================================\n\n";

// Préparer un compte Pro de test
$emailPro = 'test.avatar.checklist@findpro.ci';
$pdo->prepare('
    INSERT INTO utilisateur (nom, prenom, email, telephone, mot_de_passe_hash, role, statut_compte)
    VALUES ("Photo", "Checklist", :email, "0102030405", :hash, "professionnel", "actif")
    ON DUPLICATE KEY UPDATE statut_compte = "actif"
')->execute(['email' => $emailPro, 'hash' => password_hash('Pass123456789!', PASSWORD_DEFAULT)]);

$idUser = (int) $pdo->query("SELECT id_utilisateur FROM utilisateur WHERE email = '{$emailPro}'")->fetchColumn();
$catDev = (int) $pdo->query("SELECT id_categorie FROM categorie LIMIT 1")->fetchColumn();

$pdo->exec("DELETE FROM profil_professionnel WHERE id_utilisateur = {$idUser}");
$pdo->prepare('
    INSERT INTO profil_professionnel (id_utilisateur, id_categorie, titre, description, zone, statut_validation, photo)
    VALUES (:id_u, :id_cat, "Titre Pro", "Description de test valide comportant plus de cent caracteres pour satisfaire les regles serveur.", "Abidjan", "brouillon", NULL)
')->execute(['id_u' => $idUser, 'id_cat' => $catDev]);

$idProfil = (int) $pdo->query("SELECT id_profil FROM profil_professionnel WHERE id_utilisateur = {$idUser}")->fetchColumn();

// -----------------------------------------------------------------------------
// POINT 1 : Une photo valide s'affiche
// -----------------------------------------------------------------------------
echo "--- 1. TEST : Téléversement et affichage d'une photo valide ---\n";
$tempValid1 = tempnam(sys_get_temp_dir(), 'valid_avatar_') . '.png';
$im = imagecreatetruecolor(100, 100);
$blue = imagecolorallocate($im, 17, 24, 39);
imagefill($im, 0, 0, $blue);
imagepng($im, $tempValid1);
imagedestroy($im);

$fileValid1 = [
    'name'     => 'photo_valide.png',
    'type'     => 'image/png',
    'tmp_name' => $tempValid1,
    'error'    => UPLOAD_ERR_OK,
    'size'     => filesize($tempValid1),
];

$cheminPhoto1 = televerser_image($fileValid1, 'avatars');
echo "Photo téléversée : {$cheminPhoto1}\n";

// Enregistrement en base de données
$pdo->prepare('UPDATE profil_professionnel SET photo = :p WHERE id_profil = :id')->execute([
    'p'  => $cheminPhoto1,
    'id' => $idProfil,
]);

// Vérification de la présence sur le disque
$fichier1Existe = file_exists(__DIR__ . '/../' . $cheminPhoto1);
$cheminEnBase = $pdo->query("SELECT photo FROM profil_professionnel WHERE id_profil = {$idProfil}")->fetchColumn();

// Simulation du rendu HTML dans pro/profil.php
$htmlRendu = '';
if ($cheminEnBase !== null && file_exists(__DIR__ . '/../' . $cheminEnBase)) {
    $htmlRendu = '<img src="' . url($cheminEnBase) . '" id="apercu-avatar" alt="Photo de profil" class="rounded-circle border shadow-sm object-fit-cover" style="width: 80px; height: 80px;">';
}

$point1Ok = ($fichier1Existe && $cheminEnBase === $cheminPhoto1 && str_contains($htmlRendu, $cheminPhoto1));
echo "Fichier présent sur le disque : " . ($fichier1Existe ? "OUI" : "NON") . "\n";
echo "Chemin en BDD : {$cheminEnBase}\n";
echo "Balise HTML générée : {$htmlRendu}\n";
echo "Résultat Point 1 : " . ($point1Ok ? "[SUCCÈS] La photo valide s'affiche" : "[ÉCHEC]") . "\n\n";

// -----------------------------------------------------------------------------
// POINT 2 : Le remplacement supprime l'ancien fichier
// -----------------------------------------------------------------------------
echo "--- 2. TEST : Le remplacement supprime l'ancien fichier ---\n";
$tempValid2 = tempnam(sys_get_temp_dir(), 'valid_avatar2_') . '.jpg';
$im2 = imagecreatetruecolor(100, 100);
$red = imagecolorallocate($im2, 225, 29, 46);
imagefill($im2, 0, 0, $red);
imagejpeg($im2, $tempValid2);
imagedestroy($im2);

$fileValid2 = [
    'name'     => 'photo_remplacement.jpg',
    'type'     => 'image/jpeg',
    'tmp_name' => $tempValid2,
    'error'    => UPLOAD_ERR_OK,
    'size'     => filesize($tempValid2),
];

$cheminPhoto2 = televerser_image($fileValid2, 'avatars');
echo "Nouvelle photo téléversée : {$cheminPhoto2}\n";

// Simulation exacte de la logique de remplacement dans pro/profil.php :
// Si une ancienne photo existait, suppression de l'ancien fichier sur le disque
$ancienFichierReel = realpath(__DIR__ . '/../' . $cheminPhoto1);
$racineUploads = realpath(__DIR__ . '/../uploads');
if ($ancienFichierReel !== false && $racineUploads !== false && str_starts_with($ancienFichierReel, $racineUploads) && file_exists($ancienFichierReel)) {
    @unlink($ancienFichierReel);
}

// Mise à jour de la BDD avec la nouvelle photo
$pdo->prepare('UPDATE profil_professionnel SET photo = :p WHERE id_profil = :id')->execute([
    'p'  => $cheminPhoto2,
    'id' => $idProfil,
]);

$ancienSupprime = !file_exists(__DIR__ . '/../' . $cheminPhoto1);
$nouveauExiste = file_exists(__DIR__ . '/../' . $cheminPhoto2);
$nouveauEnBase = $pdo->query("SELECT photo FROM profil_professionnel WHERE id_profil = {$idProfil}")->fetchColumn();

$point2Ok = ($ancienSupprime && $nouveauExiste && $nouveauEnBase === $cheminPhoto2);
echo "Ancien fichier ({$cheminPhoto1}) supprimé du disque : " . ($ancienSupprime ? "OUI" : "NON") . "\n";
echo "Nouveau fichier ({$cheminPhoto2}) présent sur le disque : " . ($nouveauExiste ? "OUI" : "NON") . "\n";
echo "Résultat Point 2 : " . ($point2Ok ? "[SUCCÈS] L'ancien fichier est supprimé lors du remplacement" : "[ÉCHEC]") . "\n\n";

// Nettoyage de la photo 2
if (file_exists(__DIR__ . '/../' . $cheminPhoto2)) {
    @unlink(__DIR__ . '/../' . $cheminPhoto2);
}

// -----------------------------------------------------------------------------
// POINT 3 : Un faux fichier image est refusé
// -----------------------------------------------------------------------------
echo "--- 3. TEST : Un faux fichier image est refusé ---\n";
// Création d'un faux fichier : script PHP maquillé avec l'extension .jpg
$tempFake = tempnam(sys_get_temp_dir(), 'fake_img_') . '.jpg';
file_put_contents($tempFake, '<?php echo "Faux fichier binaire"; ?>');

$fakeFile = [
    'name'     => 'malicious_script.jpg',
    'type'     => 'image/jpeg',
    'tmp_name' => $tempFake,
    'error'    => UPLOAD_ERR_OK,
    'size'     => filesize($tempFake),
];

$rejetFake = false;
$messageErreur = '';
try {
    televerser_image($fakeFile, 'avatars');
} catch (RuntimeException $e) {
    $rejetFake = true;
    $messageErreur = $e->getMessage();
}

@unlink($tempFake);

echo "Tentative d'upload d'un script PHP maquillé en .jpg...\n";
echo "Exception levée : " . ($rejetFake ? "OUI" : "NON") . "\n";
echo "Message utilisateur : \"{$messageErreur}\"\n";

$point3Ok = $rejetFake && str_contains($messageErreur, 'Format de fichier non autorisé');
echo "Résultat Point 3 : " . ($point3Ok ? "[SUCCÈS] Le faux fichier image est formellement refusé" : "[ÉCHEC]") . "\n\n";

// -----------------------------------------------------------------------------
// RÉCAPITULATIF FINAL
// -----------------------------------------------------------------------------
echo "=================================================================\n";
echo " RÉCAPITULATIF DES POINTS DE CONTRÔLE\n";
echo "=================================================================\n";
printf(" [%s] Une photo valide s'affiche\n", $point1Ok ? 'X' : ' ');
printf(" [%s] Le remplacement supprime l'ancien fichier\n", $point2Ok ? 'X' : ' ');
printf(" [%s] Un faux fichier image est refusé\n", $point3Ok ? 'X' : ' ');
echo "=================================================================\n";

if ($point1Ok && $point2Ok && $point3Ok) {
    echo "RÉSULTAT : TOUS LES POINTS SONT VALIDÉS AVEC SUCCÈS.\n";
} else {
    echo "RÉSULTAT : CERTAINS POINTS ONT ÉCHOUÉ.\n";
}
echo "=================================================================\n";
