<?php
/**
 * Test de vérification de l'affichage du profil soumis à l'étape 6.6 dans admin/validation.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';

demarrer_session();

// Authentification Admin
$pdo = db();
$admin = $pdo->query("SELECT id_utilisateur, prenom, nom, email, role FROM utilisateur WHERE email = 'k10developpeur@gmail.com'")->fetch();
$_SESSION['utilisateur'] = [
    'id'     => (int) $admin['id_utilisateur'],
    'role'   => 'admin',
    'prenom' => $admin['prenom'],
    'nom'    => $admin['nom'],
    'email'  => $admin['email'],
];

$_GET = []; // Paramètres GET par défaut

ob_start();
require __DIR__ . '/../admin/validation.php';
$html = ob_get_clean();

echo "====================================================================\n";
echo "VÉRIFICATION DU PROFIL SOUMIS DANS LA LISTE ADMIN/VALIDATION.PHP\n";
echo "====================================================================\n";

$tests = [
    "Présence du nom 'Ibrahim Traoré'" => str_contains($html, 'Ibrahim Traoré'),
    "Présence de l'email 'pro@findpro.ci'" => str_contains($html, 'pro@findpro.ci'),
    "Présence de la catégorie 'Développement informatique'" => str_contains($html, 'Développement informatique'),
    "Présence du titre 'Développeur Full-Stack PHP & Modern Web' (échappé avec e())" => str_contains($html, e('Développeur Full-Stack PHP & Modern Web')),
    "Présence du badge 'En attente' (statut soumis)" => str_contains($html, 'En attente'),
    "Présence du bouton 'Examiner'" => str_contains($html, 'Examiner'),
    "Lien du bouton 'Examiner' pointant vers validation-detail.php?id=" => preg_match('/href="[^"]*admin\/validation-detail\.php\?id=\d+"/', $html) === 1,
    "Présence du filtre Statut actif" => str_contains($html, 'statut=soumis'),
    "Présence du filtre Catégorie actif" => str_contains($html, 'name="categorie"'),
    "Compteur de la file en attente affichant au moins 1" => str_contains($html, 'Toutes les catégories (1)') || str_contains($html, 'Soumis <span class="badge bg-info text-dark ms-1">1</span>'),
];

$allPass = true;
foreach ($tests as $label => $pass) {
    if ($pass) {
        echo "  [PASS] {$label}\n";
    } else {
        echo "  [FAIL] {$label}\n";
        $allPass = false;
    }
}

echo "--------------------------------------------------------------------\n";
if ($allPass) {
    echo "RÉSULTAT : Le profil soumis à l'étape 6.6 apparaît parfaitement dans admin/validation.php !\n";
} else {
    echo "RÉSULTAT : Échec de certaines vérifications.\n";
}
echo "--------------------------------------------------------------------\n";
