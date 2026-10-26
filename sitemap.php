<?php
/**
 * Générateur de Sitemap XML dynamique pour Google — FindPro
 *
 * Conforme au protocole sitemaps.org 0.9.
 * Liste toutes les pages publiques indexables : accueil, catégories de métiers,
 * profils professionnels approuvés et pages légales.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/xml; charset=UTF-8');
header('X-Robots-Tag: noindex'); // Le sitemap lui-même n'est pas une page de contenu

$pdo = db();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <!-- Page d'accueil -->
    <url>
        <loc><?= e(url('')) ?></loc>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>

    <!-- Catégorie Développement informatique (Mise en avant pour recherches Développeurs) -->
    <url>
        <loc><?= e(url('category.php?slug=developpement')) ?></loc>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>

    <!-- Catégorie Montage vidéo -->
    <url>
        <loc><?= e(url('category.php?slug=montage-video')) ?></loc>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>

    <!-- Catégorie Cybersécurité -->
    <url>
        <loc><?= e(url('category.php?slug=cybersecurite')) ?></loc>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>

    <!-- Page de recherche avancée -->
    <url>
        <loc><?= e(url('search.php')) ?></loc>
        <changefreq>daily</changefreq>
        <priority>0.7</priority>
    </url>

    <!-- Profils professionnels approuvés et publics -->
    <?php
    try {
        $stmtProfils = $pdo->query("
            SELECT 
                p.id_profil, 
                COALESCE(p.date_maj, p.date_validation, NOW()) AS date_maj
            FROM profil_professionnel p
            JOIN utilisateur u ON p.id_utilisateur = u.id_utilisateur
            WHERE p.statut_validation = 'approuve' AND u.statut_compte = 'actif'
            ORDER BY p.date_maj DESC
        ");
        $profils = $stmtProfils->fetchAll(PDO::FETCH_ASSOC);

        foreach ($profils as $p):
            $dateIso = date('Y-m-d', strtotime((string) $p['date_maj']));
    ?>
    <url>
        <loc><?= e(url('profile.php?id=' . (int) $p['id_profil'])) ?></loc>
        <lastmod><?= $dateIso ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    <?php 
        endforeach;
    } catch (\Throwable $e) {
        // En cas d'indisponibilité momentanée de la base, le reste du sitemap reste valide
    }
    ?>

    <!-- Pages légales et informatives -->
    <url>
        <loc><?= e(url('mentions.php')) ?></loc>
        <changefreq>monthly</changefreq>
        <priority>0.3</priority>
    </url>
    <url>
        <loc><?= e(url('confidentialite.php')) ?></loc>
        <changefreq>monthly</changefreq>
        <priority>0.3</priority>
    </url>
    <url>
        <loc><?= e(url('regles.php')) ?></loc>
        <changefreq>monthly</changefreq>
        <priority>0.3</priority>
    </url>
</urlset>
