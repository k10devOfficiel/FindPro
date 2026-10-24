<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db();
$pdo->beginTransaction();

try {
    // 1. Create a user and profile with % in title and _ in description
    $pdo->exec("INSERT INTO utilisateur (email, mot_de_passe_hash, nom, prenom, role, statut_compte) VALUES ('test_special@findpro.ci', 'hash', 'Testeur', 'Special', 'professionnel', 'actif')");
    $uid1 = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO profil_professionnel (id_utilisateur, id_categorie, titre, description, zone, disponibilite, statut_validation) VALUES ($uid1, 1, 'Expert 100% PHP', 'Développeur super_star', 'Abidjan', 'disponible', 'approuve')");
    $pid1 = (int) $pdo->lastInsertId();

    // 2. Create another approved profile with ordinary text
    $pdo->exec("INSERT INTO utilisateur (email, mot_de_passe_hash, nom, prenom, role, statut_compte) VALUES ('test_normal@findpro.ci', 'hash', 'Normal', 'Jean', 'professionnel', 'actif')");
    $uid2 = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO profil_professionnel (id_utilisateur, id_categorie, titre, description, zone, disponibilite, statut_validation) VALUES ($uid2, 1, 'Expert Python', 'Developpeur web standard', 'Abidjan', 'disponible', 'approuve')");
    $pid2 = (int) $pdo->lastInsertId();

    // 3. Create an unapproved profile with exact matching name 'RechercheExacte'
    $pdo->exec("INSERT INTO utilisateur (email, mot_de_passe_hash, nom, prenom, role, statut_compte) VALUES ('test_unapproved@findpro.ci', 'hash', 'RechercheExacte', 'Marc', 'professionnel', 'actif')");
    $uid3 = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO profil_professionnel (id_utilisateur, id_categorie, titre, description, zone, disponibilite, statut_validation) VALUES ($uid3, 1, 'RechercheExacte Titre', 'Desc', 'Abidjan', 'disponible', 'en_attente')");
    $pid3 = (int) $pdo->lastInsertId();

    // Test A: Search for "100%"
    $resA = profils_publics($pdo, ['q' => '100%']);
    $idsA = array_column($resA, 'id_profil');
    echo "Recherche '100%' : " . (in_array($pid1, $idsA, true) && !in_array($pid2, $idsA, true) ? "PASS" : "FAIL") . "\n";

    // Test B: Search for just "%" -> should match only if literal % is present (so $pid1 matches, but NOT $pid2!)
    $resB = profils_publics($pdo, ['q' => '%']);
    $idsB = array_column($resB, 'id_profil');
    echo "Recherche '%' : " . (in_array($pid1, $idsB, true) && !in_array($pid2, $idsB, true) ? "PASS" : "FAIL") . "\n";
    echo "  Total matches for '%': " . count($idsB) . "\n";

    // Test C: Search for "super_" -> should match $pid1, not $pid2
    $resC = profils_publics($pdo, ['q' => 'super_']);
    $idsC = array_column($resC, 'id_profil');
    echo "Recherche 'super_' : " . (in_array($pid1, $idsC, true) && !in_array($pid2, $idsC, true) ? "PASS" : "FAIL") . "\n";

    // Test D: Unapproved profile MUST NEVER appear even with exact name 'RechercheExacte'
    $resD = profils_publics($pdo, ['q' => 'RechercheExacte']);
    $idsD = array_column($resD, 'id_profil');
    echo "Profil non approuvé exclu : " . (!in_array($pid3, $idsD, true) && count($idsD) === 0 ? "PASS" : "FAIL") . "\n";

    // Test E: Invalid tri parameter
    $resE = profils_publics($pdo, ['tri' => 'HACK_INVALID_ORDER_BY; DROP TABLE;']);
    echo "Tri invalide sécurisé sans erreur : " . (is_array($resE) ? "PASS" : "FAIL") . "\n";

} finally {
    $pdo->rollBack();
    echo "Rollback effectué.\n";
}
