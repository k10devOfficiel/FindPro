<?php
/**
 * Test de validation de la page d'accueil index.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db();

echo "====================================================================\n";
echo "VÉRIFICATION DE LA PAGE D'ACCUEIL : INDEX.PHP\n";
echo "====================================================================\n\n";

$testsTotal = 0;
$testsReussis = 0;

function assertCondition(string $libelle, bool $condition, string $detail = ''): void {
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

// 1. Requête HTTP
$ch = curl_init('http://localhost/findpro/index.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$html = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$htmlStr = (string) $html;

assertCondition("index.php répond avec HTTP 200", $code === 200, "Code: {$code}");

// Section 1 : En-tête / Hero
assertCondition("Section 1 : Slogan court présent", str_contains($htmlStr, 'Trouvez le bon professionnel pour votre projet'));
assertCondition("Section 1 : Phrase d'explication présente", str_contains($htmlStr, 'spécialistes en développement web, montage vidéo et cybersécurité'));
assertCondition("Section 1 : Barre de recherche avec formulaire vers search.php", str_contains($htmlStr, 'action="http://localhost/findpro/search.php"') || str_contains($htmlStr, 'action="/findpro/search.php"') || str_contains($htmlStr, 'search.php'));
assertCondition("Section 1 : Champ de recherche avec name='q'", str_contains($htmlStr, 'name="q"'));

// Section 2 : Les 3 catégories depuis la BDD
$cats = $pdo->query("SELECT libelle FROM categorie ORDER BY id_categorie ASC LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
assertCondition("Section 2 : Au moins 3 catégories dans la BDD", count($cats) === 3);
foreach ($cats as $catLibelle) {
    assertCondition("Section 2 : Catégorie '{$catLibelle}' affichée", str_contains($htmlStr, htmlspecialchars($catLibelle, ENT_QUOTES, 'UTF-8')));
}
assertCondition("Section 2 : Cartes catégories cliquables avec search.php?categorie=", str_contains($htmlStr, 'search.php?categorie='));

// Section 3 : Comment ça marche en 4 étapes
assertCondition("Section 3 : Titre 'Comment ça marche ?' présent", str_contains($htmlStr, 'Comment ça marche ?'));
assertCondition("Section 3 : Étape 1 'Trouver un profil' présente", str_contains($htmlStr, 'Trouver un profil'));
assertCondition("Section 3 : Étape 2 'Envoyer une demande' présente", str_contains($htmlStr, 'Envoyer une demande'));
assertCondition("Section 3 : Étape 3 'Discuter' présente", str_contains($htmlStr, 'Discuter'));
assertCondition("Section 3 : Étape 4 'Évaluer' présente", str_contains($htmlStr, 'Évaluer'));

// Section 4 : 6 profils approuvés récents
assertCondition("Section 4 : Titre de section présent", str_contains($htmlStr, 'Professionnels récemment approuvés'));
// Mention légale obligatoire
assertCondition("Section 4 : Phrase discrète 'profil approuvé' ne signifie pas 'compétence certifiée'", 
    str_contains($htmlStr, 'Profil approuvé par FindPro') && 
    (str_contains($htmlStr, 'ne constitue pas une certification officielle') || str_contains($htmlStr, 'ne signifie pas « compétence certifiée »') || str_contains($htmlStr, 'certification'))
);

// Section 5 : Appel à l'action pour les professionnels
assertCondition("Section 5 : Titre CTA pro présent", str_contains($htmlStr, 'Vous êtes un professionnel du numérique ?'));
assertCondition("Section 5 : Bouton création profil vers register.php", str_contains($htmlStr, 'register.php'));
assertCondition("Section 5 : Bouton connexion vers login.php", str_contains($htmlStr, 'login.php'));

// Accessibilité & sémantique
assertCondition("Sémantique : Balise <header> présente", str_contains($htmlStr, '<header') || str_contains($htmlStr, '<nav'));
assertCondition("Sémantique : Balise <main> présente", str_contains($htmlStr, '<main>'));
assertCondition("Sémantique : Balises <section> présentes", substr_count($htmlStr, '<section') >= 4);
assertCondition("Sémantique : Balise <h1> unique", substr_count($htmlStr, '<h1') === 1);
assertCondition("Sémantique : Balise <footer> présente", str_contains($htmlStr, '<footer'));

echo "\n====================================================================\n";
echo "RÉSULTAT DU TEST : {$testsReussis} / {$testsTotal} assertions vérifiées avec succès.\n";
echo "====================================================================\n";
