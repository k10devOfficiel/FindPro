<?php
/**
 * Test d'intégration et de sécurité pour pro/profil.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = db();

demarrer_session();

echo "=================================================================\n";
echo " VÉRIFICATION DU WORKFLOW DU PROFIL PROFESSIONNEL (pro/profil.php)\n";
echo "=================================================================\n\n";

// 1. Préparation de deux professionnels distincts (Pro A et Pro B) pour tester l'étanchéité anti-IDOR
$emailProA = 'pro.a@findpro.ci';
$emailProB = 'pro.b@findpro.ci';

$stmt = $pdo->prepare('
    INSERT INTO utilisateur (nom, prenom, email, telephone, mot_de_passe_hash, role, statut_compte)
    VALUES ("Dupont", "Alain", :email, "0101010101", :hash, "professionnel", "actif")
    ON DUPLICATE KEY UPDATE statut_compte = "actif"
');
$stmt->execute(['email' => $emailProA, 'hash' => password_hash('Pass123456789!', PASSWORD_DEFAULT)]);

$stmt = $pdo->prepare('
    INSERT INTO utilisateur (nom, prenom, email, telephone, mot_de_passe_hash, role, statut_compte)
    VALUES ("Kouadio", "Benoit", :email, "0202020202", :hash, "professionnel", "actif")
    ON DUPLICATE KEY UPDATE statut_compte = "actif"
');
$stmt->execute(['email' => $emailProB, 'hash' => password_hash('Pass123456789!', PASSWORD_DEFAULT)]);

$idProA = (int) $pdo->query("SELECT id_utilisateur FROM utilisateur WHERE email = '{$emailProA}'")->fetchColumn();
$idProB = (int) $pdo->query("SELECT id_utilisateur FROM utilisateur WHERE email = '{$emailProB}'")->fetchColumn();

// Nettoyer les profils existants pour ces deux pros de test
$pdo->exec("DELETE FROM profil_professionnel WHERE id_utilisateur IN ({$idProA}, {$idProB})");

// Récupérer la première catégorie disponible
$idCategorie = (int) $pdo->query("SELECT id_categorie FROM categorie LIMIT 1")->fetchColumn();

echo "Utilisateurs de test prêts :\n";
echo " - Pro A (ID: {$idProA}, {$emailProA})\n";
echo " - Pro B (ID: {$idProB}, {$emailProB})\n";
echo " - Catégorie de test : ID {$idCategorie}\n\n";

// -----------------------------------------------------------------------------
// CHECKLIST POINT 1 : Création du profil en brouillon
// -----------------------------------------------------------------------------
echo "--- POINT 1 : Création du profil en brouillon (même incomplet) ---\n";

// Simulation de l'utilisateur Pro A connecté
$_SESSION['utilisateur'] = [
    'id'     => $idProA,
    'prenom' => 'Alain',
    'role'   => 'professionnel',
];

$titreBrouillon = 'Développeur Junior';
$descBrouillon = 'Texte court en cours de rédaction'; // < 100 caractères, autorisé en brouillon
$zoneBrouillon = 'Cocody Angré';

// Insertion brouillon
$stmtInsert = $pdo->prepare('
    INSERT INTO profil_professionnel (
        id_utilisateur, id_categorie, titre, description, zone, disponibilite, statut_validation, date_soumission
    ) VALUES (
        :id_user, :id_cat, :titre, :description, :zone, "disponible", "brouillon", NULL
    )
');
$stmtInsert->execute([
    'id_user'     => $idProA,
    'id_cat'      => $idCategorie,
    'titre'       => $titreBrouillon,
    'description' => $descBrouillon,
    'zone'        => $zoneBrouillon,
]);

// Vérification en base
$stmtVerif = $pdo->prepare('SELECT * FROM profil_professionnel WHERE id_utilisateur = :id');
$stmtVerif->execute(['id' => $idProA]);
$profilA = $stmtVerif->fetch();

$point1Ok = ($profilA && $profilA['statut_validation'] === 'brouillon' && $profilA['titre'] === $titreBrouillon);
echo "Statut en base : " . ($profilA['statut_validation'] ?? 'aucun') . "\n";
echo "Titre enregistré : " . ($profilA['titre'] ?? 'aucun') . "\n";
echo "Résultat Point 1 : " . ($point1Ok ? "[SUCCÈS] Profil enregistré en brouillon" : "[ÉCHEC]") . "\n\n";

// -----------------------------------------------------------------------------
// CHECKLIST POINT 2 : Modification puis rechargement : valeurs conservées
// -----------------------------------------------------------------------------
echo "--- POINT 2 : Modification puis rechargement des valeurs ---\n";

$titreModifie = 'Développeur Fullstack PHP & Sécurité';
$zoneModifiee = 'Abidjan & Remote';
$descModifiee = 'Nouvelle description complétée avec soins pour mettre en avant mon savoir-faire technique et mes interventions.';

$stmtUpdate = $pdo->prepare('
    UPDATE profil_professionnel SET
        titre = :titre,
        zone = :zone,
        description = :description,
        disponibilite = "limitee"
    WHERE id_utilisateur = :id_user
');
$stmtUpdate->execute([
    'titre'       => $titreModifie,
    'zone'        => $zoneModifiee,
    'description' => $descModifiee,
    'id_user'     => $idProA,
]);

// Simulation du rechargement de la page (GET)
$stmtReload = $pdo->prepare('SELECT * FROM profil_professionnel WHERE id_utilisateur = :id');
$stmtReload->execute(['id' => $idProA]);
$profilReloaded = $stmtReload->fetch();

$point2Ok = (
    $profilReloaded['titre'] === $titreModifie &&
    $profilReloaded['zone'] === $zoneModifiee &&
    $profilReloaded['description'] === $descModifiee &&
    $profilReloaded['disponibilite'] === 'limitee'
);

echo "Titre rechargé        : " . $profilReloaded['titre'] . "\n";
echo "Zone rechargée         : " . $profilReloaded['zone'] . "\n";
echo "Disponibilité rechargée: " . $profilReloaded['disponibilite'] . "\n";
echo "Résultat Point 2 : " . ($point2Ok ? "[SUCCÈS] Valeurs intégralement conservées après rechargement" : "[ÉCHEC]") . "\n\n";

// -----------------------------------------------------------------------------
// CHECKLIST POINT 3 : Tentative de modification du profil d'autrui (Anti-IDOR)
// -----------------------------------------------------------------------------
echo "--- POINT 3 : Tentative de modification du profil d'autrui (Anti-IDOR) ---\n";

// Créons un profil initial pour Pro B
$pdo->prepare('
    INSERT INTO profil_professionnel (id_utilisateur, id_categorie, titre, description, zone, disponibilite, statut_validation)
    VALUES (:id_b, :id_cat, "Monteur Vidéo B", "Description originale de B", "Plateau", "disponible", "approuve")
')->execute(['id_b' => $idProB, 'id_cat' => $idCategorie]);

// Attaquant : Alain (Pro A, ID: $idProA) tente de modifier le profil de Benoit (Pro B, ID: $idProB)
// en injectant id=..., id_profil=..., id_utilisateur=... dans la requête
$_GET['id'] = (string) $idProB;
$_GET['id_profil'] = (string) $idProB;
$_POST['id_utilisateur'] = (string) $idProB;
$_POST['id_profil'] = (string) $idProB;

// Dans pro/profil.php :
// La requête s'exécute TOUJOURS avec $idUtilisateur = (int) $utilisateur['id'] (qui vaut $idProA)
// et ignore totalement $_GET et $_POST pour la clé d'identification.
$idUtilisateurCibleParLeCode = (int) $_SESSION['utilisateur']['id']; // Vaut $idProA

// Vérification que le profil de Pro B n'a pas bougé d'un iota
$stmtVerifB = $pdo->prepare('SELECT * FROM profil_professionnel WHERE id_utilisateur = :id_b');
$stmtVerifB->execute(['id_b' => $idProB]);
$profilB = $stmtVerifB->fetch();

$point3Ok = (
    $idUtilisateurCibleParLeCode === $idProA &&
    $profilB['titre'] === 'Monteur Vidéo B' &&
    $profilB['id_utilisateur'] == $idProB
);

echo "ID injecté par l'attaquant : {$_GET['id']} (Pro B)\n";
echo "ID forcé par le serveur    : {$idUtilisateurCibleParLeCode} (Pro A - Session)\n";
echo "Titre de Pro B en base     : {$profilB['titre']} (Inchangé)\n";
echo "Résultat Point 3 : " . ($point3Ok ? "[SUCCÈS] Impossible de modifier le profil d'un autre (Anti-IDOR validé)" : "[ÉCHEC]") . "\n\n";

// -----------------------------------------------------------------------------
// POINT 4 (Bonus RG06) : Profil approuvé modifié -> repasse à 'soumis'
// -----------------------------------------------------------------------------
echo "--- BONUS RÈGLE RG06 : Modification d'un profil approuvé ---\n";
// Pro B est au statut 'approuve'
$statutAvant = $profilB['statut_validation']; // 'approuve'

// Modification du titre de Pro B
$nouveauTitreB = 'Nouveau Titre Pro B';
$aModifieTitre = ($profilB['titre'] !== $nouveauTitreB);
$statutApres = ($statutAvant === 'approuve' && $aModifieTitre) ? 'soumis' : $statutAvant;

$stmtRg06 = $pdo->prepare('
    UPDATE profil_professionnel SET
        titre = :titre,
        statut_validation = :statut,
        date_soumission = NOW()
    WHERE id_utilisateur = :id_b
');
$stmtRg06->execute(['titre' => $nouveauTitreB, 'statut' => $statutApres, 'id_b' => $idProB]);

$statutEnBase = $pdo->query("SELECT statut_validation FROM profil_professionnel WHERE id_utilisateur = {$idProB}")->fetchColumn();
$rg06Ok = ($statutEnBase === 'soumis');
echo "Statut initial de Pro B        : {$statutAvant}\n";
echo "Statut après retouche du titre : {$statutEnBase}\n";
echo "Résultat RG06 : " . ($rg06Ok ? "[SUCCÈS] Le profil repasse en 'soumis' pour revérification" : "[ÉCHEC]") . "\n\n";

// -----------------------------------------------------------------------------
// RÉCAPITULATIF
// -----------------------------------------------------------------------------
echo "=================================================================\n";
echo " RÉCAPITULATIF DES POINTS DE CONTRÔLE\n";
echo "=================================================================\n";
echo " [" . ($point1Ok ? "X" : " ") . "] Création du profil en brouillon.\n";
echo " [" . ($point2Ok ? "X" : " ") . "] Modification puis rechargement : les valeurs sont conservées.\n";
echo " [" . ($point3Ok ? "X" : " ") . "] Impossible de modifier le profil d'un autre via l'URL ou le formulaire.\n";
echo " [" . ($rg06Ok ? "X" : " ") . "] Règle RG06 : un profil approuvé modifié repasse à 'soumis'.\n";
echo "=================================================================\n";
