<?php
/**
 * Configuration d'exemple pour FindPro.
 * 
 * Copiez ce fichier sous le nom 'config.local.php' dans le même dossier
 * et adaptez les valeurs selon votre environnement local.
 * 
 * IMPORTANT : Ne commitez jamais 'config.local.php'.
 */

return [
    // Paramètres de connexion à la base de données
    'db_host'         => 'localhost',
    'db_name'         => 'findpro',
    'db_user'         => 'votre_utilisateur',
    'db_pass'         => 'votre_mot_de_passe',
    'db_charset'      => 'utf8mb4',

    // Paramètres généraux de l'application
    'app_url'         => 'http://localhost/findpro',
    'app_env'         => 'dev', // 'dev' ou 'prod'

    // Limites de téléversement (en octets : 2097152 = 2 Mo)
    'upload_max_size' => 2097152,
];
