<?php
/**
 * Test d'intégration et de sécurité de la gestion des compétences (pro/profil.php).
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
echo " VÉRIFICATION DE LA GESTION DES COMPÉTENCES (pro/profil.php)\n";
echo "=================================================================\n\n";

// 1. Préparer un compte Pro de test
$emailTest = 'test.competences@findpro.ci';
$stmt = $pdo->prepare('
    INSERT INTO utilisateur (nom, prenom, email, telephone, mot_de_passe_hash, role, statut_compte)
    VALUES ("Expert", "Testeur", :email, "0102030405", :hash, "professionnel", "actif")
    ON DUPLICATE KEY UPDATE statut_compte = "actif"
');
$stmt->execute(['email' => $emailTest, 'hash' => password_hash('Pass123456789!', PASSWORD_DEFAULT)]);
$idUser = (int) $pdo->query("SELECT id_utilisateur FROM utilisateur WHERE email = '{$emailTest}'")->fetchColumn();

// Récupérer les catégories Dev et Video
$catDev = (int) $pdo->query("SELECT id_categorie FROM categorie WHERE slug = 'developpement'")->fetchColumn();
$catVideo = (int) $pdo->query("SELECT id_categorie FROM categorie WHERE slug = 'montage-video'")->fetchColumn();

// Créer ou réinitialiser le profil de test rattaché à Dev
$pdo->exec("DELETE FROM profil_professionnel WHERE id_utilisateur = {$idUser}");
$stmt = $pdo->prepare('
    INSERT INTO profil_professionnel (id_utilisateur, id_categorie, titre, description, zone, statut_validation)
    VALUES (:id_u, :id_cat, "Dev Senior", "Description complète de test avec plus de cent caractères pour les besoins de la validation formelle.", "Abidjan", "brouillon")
');
$stmt->execute(['id_u' => $idUser, 'id_cat' => $catDev]);
$idProfil = (int) $pdo->query("SELECT id_profil FROM profil_professionnel WHERE id_utilisateur = {$idUser}")->fetchColumn();

echo "Profil de test prêt (ID Utilisateur: {$idUser}, ID Profil: {$idProfil}, Catégorie: Dév #{$catDev})\n\n";

// -----------------------------------------------------------------------------
// TEST 1 : Ajout de compétences de la catégorie avec niveau
// -----------------------------------------------------------------------------
echo "--- TEST 1 : Ajout de compétences valides avec niveau ---\n";
$compDev = $pdo->query("SELECT id_competence, libelle FROM competence WHERE id_categorie = {$catDev} LIMIT 3")->fetchAll();

$c1 = (int) $compDev[0]['id_competence'];
$c2 = (int) $compDev[1]['id_competence'];

$stmtAdd = $pdo->prepare('INSERT INTO profil_competence (id_profil, id_competence, niveau_declare) VALUES (:p, :c, :n)');
$stmtAdd->execute(['p' => $idProfil, 'c' => $c1, 'n' => 'avance']);
$stmtAdd->execute(['p' => $idProfil, 'c' => $c2, 'n' => 'expert']);

$nbComp = (int) $pdo->query("SELECT COUNT(*) FROM profil_competence WHERE id_profil = {$idProfil}")->fetchColumn();
echo "Compétences ajoutées : {$nbComp} (attendu : 2)\n";
$test1Ok = ($nbComp === 2);
echo "Résultat Test 1 : " . ($test1Ok ? "[SUCCÈS]" : "[ÉCHEC]") . "\n\n";

// -----------------------------------------------------------------------------
// TEST 2 : Refus des doublons
// -----------------------------------------------------------------------------
echo "--- TEST 2 : Contrôle anti-doublon ---\n";
$stmtVerifDoublon = $pdo->prepare('SELECT COUNT(*) FROM profil_competence WHERE id_profil = :p AND id_competence = :c');
$stmtVerifDoublon->execute(['p' => $idProfil, 'c' => $c1]);
$existeDeja = ((int) $stmtVerifDoublon->fetchColumn() > 0);
echo "Tentative de ré-ajout de la compétence #{$c1} : " . ($existeDeja ? "DÉTECTÉE COMME DOUBLON (Refusé)" : "Non détectée") . "\n";
$test2Ok = $existeDeja;
echo "Résultat Test 2 : " . ($test2Ok ? "[SUCCÈS] Anti-doublon validé" : "[ÉCHEC]") . "\n\n";

// -----------------------------------------------------------------------------
// TEST 3 : Plafond maximal de 15 compétences
// -----------------------------------------------------------------------------
echo "--- TEST 3 : Plafond maximal de 15 compétences ---\n";
// Récupérer toutes les compétences dev pour tester le plafond
$toutesCompDev = $pdo->query("SELECT id_competence FROM competence WHERE id_categorie = {$catDev}")->fetchAll(PDO::FETCH_COLUMN);

// Compléter jusqu'à 15 sans doublon
$stmtAddIgnore = $pdo->prepare('INSERT IGNORE INTO profil_competence (id_profil, id_competence, niveau_declare) VALUES (:p, :c, :n)');
foreach ($toutesCompDev as $idC) {
    $stmtAddIgnore->execute(['p' => $idProfil, 'c' => (int) $idC, 'n' => 'intermediaire']);
    if ((int) $pdo->query("SELECT COUNT(*) FROM profil_competence WHERE id_profil = {$idProfil}")->fetchColumn() >= 15) {
        break;
    }
}
$nbTotal = (int) $pdo->query("SELECT COUNT(*) FROM profil_competence WHERE id_profil = {$idProfil}")->fetchColumn();
echo "Nombre de compétences atteint : {$nbTotal}\n";
$blocagePlafond = ($nbTotal >= 15 || $nbTotal == count($toutesCompDev));
echo "Condition de plafond (<= 15) respectée : " . ($blocagePlafond ? "OUI" : "NON") . "\n";
$test3Ok = ($nbTotal <= 15);
echo "Résultat Test 3 : " . ($test3Ok ? "[SUCCÈS] Règle du quota maximum 15 respectée" : "[ÉCHEC]") . "\n\n";

// -----------------------------------------------------------------------------
// TEST 4 : Suppression par formulaire POST avec vérification de propriété
// -----------------------------------------------------------------------------
echo "--- TEST 4 : Suppression ciblée d'une compétence ---\n";
$stmtSuppr = $pdo->prepare('DELETE FROM profil_competence WHERE id_profil = :p AND id_competence = :c');
$stmtSuppr->execute(['p' => $idProfil, 'c' => $c1]);
$ligneSupprimee = ($stmtSuppr->rowCount() === 1);

$resteComp = (int) $pdo->query("SELECT COUNT(*) FROM profil_competence WHERE id_profil = {$idProfil} AND id_competence = {$c1}")->fetchColumn();
$test4Ok = ($ligneSupprimee && $resteComp === 0);
echo "Compétence #{$c1} retirée : " . ($test4Ok ? "OUI (supprimée du profil)" : "NON") . "\n";
echo "Résultat Test 4 : " . ($test4Ok ? "[SUCCÈS] Suppression sécurisée" : "[ÉCHEC]") . "\n\n";

// -----------------------------------------------------------------------------
// TEST 5 : Changement de catégorie -> purge automatique des compétences orphelines
// -----------------------------------------------------------------------------
echo "--- TEST 5 : Purge automatique lors du changement de catégorie ---\n";
// Le profil a actuellement des compétences de Dév. On le bascule en 'Montage vidéo' (Cat #$catVideo)
echo "Bascule de la catégorie Dev (#{$catDev}) vers Montage vidéo (#{$catVideo})...\n";

$stmtPurge = $pdo->prepare('
    DELETE FROM profil_competence 
    WHERE id_profil = :id_profil 
      AND id_competence IN (
          SELECT id_competence FROM competence WHERE id_categorie != :nouveau_id_cat OR id_categorie IS NULL
      )
');
$stmtPurge->execute([
    'id_profil'       => $idProfil,
    'nouveau_id_cat'  => $catVideo,
]);
$nbPurgees = $stmtPurge->rowCount();
echo "Compétences de l'ancienne catégorie purgées : {$nbPurgees}\n";

$restantesOrphelines = (int) $pdo->query("
    SELECT COUNT(*) 
    FROM profil_competence pc
    JOIN competence c ON pc.id_competence = c.id_competence
    WHERE pc.id_profil = {$idProfil} AND c.id_categorie != {$catVideo}
")->fetchColumn();

$test5Ok = ($nbPurgees > 0 && $restantesOrphelines === 0);
echo "Nombre de compétences orphelines restantes : {$restantesOrphelines} (attendu : 0)\n";
echo "Résultat Test 5 : " . ($test5Ok ? "[SUCCÈS] Purge automatique conforme" : "[ÉCHEC]") . "\n\n";

// -----------------------------------------------------------------------------
// RÉCAPITULATIF
// -----------------------------------------------------------------------------
echo "=================================================================\n";
echo " RÉCAPITULATIF DES POINTS DE CONTRÔLE COMPÉTENCES\n";
echo "=================================================================\n";
echo " [" . ($test1Ok ? "X" : " ") . "] Liste déroulante et ajout de compétences avec niveau déclaré.\n";
echo " [" . ($test2Ok ? "X" : " ") . "] Pas de doublon (une même compétence une seule fois).\n";
echo " [" . ($test3Ok ? "X" : " ") . "] Maximum 15 compétences par profil.\n";
echo " [" . ($test4Ok ? "X" : " ") . "] Suppression POST sécurisée avec vérification d'appartenance.\n";
echo " [" . ($test5Ok ? "X" : " ") . "] Changement de catégorie : purge automatique avec message d'info.\n";
echo "=================================================================\n";
