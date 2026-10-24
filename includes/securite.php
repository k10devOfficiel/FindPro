<?php
/**
 * Module centralisé d'émission des en-têtes de sécurité HTTP — FindPro.
 * 
 * En-têtes émis :
 * 1. X-Content-Type-Options: nosniff
 *    -> Neutralise le MIME-sniffing en forçant le navigateur à respecter strictement
 *       le Content-Type déclaré par le serveur (empêche d'exécuter un fichier texte/image comme script).
 * 
 * 2. X-Frame-Options: SAMEORIGIN
 *    -> Protège contre le Clickjacking (détournement de clic). Interdit l'inclusion
 *       des pages de FindPro au sein de cadres (iframe, frame, object) d'un site tiers.
 * 
 * 3. Referrer-Policy: strict-origin-when-cross-origin
 *    -> Protège la confidentialité des requêtes : transmet l'URL complète uniquement en interne,
 *       et ne transmet que l'origine (sans paramètres ni chemin) vers des domaines tiers en HTTPS.
 * 
 * 4. Content-Security-Policy (CSP) :
 *    -> Immunise l'application contre les attaques XSS (Cross-Site Scripting) :
 *       - Autorise l'exécution des scripts locaux ('self') et du CDN Bootstrap (cdn.jsdelivr.net).
 *       - Interdit formellement les scripts en ligne (<script>...</script> ou onclick).
 *       - Autorise les feuilles de style locales, Bootstrap CDN et Google Fonts.
 *       - Autorise les polices Google Fonts et Bootstrap Icons.
 */

declare(strict_types=1);

/**
 * Envoie l'ensemble des en-têtes HTTP de sécurité recommandés.
 *
 * @return void
 */
function envoyer_entetes_securite(): void
{
    if (headers_sent()) {
        return;
    }

    // 1. Protection contre le détournement de type MIME
    header('X-Content-Type-Options: nosniff');

    // 2. Protection contre le Clickjacking
    header('X-Frame-Options: SAMEORIGIN');

    // 3. Politique de référencement stricte
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // 4. Politique de sécurité de contenu (Content-Security-Policy)
    $cspDirectives = [
        "default-src 'self'",
        "script-src 'self' https://cdn.jsdelivr.net",
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com",
        "font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com data:",
        "img-src 'self' data:",
        "connect-src 'self'",
        "frame-ancestors 'self'",
        "base-uri 'self'",
        "form-action 'self'",
    ];

    header('Content-Security-Policy: ' . implode('; ', $cspDirectives));
}

// Exécution automatique dès l'inclusion
envoyer_entetes_securite();
