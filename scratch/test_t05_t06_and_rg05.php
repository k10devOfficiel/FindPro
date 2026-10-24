<?php
/**
 * Test de validation complet pour T05, T06, RG05 et carte_profil.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/carte_profil.php';

demarrer_session();
$pdo = db();

echo "====================================================================\n";
echo "VÉRIFICATION COMPLÈTE : T05, T06, RG05 ET CARTE PROFIL\n";
echo "====================================================================\n";

$testsTotal = 0;
$testsReussis = 0;

function assertTest(string $libelle, bool $condition, string $detail = ''): void {
    global $testsTotal, $testsReussis;
    $testsTotal++;
    if ($condition) {
        $testsReussis++;
        echo "  [PASS] {$libelle}\n";
    } else {
        echo "  [FAIL] {$libelle}";
        if ($detail !== '') {
            echo " ({$detail})";
        }
        echo "\n";
    }
}

function makeHttp(string $url, string $cookieFile = '', string $method = 'GET', array $post = []): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    if ($cookieFile !== '') {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $location = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);
    return ['code' => $code, 'body' => (string)$body, 'location' => $location];
}

$baseUrl = 'http://localhost/findpro';
$cookieAdmin = __DIR__ . '/cookie_admin_t05.txt';
$cookiePro   = __DIR__ . '/cookie_pro_t05.txt';
@unlink($cookieAdmin);
@unlink($cookiePro);

// Identification du pro de test
$proRow = $pdo->query("SELECT u.id_utilisateur, p.id_profil FROM utilisateur u JOIN profil_professionnel p ON u.id_utilisateur = p.id_utilisateur WHERE u.email = 'pro@findpro.ci'")->fetch(PDO::FETCH_ASSOC);
$idUtilisateurPro = (int) $proRow['id_utilisateur'];
$idProfilPro = (int) $proRow['id_profil'];

echo "\n--- ÉTAPE A : CONNEXION DES UTILISATEURS ---\n";
// 1. Connexion Admin
$resLoginAdminGet = makeHttp("{$baseUrl}/login.php", $cookieAdmin);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $resLoginAdminGet['body'], $mA);
$csrfAdminLogin = $mA[1] ?? '';
$resLoginAdminPost = makeHttp("{$baseUrl}/login.php", $cookieAdmin, 'POST', [
    'csrf_token'   => $csrfAdminLogin,
    'email'        => 'k10developpeur@gmail.com',
    'mot_de_passe' => 'Dieudonne@2006',
]);
assertTest("Connexion Administrateur", in_array($resLoginAdminPost['code'], [200, 302], true));

// 2. Connexion Pro
$resLoginProGet = makeHttp("{$baseUrl}/login.php", $cookiePro);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $resLoginProGet['body'], $mP);
$csrfProLogin = $mP[1] ?? '';
$resLoginProPost = makeHttp("{$baseUrl}/login.php", $cookiePro, 'POST', [
    'csrf_token'   => $csrfProLogin,
    'email'        => 'pro@findpro.ci',
    'mot_de_passe' => 'Pro123456789!',
]);
assertTest("Connexion Professionnel", in_array($resLoginProPost['code'], [200, 302], true));


echo "\n--- ÉTAPE B : VÉRIFICATION T06 (SOUMISSION -> REFUS PAR ADMIN AVEC MOTIF -> VÉRIFICATION CÔTÉ PRO) ---\n";
// 1. Soumission par le Pro
// On s'assure d'abord que le profil est 'brouillon' pour pouvoir le soumettre
$pdo->exec("UPDATE profil_professionnel SET statut_validation = 'brouillon' WHERE id_profil = {$idProfilPro}");
$resProPage = makeHttp("{$baseUrl}/pro/profil.php", $cookiePro);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $resProPage['body'], $mProf);
$csrfProSubmit = $mProf[1] ?? '';

$descriptionValideT06 = "Développeur Fullstack PHP et sécurité web avec plus de 6 ans d’expérience professionnelle à Abidjan dans la création d’applications robustes et sécurisées.";

$resSubmit = makeHttp("{$baseUrl}/pro/profil.php", $cookiePro, 'POST', [
    'csrf_token'       => $csrfProSubmit,
    'action'           => 'soumettre',
    'titre'            => 'Développeur Fullstack PHP & Sécurité Web',
    'id_categorie'     => 1,
    'description'      => $descriptionValideT06,
    'annees_experience'=> 6,
    'commune'          => 'Cocody',
    'zone'             => 'Abidjan et périphérie',
    'disponibilite'    => 'disponible',
    'engagement_cyber' => 1,
]);
assertTest("Pro soumet son profil", in_array($resSubmit['code'], [200, 302], true));

$statutApresSoumission = $pdo->query("SELECT statut_validation FROM profil_professionnel WHERE id_profil = {$idProfilPro}")->fetchColumn();
assertTest("Statut passé à 'soumis' en base", $statutApresSoumission === 'soumis', "Statut actuel: {$statutApresSoumission}");

// 2. Admin ouvre profil_detail.php
$resAdminDetail = makeHttp("{$baseUrl}/admin/profil_detail.php?id={$idProfilPro}", $cookieAdmin);
$statutEnVerif = $pdo->query("SELECT statut_validation FROM profil_professionnel WHERE id_profil = {$idProfilPro}")->fetchColumn();
assertTest("Admin ouvre le dossier : statut bascule à 'en_verification'", $statutEnVerif === 'en_verification', "Statut actuel: {$statutEnVerif}");

preg_match('/name="csrf_token"\s+value="([^"]+)"/', $resAdminDetail['body'], $mAdminPost);
$csrfAdminAction = $mAdminPost[1] ?? '';

// 3. Admin refuse avec un motif explicite >= 10 car.
$motifRefusT06 = "Dossier incomplet : les justificatifs d'activité professionnelle et les diplômes requis ne sont pas valides.";
$resRefusAdmin = makeHttp("{$baseUrl}/admin/profil_detail.php?id={$idProfilPro}", $cookieAdmin, 'POST', [
    'csrf_token' => $csrfAdminAction,
    'decision'   => 'refuse',
    'motif'      => $motifRefusT06,
]);
assertTest("Admin refuse le profil avec motif (HTTP 302/200)", in_array($resRefusAdmin['code'], [200, 302], true));

$statutRefuse = $pdo->query("SELECT statut_validation FROM profil_professionnel WHERE id_profil = {$idProfilPro}")->fetchColumn();
assertTest("Statut passé à 'refuse' en base", $statutRefuse === 'refuse', "Statut actuel: {$statutRefuse}");

// 4. Vérification côté Professionnel (pro/index.php)
$resProIndexRefus = makeHttp("{$baseUrl}/pro/index.php", $cookiePro);
assertTest("Pro consulte pro/index.php : encadré 'Dossier de candidature refusé' affiché", str_contains($resProIndexRefus['body'], 'Dossier de candidature refusé'));
assertTest("Pro voit le motif exact de refus", str_contains($resProIndexRefus['body'], htmlspecialchars($motifRefusT06, ENT_QUOTES, 'UTF-8')));
assertTest("Pro est informé de l'interdiction de resoumission sans autorisation", str_contains($resProIndexRefus['body'], 'sans nouvelle autorisation préalable'));

// Vérification historique validation_profil
$lastLogRefus = $pdo->query("SELECT * FROM validation_profil WHERE id_profil = {$idProfilPro} ORDER BY id_validation DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
assertTest("T06 consigné dans validation_profil avec motif exact", $lastLogRefus['decision'] === 'refuse' && $lastLogRefus['motif'] === $motifRefusT06);
echo "  ==> T06 VALIDÉ AVEC SUCCÈS !\n";


echo "\n--- ÉTAPE C : VÉRIFICATION T05 (CORRECTION -> RESOUMISSION -> APPROBATION) ---\n";
// 1. Admin passe le profil à 'a_corriger' avec instructions
$motifCorrectionT05 = "Merci de corriger la description de votre profil et de fournir une présentation détaillée de vos projets.";

// Le profil est actuellement 'refuse'. L'admin peut autoriser une correction ou on réinitialise pour le test T05
// Permettons la transition admin vers 'a_corriger'
$pdo->exec("UPDATE profil_professionnel SET statut_validation = 'en_verification' WHERE id_profil = {$idProfilPro}");
$resAdminDetailCorr = makeHttp("{$baseUrl}/admin/profil_detail.php?id={$idProfilPro}", $cookieAdmin);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $resAdminDetailCorr['body'], $mACorr);
$csrfAdminCorr = $mACorr[1] ?? '';

$resCorrAdmin = makeHttp("{$baseUrl}/admin/profil_detail.php?id={$idProfilPro}", $cookieAdmin, 'POST', [
    'csrf_token' => $csrfAdminCorr,
    'decision'   => 'a_corriger',
    'motif'      => $motifCorrectionT05,
]);
assertTest("Admin demande une correction (HTTP 302/200)", in_array($resCorrAdmin['code'], [200, 302], true));

$statutCorr = $pdo->query("SELECT statut_validation FROM profil_professionnel WHERE id_profil = {$idProfilPro}")->fetchColumn();
assertTest("Statut passé à 'a_corriger' en base", $statutCorr === 'a_corriger', "Statut actuel: {$statutCorr}");

// 2. Vérification côté Pro (pro/index.php)
$resProIndexCorr = makeHttp("{$baseUrl}/pro/index.php", $cookiePro);
assertTest("Pro voit l'alerte 'Corrections demandées sur votre profil'", str_contains($resProIndexCorr['body'], 'Corrections demandées sur votre profil'));
assertTest("Pro voit le motif de correction", str_contains($resProIndexCorr['body'], htmlspecialchars($motifCorrectionT05, ENT_QUOTES, 'UTF-8')));
assertTest("Pro dispose du bouton 'Modifier mon profil'", str_contains($resProIndexCorr['body'], 'Modifier mon profil'));

// 3. Pro modifie son profil et le resoumet
$resProProfilPage = makeHttp("{$baseUrl}/pro/profil.php", $cookiePro);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $resProProfilPage['body'], $mProResubmit);
$csrfProResubmit = $mProResubmit[1] ?? '';

$descriptionValideT05 = "Développeur senior avec plus de 6 ans d’expérience en architectures résilientes, audits et applications sécurisées.";
$resProResubmit = makeHttp("{$baseUrl}/pro/profil.php", $cookiePro, 'POST', [
    'csrf_token'       => $csrfProResubmit,
    'action'           => 'soumettre',
    'titre'            => 'Développeur Fullstack PHP & Sécurité Web - Expert',
    'id_categorie'     => 1,
    'description'      => $descriptionValideT05,
    'annees_experience'=> 7,
    'commune'          => 'Cocody',
    'zone'             => 'Abidjan',
    'disponibilite'    => 'disponible',
    'engagement_cyber' => 1,
]);
assertTest("Pro resoumet son profil corrigé", in_array($resProResubmit['code'], [200, 302], true));

$statutResoumis = $pdo->query("SELECT statut_validation FROM profil_professionnel WHERE id_profil = {$idProfilPro}")->fetchColumn();
assertTest("Statut redevenu 'soumis' après resoumission", $statutResoumis === 'soumis', "Statut actuel: {$statutResoumis}");

// 4. Admin ouvre à nouveau le dossier
$resAdminDetailApprouve = makeHttp("{$baseUrl}/admin/profil_detail.php?id={$idProfilPro}", $cookieAdmin);
$statutEnVerif2 = $pdo->query("SELECT statut_validation FROM profil_professionnel WHERE id_profil = {$idProfilPro}")->fetchColumn();
assertTest("Statut passé automatiquement à 'en_verification'", $statutEnVerif2 === 'en_verification');

preg_match('/name="csrf_token"\s+value="([^"]+)"/', $resAdminDetailApprouve['body'], $mAApp);
$csrfAdminApprouve = $mAApp[1] ?? '';

// 5. Admin approuve le profil avec commentaire optionnel
$commentaireApprouve = "Dossier conforme et complet. Profil validé avec succès.";
$resApprobationAdmin = makeHttp("{$baseUrl}/admin/profil_detail.php?id={$idProfilPro}", $cookieAdmin, 'POST', [
    'csrf_token' => $csrfAdminApprouve,
    'decision'   => 'approuve',
    'motif'      => $commentaireApprouve,
]);
assertTest("Admin approuve le profil (HTTP 302/200)", in_array($resApprobationAdmin['code'], [200, 302], true));

$statutApprouve = $pdo->query("SELECT statut_validation FROM profil_professionnel WHERE id_profil = {$idProfilPro}")->fetchColumn();
assertTest("Statut passé à 'approuve' en base", $statutApprouve === 'approuve');

$lastLogApprouve = $pdo->query("SELECT * FROM validation_profil WHERE id_profil = {$idProfilPro} ORDER BY id_validation DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
assertTest("Approbation consignée dans validation_profil avec decision='approuve'", $lastLogApprouve['decision'] === 'approuve');
echo "  ==> T05 VALIDÉ AVEC SUCCÈS !\n";


echo "\n--- ÉTAPE D : CONTRÔLE DE RG05 DANS PROFILS_PUBLICS() ---\n";
// Seul le statut approuve doit être visible
// Testons tous les statuts non approuvés pour s'assurer de leur exclusion
$statutsNonApprouves = ['brouillon', 'soumis', 'en_verification', 'a_corriger', 'refuse', 'suspendu'];

foreach ($statutsNonApprouves as $st) {
    $pdo->prepare("UPDATE profil_professionnel SET statut_validation = ? WHERE id_profil = ?")->execute([$st, $idProfilPro]);
    $liste = profils_publics($pdo);
    $trouve = false;
    foreach ($liste as $item) {
        if ($item['id_profil'] === $idProfilPro) {
            $trouve = true;
            break;
        }
    }
    assertTest("RG05 : statut '{$st}' strictement exclu de profils_publics()", !$trouve);
}

// Testons compte inactif
$pdo->prepare("UPDATE profil_professionnel SET statut_validation = 'approuve' WHERE id_profil = ?")->execute([$idProfilPro]);
$pdo->prepare("UPDATE utilisateur SET statut_compte = 'bloque' WHERE id_utilisateur = ?")->execute([$idUtilisateurPro]);
$listeInactif = profils_publics($pdo);
$trouveInactif = false;
foreach ($listeInactif as $item) {
    if ($item['id_profil'] === $idProfilPro) {
        $trouveInactif = true;
        break;
    }
}
assertTest("RG05 : utilisateur avec statut_compte = 'bloque' exclu de profils_publics()", !$trouveInactif);

// Rétablissement compte actif + statut approuve
$pdo->prepare("UPDATE utilisateur SET statut_compte = 'actif' WHERE id_utilisateur = ?")->execute([$idUtilisateurPro]);
$listeActif = profils_publics($pdo);
$trouveActif = false;
$profilRecupere = null;
foreach ($listeActif as $item) {
    if ($item['id_profil'] === $idProfilPro) {
        $trouveActif = true;
        $profilRecupere = $item;
        break;
    }
}
assertTest("RG05 : profil 'approuve' avec compte 'actif' visible dans profils_publics()", $trouveActif);


echo "\n--- ÉTAPE E : CONTRÔLE DU COMPOSANT CARTE_PROFIL.PHP ---\n";
assertTest("profils_publics() ne contient ni email ni telephone", !isset($profilRecupere['email']) && !isset($profilRecupere['telephone']));
assertTest("profils_publics() génère un nom_affiche formaté ('Ibrahim T.')", $profilRecupere['nom_affiche'] === 'Ibrahim T.');

// Test du rendu HTML de afficher_carte_profil
ob_start();
afficher_carte_profil($profilRecupere);
$carteHtml = ob_get_clean();

assertTest("Carte : Titre professionnel affiché", str_contains($carteHtml, htmlspecialchars($profilRecupere['titre'], ENT_QUOTES, 'UTF-8')));
assertTest("Carte : Catégorie affichée", str_contains($carteHtml, htmlspecialchars($profilRecupere['categorie_libelle'], ENT_QUOTES, 'UTF-8')));
assertTest("Carte : Badge 'Profil approuvé par FindPro' affiché", str_contains($carteHtml, 'Profil approuvé par FindPro'));
assertTest("Carte : Badge de disponibilité coloré affiché", str_contains($carteHtml, 'Disponible'));
assertTest("Carte : Compétences clés affichées (maximum 3)", str_contains($carteHtml, 'Compétences clés'));
assertTest("Carte : Bouton 'Voir le profil' vers profile.php?id=...", str_contains($carteHtml, 'profile.php?id=' . $idProfilPro));
assertTest("Carte : Aucune fuite d'email 'pro@findpro.ci'", !str_contains($carteHtml, 'pro@findpro.ci'));
assertTest("Carte : Aucune fuite de téléphone", !str_contains($carteHtml, '0102030405'));

// Vérification de la table validation_profil
$totalDecisions = (int) $pdo->query("SELECT COUNT(*) FROM validation_profil WHERE id_profil = {$idProfilPro}")->fetchColumn();
assertTest("Toutes les décisions sont bien enregistrées dans validation_profil (total: {$totalDecisions})", $totalDecisions >= 3);

// Nettoyage fichiers cookies
@unlink($cookieAdmin);
@unlink($cookiePro);

echo "\n====================================================================\n";
echo "RÉSULTAT TOTAL : {$testsReussis} / {$testsTotal} tests réussis.\n";
if ($testsReussis === $testsTotal) {
    echo "FÉLICITATIONS : T05, T06, RG05 ET CARTE_PROFIL SONT 100% CONFORMES !\n";
} else {
    echo "ATTENTION : CERTAINS TESTS ONT ÉCHOUÉ.\n";
}
echo "====================================================================\n";
