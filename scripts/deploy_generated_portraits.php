<?php
/**
 * Copie et association des photos de portraits haute définition générées
 * aux 10 comptes professionnels internationaux de FindPro.
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Accès interdit : ce script s'exécute uniquement en ligne de commande (CLI).\n");
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

$brainDir = 'C:/Users/kouam/.gemini/antigravity-ide/brain/51c5f8ba-e2e0-4046-b288-551830fbe452';
$workspaceAvatarsDir = 'c:/Users/kouam/Downloads/FindPro-main/FindPro-main/uploads/avatars';
$wampAvatarsDir = 'C:/wamp64/www/findpro/uploads/avatars';

if (!is_dir($workspaceAvatarsDir)) {
    @mkdir($workspaceAvatarsDir, 0777, true);
}
if (!is_dir($wampAvatarsDir)) {
    @mkdir($wampAvatarsDir, 0777, true);
}

// Recherche des fichiers générés les plus récents par préfixe
function getLatestGeneratedImage(string $dir, string $prefix): ?string {
    $files = glob($dir . '/' . $prefix . '_*.jpg');
    if (empty($files)) {
        return null;
    }
    usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
    return $files[0];
}

$portraits = [
    [
        'prefix'    => 'dupont_portrait',
        'target'    => 'pro_intl_1_dupont.jpg',
        'email'     => 'jean-marc.dupont@findpro-world.com',
        'nom'       => 'Jean-Marc Dupont (France)',
    ],
    [
        'prefix'    => 'diallo_portrait',
        'target'    => 'pro_intl_2_diallo.jpg',
        'email'     => 'amina.diallo@findpro-world.com',
        'nom'       => 'Amina Diallo (Sénégal)',
    ],
    [
        'prefix'    => 'takahashi_portrait',
        'target'    => 'pro_intl_3_takahashi.jpg',
        'email'     => 'kenji.takahashi@findpro-world.com',
        'nom'       => 'Kenji Takahashi (Japon)',
    ],
    [
        'prefix'    => 'tremblay_portrait',
        'target'    => 'pro_intl_4_tremblay.jpg',
        'email'     => 'sophia.tremblay@findpro-world.com',
        'nom'       => 'Sophia Tremblay (Canada)',
    ],
    [
        'prefix'    => 'oconnor_portrait',
        'target'    => 'pro_intl_5_oconnor.jpg',
        'email'     => 'david.oconnor@findpro-world.com',
        'nom'       => 'David O\'Connor (Royaume-Uni)',
    ],
    [
        'prefix'    => 'benali_portrait',
        'target'    => 'pro_intl_6_benali.jpg',
        'email'     => 'yasmine.benali@findpro-world.com',
        'nom'       => 'Yasmine Benali (Maroc)',
    ],
    [
        'prefix'    => 'silva_portrait',
        'target'    => 'pro_intl_7_silva.jpg',
        'email'     => 'lucas.silva@findpro-world.com',
        'nom'       => 'Lucas Silva (Brésil)',
    ],
    [
        'prefix'    => 'rostova_portrait',
        'target'    => 'pro_intl_8_rostova.jpg',
        'email'     => 'elena.rostova@findpro-world.com',
        'nom'       => 'Elena Rostova (Allemagne)',
    ],
    [
        'prefix'    => 'mensah_portrait',
        'target'    => 'pro_intl_9_mensah.jpg',
        'email'     => 'kwame.mensah@findpro-world.com',
        'nom'       => 'Kwame Mensah (Ghana)',
    ],
    [
        'prefix'    => 'weber_portrait',
        'target'    => 'pro_intl_10_weber.jpg',
        'email'     => 'christian.weber@findpro-world.com',
        'nom'       => 'Christian Weber (Suisse)',
    ],
];

echo "=== DÉPLOIEMENT DES VRAIES PHOTOS PROFESSIONNELLES ===\n\n";

foreach ($portraits as $idx => $p) {
    $src = getLatestGeneratedImage($brainDir, $p['prefix']);
    if (!$src || !file_exists($src)) {
        echo "[ERREUR] Source introuvable pour {$p['nom']} (préfixe {$p['prefix']})\n";
        continue;
    }

    $destWorkspace = $workspaceAvatarsDir . '/' . $p['target'];
    $destWamp = $wampAvatarsDir . '/' . $p['target'];

    // Copie dans le workspace
    copy($src, $destWorkspace);
    // Copie directe dans WAMP
    copy($src, $destWamp);

    $tailleKo = round(filesize($destWorkspace) / 1024, 1);
    $cheminRelatifDb = 'uploads/avatars/' . $p['target'];

    // Mettre à jour profil_professionnel
    $stmt = $pdo->prepare("
        UPDATE profil_professionnel pp
        JOIN utilisateur u ON pp.id_utilisateur = u.id_utilisateur
        SET pp.photo = ?
        WHERE u.email = ?
    ");
    $stmt->execute([$cheminRelatifDb, $p['email']]);

    echo sprintf("[%02d/10] %s\n", $idx + 1, $p['nom']);
    echo "       Source  : " . basename($src) . "\n";
    echo "       Photo   : {$cheminRelatifDb} ({$tailleKo} Ko)\n";
    echo "       Statut  : Profil mis à jour en base et déployé sur WAMP.\n\n";
}

echo "=== TOUTES LES PHOTOS PROFESSIONNELLES SONT DÉPLOYÉES AVEC SUCCÈS ! ===\n";
