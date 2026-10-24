<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

echo "--- CATEGORIES ---\n";
$cats = $pdo->query("SELECT id_categorie, libelle, slug FROM categorie ORDER BY id_categorie")->fetchAll();
print_r($cats);

echo "--- COMPETENCES ---\n";
$comps = $pdo->query("SELECT id_competence, id_categorie, libelle FROM competence ORDER BY id_categorie, id_competence")->fetchAll();
print_r($comps);

echo "--- PROFILS EN BASE ---\n";
$pros = $pdo->query("
    SELECT p.id_profil, p.titre, p.statut_validation, u.statut_compte, u.prenom, u.nom, c.slug as cat_slug, p.zone, p.disponibilite
    FROM profil_professionnel p
    JOIN utilisateur u ON p.id_utilisateur = u.id_utilisateur
    LEFT JOIN categorie c ON p.id_categorie = c.id_categorie
")->fetchAll();
print_r($pros);
