<?php
/**
 * Module sécurisé de téléversement d'images — FindPro.
 * 
 * Assure les contrôles stricts de sécurité sur tout téléversement :
 * - Absence d'erreur d'upload et origine vérifiée (is_uploaded_file).
 * - Contrôle de taille maximale lue dans la configuration (2 Mo par défaut).
 * - Contrôle du type MIME réel via finfo sur le contenu binaire (JPG, PNG, WEBP uniquement).
 * - Contrôle d'intégrité de l'image et limitation des dimensions (max 4000x4000 px) via getimagesize.
 * - Génération d'un nom aléatoire sécurisé (random_bytes) avec extension déduite du MIME réel.
 * - Validation stricte du sous-dossier de destination (protection anti-traversée de répertoire).
 */

declare(strict_types=1);

/**
 * Téléverse et sécurise un fichier image dans un sous-dossier de uploads/.
 *
 * @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int} $fichier Données issues de $_FILES['champ']
 * @param string $sous_dossier Sous-dossier de destination (ex: 'avatars', 'portfolio')
 * @return string Chemin relatif normalisé du fichier enregistré (ex: 'uploads/avatars/ab12cd...webp')
 * @throws RuntimeException En cas de fichier invalide, trop lourd, corrompu ou d'échec d'enregistrement
 * @throws InvalidArgumentException En cas de sous-dossier non conforme
 */
function televerser_image(array $fichier, string $sous_dossier): string
{
    // 1. Vérification de la présence des clés obligatoires et de l'erreur d'upload
    if (!isset($fichier['error'], $fichier['tmp_name'], $fichier['size'])) {
        throw new RuntimeException("Structure de fichier téléversé invalide.");
    }

    $codeErreur = (int) $fichier['error'];
    if ($codeErreur !== UPLOAD_ERR_OK) {
        $message = match ($codeErreur) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "L'image dépasse la taille maximale autorisée par le serveur.",
            UPLOAD_ERR_PARTIAL                         => "Le fichier n'a été que partiellement téléversé. Veuillez réessayer.",
            UPLOAD_ERR_NO_FILE                         => "Aucun fichier n'a été sélectionné.",
            UPLOAD_ERR_NO_TMP_DIR                      => "Dossier temporaire manquant sur le serveur.",
            UPLOAD_ERR_CANT_WRITE                      => "Échec de l'écriture du fichier sur le disque du serveur.",
            UPLOAD_ERR_EXTENSION                      => "Le téléversement a été interrompu par une extension du serveur.",
            default                                    => "Une erreur est survenue lors du téléversement du fichier.",
        };
        throw new RuntimeException($message);
    }

    // 2. Vérification que le fichier temporaire existe et est accessible
    $cheminTemporaire = (string) $fichier['tmp_name'];
    if ($cheminTemporaire === '' || !file_exists($cheminTemporaire)) {
        throw new RuntimeException("Fichier temporaire introuvable.");
    }

    // Vérification de la légitimité de l'upload HTTP (is_uploaded_file)
    if (php_sapi_name() !== 'cli' && !is_uploaded_file($cheminTemporaire)) {
        throw new RuntimeException("Le fichier soumis ne provient pas d'un téléversement HTTP légitime.");
    }

    // 3. Vérification de la taille maximale (lue depuis la configuration FindPro)
    $config = require __DIR__ . '/../config/config.php';
    $tailleMax = (int) ($config['upload_max_size'] ?? 2097152); // 2 Mo (2 097 152 octets) par défaut

    $tailleReelle = filesize($cheminTemporaire);
    if ($tailleReelle === false || $tailleReelle > $tailleMax || (int) $fichier['size'] > $tailleMax) {
        $maxMo = round($tailleMax / (1024 * 1024), 1);
        throw new RuntimeException("Le fichier est trop volumineux. La taille maximale autorisée est de {$maxMo} Mo.");
    }

    // 4. Vérification du type MIME réel par inspection binaire (finfo)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        throw new RuntimeException("Impossible d'initialiser l'analyseur MIME du serveur.");
    }

    $mimeReel = finfo_file($finfo, $cheminTemporaire);
    finfo_close($finfo);

    $mimesAutorises = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    if (!is_string($mimeReel) || !isset($mimesAutorises[$mimeReel])) {
        throw new RuntimeException("Format de fichier non autorisé. Seules les images au format JPG, PNG ou WEBP sont acceptées.");
    }

    $extension = $mimesAutorises[$mimeReel];

    // 5. Validation que le fichier est une vraie image et limitation des dimensions (getimagesize)
    $infoDimensions = @getimagesize($cheminTemporaire);
    if ($infoDimensions === false) {
        throw new RuntimeException("Le fichier téléversé n'est pas une image valide ou est corrompu.");
    }

    $largeur = (int) ($infoDimensions[0] ?? 0);
    $hauteur = (int) ($infoDimensions[1] ?? 0);
    $maxDimension = 4000;

    if ($largeur <= 0 || $hauteur <= 0) {
        throw new RuntimeException("Dimensions d'image invalides.");
    }

    if ($largeur > $maxDimension || $hauteur > $maxDimension) {
        throw new RuntimeException("Les dimensions de l'image sont trop grandes ({$largeur}x{$hauteur} px). Le maximum autorisé est de {$maxDimension}x{$maxDimension} pixels.");
    }

    // 6. Nettoyage strict du sous-dossier (protection anti-traversée de répertoire)
    $sousDossierPropre = trim($sous_dossier, " \t\n\r\0\x0B/\\");
    if ($sousDossierPropre === '' || !preg_match('#^[a-zA-Z0-9_\-]+$#', $sousDossierPropre)) {
        throw new InvalidArgumentException("Sous-dossier de destination non valide.");
    }

    $dossierUploads = realpath(__DIR__ . '/../uploads');
    if ($dossierUploads === false) {
        $dossierUploads = __DIR__ . '/../uploads';
    }

    $dossierCible = $dossierUploads . DIRECTORY_SEPARATOR . $sousDossierPropre;
    if (!is_dir($dossierCible)) {
        if (!mkdir($dossierCible, 0755, true) && !is_dir($dossierCible)) {
            throw new RuntimeException("Impossible de créer le dossier de stockage sur le serveur.");
        }
    }

    // 7. Génération d'un nom de fichier aléatoire sécurisé (random_bytes)
    $nomFichier = bin2hex(random_bytes(16)) . '.' . $extension;
    $cheminFinal = $dossierCible . DIRECTORY_SEPARATOR . $nomFichier;

    // 8. Déplacement sécurisé du fichier
    $deplace = (php_sapi_name() === 'cli')
        ? @rename($cheminTemporaire, $cheminFinal)
        : @move_uploaded_file($cheminTemporaire, $cheminFinal);

    if (!$deplace) {
        throw new RuntimeException("Une erreur est survenue lors de l'enregistrement de l'image.");
    }

    // 9. Retour du chemin relatif normalisé
    return 'uploads/' . $sousDossierPropre . '/' . $nomFichier;
}
