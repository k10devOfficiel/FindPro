<?php
/**
 * Déconnexion sécurisée de la plateforme FindPro.
 * 
 * - Accepte UNIQUEMENT les requêtes POST.
 * - Validation stricte du jeton CSRF obligatoire (protection anti-déconnexion forcée).
 * - Destruction complète de la session et suppression du cookie findpro_sid.
 * - Message flash de confirmation et redirection vers la page d'accueil.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/errors.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/csrf.php';

demarrer_session();

// 1. Refus formel de toute méthode autre que POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>405 Méthode non autorisée — FindPro</title>
        <style>
            body { font-family: sans-serif; background: #f8fafc; color: #1f2937; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
            .box { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 32px; text-align: center; max-width: 460px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
            h1 { font-size: 1.3rem; color: #b91c1c; margin-bottom: 12px; }
            p { color: #64748b; line-height: 1.5; margin-bottom: 20px; }
            a { color: #111827; font-weight: 600; text-decoration: none; border: 1px solid #e2e8f0; padding: 8px 16px; border-radius: 6px; }
        </style>
    </head>
    <body>
        <div class="box">
            <h1>Action non autorisée</h1>
            <p>La déconnexion doit obligatoirement être déclenchée par un formulaire sécurisé (méthode POST).</p>
            <a href="<?= url('') ?>">← Retour à l'accueil</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 2. Contrôle du jeton CSRF
csrf_verifier();

// 3. Déconnexion complète et suppression du cookie client
deconnecter_session();

// 4. Initialisation d'une nouvelle session saine pour poser le message flash
demarrer_session();
flash_set('info', 'Vous avez été déconnecté avec succès. À bientôt !');

// 5. Redirection vers la page d'accueil
redirect(url(''));
