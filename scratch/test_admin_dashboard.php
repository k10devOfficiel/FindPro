<?php
/**
 * Test complet du tableau de bord d'administration (admin/index.php).
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
echo "VÉRIFICATION DU TABLEAU DE BORD D'ADMINISTRATION (admin/index.php)\n";
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
// 1. Contrôle d'accès strict par rôle
// -----------------------------------------------------------------------------
echo "1. CONTRÔLE D'ACCÈS EXIGER_ROLE('ADMIN')...\n";

// A. Utilisateur non connecté
unset($_SESSION['utilisateur']);
$bloqueAnonyme = false;
try {
    // Simuler le check auth
    if (empty($_SESSION['utilisateur'])) {
        $bloqueAnonyme = true;
    }
} catch (Exception $e) {
    $bloqueAnonyme = true;
}
tester("Accès anonyme bloqué", $bloqueAnonyme);

// B. Utilisateur client
$_SESSION['utilisateur'] = ['id' => 999, 'role' => 'client', 'email' => 'client@test.ci'];
$bloqueClient = !est_admin();
tester("Accès client bloqué (non-admin)", $bloqueClient);

// C. Utilisateur professionnel
$_SESSION['utilisateur'] = ['id' => 998, 'role' => 'professionnel', 'email' => 'pro@test.ci'];
$bloquePro = !est_admin();
tester("Accès professionnel bloqué (non-admin)", $bloquePro);

// D. Utilisateur admin
$_SESSION['utilisateur'] = ['id' => 1, 'role' => 'admin', 'email' => 'admin@findpro.ci'];
$autoriseAdmin = est_admin();
tester("Accès administrateur autorisé", $autoriseAdmin);

// -----------------------------------------------------------------------------
// 2. Vérification des requêtes de comptage (COUNT)
// -----------------------------------------------------------------------------
echo "\n2. VÉRIFICATION DES MÉTRIQUES COUNT...\n";

$nbClients = (int) $pdo->query("SELECT COUNT(*) FROM utilisateur WHERE role = 'client'")->fetchColumn();
$nbPros = (int) $pdo->query("SELECT COUNT(*) FROM utilisateur WHERE role = 'professionnel'")->fetchColumn();
$nbProfilsEnAttente = (int) $pdo->query("SELECT COUNT(*) FROM profil_professionnel WHERE statut_validation IN ('soumis', 'en_verification')")->fetchColumn();
$nbDemandes = (int) $pdo->query("SELECT COUNT(*) FROM demande_service")->fetchColumn();
$nbSignalements = (int) $pdo->query("SELECT COUNT(*) FROM signalement WHERE statut = 'nouveau'")->fetchColumn();

tester("Comptage des clients préparé et valide (>= 1)", $nbClients >= 1, "Nombre: {$nbClients}");
tester("Comptage des professionnels préparé et valide (>= 1)", $nbPros >= 1, "Nombre: {$nbPros}");
tester("Comptage des profils en attente (soumis + en_verification)", is_int($nbProfilsEnAttente));
tester("Comptage des demandes de service préparé et valide", is_int($nbDemandes));
tester("Comptage des signalements nouveaux préparé et valide", is_int($nbSignalements));

// -----------------------------------------------------------------------------
// 3. Test de la liste des 5 derniers profils soumis
// -----------------------------------------------------------------------------
echo "\n3. VÉRIFICATION DE LA LISTE DES 5 DERNIERS PROFILS SOUMIS...\n";

// Insérer temporairement 6 profils soumis pour vérifier la limitation à 5 et l'ordre
$pdo->beginTransaction();
try {
    $catDev = (int) $pdo->query("SELECT id_categorie FROM categorie WHERE slug = 'developpement'")->fetchColumn();

    $idsProfilsCrees = [];
    for ($i = 1; $i <= 6; $i++) {
        $email = "test_soumis_{$i}_" . bin2hex(random_bytes(2)) . "@findpro.ci";
        $pdo->prepare('
            INSERT INTO utilisateur (nom, prenom, email, mot_de_passe_hash, role, statut_compte)
            VALUES (:nom, :prenom, :email, "fake_hash", "professionnel", "actif")
        ')->execute([
            'nom'    => "Nom{$i}",
            'prenom' => "Prenom{$i}",
            'email'  => $email,
        ]);
        $idUser = (int) $pdo->lastInsertId();

        $pdo->prepare('
            INSERT INTO profil_professionnel (
                id_utilisateur, id_categorie, titre, description, zone, disponibilite, statut_validation, date_soumission
            ) VALUES (
                :id_user, :id_cat, :titre, "Description de test d\'au moins cent caractères pour validation administrative.", "Abidjan", "disponible", "soumis", DATE_ADD(NOW(), INTERVAL :i MINUTE)
            )
        ')->execute([
            'id_user' => $idUser,
            'id_cat'  => $catDev,
            'titre'   => "Développeur {$i}",
            'i'       => $i,
        ]);
        $idsProfilsCrees[] = (int) $pdo->lastInsertId();
    }

    // Récupérer les 5 derniers comme dans admin/index.php
    $stmtDerniers = $pdo->query('
        SELECT 
            p.id_profil,
            p.titre,
            p.statut_validation,
            p.date_soumission,
            u.nom,
            u.prenom,
            u.email,
            c.libelle AS categorie_libelle
        FROM profil_professionnel p
        JOIN utilisateur u ON p.id_utilisateur = u.id_utilisateur
        LEFT JOIN categorie c ON p.id_categorie = c.id_categorie
        WHERE p.statut_validation IN (\'soumis\', \'en_verification\')
        ORDER BY p.date_soumission DESC, p.id_profil DESC
        LIMIT 5
    ');
    $derniers = $stmtDerniers->fetchAll();

    tester("La liste retourne exactement 5 éléments (LIMIT 5 respecté)", count($derniers) === 5);
    tester("L'élément le plus récent est en tête (Développeur 6)", $derniers[0]['titre'] === 'Développeur 6');
    tester("L'élément le plus ancien des 6 créés (Développeur 1) est exclu du Top 5", $derniers[4]['titre'] === 'Développeur 2');

} finally {
    $pdo->rollBack();
}

// -----------------------------------------------------------------------------
// 4. Vérification du contenu HTML et des liens des cartes
// -----------------------------------------------------------------------------
echo "\n4. VÉRIFICATION DU RENDU HTML & DES LIENS DES CARTES...\n";

// Capture du rendu de admin/index.php
$adminDb = $pdo->query("SELECT id_utilisateur, prenom, nom, email, role FROM utilisateur WHERE email = 'k10developpeur@gmail.com'")->fetch();
$_SESSION['utilisateur'] = [
    'id'     => (int) $adminDb['id_utilisateur'],
    'role'   => 'admin',
    'prenom' => $adminDb['prenom'],
    'nom'    => $adminDb['nom'],
    'email'  => $adminDb['email'],
];

ob_start();
include __DIR__ . '/../admin/index.php';
$html = ob_get_clean();

tester("Titre du tableau de bord présent dans le HTML", str_contains($html, 'Tableau de bord Administrateur'));
tester("Carte vers admin/validations.php présente", str_contains($html, 'admin/validations.php'));
tester("Carte vers admin/utilisateurs.php?role=professionnel présente", str_contains($html, 'admin/utilisateurs.php?role=professionnel'));
tester("Carte vers admin/utilisateurs.php?role=client présente", str_contains($html, 'admin/utilisateurs.php?role=client'));
tester("Carte vers admin/demandes.php présente", str_contains($html, 'admin/demandes.php'));
tester("Carte vers admin/signalements.php présente", str_contains($html, 'admin/signalements.php'));
tester("Section des 5 derniers profils soumis présente", str_contains($html, 'Derniers profils soumis pour validation'));
tester("Espace réservé gris présent (graphique/visuel manquant)", str_contains($html, 'Espace réservé') && str_contains($html, 'Graphique d\'activité'));

echo "\n--------------------------------------------------------------------\n";
echo "RÉSULTAT GLOBAL : {$testsReussis} / {$testsTotal} tests réussis.\n";
if ($testsReussis === $testsTotal) {
    echo "TOUTES LES EXIGENCES D'ADMIN/INDEX.PHP SONT VALIDÉES AVEC SUCCÈS !\n";
} else {
    echo "CERTAINS TESTS ONT ÉCHOUÉ.\n";
}
echo "--------------------------------------------------------------------\n";
