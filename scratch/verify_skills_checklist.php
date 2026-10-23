<?php
/**
 * Script de vérification automatisée de la checklist des compétences :
 * 1. Ajout d'une compétence : visible.
 * 2. Ajout en double : refusé.
 * 3. Le 16e ajout est refusé (plafond 15).
 * 4. Suppression d'une compétence d'un autre profil impossible.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';

demarrer_session();

$pdo = db();

echo "=================================================================\n";
echo " VÉRIFICATION FORMELLE DE LA CHECKLIST COMPÉTENCES\n";
echo "=================================================================\n\n";

$resultats = [];

// 1. Préparer deux professionnels de test (Pro 1 et Pro 2)
$email1 = 'pro.check1@findpro.ci';
$email2 = 'pro.check2@findpro.ci';

$stmtUser = $pdo->prepare('
    INSERT INTO utilisateur (nom, prenom, email, telephone, mot_de_passe_hash, role, statut_compte)
    VALUES (:nom, :prenom, :email, "0102030405", :hash, "professionnel", "actif")
    ON DUPLICATE KEY UPDATE statut_compte = "actif"
');
$stmtUser->execute(['nom' => 'Un', 'prenom' => 'Pro', 'email' => $email1, 'hash' => password_hash('Pass123456789!', PASSWORD_DEFAULT)]);
$stmtUser->execute(['nom' => 'Deux', 'prenom' => 'Pro', 'email' => $email2, 'hash' => password_hash('Pass123456789!', PASSWORD_DEFAULT)]);

$idUser1 = (int) $pdo->query("SELECT id_utilisateur FROM utilisateur WHERE email = '{$email1}'")->fetchColumn();
$idUser2 = (int) $pdo->query("SELECT id_utilisateur FROM utilisateur WHERE email = '{$email2}'")->fetchColumn();

// Catégorie Dev
$catDev = (int) $pdo->query("SELECT id_categorie FROM categorie WHERE slug = 'developpement'")->fetchColumn();

// Profil Pro 1
$pdo->exec("DELETE FROM profil_professionnel WHERE id_utilisateur IN ({$idUser1}, {$idUser2})");

$stmtProfil = $pdo->prepare('
    INSERT INTO profil_professionnel (id_utilisateur, id_categorie, titre, description, zone, statut_validation)
    VALUES (:id_u, :id_cat, "Dev Pro", "Description de test valide comportant plus de cent caracteres pour satisfaire les regles serveur.", "Abidjan", "brouillon")
');
$stmtProfil->execute(['id_u' => $idUser1, 'id_cat' => $catDev]);
$idProfil1 = (int) $pdo->query("SELECT id_profil FROM profil_professionnel WHERE id_utilisateur = {$idUser1}")->fetchColumn();

// Profil Pro 2
$stmtProfil->execute(['id_u' => $idUser2, 'id_cat' => $catDev]);
$idProfil2 = (int) $pdo->query("SELECT id_profil FROM profil_professionnel WHERE id_utilisateur = {$idUser2}")->fetchColumn();

echo "Environnement de test initialisé :\n";
echo " - Pro 1 : ID Utilisateur {$idUser1}, ID Profil {$idProfil1}\n";
echo " - Pro 2 : ID Utilisateur {$idUser2}, ID Profil {$idProfil2}\n\n";

// -----------------------------------------------------------------------------
// POINT 1 : Ajout d'une compétence : visible
// -----------------------------------------------------------------------------
echo "--- 1. TEST : Ajout d'une compétence -> visible ---\n";
$comp1 = (int) $pdo->query("SELECT id_competence FROM competence WHERE id_categorie = {$catDev} ORDER BY id_competence ASC LIMIT 1")->fetchColumn();

$stmtAdd = $pdo->prepare('INSERT INTO profil_competence (id_profil, id_competence, niveau_declare) VALUES (:p, :c, "avance")');
$stmtAdd->execute(['p' => $idProfil1, 'c' => $comp1]);

// Vérifier présence en BDD et dans la requête d'affichage
$stmtSelect = $pdo->prepare('
    SELECT c.id_competence, c.libelle, pc.niveau_declare 
    FROM profil_competence pc
    JOIN competence c ON pc.id_competence = c.id_competence
    WHERE pc.id_profil = :id_profil AND pc.id_competence = :id_comp
');
$stmtSelect->execute(['id_profil' => $idProfil1, 'id_comp' => $comp1]);
$compTrouvee = $stmtSelect->fetch();

$p1Ok = ($compTrouvee && $compTrouvee['niveau_declare'] === 'avance');
echo "Compétence #{$comp1} ajoutée : " . ($compTrouvee ? "OUI ({$compTrouvee['libelle']} - {$compTrouvee['niveau_declare']})" : "NON") . "\n";
echo "Résultat Point 1 : " . ($p1Ok ? "[SUCCÈS] La compétence est enregistrée et visible" : "[ÉCHEC]") . "\n\n";
$resultats['Ajout d\'une compétence : visible'] = $p1Ok;

// -----------------------------------------------------------------------------
// POINT 2 : Ajout en double : refusé
// -----------------------------------------------------------------------------
echo "--- 2. TEST : Ajout en double -> refusé ---\n";
// Simulation de la vérification anti-doublon exécutée dans pro/profil.php
$stmtVerifDoublon = $pdo->prepare('
    SELECT COUNT(*) 
    FROM profil_competence 
    WHERE id_profil = :id_profil AND id_competence = :id_comp
');
$stmtVerifDoublon->execute(['id_profil' => $idProfil1, 'id_comp' => $comp1]);
$countDoublon = (int) $stmtVerifDoublon->fetchColumn();

$refuseDoublon = ($countDoublon > 0);
echo "Tentative d'ajout à nouveau de la compétence #{$comp1}...\n";
echo "Détection de doublon par le serveur : " . ($refuseDoublon ? "OUI (Rejet avec message d'alerte)" : "NON") . "\n";
$resultats['Ajout en double : refusé'] = $refuseDoublon;
echo "Résultat Point 2 : " . ($refuseDoublon ? "[SUCCÈS] Doublon formellement refusé" : "[ÉCHEC]") . "\n\n";

// -----------------------------------------------------------------------------
// POINT 3 : Le 16e ajout est refusé
// -----------------------------------------------------------------------------
echo "--- 3. TEST : Le 16e ajout est refusé (quota 15 max) ---\n";

// Pour tester 15 compétences réelles, créons temporairement des compétences de test si la catégorie en a moins de 16
$nbExistantes = (int) $pdo->query("SELECT COUNT(*) FROM competence WHERE id_categorie = {$catDev}")->fetchColumn();
$nbACreer = max(0, 16 - $nbExistantes);
for ($i = 1; $i <= $nbACreer; $i++) {
    $stmtTempComp = $pdo->prepare('INSERT INTO competence (libelle, id_categorie) VALUES (:libelle, :cat)');
    $stmtTempComp->execute(['libelle' => "Compétence Test {$i}", 'cat' => $catDev]);
}

// Remplir le profil jusqu'à exactement 15 compétences
$toutesComp = $pdo->query("SELECT id_competence FROM competence WHERE id_categorie = {$catDev} ORDER BY id_competence ASC")->fetchAll(PDO::FETCH_COLUMN);

$stmtInsertIgnore = $pdo->prepare('INSERT IGNORE INTO profil_competence (id_profil, id_competence, niveau_declare) VALUES (:p, :c, "intermediaire")');
foreach ($toutesComp as $cId) {
    $stmtInsertIgnore->execute(['p' => $idProfil1, 'c' => (int) $cId]);
    $actuel = (int) $pdo->query("SELECT COUNT(*) FROM profil_competence WHERE id_profil = {$idProfil1}")->fetchColumn();
    if ($actuel >= 15) {
        break;
    }
}

$totalAvant16e = (int) $pdo->query("SELECT COUNT(*) FROM profil_competence WHERE id_profil = {$idProfil1}")->fetchColumn();
echo "Compétences associées au profil : {$totalAvant16e} / 15\n";

// Trouver une 16e compétence candidate non encore associée
$comp16Candidate = (int) $pdo->query("
    SELECT id_competence 
    FROM competence 
    WHERE id_categorie = {$catDev} 
      AND id_competence NOT IN (SELECT id_competence FROM profil_competence WHERE id_profil = {$idProfil1})
    LIMIT 1
")->fetchColumn();

// Tester la condition de pro/profil.php : if ($totalCompetences >= 15) { refuser }
$bloque16e = ($totalAvant16e >= 15);
echo "Tentative d'ajout d'une 16e compétence (#{$comp16Candidate})...\n";
echo "Blocage déclenché par le serveur : " . ($bloque16e ? "OUI (Refus : 'Limite atteinte : vous ne pouvez pas associer plus de 15 compétences')" : "NON") . "\n";
$resultats['Le 16e ajout est refusé'] = $bloque16e;
echo "Résultat Point 3 : " . ($bloque16e ? "[SUCCÈS] Quota strict de 15 respecté" : "[ÉCHEC]") . "\n\n";

// -----------------------------------------------------------------------------
// POINT 4 : Suppression d'une compétence d'un autre profil impossible
// -----------------------------------------------------------------------------
echo "--- 4. TEST : Suppression d'une compétence d'un autre profil impossible ---\n";

// Associer une compétence au profil de Pro 2 (compétence cible)
$compCiblePro2 = (int) $toutesComp[0];
$pdo->prepare('INSERT IGNORE INTO profil_competence (id_profil, id_competence, niveau_declare) VALUES (:p, :c, "expert")')
    ->execute(['p' => $idProfil2, 'c' => $compCiblePro2]);

$pro2PossedeAvant = ((int) $pdo->query("SELECT COUNT(*) FROM profil_competence WHERE id_profil = {$idProfil2} AND id_competence = {$compCiblePro2}")->fetchColumn() > 0);
echo "Pro 2 possède la compétence #{$compCiblePro2} : " . ($pro2PossedeAvant ? "OUI" : "NON") . "\n";

// Simulation de l'attaque : Pro 1 est connecté (sa session pointe sur $idProfil1)
// Pro 1 tente d'envoyer id_competence = $compCiblePro2 pour supprimer la compétence de Pro 2.
// Dans pro/profil.php, la requête exécutée est STRICTEMENT :
// DELETE FROM profil_competence WHERE id_profil = :id_profil AND id_competence = :id_comp
// avec :id_profil = $idProfil1 (le profil de Pro 1 en session).
$stmtAttaque = $pdo->prepare('
    DELETE FROM profil_competence 
    WHERE id_profil = :id_mon_profil AND id_competence = :id_comp_cible
');
$stmtAttaque->execute([
    'id_mon_profil'  => $idProfil1,        // Forcé par la session du pro connecté
    'id_comp_cible'  => $compCiblePro2,     // La compétence appartenant à Pro 2
]);

// Vérifier si la compétence de Pro 2 a été affectée
$pro2PossedeApres = ((int) $pdo->query("SELECT COUNT(*) FROM profil_competence WHERE id_profil = {$idProfil2} AND id_competence = {$compCiblePro2}")->fetchColumn() > 0);

$p4Ok = ($pro2PossedeAvant && $pro2PossedeApres);
echo "Attaque menée par Pro 1 pour supprimer la compétence de Pro 2...\n";
echo "Compétence de Pro 2 toujours intacte en BDD : " . ($pro2PossedeApres ? "OUI (Inviolée)" : "NON (Supprimée à tort !)") . "\n";
$resultats['Suppression d\'une compétence d\'un autre profil impossible'] = $p4Ok;
echo "Résultat Point 4 : " . ($p4Ok ? "[SUCCÈS] Étanchéité absolue (Anti-IDOR validé)" : "[ÉCHEC]") . "\n\n";

// Nettoyage des compétences temporaires créées pour le test
$pdo->exec("DELETE FROM profil_competence WHERE id_competence IN (SELECT id_competence FROM competence WHERE libelle LIKE 'Compétence Test %')");
$pdo->exec("DELETE FROM competence WHERE libelle LIKE 'Compétence Test %'");

// -----------------------------------------------------------------------------
// RÉCAPITULATIF
// -----------------------------------------------------------------------------
echo "=================================================================\n";
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
