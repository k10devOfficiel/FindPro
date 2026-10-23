<?php
declare(strict_types=1);

$urls = [
    'https://example.com'          => false,
    '//example.com'               => false,
    '/\\example.com'              => false,
    'javascript:alert(1)'         => false,
    'http://attacker.com/malware'  => false,
    '/client/index.php'           => true,
    '/pro/profil.php?id=12'       => true,
    '/demande.php'                => true,
];

echo "--- TEST DES REDIRECTIONS OUVERTES ---\n";
$allOk = true;
foreach ($urls as $url => $expected) {
    $valid = str_starts_with($url, '/') && !str_contains($url, '//') && !str_contains($url, ':') && !str_contains($url, '\\');
    $status = ($valid === $expected);
    if (!$status) {
        $allOk = false;
    }
    printf("%-30s : %-8s (attendu: %-8s) -> %s\n", $url, $valid ? 'VALIDE' : 'REJETE', $expected ? 'VALIDE' : 'REJETE', $status ? 'OK' : 'ECHEC');
}

echo "\nRésultat global test redirection ouverte : " . ($allOk ? "SUCCÈS TOTAL" : "ÉCHEC") . "\n";
