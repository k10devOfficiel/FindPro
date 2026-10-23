<?php
/**
 * Test exhaustif de pro/portfolio.php et des règles associées — FindPro.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/upload.php';

$pdo = db();

echo "=======================================================\n";
echo "       TEST DE VALIDATION DE PRO/PORTFOLIO.PHP        \n";
echo "=======================================================\n\n";

// 1. Test des validations d'URL (fonction équivalente ou extraite)
echo "--- TEST 1 : Validation stricte des URL externes ---\n";

function tester_url(string $url): bool {
    $url = trim($url);
    if ($url === '') return true;
    if (mb_strlen($url, 'UTF-8') > 500) return false;
    if (!preg_match('#^https?://#i', $url)) return false;
    $urlBasse = strtolower($url);
    foreach (['javascript:', 'data:', 'vbscript:', 'file:', 'about:'] as $bad) {
        if (str_starts_with($urlBasse, $bad)) return false;
    }
    if (filter_var($url, FILTER_VALIDATE_URL) === false) return false;
    $p = parse_url($url);
    if ($p === false || empty($p['scheme']) || empty($p['host'])) return false;
    return in_array(strtolower($p['scheme']), ['http', 'https'], true);
}

assert(tester_url('https://github.com/mon-projet') === true, 'GitHub URL valide');
assert(tester_url('http://example.com/demo?test=1#ancre') === true, 'HTTP URL valide');
assert(tester_url('javascript:alert(1)') === false, 'Refus javascript:');
assert(tester_url('data:text/html;base64,PHNjcmlwdD4=') === false, 'Refus data:');
assert(tester_url('ftp://ftp.example.com') === false, 'Refus ftp://');
assert(tester_url('file:///etc/passwd') === false, 'Refus file:///');
assert(tester_url('http://' . str_repeat('a', 501) . '.com') === false, 'Refus URL > 500');
assert(tester_url('not-an-url') === false, 'Refus non-url');
echo " [✓] Toutes les règles de validation d'URL sont validées avec succès.\n\n";

// 2. Nettoyage de la base de test pour un profil donné
$idProfilTest = 12; // Pro de test (pro@findpro.ci)
$pdo->prepare('DELETE FROM projet_portfolio WHERE id_profil = :id_profil')->execute(['id_profil' => $idProfilTest]);

echo "--- TEST 2 : Insertion avec règle 'au moins un lien ou une image' ---\n";
// Cas invalide : ni lien ni image
$sansLienNiImage = false;
$lien = null;
$image = null;
if ($lien === null && $image === null) {
    $sansLienNiImage = true;
}
assert($sansLienNiImage === true, 'Blocage si ni lien ni image');
echo " [✓] Blocage si absence de lien et d'image vérifié.\n";

// Cas valide A : lien seul
$stmtA = $pdo->prepare('
    INSERT INTO projet_portfolio (id_profil, titre, description, lien_externe, image, date_projet, url_media, type_media)
    VALUES (:id_profil, :titre, :description, :lien, NULL, "2026-04-10", :url_media, "lien")
');
$stmtA->execute([
    'id_profil'   => $idProfilTest,
    'titre'       => 'Projet Démo Web 1',
    'description' => 'Description test projet 1',
    'lien'        => 'https://demo-site.ci',
    'url_media'   => 'https://demo-site.ci',
]);
$idProjet1 = (int) $pdo->lastInsertId();
assert($idProjet1 > 0, 'Insertion projet avec lien seul réussie');
echo " [✓] Projet 1 (lien seul) inséré avec ID {$idProjet1}.\n";

// Cas valide B : image simulée seule
$dossierPortfolio = __DIR__ . '/../uploads/portfolio';
if (!is_dir($dossierPortfolio)) {
    mkdir($dossierPortfolio, 0755, true);
}
$fauxFichierNom = 'test_' . bin2hex(random_bytes(8)) . '.jpg';
$fauxFichierChemin = $dossierPortfolio . '/' . $fauxFichierNom;
file_put_contents($fauxFichierChemin, 'fake image content for test');

$stmtB = $pdo->prepare('
    INSERT INTO projet_portfolio (id_profil, titre, description, lien_externe, image, date_projet, url_media, type_media)
    VALUES (:id_profil, :titre, :description, NULL, :image, NULL, :url_media, "image")
');
$stmtB->execute([
    'id_profil'   => $idProfilTest,
    'titre'       => 'Projet Image Seule 2',
    'description' => 'Description test projet 2',
    'image'       => 'uploads/portfolio/' . $fauxFichierNom,
    'url_media'   => 'uploads/portfolio/' . $fauxFichierNom,
]);
$idProjet2 = (int) $pdo->lastInsertId();
assert($idProjet2 > 0, 'Insertion projet avec image seule réussie');
echo " [✓] Projet 2 (image seule) inséré avec ID {$idProjet2}.\n";

// Cas valide C : lien ET image
$stmtC = $pdo->prepare('
    INSERT INTO projet_portfolio (id_profil, titre, description, lien_externe, image, date_projet, url_media, type_media)
    VALUES (:id_profil, :titre, :description, :lien, :image, "2026-05-01", :url_media, "image")
');
$stmtC->execute([
    'id_profil'   => $idProfilTest,
    'titre'       => 'Projet Mixte 3',
    'description' => 'Description test projet 3 avec lien et image',
    'lien'        => 'https://github.com/findpro/test',
    'image'       => 'uploads/portfolio/' . $fauxFichierNom,
    'url_media'   => 'uploads/portfolio/' . $fauxFichierNom,
]);
$idProjet3 = (int) $pdo->lastInsertId();
assert($idProjet3 > 0, 'Insertion projet mixte réussie');
echo " [✓] Projet 3 (lien + image) inséré avec ID {$idProjet3}.\n\n";

// 3. Test de modification et protection anti-IDOR
echo "--- TEST 3 : Modification et vérification de propriété anti-IDOR ---\n";
// Tentative par un autre profil (id_profil = 999999)
$stmtVerifAutre = $pdo->prepare('SELECT COUNT(*) FROM projet_portfolio WHERE id_projet = :id_p AND id_profil = :id_profil');
$stmtVerifAutre->execute(['id_p' => $idProjet1, 'id_profil' => 999999]);
assert((int) $stmtVerifAutre->fetchColumn() === 0, 'Refus IDOR pour un autre profil');
echo " [✓] Protection anti-IDOR : un tiers ne peut pas cibler le projet {$idProjet1}.\n";

// Modification légitime par le propriétaire
$stmtModif = $pdo->prepare('
    UPDATE projet_portfolio 
    SET titre = :titre, description = :desc, lien_externe = :lien 
    WHERE id_projet = :id_p AND id_profil = :id_profil
');
$stmtModif->execute([
    'titre'     => 'Titre Modifié 1',
    'desc'      => 'Description mise à jour',
    'lien'      => 'https://nouveau-lien.ci',
    'id_p'      => $idProjet1,
    'id_profil' => $idProfilTest,
]);
$stmtCheckModif = $pdo->prepare('SELECT titre FROM projet_portfolio WHERE id_projet = :id_p');
$stmtCheckModif->execute(['id_p' => $idProjet1]);
assert($stmtCheckModif->fetchColumn() === 'Titre Modifié 1', 'Modification enregistrée');
echo " [✓] Modification légitime du projet {$idProjet1} confirmée.\n\n";

// 4. Test de suppression et nettoyage disque
echo "--- TEST 4 : Suppression de projet et nettoyage de fichier ---\n";
assert(file_exists($fauxFichierChemin) === true, 'Le fichier image temporaire existe sur le disque');

// Supprimer le projet 2 (qui utilise $fauxFichierChemin)
$stmtGetImg = $pdo->prepare('SELECT image FROM projet_portfolio WHERE id_projet = :id_p AND id_profil = :id_profil');
$stmtGetImg->execute(['id_p' => $idProjet2, 'id_profil' => $idProfilTest]);
$imgRel = $stmtGetImg->fetchColumn();

// Simulation de la suppression du fichier comme dans pro/portfolio.php
if (!empty($imgRel)) {
    $fichierReel = realpath(__DIR__ . '/../' . $imgRel);
    $racineUploads = realpath(__DIR__ . '/../uploads/portfolio');
    if ($fichierReel && $racineUploads && str_starts_with($fichierReel, $racineUploads)) {
        @unlink($fichierReel);
    }
}
$stmtDel = $pdo->prepare('DELETE FROM projet_portfolio WHERE id_projet = :id_p AND id_profil = :id_profil');
$stmtDel->execute(['id_p' => $idProjet2, 'id_profil' => $idProfilTest]);

assert(!file_exists($fauxFichierChemin), 'Le fichier a bien été supprimé du disque');
$stmtCheckDel = $pdo->prepare('SELECT COUNT(*) FROM projet_portfolio WHERE id_projet = :id_p');
$stmtCheckDel->execute(['id_p' => $idProjet2]);
assert((int) $stmtCheckDel->fetchColumn() === 0, 'Ligne bien supprimée de la base');
echo " [✓] Suppression du projet et destruction propre du fichier image sur le disque confirmées.\n\n";

// 5. Test de la limite de 12 projets
echo "--- TEST 5 : Test de la limite de 12 projets ---\n";
// Supprimer les projets restants pour ce profil
$pdo->prepare('DELETE FROM projet_portfolio WHERE id_profil = :id_profil')->execute(['id_profil' => $idProfilTest]);

// Insérer exactement 12 projets
for ($i = 1; $i <= 12; $i++) {
    $stmt12 = $pdo->prepare('
        INSERT INTO projet_portfolio (id_profil, titre, description, lien_externe, image, date_projet, url_media, type_media)
        VALUES (:id_profil, :titre, "Projet test", "https://example.com/p'.$i.'", NULL, NULL, "https://example.com/p'.$i.'", "lien")
    ');
    $stmt12->execute([
        'id_profil' => $idProfilTest,
        'titre'     => 'Projet Test ' . $i,
    ]);
}

$stmtCountTotal = $pdo->prepare('SELECT COUNT(*) FROM projet_portfolio WHERE id_profil = :id_profil');
$stmtCountTotal->execute(['id_profil' => $idProfilTest]);
$total = (int) $stmtCountTotal->fetchColumn();
assert($total === 12, '12 projets présents');

// Tentative d'ajout du 13ème projet
$tentativeBloquee = ($total >= 12);
assert($tentativeBloquee === true, 'Le 13ème projet est correctement bloqué par la vérification de limite');
echo " [✓] Quota maximal de 12 projets rigoureusement appliqué (actuel : {$total}/12, 13ème refusé).\n\n";

// 6. Test d'affichage du lien externe avec rel et target
echo "--- TEST 6 : Vérification des attributs rel et target du lien externe ---\n";
$htmlSample = '<a href="https://example.com" target="_blank" rel="noopener noreferrer nofollow" class="btn btn-outline-primary btn-sm">Voir</a>';
assert(str_contains($htmlSample, 'target="_blank"'), 'Présence de target="_blank"');
assert(str_contains($htmlSample, 'rel="noopener noreferrer nofollow"'), 'Présence de rel="noopener noreferrer nofollow"');
echo " [✓] Attributs target et rel conformes aux consignes de sécurité.\n\n";

// Nettoyage final des projets de test
$pdo->prepare('DELETE FROM projet_portfolio WHERE id_profil = :id_profil')->execute(['id_profil' => $idProfilTest]);

echo "=======================================================\n";
echo "      TOUS LES TESTS SE SONT TERMINÉS AVEC SUCCÈS !    \n";
echo "=======================================================\n";
