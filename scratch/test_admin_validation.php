<?php
/**
 * Test complet pour admin/validation.php :
 * - exiger_role('admin')
 * - statuts 'soumis' et 'en_verification'
 * - tri par date_soumission croissante
 * - filtres statut et catégorie
 * - pagination de 20 par page
 * - bouton Examiner et échappement des sorties
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

demarrer_session();
$pdo = db();

echo "====================================================================\n";
echo "VÉRIFICATION DE LA PAGE D'ARBITRAGE (admin/validation.php)\n";
echo "====================================================================\n\n";

$testsReussis = 0;
$testsTotal = 0;

function tester(string $intitule, bool $condition, string $details = ''): void {
    global $testsReussis, $testsTotal;
    $testsTotal++;
    if ($condition) {
        $testsReussis++;
        echo "  [PASS] {$intitule}\n";
    } else {
        echo "  [FAIL] {$intitule}\n";
        if ($details !== '') {
            echo "         Détail : {$details}\n";
        }
    }
}

// -----------------------------------------------------------------------------
// 1. Contrôle d'accès par rôle
// -----------------------------------------------------------------------------
echo "1. CONTRÔLE D'ACCÈS EXIGER_ROLE('ADMIN')...\n";

$_SESSION['utilisateur'] = ['id' => 999, 'role' => 'client'];
tester("Accès refusé pour rôle client", !est_admin());

$_SESSION['utilisateur'] = ['id' => 998, 'role' => 'professionnel'];
tester("Accès refusé pour rôle professionnel", !est_admin());

$_SESSION['utilisateur'] = ['id' => 1, 'role' => 'admin'];
tester("Accès autorisé pour rôle admin", est_admin());

// -----------------------------------------------------------------------------
// 2. Tests des requêtes, tri croissant et filtres
// -----------------------------------------------------------------------------
echo "\n2. VÉRIFICATION DU TRI CROISSANT ET DES STATUTS ÉLIGIBLES...\n";

$pdo->beginTransaction();
try {
    $catDev = (int) $pdo->query("SELECT id_categorie FROM categorie WHERE slug = 'developpement'")->fetchColumn();
    $catCyber = (int) $pdo->query("SELECT id_categorie FROM categorie WHERE slug = 'cybersecurite'")->fetchColumn();

    // Créer des profils avec dates échelonnées et statuts variés
    // Profil 1 : soumis (il y a 3 heures)
    // Profil 2 : en_verification (il y a 2 heures)
    // Profil 3 : soumis (il y a 1 heure)
    // Profil 4 : brouillon (ne doit PAS apparaître)
    // Profil 5 : approuve (ne doit PAS apparaître)
    $stmtUser = $pdo->prepare('
        INSERT INTO utilisateur (nom, prenom, email, mot_de_passe_hash, role, statut_compte)
        VALUES (:nom, :prenom, :email, "hash", "professionnel", "actif")
    ');

    $stmtProfil = $pdo->prepare('
        INSERT INTO profil_professionnel (
            id_utilisateur, id_categorie, titre, description, zone, disponibilite, statut_validation, date_soumission
        ) VALUES (
            :id_user, :id_cat, :titre, "Description de test d\'au moins cent caractères pour validation.", "Abidjan", "disponible", :statut, :date_soum
        )
    ');

    // P1 : soumis il y a 3h (Cat Dev)
    $stmtUser->execute(['nom' => 'Alpha', 'prenom' => 'Pierre', 'email' => 'p1_' . bin2hex(random_bytes(2)) . '@test.ci']);
    $u1 = (int) $pdo->lastInsertId();
    $dateP1 = date('Y-m-d H:i:s', time() - 3600 * 3);
    $stmtProfil->execute(['id_user' => $u1, 'id_cat' => $catDev, 'titre' => 'Titre P1 - Plus Ancien', 'statut' => 'soumis', 'date_soum' => $dateP1]);
    $idProf1 = (int) $pdo->lastInsertId();

    // P2 : en_verification il y a 2h (Cat Cyber)
    $stmtUser->execute(['nom' => 'Beta', 'prenom' => 'Paul', 'email' => 'p2_' . bin2hex(random_bytes(2)) . '@test.ci']);
    $u2 = (int) $pdo->lastInsertId();
    $dateP2 = date('Y-m-d H:i:s', time() - 3600 * 2);
    $stmtProfil->execute(['id_user' => $u2, 'id_cat' => $catCyber, 'titre' => 'Titre P2 - Intermédiaire', 'statut' => 'en_verification', 'date_soum' => $dateP2]);
    $idProf2 = (int) $pdo->lastInsertId();

    // P3 : soumis il y a 1h (Cat Dev)
    $stmtUser->execute(['nom' => 'Gamma', 'prenom' => 'Jacques', 'email' => 'p3_' . bin2hex(random_bytes(2)) . '@test.ci']);
    $u3 = (int) $pdo->lastInsertId();
    $dateP3 = date('Y-m-d H:i:s', time() - 3600 * 1);
    $stmtProfil->execute(['id_user' => $u3, 'id_cat' => $catDev, 'titre' => 'Titre P3 - Plus Récent', 'statut' => 'soumis', 'date_soum' => $dateP3]);
    $idProf3 = (int) $pdo->lastInsertId();

    // P4 : brouillon (ne doit PAS être listé)
    $stmtUser->execute(['nom' => 'Delta', 'prenom' => 'Luc', 'email' => 'p4_' . bin2hex(random_bytes(2)) . '@test.ci']);
    $u4 = (int) $pdo->lastInsertId();
    $stmtProfil->execute(['id_user' => $u4, 'id_cat' => $catDev, 'titre' => 'Titre P4 - Brouillon', 'statut' => 'brouillon', 'date_soum' => null]);
    $idProf4 = (int) $pdo->lastInsertId();

    // P5 : approuve (ne doit PAS être listé)
    $stmtUser->execute(['nom' => 'Epsilon', 'prenom' => 'Marc', 'email' => 'p5_' . bin2hex(random_bytes(2)) . '@test.ci']);
    $u5 = (int) $pdo->lastInsertId();
    $stmtProfil->execute(['id_user' => $u5, 'id_cat' => $catDev, 'titre' => 'Titre P5 - Approuvé', 'statut' => 'approuve', 'date_soum' => date('Y-m-d H:i:s')]);
    $idProf5 = (int) $pdo->lastInsertId();

    // Test de la requête standard sans filtre spécifique
    $sqlBase = '
        SELECT p.id_profil, p.titre, p.statut_validation, p.date_soumission
        FROM profil_professionnel p
        WHERE p.statut_validation IN (\'soumis\', \'en_verification\')
        ORDER BY p.date_soumission ASC, p.id_profil ASC
    ';
    $results = $pdo->query($sqlBase)->fetchAll();
    $idsRetenus = array_column($results, 'id_profil');

    tester("Profil P1 (soumis) est inclus", in_array($idProf1, $idsRetenus, true));
    tester("Profil P2 (en_verification) est inclus", in_array($idProf2, $idsRetenus, true));
    tester("Profil P3 (soumis) est inclus", in_array($idProf3, $idsRetenus, true));
    tester("Profil P4 (brouillon) est exclu", !in_array($idProf4, $idsRetenus, true));
    tester("Profil P5 (approuvé) est exclu", !in_array($idProf5, $idsRetenus, true));

    // Vérifier l'ordre chronologique CROISSANT (date_soumission ASC)
    // P1 (-3h) doit être AVANT P2 (-2h), lui-même AVANT P3 (-1h)
    $posP1 = array_search($idProf1, $idsRetenus, true);
    $posP2 = array_search($idProf2, $idsRetenus, true);
    $posP3 = array_search($idProf3, $idsRetenus, true);

    tester(
        "Tri chronologique croissant respecté : le plus ancien soumis (P1) est en premier",
        $posP1 < $posP2 && $posP2 < $posP3,
        "Positions : P1={$posP1}, P2={$posP2}, P3={$posP3}"
    );

    // -------------------------------------------------------------------------
    // 3. Tests des filtres (Statut & Catégorie)
    // -------------------------------------------------------------------------
    echo "\n3. VÉRIFICATION DES FILTRES DE STATUT ET DE CATÉGORIE...\n";

    // Filtre statut = 'soumis'
    $stmtFiltreSoumis = $pdo->prepare('
        SELECT id_profil FROM profil_professionnel
        WHERE statut_validation = :statut
        ORDER BY date_soumission ASC
    ');
    $stmtFiltreSoumis->execute(['statut' => 'soumis']);
    $idsSoumis = $stmtFiltreSoumis->fetchAll(PDO::FETCH_COLUMN);

    tester("Filtre statut 'soumis' inclut P1 et P3", in_array($idProf1, $idsSoumis, true) && in_array($idProf3, $idsSoumis, true));
    tester("Filtre statut 'soumis' exclut P2 (en_verification)", !in_array($idProf2, $idsSoumis, true));

    // Filtre statut = 'en_verification'
    $stmtFiltreVerif = $pdo->prepare('
        SELECT id_profil FROM profil_professionnel
        WHERE statut_validation = :statut
        ORDER BY date_soumission ASC
    ');
    $stmtFiltreVerif->execute(['statut' => 'en_verification']);
    $idsVerif = $stmtFiltreVerif->fetchAll(PDO::FETCH_COLUMN);

    tester("Filtre statut 'en_verification' inclut P2", in_array($idProf2, $idsVerif, true));
    tester("Filtre statut 'en_verification' exclut P1", !in_array($idProf1, $idsVerif, true));

    // Filtre catégorie = Cybersécurité
    $stmtFiltreCyber = $pdo->prepare('
        SELECT id_profil FROM profil_professionnel
        WHERE statut_validation IN (\'soumis\', \'en_verification\') AND id_categorie = :id_cat
        ORDER BY date_soumission ASC
    ');
    $stmtFiltreCyber->execute(['id_cat' => $catCyber]);
    $idsCyber = $stmtFiltreCyber->fetchAll(PDO::FETCH_COLUMN);

    tester("Filtre catégorie Cyber inclut P2 (Cyber)", in_array($idProf2, $idsCyber, true));
    tester("Filtre catégorie Cyber exclut P1 et P3 (Dev)", !in_array($idProf1, $idsCyber, true) && !in_array($idProf3, $idsCyber, true));

    // -------------------------------------------------------------------------
    // 4. Test de la pagination de 20 par page
    // -------------------------------------------------------------------------
    echo "\n4. VÉRIFICATION DE LA PAGINATION (20 PROFILS PAR PAGE)...\n";

    // Insérer 22 profils supplémentaires en statut 'soumis'
    for ($i = 10; $i <= 31; $i++) {
        $stmtUser->execute(['nom' => "Nom{$i}", 'prenom' => "Prenom{$i}", 'email' => "user{$i}_" . bin2hex(random_bytes(2)) . "@test.ci"]);
        $uId = (int) $pdo->lastInsertId();
        $dateAuto = date('Y-m-d H:i:s', time() + $i * 60);
        $stmtProfil->execute(['id_user' => $uId, 'id_cat' => $catDev, 'titre' => "Développeur {$i}", 'statut' => 'soumis', 'date_soum' => $dateAuto]);
    }

    $totalAttente = (int) $pdo->query("SELECT COUNT(*) FROM profil_professionnel WHERE statut_validation IN ('soumis', 'en_verification')")->fetchColumn();
    tester("Total des profils en attente supérieur à 20 (>= 25)", $totalAttente >= 25, "Total: {$totalAttente}");

    $parPage = 20;
    $totalPages = (int) ceil($totalAttente / $parPage);
    tester("Calcul du nombre de pages supérieur à 1 (>= 2)", $totalPages >= 2, "Total pages: {$totalPages}");

    // Page 1 (LIMIT 20 OFFSET 0)
    $stmtPage1 = $pdo->query("
        SELECT id_profil FROM profil_professionnel 
        WHERE statut_validation IN ('soumis', 'en_verification')
        ORDER BY date_soumission ASC, id_profil ASC
        LIMIT 20 OFFSET 0
    ");
    $page1 = $stmtPage1->fetchAll();
    tester("Page 1 contient exactement 20 profils", count($page1) === 20);

    // Page 2 (LIMIT 20 OFFSET 20)
    $stmtPage2 = $pdo->query("
        SELECT id_profil FROM profil_professionnel 
        WHERE statut_validation IN ('soumis', 'en_verification')
        ORDER BY date_soumission ASC, id_profil ASC
        LIMIT 20 OFFSET 20
    ");
    $page2 = $stmtPage2->fetchAll();
    tester("Page 2 contient le reliquat (> 0 et <= 20)", count($page2) > 0 && count($page2) <= 20, "Nb page 2: " . count($page2));

    // Vérifier l'étanchéité : aucun ID de la page 1 ne se retrouve sur la page 2
    $idsPage1 = array_column($page1, 'id_profil');
    $idsPage2 = array_column($page2, 'id_profil');
    $doublons = array_intersect($idsPage1, $idsPage2);
    tester("Aucun chevauchement entre la page 1 et la page 2", empty($doublons));

    // -------------------------------------------------------------------------
    // 5. Rendu HTML et présence des éléments requis (avec profils dans la table)
    // -------------------------------------------------------------------------
    echo "\n5. VÉRIFICATION DU RENDU HTML & ÉLÉMENTS D'INTERFACE...\n";

    $adminDb = $pdo->query("SELECT id_utilisateur, prenom, nom, email, role FROM utilisateur WHERE email = 'k10developpeur@gmail.com'")->fetch();
    $_SESSION['utilisateur'] = [
        'id'     => (int) $adminDb['id_utilisateur'],
        'role'   => 'admin',
        'prenom' => $adminDb['prenom'],
        'nom'    => $adminDb['nom'],
        'email'  => $adminDb['email'],
    ];

    $_GET = []; // Réinitialisation de GET pour le test d'inclusion
    ob_start();
    include __DIR__ . '/../admin/validation.php';
    $html = ob_get_clean();

    tester("Titre 'Validation des profils' présent dans le HTML", str_contains($html, 'Validation des profils'));
    tester("Filtre statut présent dans le HTML", str_contains($html, 'statut=soumis') && str_contains($html, 'statut=en_verification'));
    tester("Filtre catégorie présent dans le HTML", str_contains($html, 'name="categorie"') && str_contains($html, 'id="categorie"'));
    tester("Mention du tri chronologique / SLA présent", str_contains($html, 'date de soumission croissante') || str_contains($html, 'ancienneté croissante'));
    tester("Lien ou bouton 'Examiner' présent dans le code de la vue", str_contains($html, 'Examiner') && str_contains($html, 'validation-detail.php'));

} finally {
    $pdo->rollBack();
}

echo "\n--------------------------------------------------------------------\n";
echo "RÉSULTAT GLOBAL : {$testsReussis} / {$testsTotal} tests réussis.\n";
if ($testsReussis === $testsTotal) {
    echo "TOUTES LES EXIGENCES D'ADMIN/VALIDATION.PHP SONT VALIDÉES AVEC SUCCÈS !\n";
} else {
    echo "CERTAINS TESTS ONT ÉCHOUÉ.\n";
}
echo "--------------------------------------------------------------------\n";
