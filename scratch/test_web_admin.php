<?php
/**
 * Test HTTP end-to-end de connexion et accès admin sur http://localhost/FindPro/admin/index.php
 */

declare(strict_types=1);

$cookieFile = __DIR__ . '/cookie_admin.txt';
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

echo "1. Récupération CSRF login...\n";
$resLoginGet = httpRequest("{$baseUrl}/login.php", 'GET', [], $cookieFile);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $resLoginGet['body'], $matches);
$csrfToken = $matches[1] ?? '';

echo "2. Connexion admin (k10developpeur@gmail.com)...\n";
$resLoginPost = httpRequest("{$baseUrl}/login.php", 'POST', [
    'csrf_token'    => $csrfToken,
    'email'         => 'k10developpeur@gmail.com',
    'mot_de_passe'  => 'Dieudonne@2006',
], $cookieFile);

echo "   Redirection vers : {$resLoginPost['url']} (Code {$resLoginPost['code']})\n";

echo "3. Chargement de admin/index.php...\n";
$resAdmin = httpRequest("{$baseUrl}/admin/index.php", 'GET', [], $cookieFile);
echo "   Code HTTP : {$resAdmin['code']}\n";

$checks = [
    'Titre Administrateur' => str_contains($resAdmin['body'], 'Tableau de bord Administrateur'),
    'Carte En attente'     => str_contains($resAdmin['body'], 'En attente') && str_contains($resAdmin['body'], 'admin/validations.php'),
    'Carte Professionnels' => str_contains($resAdmin['body'], 'Professionnels') && str_contains($resAdmin['body'], 'admin/utilisateurs.php?role=professionnel'),
    'Carte Clients'        => str_contains($resAdmin['body'], 'Clients') && str_contains($resAdmin['body'], 'admin/utilisateurs.php?role=client'),
    'Carte Demandes'       => str_contains($resAdmin['body'], 'Demandes') && str_contains($resAdmin['body'], 'admin/demandes.php'),
    'Carte Signalements'   => str_contains($resAdmin['body'], 'Signalements') && str_contains($resAdmin['body'], 'admin/signalements.php'),
    'Tableau 5 derniers'   => str_contains($resAdmin['body'], 'Derniers profils soumis'),
    'Espace réservé gris'  => str_contains($resAdmin['body'], 'Espace réservé'),
];

foreach ($checks as $nom => $ok) {
    echo "   [" . ($ok ? "PASS" : "FAIL") . "] {$nom}\n";
}

if (file_exists($cookieFile)) {
    unlink($cookieFile);
}
