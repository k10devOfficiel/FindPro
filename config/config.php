<?php
/**
 * Chargeur de configuration de FindPro.
 * Charge config.local.php et retourne le tableau de configuration.
 */

$localConfigFile = __DIR__ . '/config.local.php';

if (!file_exists($localConfigFile)) {
    http_response_code(500);
    die(
        "<h1>Erreur de configuration FindPro</h1>" .
        "<p>Le fichier de configuration locale <code>config/config.local.php</code> est introuvable.</p>" .
        "<p>Pour configurer l'application, veuillez copier <code>config/config.example.php</code> en <code>config/config.local.php</code> et y renseigner vos identifiants de base de données.</p>"
    );
}

$config = require $localConfigFile;

if (!is_array($config)) {
    http_response_code(500);
    die(
        "<h1>Erreur de configuration FindPro</h1>" .
        "<p>Le fichier <code>config/config.local.php</code> doit retourner un tableau PHP valide.</p>"
    );
}

return $config;
