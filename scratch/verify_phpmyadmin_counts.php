<?php
/**
 * Comparaison directe des compteurs de la base de données (phpMyAdmin)
 * avec les valeurs affichées sur admin/index.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db();

echo "====================================================================\n";
echo "VÉRIFICATION DE CORRESPONDANCE DES COMPTEURS BDD (PHPMYADMIN)\n";
echo "====================================================================\n\n";

// 1. Valeurs exactes dans les tables MySQL (identiques à phpMyAdmin)
$sqlClients = "SELECT COUNT(*) FROM utilisateur WHERE role = 'client'";
$sqlPros = "SELECT COUNT(*) FROM utilisateur WHERE role = 'professionnel'";
$sqlAttente = "SELECT COUNT(*) FROM profil_professionnel WHERE statut_validation IN ('soumis', 'en_verification')";
$sqlDemandes = "SELECT COUNT(*) FROM demande_service";
$sqlSignalements = "SELECT COUNT(*) FROM signalement WHERE statut = 'nouveau'";

$dbClients = (int) $pdo->query($sqlClients)->fetchColumn();
$dbPros = (int) $pdo->query($sqlPros)->fetchColumn();
$dbAttente = (int) $pdo->query($sqlAttente)->fetchColumn();
$dbDemandes = (int) $pdo->query($sqlDemandes)->fetchColumn();
$dbSignalements = (int) $pdo->query($sqlSignalements)->fetchColumn();

echo "1. Requêtes SQL brutes exécutées dans la base 'findpro' :\n";
echo "   - [Clients]             `{$sqlClients}` => {$dbClients}\n";
echo "   - [Professionnels]      `{$sqlPros}` => {$dbPros}\n";
echo "   - [Profils en attente]  `{$sqlAttente}` => {$dbAttente}\n";
echo "   - [Demandes de service] `{$sqlDemandes}` => {$dbDemandes}\n";
echo "   - [Signalements neufs]  `{$sqlSignalements}` => {$dbSignalements}\n\n";

// 2. Récupération des valeurs rendues par le tableau de bord admin
$cookieFile = __DIR__ . '/cookie_verify_counts.txt';
if (file_exists($cookieFile)) {
    unlink($cookieFile);
}

function req(string $url, string $method = 'GET', array $data = [], string $cookieFile = ''): string {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $res = curl_exec($ch);
    curl_close($ch);
    return (string) $res;
}

$baseUrl = 'http://localhost/FindPro';
$loginPage = req("{$baseUrl}/login.php", 'GET', [], $cookieFile);
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginPage, $m);
$token = $m[1] ?? '';

req("{$baseUrl}/login.php", 'POST', [
    'csrf_token'   => $token,
    'email'        => 'k10developpeur@gmail.com',
    'mot_de_passe' => 'Dieudonne@2006',
], $cookieFile);

$adminHtml = req("{$baseUrl}/admin/index.php", 'GET', [], $cookieFile);
if (file_exists($cookieFile)) {
    unlink($cookieFile);
}

echo "2. Analyse du rendu HTML sur http://localhost/FindPro/admin/index.php :\n";

// Vérification de la présence des valeurs dans les cartes
$cartes = [
    'Profils en attente' => $dbAttente,
    'Professionnels'     => $dbPros,
    'Clients'            => $dbClients,
    'Demandes'           => $dbDemandes,
    'Signalements'       => $dbSignalements,
];

$tousConformes = true;
foreach ($cartes as $nom => $valeurAttendue) {
    // Dans le HTML, les valeurs sont affichées dans `<div class="h3 fw-bold mb-1 ...">VALEUR</div>`
    $motif = '/<div class="h3 fw-bold mb-1[^"]*">\s*' . preg_quote((string) $valeurAttendue, '/') . '\s*<\/div>/';
    $trouve = (bool) preg_match($motif, $adminHtml);
    if ($trouve) {
        echo "   [✓ CONFORME] Carte '{$nom}' affiche bien la valeur {$valeurAttendue} (identique BDD)\n";
    } else {
        echo "   [✗ ERREUR]   Carte '{$nom}' : valeur attendue {$valeurAttendue} non trouvée dans le bloc HTML\n";
        $tousConformes = false;
    }
}

echo "\n--------------------------------------------------------------------\n";
if ($tousConformes) {
    echo "RÉSULTAT : Tous les compteurs sont 100% conformes aux données réelles de phpMyAdmin !\n";
} else {
    echo "RÉSULTAT : Discordance détectée.\n";
}
echo "====================================================================\n";
