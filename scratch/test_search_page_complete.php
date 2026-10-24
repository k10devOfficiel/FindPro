<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db();
$pdo->beginTransaction();

function runSearchPage(array $getParams): string {
    $_GET = $getParams;
    ob_start();
    require __DIR__ . '/../search.php';
    return ob_get_clean();
}

$checklist = [
    'keyword_search'       => false,
    'unapproved_rejected'  => false,
    'percent_underscore'   => false,
    'invalid_tri_safe'     => false,
    'mobile_collapse'      => false,
    'active_filter_chips'  => false,
    'helpful_empty_state'  => false,
];

try {
    // Setup test profiles:
    // 1. Approved pro: "Jean Dupont", title "Développeur Symfony 100% PHP", zone "Cocody", dispo "disponible", cat 1 (developpement), comp 1 (PHP & Laravel)
    $pdo->exec("INSERT INTO utilisateur (email, mot_de_passe_hash, nom, prenom, role, statut_compte) VALUES ('jean.dupont@test.ci', 'hash', 'Dupont', 'Jean', 'professionnel', 'actif')");
    $u1 = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO profil_professionnel (id_utilisateur, id_categorie, titre, description, zone, disponibilite, statut_validation, date_maj) VALUES ($u1, 1, 'Développeur Symfony 100% PHP', 'Expert en bases de données SQL_PRO', 'Cocody', 'disponible', 'approuve', '2026-02-10 10:00:00')");
    $p1 = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO profil_competence (id_profil, id_competence, niveau_declare) VALUES ($p1, 1, 'expert')");

    // 2. Unapproved pro: "Secret Unapproved", name "HackerZ", title "HackerZ Non Approuve", cat 1, comp 1
    $pdo->exec("INSERT INTO utilisateur (email, mot_de_passe_hash, nom, prenom, role, statut_compte) VALUES ('secret.unapproved@test.ci', 'hash', 'HackerZ', 'Secret', 'professionnel', 'actif')");
    $u2 = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO profil_professionnel (id_utilisateur, id_categorie, titre, description, zone, disponibilite, statut_validation, date_maj) VALUES ($u2, 1, 'HackerZ Non Approuve', 'Description pirate', 'Abidjan', 'disponible', 'en_attente', '2026-02-11 10:00:00')");
    $p2 = (int) $pdo->lastInsertId();

    // 3. Inactive account pro: "Inactif", title "Dev Inactif", statut_validation 'approuve', statut_compte 'suspendu'
    $pdo->exec("INSERT INTO utilisateur (email, mot_de_passe_hash, nom, prenom, role, statut_compte) VALUES ('inactif@test.ci', 'hash', 'Inactif', 'Paul', 'professionnel', 'suspendu')");
    $u3 = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO profil_professionnel (id_utilisateur, id_categorie, titre, description, zone, disponibilite, statut_validation, date_maj) VALUES ($u3, 1, 'Dev Inactif Approuve', 'Description', 'Abidjan', 'disponible', 'approuve', '2026-02-12 10:00:00')");
    $p3 = (int) $pdo->lastInsertId();

    // ------------------------------------------------------------------------
    // TEST 1: Keyword search returns correct results
    // ------------------------------------------------------------------------
    echo "--- TEST 1 : Recherche mot-clé ---\n";
    $html1 = runSearchPage(['q' => 'Symfony']);
    if (str_contains($html1, 'Symfony') && str_contains($html1, 'Jean D.')) {
        echo "PASS : Le profil approuvé avec 'Symfony' a été trouvé.\n";
        $checklist['keyword_search'] = true;
    } else {
        echo "FAIL : Le mot-clé n'a pas retourné le bon profil.\n";
    }

    // ------------------------------------------------------------------------
    // TEST 2: Unapproved profile NEVER appears, even with exact search
    // ------------------------------------------------------------------------
    echo "\n--- TEST 2 : Profil non approuvé exclu même avec nom exact ---\n";
    $html2 = runSearchPage(['q' => 'HackerZ']);
    if (!str_contains($html2, 'HackerZ Non Approuve') && str_contains($html2, '0</strong> résultat') && str_contains($html2, 'Aucun professionnel ne correspond à vos critères')) {
        echo "PASS : Profil 'en_attente' totalement invisible même avec son nom exact.\n";
        $checklist['unapproved_rejected'] = true;
    } else {
        echo "FAIL : Profil non approuvé détecté dans la recherche !\n";
    }

    // Also test inactive user
    $html2b = runSearchPage(['q' => 'Inactif']);
    if (!str_contains($html2b, 'Dev Inactif Approuve')) {
        echo "PASS : Utilisateur suspendu exclu même si profil approuvé.\n";
    } else {
        echo "FAIL : Utilisateur suspendu présent dans la recherche !\n";
    }

    // ------------------------------------------------------------------------
    // TEST 3: Special characters % and _ don't break anything and match correctly
    // ------------------------------------------------------------------------
    echo "\n--- TEST 3 : Recherche avec % et _ ---\n";
    $html3_percent = runSearchPage(['q' => '100%']);
    $percentOk = str_contains($html3_percent, '100% PHP');

    $html3_underscore = runSearchPage(['q' => 'SQL_PRO']);
    $underscoreOk = str_contains($html3_underscore, 'Symfony 100% PHP');

    $html3_only_percent = runSearchPage(['q' => '%']);
    $onlyPercentOk = !str_contains($html3_only_percent, 'Fatal error') && !str_contains($html3_only_percent, 'SQLSTATE');

    $html3_only_underscore = runSearchPage(['q' => '_']);
    $onlyUnderscoreOk = !str_contains($html3_only_underscore, 'Fatal error') && !str_contains($html3_only_underscore, 'SQLSTATE');

    if ($percentOk && $underscoreOk && $onlyPercentOk && $onlyUnderscoreOk) {
        echo "PASS : Les caractères % et _ sont correctement échappés et n'engendrent aucune erreur.\n";
        $checklist['percent_underscore'] = true;
    } else {
        echo "FAIL : Problème avec % ou _ (percentOk=$percentOk, underscoreOk=$underscoreOk).\n";
    }

    // ------------------------------------------------------------------------
    // TEST 4: Invalid tri parameter produces no errors
    // ------------------------------------------------------------------------
    echo "\n--- TEST 4 : Paramètre tri=invalide ---\n";
    $html4 = runSearchPage(['tri' => 'HACK_INVALIDE_ORDER_BY']);
    $hasPhpError = str_contains($html4, 'Fatal error') 
        || str_contains($html4, 'SQLSTATE') 
        || str_contains($html4, 'PHP Notice') 
        || str_contains($html4, '<b>Notice</b>')
        || str_contains($html4, 'PHP Warning')
        || str_contains($html4, '<b>Warning</b>');
    if (!$hasPhpError) {
        echo "PASS : tri=HACK_INVALIDE_ORDER_BY géré en toute sécurité sans erreur.\n";
        $checklist['invalid_tri_safe'] = true;
    } else {
        echo "FAIL : tri invalide a causé une erreur !\n";
    }

    // ------------------------------------------------------------------------
    // TEST 5: Mobile collapsible panel
    // ------------------------------------------------------------------------
    echo "\n--- TEST 5 : Panneau repliable mobile ---\n";
    if (str_contains($html1, 'data-bs-toggle="collapse"') && 
        str_contains($html1, 'data-bs-target="#filtresMobilePanneau"') && 
        str_contains($html1, 'id="filtresMobilePanneau"') &&
        str_contains($html1, 'collapse d-lg-block')) {
        echo "PASS : Panneau mobile repliable Bootstrap 5 présent et conforme.\n";
        $checklist['mobile_collapse'] = true;
    } else {
        echo "FAIL : Panneau repliable mobile manquant ou incomplet.\n";
    }

    // ------------------------------------------------------------------------
    // TEST 6: Active filter chips and removal buttons
    // ------------------------------------------------------------------------
    echo "\n--- TEST 6 : Filtres actifs avec boutons de retrait ---\n";
    $html6 = runSearchPage([
        'q' => 'Symfony',
        'categorie' => 'developpement',
        'disponibilite' => 'disponible',
        'zone' => 'Cocody',
    ]);
    if (str_contains($html6, 'Filtres appliqués :') && 
        str_contains($html6, 'Mot-clé : « Symfony »') && 
        str_contains($html6, 'Catégorie : Développement informatique') && 
        str_contains($html6, 'Disponibilité : Disponible immédiatement') && 
        str_contains($html6, 'Zone : Cocody') && 
        str_contains($html6, 'Tout effacer')) {
        echo "PASS : Filtres actifs et liens de retrait individuels présents.\n";
        $checklist['active_filter_chips'] = true;
    } else {
        echo "FAIL : Badges de filtres actifs manquants ou erronés.\n";
    }

    // ------------------------------------------------------------------------
    // TEST 7: Helpful empty state message
    // ------------------------------------------------------------------------
    echo "\n--- TEST 7 : Message utile en cas d'absence de résultat ---\n";
    $html7 = runSearchPage(['q' => 'RechercheIntrouvable999999']);
    if (str_contains($html7, 'Aucun professionnel ne correspond à vos critères') && 
        str_contains($html7, 'Conseils pour trouver un professionnel') && 
        str_contains($html7, 'Réinitialiser tous les filtres')) {
        echo "PASS : Message utile d'absence de résultat présent avec conseils et actions.\n";
        $checklist['helpful_empty_state'] = true;
    } else {
        echo "FAIL : Message utile d'absence de résultat absent.\n";
    }

    echo "\n--- RÉCAPITULATIF DU CHECKLIST ---\n";
    foreach ($checklist as $cle => $val) {
        echo ($val ? "[X]" : "[ ]") . " $cle\n";
    }

} finally {
    $pdo->rollBack();
    echo "\nRollback effectué (base de données propre).\n";
}
