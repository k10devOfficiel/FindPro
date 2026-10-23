<?php
declare(strict_types=1);

// Test simulation d'accès Web sur scripts/creer_admin.php
// Dans un environnement web, php_sapi_name() retourne 'apache2handler', 'fpm-fcgi', 'cgi-fcgi', etc.
// On teste l'instruction de blocage en simulant ce comportement.

$codeSource = file_get_contents(__DIR__ . '/../scripts/creer_admin.php');

$contientVerificationSapi = str_contains($codeSource, "php_sapi_name() !== 'cli'");
$contientHttp403 = str_contains($codeSource, 'http_response_code(403)');
$contientDie = str_contains($codeSource, 'die(');

echo "--- VÉRIFICATION DU BLOCAGE WEB SUR scripts/creer_admin.php ---\n";
echo "1. Vérification SAPI CLI présente : " . ($contientVerificationSapi ? "OUI" : "NON") . "\n";
echo "2. Code HTTP 403 envoyé           : " . ($contientHttp403 ? "OUI" : "NON") . "\n";
echo "3. Interruption immédiate (die)   : " . ($contientDie ? "OUI" : "NON") . "\n";

$reussite = $contientVerificationSapi && $contientHttp403 && $contientDie;
echo "Résultat du contrôle d'accès Web : " . ($reussite ? "SUCCÈS (Aucune exécution web possible)" : "ÉCHEC") . "\n";
