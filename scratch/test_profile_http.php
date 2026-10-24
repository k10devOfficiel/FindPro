<?php
declare(strict_types=1);

$ctx = stream_context_create(['http' => ['ignore_errors' => true]]);

// Test 1: approved profile #66 as anonymous public visitor
$h66 = file_get_contents('http://localhost/FindPro/profile.php?id=66', false, $ctx);
$code66 = $http_response_header[0];
echo "Profil 66 (approuve) HTTP Code: " . $code66 . PHP_EOL;
echo "Profil 66 contient K10DEV: " . (str_contains($h66, 'K10DEV') ? 'OUI' : 'NON') . PHP_EOL;
echo "Profil 66 contient Envoyer une demande: " . (str_contains($h66, 'request_new.php?profil=66') ? 'OUI' : 'NON') . PHP_EOL;
echo "Profil 66 contient badge FindPro: " . (str_contains($h66, 'Profil approuvé par FindPro') ? 'OUI' : 'NON') . PHP_EOL;
echo "Profil 66 contient mention avertissement compétence: " . (str_contains($h66, "ce badge n'est pas une certification de compétence") ? 'OUI' : 'NON') . PHP_EOL;
echo "Profil 66 contient bouton Signaler: " . (str_contains($h66, 'Signaler') ? 'OUI' : 'NON') . PHP_EOL;

// Test 2: unapproved profile #65 as anonymous public visitor
$h65 = file_get_contents('http://localhost/FindPro/profile.php?id=65', false, $ctx);
$code65 = $http_response_header[0];
echo "\nProfil 65 (soumis) HTTP Code: " . $code65 . PHP_EOL;
echo "Profil 65 affiche 404: " . (str_contains($code65, '404') && str_contains($h65, 'Profil introuvable') ? 'OUI' : 'NON') . PHP_EOL;
echo "Profil 65 fuit des infos (Ibrahim): " . (str_contains($h65, 'Traoré') ? 'OUI (BUG)' : 'NON (SECURISE)') . PHP_EOL;

// Test 3: non-integer ID
$hInv = file_get_contents('http://localhost/FindPro/profile.php?id=invalid_id', false, $ctx);
$codeInv = $http_response_header[0];
echo "\nID non entier HTTP Code: " . $codeInv . PHP_EOL;
echo "ID non entier affiche 404: " . (str_contains($codeInv, '404') ? 'OUI' : 'NON') . PHP_EOL;

// Test 4: non-existent ID
$hNone = file_get_contents('http://localhost/FindPro/profile.php?id=999999', false, $ctx);
$codeNone = $http_response_header[0];
echo "\nID inexistant HTTP Code: " . $codeNone . PHP_EOL;
echo "ID inexistant affiche 404: " . (str_contains($codeNone, '404') ? 'OUI' : 'NON') . PHP_EOL;
