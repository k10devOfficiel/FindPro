<?php
declare(strict_types=1);

// Test 1: Simulation requête GET sur logout.php
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
require __DIR__ . '/../logout.php';
$output = ob_get_clean();

$code = http_response_code();
echo "--- TEST LOGOUT ACCÈS GET ---\n";
echo "Code HTTP retourné : " . $code . " (attendu: 405)\n";
$contientMessage = str_contains($output, '405') && str_contains($output, 'Action non autoris');
echo "Contenu d'erreur 405 affiché : " . ($contientMessage ? "OUI" : "NON") . "\n";
echo "Résultat test GET logout : " . ($code === 405 && $contientMessage ? "SUCCÈS" : "ÉCHEC") . "\n";
