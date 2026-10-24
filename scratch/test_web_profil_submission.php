<?php
/**
 * Test HTTP de bout en bout simulant un navigateur sur http://localhost/FindPro/
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
$pdo = db();

// Remettons temporairement l'utilisateur pro@findpro.ci en statut 'brouillon' pour tester l'affichage du bouton
$pdo->exec("UPDATE profil_professionnel SET statut_validation = 'brouillon' WHERE id_utilisateur = 20");

$cookieFile = __DIR__ . '/cookie_web_pro.txt';
if (file_exists($cookieFile)) {
    unlink($cookieFile);
}

$baseUrl = 'http://localhost/FindPro';

function httpRequest(string $url, string $method = 'GET', array $data = [], string $cookieFile = ''): array {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) TestRunner/1.0');
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    
    $body = curl_exec($ch);
    $info = curl_getinfo($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'code' => $info['http_code'] ?? 0,
        'body' => (string) $body,
        'url'  => $info['url'] ?? '',
        'error'=> $error,
    ];
}

echo "1. Connexion en tant que pro@findpro.ci (statut: brouillon)...\n";
$resLoginGet = httpRequest("{$baseUrl}/login.php", 'GET', [], $cookieFile);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $resLoginGet['body'], $matches);
$csrfToken = $matches[1] ?? '';

$resLoginPost = httpRequest("{$baseUrl}/login.php", 'POST', [
    'csrf_token'    => $csrfToken,
    'email'         => 'pro@findpro.ci',
    'mot_de_passe'  => 'Pro123456789!',
], $cookieFile);

// Accéder à pro/profil.php
echo "2. Consultation de pro/profil.php en mode brouillon...\n";
$resProfil = httpRequest("{$baseUrl}/pro/profil.php", 'GET', [], $cookieFile);

$hasSubmitBtn = str_contains($resProfil['body'], 'Soumettre mon profil');
$hasCsrf = str_contains($resProfil['body'], 'name="csrf_token"');
$hasCyberBloc = str_contains($resProfil['body'], 'bloc-engagement-cyber');

echo "  -> Présence du bouton 'Soumettre mon profil' : " . ($hasSubmitBtn ? "OUI" : "NON") . "\n";
echo "  -> Présence du token CSRF : " . ($hasCsrf ? "OUI" : "NON") . "\n";
echo "  -> Présence du bloc d'engagement cybersécurité : " . ($hasCyberBloc ? "OUI" : "NON") . "\n";

// Test 3 : passage en lecture seule
echo "3. Passage en statut 'soumis'...\n";
$pdo->exec("UPDATE profil_professionnel SET statut_validation = 'soumis', date_soumission = NOW() WHERE id_utilisateur = 20");
$resProfilSoumis = httpRequest("{$baseUrl}/pro/profil.php", 'GET', [], $cookieFile);

$hasReadOnlyNotice = str_contains($resProfilSoumis['body'], 'Dossier en cours d\'examen administratif');
$hasDisabledBtn = str_contains($resProfilSoumis['body'], 'Examen en cours');
$isSubmitHidden = !str_contains($resProfilSoumis['body'], 'Soumettre mon profil');

echo "  -> Présence de la bannière lecture seule : " . ($hasReadOnlyNotice ? "OUI" : "NON") . "\n";
echo "  -> Bouton de soumission verrouillé/masqué : " . ($isSubmitHidden ? "OUI" : "NON") . "\n";
echo "  -> Bouton 'Examen en cours' désactivé : " . ($hasDisabledBtn ? "OUI" : "NON") . "\n";

// Remettons le statut initial
$pdo->exec("UPDATE profil_professionnel SET statut_validation = 'brouillon' WHERE id_utilisateur = 20");

if (file_exists($cookieFile)) {
    unlink($cookieFile);
}

if ($hasSubmitBtn && $hasCsrf && $hasCyberBloc && $hasReadOnlyNotice && $isSubmitHidden && $hasDisabledBtn) {
    echo "\nSUCCÈS : Tous les rendus et états du formulaire web fonctionnent parfaitement !\n";
} else {
    echo "\nÉchec partiel du test web.\n";
}
