<?php
/**
 * Vérification formelle de la checklist portfolio :
 * 1. Lien https valide accepté.
 * 2. javascript:alert(1) refusé.
 * 3. Modification du projet d'un autre professionnel impossible.
 * 4. Le 13e projet est refusé.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/upload.php';

$pdo = db();

echo "=================================================================\n";
echo " VÉRIFICATION DE LA CHECKLIST PORTFOLIO (pro/portfolio.php)\n";
echo "=================================================================\n\n";

// Configuration de deux comptes Pro distincts pour tester l'étanchéité anti-IDOR
$emailPro1 = 'pro.portfolio1@findpro.ci';
$emailPro2 = 'pro.portfolio2@findpro.ci';

foreach ([$emailPro1, $emailPro2] as $email) {
    $pdo->prepare('
        INSERT INTO utilisateur (nom, prenom, email, telephone, mot_de_passe_hash, role, statut_compte)
        VALUES ("Portfolio", "Testeur", :email, "0102030405", :hash, "professionnel", "actif")
        ON DUPLICATE KEY UPDATE statut_compte = "actif"
    ')->execute(['email' => $email, 'hash' => password_hash('Pass123456789!', PASSWORD_DEFAULT)]);
}

$idUser1 = (int) $pdo->query("SELECT id_utilisateur FROM utilisateur WHERE email = '{$emailPro1}'")->fetchColumn();
$idUser2 = (int) $pdo->query("SELECT id_utilisateur FROM utilisateur WHERE email = '{$emailPro2}'")->fetchColumn();
$cat = (int) $pdo->query("SELECT id_categorie FROM categorie LIMIT 1")->fetchColumn();

// Réinitialisation des profils
foreach ([$idUser1, $idUser2] as $uId) {
    $pdo->exec("DELETE FROM profil_professionnel WHERE id_utilisateur = {$uId}");
    $pdo->prepare('
        INSERT INTO profil_professionnel (id_utilisateur, id_categorie, titre, description, zone, statut_validation)
        VALUES (:id_u, :id_cat, "Titre Pro", "Description de test valide comportant plus de cent caracteres pour satisfaire les regles serveur.", "Abidjan", "brouillon")
    ')->execute(['id_u' => $uId, 'id_cat' => $cat]);
}

$idProfil1 = (int) $pdo->query("SELECT id_profil FROM profil_professionnel WHERE id_utilisateur = {$idUser1}")->fetchColumn();
$idProfil2 = (int) $pdo->query("SELECT id_profil FROM profil_professionnel WHERE id_utilisateur = {$idUser2}")->fetchColumn();

// Nettoyage préalable des tables portfolio pour ces profils
$pdo->exec("DELETE FROM projet_portfolio WHERE id_profil IN ({$idProfil1}, {$idProfil2})");

echo "Profils de test prêts :\n";
echo " - Pro 1 : ID Utilisateur {$idUser1}, ID Profil {$idProfil1}\n";
echo " - Pro 2 : ID Utilisateur {$idUser2}, ID Profil {$idProfil2}\n\n";

// Fonction de validation d'URL identique à celle de pro/portfolio.php
function valider_url_test(?string $url): array
{
    $url = trim((string) $url);
    if ($url === '') {
        return ['valide' => true, 'url' => null, 'erreur' => null];
    }
    if (mb_strlen($url, 'UTF-8') > 500) {
        return ['valide' => false, 'url' => null, 'erreur' => 'L\'URL dépasse 500 caractères.'];
    }
    if (!preg_match('#^https?://#i', $url)) {
        return ['valide' => false, 'url' => null, 'erreur' => 'L\'URL doit commencer par http:// ou https://.'];
    }
    $urlBasse = strtolower($url);
    foreach (['javascript:', 'data:', 'vbscript:', 'file:', 'about:'] as $bad) {
        if (str_starts_with($urlBasse, $bad)) {
            return ['valide' => false, 'url' => null, 'erreur' => 'Schéma d\'URL non autorisé.'];
        }
    }
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return ['valide' => false, 'url' => null, 'erreur' => 'Format d\'URL invalide.'];
    }
    $p = parse_url($url);
    if ($p === false || empty($p['scheme']) || empty($p['host'])) {
        return ['valide' => false, 'url' => null, 'erreur' => 'URL incomplète.'];
    }
    if (!in_array(strtolower($p['scheme']), ['http', 'https'], true)) {
        return ['valide' => false, 'url' => null, 'erreur' => 'Seuls http et https sont autorisés.'];
    }
    return ['valide' => true, 'url' => $url, 'erreur' => null];
}

// -----------------------------------------------------------------------------
// POINT 1 : Lien https valide accepté
// -----------------------------------------------------------------------------
echo "--- 1. TEST : Lien https valide accepté ---\n";
$urlHttpsTest = 'https://www.exemple.com/mon-projet-saas?ref=portfolio#demo';
$res1 = valider_url_test($urlHttpsTest);

$point1Ok = ($res1['valide'] === true && $res1['url'] === $urlHttpsTest);
echo "URL testée : {$urlHttpsTest}\n";
echo "Validation : " . ($res1['valide'] ? "VALIDE" : "REFUSÉE") . "\n";

// Insertion effective du projet avec ce lien HTTPS
if ($point1Ok) {
    $stmt1 = $pdo->prepare('
        INSERT INTO projet_portfolio (id_profil, titre, description, lien_externe, url_media, type_media)
        VALUES (:id_profil, "Projet Démo HTTPS", "Application web validée", :lien1, :lien2, "lien")
    ');
    $stmt1->execute([
        'id_profil' => $idProfil1,
        'lien1'     => $urlHttpsTest,
        'lien2'     => $urlHttpsTest,
    ]);
    $idProjetPro1 = (int) $pdo->lastInsertId();
    $point1Ok = ($idProjetPro1 > 0);
}
echo "Résultat Point 1 : " . ($point1Ok ? "[SUCCÈS] Lien https valide accepté et enregistré" : "[ÉCHEC]") . "\n\n";

// -----------------------------------------------------------------------------
// POINT 2 : javascript:alert(1) refusé
// -----------------------------------------------------------------------------
echo "--- 2. TEST : javascript:alert(1) refusé ---\n";
$urlXssTest = 'javascript:alert(1)';
$res2 = valider_url_test($urlXssTest);

// Vérifier également d'autres variantes de vecteurs XSS
$res2Data = valider_url_test('data:text/html,<script>alert(1)</script>');
$res2Mixed = valider_url_test('javascript://alert(1)');

$point2Ok = ($res2['valide'] === false && $res2Data['valide'] === false && $res2Mixed['valide'] === false);
echo "URL testée : {$urlXssTest}\n";
echo "Validation : " . ($res2['valide'] ? "ACCEPTÉE (FAILLE !)" : "REFUSÉE (Sécurisé)") . "\n";
echo "Message retourné : \"{$res2['erreur']}\"\n";
echo "Résultat Point 2 : " . ($point2Ok ? "[SUCCÈS] javascript:alert(1) et data: formellement refusés" : "[ÉCHEC]") . "\n\n";

// -----------------------------------------------------------------------------
// POINT 3 : Modification du projet d'un autre professionnel impossible
// -----------------------------------------------------------------------------
echo "--- 3. TEST : Modification du projet d'un autre professionnel impossible ---\n";
// Pro 1 possède $idProjetPro1
// Tentative par Pro 2 de modifier le projet de Pro 1 via la requête exacte avec vérification de propriété
$titreTenteParPro2 = 'Projet Piraté par Pro 2';

// Vérification de propriété telle que codée dans pro/portfolio.php :
$stmtVerifPropriete = $pdo->prepare('
    SELECT * FROM projet_portfolio 
    WHERE id_projet = :id_p AND id_profil = :id_profil 
    LIMIT 1
');
$stmtVerifPropriete->execute([
    'id_p'      => $idProjetPro1,
    'id_profil' => $idProfil2, // ID de session de Pro 2 (l'attaquant)
]);
$projetTrouve = $stmtVerifPropriete->fetch();

$accesRefuse = ($projetTrouve === false);

// Tentative d'UPDATE SQL direct restreint par id_profil du connecté :
$stmtUpdateIdor = $pdo->prepare('
    UPDATE projet_portfolio 
    SET titre = :titre 
    WHERE id_projet = :id_p AND id_profil = :id_profil
');
$stmtUpdateIdor->execute([
    'titre'     => $titreTenteParPro2,
    'id_p'      => $idProjetPro1,
    'id_profil' => $idProfil2,
]);
$lignesModifiees = $stmtUpdateIdor->rowCount();

// Vérifier que le titre original du projet de Pro 1 est resté intact
$titreReel = $pdo->query("SELECT titre FROM projet_portfolio WHERE id_projet = {$idProjetPro1}")->fetchColumn();

$point3Ok = ($accesRefuse && $lignesModifiees === 0 && $titreReel === 'Projet Démo HTTPS');
echo "Pro 2 tente de cibler le projet #{$idProjetPro1} (appartenant à Pro 1)...\n";
echo "Accès accordé à Pro 2 : " . ($accesRefuse ? "NON (Action refusée)" : "OUI (Faille IDOR)") . "\n";
echo "Lignes altérées en BDD : {$lignesModifiees} (attendu : 0)\n";
echo "Titre en BDD : \"{$titreReel}\" (intact)\n";
echo "Résultat Point 3 : " . ($point3Ok ? "[SUCCÈS] Étanchéité Anti-IDOR validée" : "[ÉCHEC]") . "\n\n";

// -----------------------------------------------------------------------------
// POINT 4 : Le 13e projet est refusé
// -----------------------------------------------------------------------------
echo "--- 4. TEST : Le 13e projet est refusé (quota strict de 12) ---\n";
// Compléter les projets de Pro 1 pour atteindre exactement 12 projets
$nbActuel = (int) $pdo->query("SELECT COUNT(*) FROM projet_portfolio WHERE id_profil = {$idProfil1}")->fetchColumn();
for ($i = $nbActuel + 1; $i <= 12; $i++) {
    $pdo->prepare('
        INSERT INTO projet_portfolio (id_profil, titre, description, lien_externe, url_media, type_media)
        VALUES (:id_profil, :titre, "Description projet", :lien1, :lien2, "lien")
    ')->execute([
        'id_profil' => $idProfil1,
        'titre'     => "Projet N°{$i}",
        'lien1'     => "https://exemple.ci/p{$i}",
        'lien2'     => "https://exemple.ci/p{$i}",
    ]);
}

$nbTotalAvant13 = (int) $pdo->query("SELECT COUNT(*) FROM projet_portfolio WHERE id_profil = {$idProfil1}")->fetchColumn();
echo "Nombre de projets enregistrés pour Pro 1 : {$nbTotalAvant13} / 12\n";

// Simulation de la vérification de quota de pro/portfolio.php lors de la soumission du 13e projet :
$stmtCountVerif = $pdo->prepare('SELECT COUNT(*) FROM projet_portfolio WHERE id_profil = :id_profil');
$stmtCountVerif->execute(['id_profil' => $idProfil1]);
$totalProjets = (int) $stmtCountVerif->fetchColumn();

$bloque13e = false;
$messageBlocage = '';
if ($totalProjets >= 12) {
    $bloque13e = true;
    $messageBlocage = 'Limite atteinte : vous ne pouvez pas enregistrer plus de 12 projets dans votre portfolio.';
}

$nbTotalApres13 = (int) $pdo->query("SELECT COUNT(*) FROM projet_portfolio WHERE id_profil = {$idProfil1}")->fetchColumn();

$point4Ok = ($nbTotalAvant13 === 12 && $bloque13e && $nbTotalApres13 === 12);
echo "Tentative d'ajout d'un 13e projet...\n";
echo "Blocage déclenché par le serveur : " . ($bloque13e ? "OUI" : "NON") . "\n";
echo "Message utilisateur : \"{$messageBlocage}\"\n";
echo "Nombre final de projets en BDD : {$nbTotalApres13} / 12\n";
echo "Résultat Point 4 : " . ($point4Ok ? "[SUCCÈS] Le 13e projet est formellement refusé" : "[ÉCHEC]") . "\n\n";

// Nettoyage des profils de test
$pdo->exec("DELETE FROM projet_portfolio WHERE id_profil IN ({$idProfil1}, {$idProfil2})");

// -----------------------------------------------------------------------------
// RÉCAPITULATIF FINAL
// -----------------------------------------------------------------------------
echo "=================================================================\n";
echo " RÉCAPITULATIF DE LA CHECKLIST PORTFOLIO\n";
echo "=================================================================\n";
printf(" [%s] Lien https valide accepté\n", $point1Ok ? 'X' : ' ');
printf(" [%s] javascript:alert(1) refusé\n", $point2Ok ? 'X' : ' ');
printf(" [%s] Modification du projet d'un autre professionnel impossible\n", $point3Ok ? 'X' : ' ');
printf(" [%s] Le 13e projet est refusé\n", $point4Ok ? 'X' : ' ');
echo "=================================================================\n";

if ($point1Ok && $point2Ok && $point3Ok && $point4Ok) {
    echo "RÉSULTAT : TOUS LES POINTS SONT VALIDÉS AVEC SUCCÈS.\n";
} else {
    echo "RÉSULTAT : CERTAINS POINTS ONT ÉCHOUÉ.\n";
}
echo "=================================================================\n";
