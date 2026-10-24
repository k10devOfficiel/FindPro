<?php
/**
 * Initialisation du profil professionnel complet et soumis pour pro@findpro.ci (étape 6.6)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

// 1. Récupération de l'utilisateur professionnel pro@findpro.ci
$proUser = $pdo->query("SELECT id_utilisateur, prenom, nom, email FROM utilisateur WHERE email = 'pro@findpro.ci'")->fetch();
if (!$proUser) {
    die("Utilisateur pro@findpro.ci introuvable.\n");
}
$idUser = (int) $proUser['id_utilisateur'];

// 2. Récupération de la catégorie 'developpement'
$cat = $pdo->query("SELECT id_categorie, libelle FROM categorie WHERE slug = 'developpement'")->fetch();
$idCat = (int) $cat['id_categorie'];

// 3. Compétences en développement
$comps = $pdo->query("SELECT id_competence FROM competence WHERE id_categorie = {$idCat} LIMIT 4")->fetchAll(PDO::FETCH_COLUMN);

// 4. Nettoyage et insertion du profil professionnel
$pdo->exec("DELETE FROM profil_professionnel WHERE id_utilisateur = {$idUser}");

$desc = "Développeur web full-stack avec plus de 6 ans d'expérience dans la création d'applications web robustes et sécurisées en PHP, MySQL et JavaScript. Spécialisé en architecture logicielle, intégration soignée et optimisation des performances.";

$stmtInsertProfil = $pdo->prepare('
    INSERT INTO profil_professionnel (
        id_utilisateur,
        id_categorie,
        titre,
        description,
        zone,
        disponibilite,
        photo,
        statut_validation,
        date_soumission
    ) VALUES (
        :id_user,
        :id_cat,
        :titre,
        :desc,
        :zone,
        "disponible",
        NULL,
        "soumis",
        NOW() - INTERVAL 2 HOUR
    )
');

$stmtInsertProfil->execute([
    'id_user' => $idUser,
    'id_cat'  => $idCat,
    'titre'   => 'Développeur Full-Stack PHP & Modern Web',
    'desc'    => $desc,
    'zone'    => 'Abidjan - Cocody & Remote',
]);

$idProfil = (int) $pdo->lastInsertId();

// 5. Compétences
foreach ($comps as $cId) {
    $pdo->exec("INSERT INTO profil_competence (id_profil, id_competence, niveau_declare) VALUES ({$idProfil}, {$cId}, 'avance')");
}

// 6. Projet Portfolio
$pdo->exec("
    INSERT INTO projet_portfolio (id_profil, titre, description, lien_externe)
    VALUES ({$idProfil}, 'Plateforme SaaS FindPro MVP', 'Conception et réalisation intégrale d\'une marketplace de services numériques', 'https://findpro.ci/portfolio-demo')
");

echo "Profil professionnel soumis créé avec succès pour {$proUser['prenom']} {$proUser['nom']} !\n";
echo "ID Profil : {$idProfil}\n";
echo "Statut : soumis\n";
echo "Date de soumission : horodatée il y a 2h\n";
