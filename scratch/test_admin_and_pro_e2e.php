<?php
/**
 * Test E2E et unitaire pour admin/profil_detail.php et pro/index.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';

demarrer_session();
$pdo = db();

echo "====================================================================\n";
echo "TEST SUITE : ARBITRAGE PROFIL_DETAIL & DASHBOARD PRO\n";
echo "====================================================================\n";

$testsTotal = 0;
$testsReussis = 0;

function tester(string $libelle, bool $condition, string $detail = ''): void {
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

// -----------------------------------------------------------------------------
// Helper cURL HTTP
// -----------------------------------------------------------------------------
function makeRequest(string $url, string $cookieFile = '', string $method = 'GET', array $post = []): array {
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

$adminCookie = __DIR__ . '/cookie_admin_test.txt';
$proCookie = __DIR__ . '/cookie_pro_test.txt';
@unlink($adminCookie);
@unlink($proCookie);

$baseUrl = 'http://localhost/findpro';

// -----------------------------------------------------------------------------
// 1. Authentification Admin
// -----------------------------------------------------------------------------
echo "\n1. AUTHENTIFICATION ADMINISTRATEUR...\n";
$resLoginGet = makeRequest("{$baseUrl}/login.php", $adminCookie);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $resLoginGet['body'], $m);
$csrf = $m[1] ?? '';

$resLoginPost = makeRequest("{$baseUrl}/login.php", $adminCookie, 'POST', [
    'csrf_token'   => $csrf,
    'email'        => 'k10developpeur@gmail.com',
    'mot_de_passe' => 'Dieudonne@2006',
]);
tester("Connexion administrateur réussie", in_array($resLoginPost['code'], [302, 200], true));

// -----------------------------------------------------------------------------
// 2. Contrôle 404 (ID alphabétique ou inexistant)
// -----------------------------------------------------------------------------
echo "\n2. CONTRÔLE HTTP 404 SUR ADMIN/PROFIL_DETAIL.PHP...\n";

$res404Alpha = makeRequest("{$baseUrl}/admin/profil_detail.php?id=abc", $adminCookie);
tester("ID 'abc' retourne HTTP 404", $res404Alpha['code'] === 404);
tester("Message 404 profil introuvable présent", str_contains($res404Alpha['body'], 'Profil professionnel introuvable'));

$res404Missing = makeRequest("{$baseUrl}/admin/profil_detail.php?id=999999", $adminCookie);
tester("ID '999999' retourne HTTP 404", $res404Missing['code'] === 404);
tester("Message 404 dossier introuvable présent", str_contains($res404Missing['body'], 'Dossier introuvable'));

// -----------------------------------------------------------------------------
// 3. Automatisme passage 'soumis' -> 'en_verification' à l'ouverture
// -----------------------------------------------------------------------------
echo "\n3. PASSAGE AUTOMATIQUE À 'EN_VERIFICATION' À L'OUVERTURE...\n";

$proUser = $pdo->query("SELECT id_utilisateur FROM utilisateur WHERE email = 'pro@findpro.ci'")->fetch();
$idPro = (int) $proUser['id_utilisateur'];
$idProfilPro = (int) $pdo->query("SELECT id_profil FROM profil_professionnel WHERE id_utilisateur = {$idPro}")->fetchColumn();

// Forcer le profil au statut 'soumis'
$pdo->exec("UPDATE profil_professionnel SET statut_validation = 'soumis' WHERE id_profil = {$idProfilPro}");
$statutAvant = $pdo->query("SELECT statut_validation FROM profil_professionnel WHERE id_profil = {$idProfilPro}")->fetchColumn();
tester("Profil positionné au statut initial 'soumis'", $statutAvant === 'soumis');

// Ouverture par l'administrateur
$resDetail = makeRequest("{$baseUrl}/admin/profil_detail.php?id={$idProfilPro}", $adminCookie);
tester("Page profil_detail charge avec HTTP 200", $resDetail['code'] === 200);

$statutApres = $pdo->query("SELECT statut_validation FROM profil_professionnel WHERE id_profil = {$idProfilPro}")->fetchColumn();
tester("Profil 'soumis' est passé automatiquement à 'en_verification'", $statutApres === 'en_verification');
tester("Affichage des coordonnées privées (admin)", str_contains($resDetail['body'], 'Coordonnées privées (Admin)') && str_contains($resDetail['body'], 'pro@findpro.ci'));
tester("Affichage du bouton 'Approuver'", str_contains($resDetail['body'], 'Approuver le profil'));
tester("Affichage du bouton 'Demander une correction'", str_contains($resDetail['body'], 'Demander une correction'));
tester("Affichage du bouton 'Refuser'", str_contains($resDetail['body'], 'Refuser le profil'));

// -----------------------------------------------------------------------------
// 4. Décision 'a_corriger' avec validation du motif >= 10 car.
// -----------------------------------------------------------------------------
echo "\n4. CONTRÔLE DÉCISION 'A_CORRIGER' ET MOTIF MINIMUM 10 CARACTÈRES...\n";

preg_match('/name="csrf_token"\s+value="([^"]+)"/', $resDetail['body'], $m);
$csrfAdmin = $m[1] ?? '';

// Test motif trop court (< 10 caractères)
$resCourt = makeRequest("{$baseUrl}/admin/profil_detail.php?id={$idProfilPro}", $adminCookie, 'POST', [
    'csrf_token' => $csrfAdmin,
    'decision'   => 'a_corriger',
    'motif'      => 'Court',
]);
$statutApresCourt = $pdo->query("SELECT statut_validation FROM profil_professionnel WHERE id_profil = {$idProfilPro}")->fetchColumn();
tester("Motif < 10 caractères rejeté côté serveur", $statutApresCourt === 'en_verification');
tester("Message d'erreur motif au moins 10 caractères présent", str_contains($resCourt['body'], 'au moins 10 caractères'));

// Test motif valide (>= 10 caractères)
$motifCorrection = "Veuillez enrichir la description de vos compétences et détailler votre rôle dans le projet portfolio.";
$resDecisionOk = makeRequest("{$baseUrl}/admin/profil_detail.php?id={$idProfilPro}", $adminCookie, 'POST', [
    'csrf_token' => $csrfAdmin,
    'decision'   => 'a_corriger',
    'motif'      => $motifCorrection,
]);
tester("Décision valide redirige (HTTP 302)", in_array($resDecisionOk['code'], [302, 200], true));

$statutCorriger = $pdo->query("SELECT statut_validation FROM profil_professionnel WHERE id_profil = {$idProfilPro}")->fetchColumn();
tester("Profil passé au statut 'a_corriger'", $statutCorriger === 'a_corriger');

// Vérifier l'insertion dans validation_profil
$lastLog = $pdo->query("SELECT * FROM validation_profil WHERE id_profil = {$idProfilPro} ORDER BY date_decision DESC LIMIT 1")->fetch();
tester("Ligne enregistrée dans validation_profil avec decision='a_corriger'", $lastLog['decision'] === 'a_corriger');
tester("Motif fidèlement consigné dans validation_profil", $lastLog['motif'] === $motifCorrection);

// -----------------------------------------------------------------------------
// 5. Tableau de bord Professionnel (pro/index.php) en statut 'a_corriger'
// -----------------------------------------------------------------------------
echo "\n5. VÉRIFICATION DE PRO/INDEX.PHP (STATUT A_CORRIGER)...\n";

// Connexion pro
$resLoginProGet = makeRequest("{$baseUrl}/login.php", $proCookie);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $resLoginProGet['body'], $m);
$csrfProLogin = $m[1] ?? '';

makeRequest("{$baseUrl}/login.php", $proCookie, 'POST', [
    'csrf_token'   => $csrfProLogin,
    'email'        => 'pro@findpro.ci',
    'mot_de_passe' => 'Pro123456789!',
]);

$resProIndex = makeRequest("{$baseUrl}/pro/index.php", $proCookie);
tester("pro/index.php charge avec succès (HTTP 200)", $resProIndex['code'] === 200);
tester("Encadré 'Corrections demandées sur votre profil' affiché", str_contains($resProIndex['body'], 'Corrections demandées sur votre profil'));
tester("Motif consigné affiché avec date", str_contains($resProIndex['body'], $motifCorrection));
tester("Bouton 'Modifier mon profil' présent", str_contains($resProIndex['body'], 'Modifier mon profil') && str_contains($resProIndex['body'], 'pro/profil.php'));
tester("Historique des décisions passées présent", str_contains($resProIndex['body'], 'Historique des décisions passées'));

// Vérifier que pro/profil.php est en mode modifiable avec bouton 'Soumettre mon profil'
$resProProfil = makeRequest("{$baseUrl}/pro/profil.php", $proCookie);
tester("pro/profil.php en mode modifiable", !str_contains($resProProfil['body'], 'Lecture seule'));
tester("Bouton 'Soumettre mon profil' disponible", str_contains($resProProfil['body'], 'Soumettre mon profil'));

// -----------------------------------------------------------------------------
// 6. Test du statut 'refuse' (Rejet définitif)
// -----------------------------------------------------------------------------
echo "\n6. VÉRIFICATION DU STATUT 'REFUSÉ' (REJET DÉFINITIF)...\n";

$motifRefus = "Documents justificatifs non probants et non conformes aux critères d'agrément FindPro.";
$pdo->beginTransaction();
$pdo->prepare("UPDATE profil_professionnel SET statut_validation = 'refuse', date_validation = NOW() WHERE id_profil = ?")->execute([$idProfilPro]);
$pdo->prepare("INSERT INTO validation_profil (id_profil, id_administrateur, decision, motif, date_decision) VALUES (?, 1, 'refuse', ?, NOW())")->execute([$idProfilPro, $motifRefus]);
$pdo->commit();

$resProIndexRefuse = makeRequest("{$baseUrl}/pro/index.php", $proCookie);
tester("pro/index.php affiche l'encadré 'Dossier de candidature refusé'", str_contains($resProIndexRefuse['body'], 'Dossier de candidature refusé'));
tester("Message de rejet définitif affiché", str_contains($resProIndexRefuse['body'], 'Message de rejet définitif'));
tester("Mention 'sans nouvelle autorisation préalable' affichée", str_contains($resProIndexRefuse['body'], 'sans nouvelle autorisation préalable'));
tester("Motif du refus affiché (échappé avec e())", str_contains($resProIndexRefuse['body'], e($motifRefus)));

// Vérifier que pro/profil.php verrouille la soumission
$resProProfilRefuse = makeRequest("{$baseUrl}/pro/profil.php", $proCookie);
tester("pro/profil.php affiche la bannière de rejet", str_contains($resProProfilRefuse['body'], 'Dossier de candidature refusé'));
tester("Bouton de soumission désactivé/verrouillé", str_contains($resProProfilRefuse['body'], 'Rejet définitif'));

// -----------------------------------------------------------------------------
// 7. Test du statut 'suspendu'
// -----------------------------------------------------------------------------
echo "\n7. VÉRIFICATION DU STATUT 'SUSPENDU'...\n";

$motifSuspension = "Suspension administrative temporaire suite à signalement d'un client.";
$pdo->beginTransaction();
$pdo->prepare("UPDATE profil_professionnel SET statut_validation = 'suspendu', date_validation = NOW() WHERE id_profil = ?")->execute([$idProfilPro]);
$pdo->prepare("INSERT INTO validation_profil (id_profil, id_administrateur, decision, motif, date_decision) VALUES (?, 1, 'suspendu', ?, NOW())")->execute([$idProfilPro, $motifSuspension]);
$pdo->commit();

$resProIndexSuspendu = makeRequest("{$baseUrl}/pro/index.php", $proCookie);
tester("pro/index.php affiche l'encadré 'Profil professionnel suspendu'", str_contains($resProIndexSuspendu['body'], 'Profil professionnel suspendu'));
tester("Motif de suspension consigné affiché (échappé avec e())", str_contains($resProIndexSuspendu['body'], e($motifSuspension)));

// Remettons le profil au statut 'soumis' pour laisser la file d'attente admin propre
$pdo->exec("UPDATE profil_professionnel SET statut_validation = 'soumis' WHERE id_profil = {$idProfilPro}");

// Nettoyage des cookies de test
@unlink($adminCookie);
@unlink($proCookie);

echo "\n--------------------------------------------------------------------\n";
echo "RÉSULTAT GLOBAL : {$testsReussis} / {$testsTotal} tests réussis.\n";
if ($testsReussis === $testsTotal) {
    echo "TOUTES LES EXIGENCES DES DEUX DEMANDES SONT 100% VALIDÉES !\n";
} else {
    echo "CERTAINS TESTS ONT ÉCHOUÉ.\n";
}
echo "--------------------------------------------------------------------\n";
