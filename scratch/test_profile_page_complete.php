<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';

$pdo = db();
$pdo->beginTransaction();

demarrer_session();

function testProfileRender(array $getParams, ?array $sessionUser = null): array {
    $_GET = $getParams;
    if ($sessionUser !== null) {
        $_SESSION['utilisateur'] = $sessionUser;
    } else {
        unset($_SESSION['utilisateur']);
    }

    ob_start();
    $httpCode = 200;
    try {
        require __DIR__ . '/../profile.php';
    } catch (Throwable $e) {
        echo "Exception caught: " . $e->getMessage() . "\n";
    }
    $content = ob_get_clean();
    $status = http_response_code();
    return ['status' => $status, 'html' => $content];
}

$checklist = [
    't01_approved_opens_without_login' => false,
    'unapproved_gives_404_public'      => false,
    'inactive_account_gives_404'       => false,
    'non_integer_id_gives_404'         => false,
    'owner_sees_preview_banner'        => false,
    'admin_sees_preview_banner'        => false,
    'other_user_sees_404_unapproved'   => false,
    'portfolio_and_skills_rendered'    => false,
    'badge_and_buttons_rendered'       => false,
];

try {
    // 1. Create approved pro: "Jean Dupont"
    $pdo->exec("INSERT INTO utilisateur (email, mot_de_passe_hash, nom, prenom, role, statut_compte) VALUES ('jean.dupont.prof@test.ci', 'hash', 'Dupont', 'Jean', 'professionnel', 'actif')");
    $uApproved = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO profil_professionnel (id_utilisateur, id_categorie, titre, description, zone, disponibilite, statut_validation, date_maj) VALUES ($uApproved, 1, 'Architecte Fullstack PHP', 'Expert en développement web sur mesure', 'Abidjan - Cocody', 'disponible', 'approuve', '2026-03-01 10:00:00')");
    $pApproved = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO profil_competence (id_profil, id_competence, niveau_declare) VALUES ($pApproved, 1, 'expert')");
    $pdo->exec("INSERT INTO projet_portfolio (id_profil, titre, description, lien_externe, image, date_projet) VALUES ($pApproved, 'Refonte E-commerce', 'Projet e-commerce complet', 'https://demo-portfolio.ci', 'uploads/portfolio/test.jpg', '2026-01-15')");

    // 2. Create unapproved pro: "Marc Secret" (statut 'soumis')
    $pdo->exec("INSERT INTO utilisateur (email, mot_de_passe_hash, nom, prenom, role, statut_compte) VALUES ('marc.secret@test.ci', 'hash', 'Secret', 'Marc', 'professionnel', 'actif')");
    $uUnapproved = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO profil_professionnel (id_utilisateur, id_categorie, titre, description, zone, disponibilite, statut_validation, date_maj) VALUES ($uUnapproved, 1, 'Dev Junior', 'En attente de validation', 'Yopougon', 'disponible', 'soumis', '2026-03-02 10:00:00')");
    $pUnapproved = (int) $pdo->lastInsertId();

    // 3. Create inactive account pro (statut_validation 'approuve' but statut_compte 'desactive')
    $pdo->exec("INSERT INTO utilisateur (email, mot_de_passe_hash, nom, prenom, role, statut_compte) VALUES ('inactif.prof@test.ci', 'hash', 'Banni', 'Luc', 'professionnel', 'desactive')");
    $uInactive = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO profil_professionnel (id_utilisateur, id_categorie, titre, description, zone, disponibilite, statut_validation, date_maj) VALUES ($uInactive, 1, 'Dev Banni', 'Compte desactive', 'Plateau', 'indisponible', 'approuve', '2026-03-03 10:00:00')");
    $pInactive = (int) $pdo->lastInsertId();

    // 4. Create admin user
    $pdo->exec("INSERT INTO utilisateur (email, mot_de_passe_hash, nom, prenom, role, statut_compte) VALUES ('admin.prof@test.ci', 'hash', 'Admin', 'Super', 'admin', 'actif')");
    $uAdmin = (int) $pdo->lastInsertId();

    // 5. Create random other user (client)
    $pdo->exec("INSERT INTO utilisateur (email, mot_de_passe_hash, nom, prenom, role, statut_compte) VALUES ('client.prof@test.ci', 'hash', 'Client', 'Simple', 'client', 'actif')");
    $uClient = (int) $pdo->lastInsertId();

    // ------------------------------------------------------------------------
    // CHECK 1: T01 - Un profil approuvé s'ouvre sans connexion
    // ------------------------------------------------------------------------
    echo "--- CHECK 1 : T01 - Profil approuvé accessible sans connexion ---\n";
    $res1 = testProfileRender(['id' => (string) $pApproved], null);
    if ($res1['status'] === 200 && str_contains($res1['html'], 'Architecte Fullstack PHP') && str_contains($res1['html'], 'Jean D.')) {
        echo "PASS : Le profil approuvé s'ouvre publiquement avec HTTP 200.\n";
        $checklist['t01_approved_opens_without_login'] = true;
    } else {
        echo "FAIL : Le profil approuvé n'a pas retourné 200 (status={$res1['status']}).\n";
    }

    // ------------------------------------------------------------------------
    // CHECK 2: L'URL d'un profil non approuvé donne une 404 pour le public
    // ------------------------------------------------------------------------
    echo "\n--- CHECK 2 : Profil non approuvé donne 404 ---\n";
    $res2 = testProfileRender(['id' => (string) $pUnapproved], null);
    if ($res2['status'] === 404 || str_contains($res2['html'], '404') || str_contains($res2['html'], 'introuvable')) {
        echo "PASS : Profil non approuvé renvoie HTTP 404.\n";
        $checklist['unapproved_gives_404_public'] = true;
    } else {
        echo "FAIL : Profil non approuvé ne renvoie pas 404 (status={$res2['status']}).\n";
    }

    // ------------------------------------------------------------------------
    // CHECK 3: Compte désactivé donne 404 pour le public
    // ------------------------------------------------------------------------
    echo "\n--- CHECK 3 : Compte désactivé donne 404 ---\n";
    $res3 = testProfileRender(['id' => (string) $pInactive], null);
    if ($res3['status'] === 404 || str_contains($res3['html'], '404') || str_contains($res3['html'], 'introuvable')) {
        echo "PASS : Compte désactivé renvoie HTTP 404.\n";
        $checklist['inactive_account_gives_404'] = true;
    } else {
        echo "FAIL : Compte désactivé ne renvoie pas 404 (status={$res3['status']}).\n";
    }

    // ------------------------------------------------------------------------
    // CHECK 4: Non-integer id donne 404
    // ------------------------------------------------------------------------
    echo "\n--- CHECK 4 : ID non entier donne 404 ---\n";
    $res4 = testProfileRender(['id' => 'abc_injection'], null);
    if ($res4['status'] === 404 || str_contains($res4['html'], '404') || str_contains($res4['html'], 'introuvable')) {
        echo "PASS : ID non entier renvoie HTTP 404.\n";
        $checklist['non_integer_id_gives_404'] = true;
    } else {
        echo "FAIL : ID non entier n'a pas renvoyé 404.\n";
    }

    // ------------------------------------------------------------------------
    // CHECK 5: Le propriétaire voit son profil avec le bandeau "Aperçu - non public"
    // ------------------------------------------------------------------------
    echo "\n--- CHECK 5 : Propriétaire voit l'aperçu avec bandeau ---\n";
    $ownerSession = [
        'id_utilisateur' => $uUnapproved,
        'nom' => 'Secret',
        'prenom' => 'Marc',
        'role' => 'professionnel',
    ];
    $res5 = testProfileRender(['id' => (string) $pUnapproved], $ownerSession);
    if ($res5['status'] === 200 && str_contains($res5['html'], 'Aperçu - non public') && str_contains($res5['html'], 'Dev Junior')) {
        echo "PASS : Le propriétaire voit son propre profil avec le bandeau 'Aperçu - non public'.\n";
        $checklist['owner_sees_preview_banner'] = true;
    } else {
        echo "FAIL : Le propriétaire n'a pas pu voir l'aperçu (status={$res5['status']}).\n";
    }

    // ------------------------------------------------------------------------
    // CHECK 6: L'administrateur voit l'aperçu avec le bandeau "Aperçu - non public"
    // ------------------------------------------------------------------------
    echo "\n--- CHECK 6 : Administrateur voit l'aperçu avec bandeau ---\n";
    $adminSession = [
        'id_utilisateur' => $uAdmin,
        'nom' => 'Admin',
        'prenom' => 'Super',
        'role' => 'admin',
    ];
    $res6 = testProfileRender(['id' => (string) $pUnapproved], $adminSession);
    if ($res6['status'] === 200 && str_contains($res6['html'], 'Aperçu - non public') && str_contains($res6['html'], 'Dev Junior')) {
        echo "PASS : L'administrateur voit le profil non approuvé avec le bandeau 'Aperçu - non public'.\n";
        $checklist['admin_sees_preview_banner'] = true;
    } else {
        echo "FAIL : L'admin n'a pas pu voir l'aperçu (status={$res6['status']}).\n";
    }

    // ------------------------------------------------------------------------
    // CHECK 7: Un autre utilisateur authentifié (non propriétaire, non admin) reçoit 404
    // ------------------------------------------------------------------------
    echo "\n--- CHECK 7 : Un autre utilisateur reçoit 404 sur profil non approuvé ---\n";
    $otherSession = [
        'id_utilisateur' => $uClient,
        'nom' => 'Client',
        'prenom' => 'Simple',
        'role' => 'client',
    ];
    $res7 = testProfileRender(['id' => (string) $pUnapproved], $otherSession);
    if ($res7['status'] === 404 || str_contains($res7['html'], '404') || str_contains($res7['html'], 'introuvable')) {
        echo "PASS : Un tiers authentifié reçoit 404 sur un profil non approuvé.\n";
        $checklist['other_user_sees_404_unapproved'] = true;
    } else {
        echo "FAIL : Un tiers a pu voir le profil non approuvé !\n";
    }

    // ------------------------------------------------------------------------
    // CHECK 8: Portfolio, compétences et liens externes rel/target
    // ------------------------------------------------------------------------
    echo "\n--- CHECK 8 : Portfolio avec rel noopener noreferrer nofollow et target _blank ---\n";
    $htmlApproved = $res1['html'];
    $hasRelNoopener = str_contains($htmlApproved, 'rel="noopener noreferrer nofollow"') || str_contains($htmlApproved, "rel='noopener noreferrer nofollow'");
    $hasTargetBlank = str_contains($htmlApproved, 'target="_blank"') || str_contains($htmlApproved, "target='_blank'");
    $hasSkillLevel = str_contains($htmlApproved, 'Expert') && str_contains($htmlApproved, 'PHP & Laravel');
    if ($hasRelNoopener && $hasTargetBlank && $hasSkillLevel) {
        echo "PASS : Portfolio et compétences avec niveaux correctement rendus.\n";
        $checklist['portfolio_and_skills_rendered'] = true;
    } else {
        echo "FAIL : Portfolio ou compétences mal formattés (hasRel=$hasRelNoopener, hasTarget=$hasTargetBlank, hasSkill=$hasSkillLevel).\n";
    }

    // ------------------------------------------------------------------------
    // CHECK 9: Badge et boutons "Envoyer une demande" et "Signaler"
    // ------------------------------------------------------------------------
    echo "\n--- CHECK 9 : Badge FindPro et boutons ---\n";
    $hasBadge = str_contains($htmlApproved, 'Profil approuvé par FindPro') && str_contains($htmlApproved, "ce badge n'est pas une certification de compétence");
    $hasDemandBtn = str_contains($htmlApproved, 'request_new.php?profil=' . $pApproved);
    $hasReportBtn = str_contains($htmlApproved, 'Signaler');
    if ($hasBadge && $hasDemandBtn && $hasReportBtn) {
        echo "PASS : Badge avec avertissement de compétence et boutons de demande/signalement présents.\n";
        $checklist['badge_and_buttons_rendered'] = true;
    } else {
        echo "FAIL : Éléments manquants (badge=$hasBadge, demand=$hasDemandBtn, report=$hasReportBtn).\n";
    }

    echo "\n=======================================================\n";
    echo "                RÉCAPITULATIF DU CHECKLIST             \n";
    echo "=======================================================\n";
    $allOk = true;
    foreach ($checklist as $k => $v) {
        echo ($v ? "[X]" : "[ ]") . " $k\n";
        if (!$v) $allOk = false;
    }
    echo "=======================================================\n";
    if ($allOk) {
        echo "TOUS LES TESTS ONT RÉUSSI AVEC SUCCÈS !\n";
    } else {
        echo "CERTAINS TESTS ONT ÉCHOUÉ.\n";
    }

} finally {
    $pdo->rollBack();
    echo "\nRollback effectué (base de données propre).\n";
}
