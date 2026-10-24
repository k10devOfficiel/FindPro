<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = db();
$pdo->beginTransaction();

try {
    // 1. Create a pro with complete data
    $pdo->exec("INSERT INTO utilisateur (email, mot_de_passe_hash, nom, prenom, role, statut_compte) VALUES ('pro.test.content@findpro.ci', 'hash', 'Kouassi', 'Aimé', 'professionnel', 'actif')");
    $uid = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO profil_professionnel (id_utilisateur, id_categorie, titre, description, zone, disponibilite, statut_validation, date_maj, engagement_cyber) VALUES ($uid, 3, 'Spécialiste Sécurité & Audit', 'Audit de code source et tests d intrusion', 'Abidjan - Plateau', 'disponible', 'approuve', '2026-03-01 12:00:00', 1)");
    $pid = (int) $pdo->lastInsertId();

    $pdo->exec("INSERT INTO profil_competence (id_profil, id_competence, niveau_declare) VALUES ($pid, 25, 'expert')");
    $pdo->exec("INSERT INTO profil_competence (id_profil, id_competence, niveau_declare) VALUES ($pid, 26, 'avance')");

    $pdo->exec("INSERT INTO projet_portfolio (id_profil, titre, description, lien_externe, image, date_projet) VALUES ($pid, 'Audit E-Banking', 'Audit de sécurité applicative', 'https://securite-audit.ci', 'uploads/portfolio/audit.jpg', '2026-02-01')");

    // Client user & review
    $pdo->exec("INSERT INTO utilisateur (email, mot_de_passe_hash, nom, prenom, role, statut_compte) VALUES ('client.test.content@findpro.ci', 'hash', 'Yao', 'Sarah', 'client', 'actif')");
    $uClient = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO demande_service (id_client, id_profil, id_categorie, titre, description, statut) VALUES ($uClient, $pid, 3, 'Mission Audit', 'Audit rapide', 'terminee')");
    $did = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO avis (id_demande, id_client, note, commentaire, statut) VALUES ($did, $uClient, 5, 'Intervention exemplaire et rapport très clair', 'publie')");

    $_GET['id'] = (string) $pid;
    ob_start();
    require __DIR__ . '/../profile.php';
    $html = ob_get_clean();

    echo "=== VÉRIFICATION CONTENU DU PROFIL ===\n";
    echo "1. Titre & Nom : " . (str_contains($html, 'Aimé K.') && str_contains($html, 'Spécialiste Sécurité &amp; Audit') ? 'PASS' : 'FAIL') . "\n";
    echo "2. Compétences & Niveaux : " . (str_contains($html, 'Audit de sécurité de code source') && str_contains($html, 'Expert') ? 'PASS' : 'FAIL') . "\n";
    echo "3. Portfolio Titre : " . (str_contains($html, 'Audit E-Banking') ? 'PASS' : 'FAIL') . "\n";
    echo "4. Portfolio Lien rel noopener noreferrer nofollow : " . (str_contains($html, 'rel="noopener noreferrer nofollow"') ? 'PASS' : 'FAIL') . "\n";
    echo "5. Portfolio Lien target _blank : " . (str_contains($html, 'target="_blank"') ? 'PASS' : 'FAIL') . "\n";
    echo "6. Avis publié : " . (str_contains($html, 'Intervention exemplaire') && str_contains($html, 'Sarah Y.') ? 'PASS' : 'FAIL') . "\n";
    echo "7. Note moyenne : " . (str_contains($html, '5') ? 'PASS' : 'FAIL') . "\n";
    echo "8. Badge approbation : " . (str_contains($html, 'Profil approuvé par FindPro') ? 'PASS' : 'FAIL') . "\n";
    echo "9. Mention certification : " . (str_contains($html, "ce badge n'est pas une certification de compétence") ? 'PASS' : 'FAIL') . "\n";
    echo "10. Encadré déontologique RG11 : " . (str_contains($html, 'Engagement cybersécurité (RG11)') ? 'PASS' : 'FAIL') . "\n";
    echo "11. Bouton Envoyer une demande : " . (str_contains($html, "request_new.php?profil={$pid}") ? 'PASS' : 'FAIL') . "\n";
    echo "12. Bouton Signaler : " . (str_contains($html, 'Signaler ce profil') ? 'PASS' : 'FAIL') . "\n";

} finally {
    $pdo->rollBack();
    echo "\nRollback effectué (base propre).\n";
}
