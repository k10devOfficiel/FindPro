<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db();
$pdo->beginTransaction();

try {
    // Insert 3 users & profiles
    // Profile A: no reviews
    $pdo->exec("INSERT INTO utilisateur (email, mot_de_passe_hash, nom, prenom, role, statut_compte) VALUES ('u_a@findpro.ci', 'h', 'Sans', 'Avis', 'professionnel', 'actif')");
    $uA = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO profil_professionnel (id_utilisateur, id_categorie, titre, description, zone, disponibilite, statut_validation, date_maj) VALUES ($uA, 1, 'Dev Sans Avis', 'Desc', 'Abidjan', 'disponible', 'approuve', '2026-02-01 12:00:00')");
    $pA = (int) $pdo->lastInsertId();

    // Client user
    $pdo->exec("INSERT INTO utilisateur (email, mot_de_passe_hash, nom, prenom, role, statut_compte) VALUES ('client_test@findpro.ci', 'h', 'Client', 'Test', 'client', 'actif')");
    $uClient = (int) $pdo->lastInsertId();

    // Profile B: note 3.0
    $pdo->exec("INSERT INTO utilisateur (email, mot_de_passe_hash, nom, prenom, role, statut_compte) VALUES ('u_b@findpro.ci', 'h', 'Moyen', 'Avis', 'professionnel', 'actif')");
    $uB = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO profil_professionnel (id_utilisateur, id_categorie, titre, description, zone, disponibilite, statut_validation, date_maj) VALUES ($uB, 1, 'Dev Note 3', 'Desc', 'Abidjan', 'disponible', 'approuve', '2026-01-01 12:00:00')");
    $pB = (int) $pdo->lastInsertId();
    // Demande & Avis 3.0
    $pdo->exec("INSERT INTO demande_service (id_client, id_profil, id_categorie, titre, description, statut) VALUES ($uClient, $pB, 1, 'Demande', 'Desc', 'terminee')");
    $d1 = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO avis (id_demande, note, commentaire, statut) VALUES ($d1, 3, 'Moyen', 'publie')");

    // Profile C: note 5.0
    $pdo->exec("INSERT INTO utilisateur (email, mot_de_passe_hash, nom, prenom, role, statut_compte) VALUES ('u_c@findpro.ci', 'h', 'Top', 'Avis', 'professionnel', 'actif')");
    $uC = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO profil_professionnel (id_utilisateur, id_categorie, titre, description, zone, disponibilite, statut_validation, date_maj) VALUES ($uC, 1, 'Dev Note 5', 'Desc', 'Abidjan', 'disponible', 'approuve', '2026-01-01 12:00:00')");
    $pC = (int) $pdo->lastInsertId();
    // Demande & Avis 5.0
    $pdo->exec("INSERT INTO demande_service (id_client, id_profil, id_categorie, titre, description, statut) VALUES ($uClient, $pC, 1, 'Demande', 'Desc', 'terminee')");
    $d2 = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO avis (id_demande, note, commentaire, statut) VALUES ($d2, 5, 'Excellent', 'publie')");

    $resultatsNote = profils_publics($pdo, ['tri' => 'note'], 10, 0);
    echo "Classement par note :\n";
    $ordreIds = [];
    foreach ($resultatsNote as $p) {
        if (in_array($p['id_profil'], [$pA, $pB, $pC], true)) {
            echo "- #{$p['id_profil']} {$p['titre']} | Note: " . ($p['note_moyenne'] ?? 'AUCUN AVIS') . "\n";
            $ordreIds[] = $p['id_profil'];
        }
    }

    if ($ordreIds === [$pC, $pB, $pA]) {
        echo "SUCCESS: Ordre strict respecté : Note 5.0 (#$pC) -> Note 3.0 (#$pB) -> Sans avis (#$pA) en dernier !\n";
    } else {
        echo "FAIL: Ordre incorrect : " . implode(', ', $ordreIds) . "\n";
    }
} finally {
    $pdo->rollBack();
    echo "Transaction rollback.\n";
}
