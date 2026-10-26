<?php
/**
 * Endpoint de Polling AJAX (Chat) — FindPro.
 * 
 * - Méthode : GET uniquement (réponse JSON).
 * - Paramètres :
 *     * id : identifiant de la conversation
 *     * apres : identifiant du dernier message déjà connu côté client (id_message)
 * - Contrôles d'accès :
 *     * Authentification requise (401 si anonyme).
 *     * L'utilisateur doit être participant (client ou pro) de la demande liée (403).
 *     * L'administrateur non-participant n'a pas accès (403).
 * - Réponse JSON :
 *     * Liste des nouveaux messages (id_message > apres) classés chronologiquement.
 *     * Statut actuel de la conversation (active ou lecture_seule).
 *     * Nouveau dernier identifiant connu.
 * - Mise à jour automatique de l'indicateur de lecture (lu = 1) pour les messages reçus.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// 1. Contrôle de la méthode HTTP (GET uniquement)
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode([
        'succes'  => false,
        'message' => 'Méthode non autorisée. Seule la méthode GET est acceptée.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/securite.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

// 2. Contrôle de l'authentification
demarrer_session();
$utilisateurConnecte = utilisateur_courant();
session_write_close(); // Déverrouille la session immédiatement pour éviter tout blocage de requêtes simultanées

if (!$utilisateurConnecte || empty($utilisateurConnecte['id'])) {
    http_response_code(401);
    echo json_encode([
        'succes'  => false,
        'message' => 'Authentification requise pour interroger les messages.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$idUtilisateurConnecte = (int) $utilisateurConnecte['id'];

// 3. Validation des paramètres GET
$idConversation = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$apresIdMessage = isset($_GET['apres']) ? max(0, (int) $_GET['apres']) : 0;

if ($idConversation <= 0) {
    http_response_code(400);
    echo json_encode([
        'succes'  => false,
        'message' => 'Identifiant de conversation manquant ou invalide.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = db();

// 4. Contrôle d'accès strict (jointure conversation, demande_service, profil_professionnel)
$stmtConv = $pdo->prepare('
    SELECT 
        c.id_conversation,
        c.id_demande,
        c.statut AS conversation_statut,
        d.statut AS demande_statut,
        d.id_client,
        d.id_profil,
        p.id_utilisateur AS pro_id_utilisateur,
        p.photo AS pro_photo,
        u_pro.nom AS pro_nom,
        u_pro.prenom AS pro_prenom,
        u_client.nom AS client_nom,
        u_client.prenom AS client_prenom
    FROM conversation c
    JOIN demande_service d ON c.id_demande = d.id_demande
    JOIN profil_professionnel p ON d.id_profil = p.id_profil
    JOIN utilisateur u_pro ON p.id_utilisateur = u_pro.id_utilisateur
    JOIN utilisateur u_client ON d.id_client = u_client.id_utilisateur
    WHERE c.id_conversation = :id_conv
    LIMIT 1
');
$stmtConv->execute(['id_conv' => $idConversation]);
$conv = $stmtConv->fetch(PDO::FETCH_ASSOC);

if (!$conv) {
    http_response_code(404);
    echo json_encode([
        'succes'  => false,
        'message' => 'Conversation introuvable.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Contrôle de participation : client ou professionnel assigné uniquement
$estClient = ($idUtilisateurConnecte === (int) $conv['id_client']);
$estPro    = ($idUtilisateurConnecte === (int) $conv['pro_id_utilisateur']);

if (!$estClient && !$estPro) {
    http_response_code(403);
    echo json_encode([
        'succes'  => false,
        'message' => 'Accès non autorisé. Vous n\'êtes pas participant de cette conversation.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 5. Récupération des nouveaux messages plus récents que $apresIdMessage
$stmtMsg = $pdo->prepare('
    SELECT 
        m.id_message,
        m.id_conversation,
        m.id_expediteur,
        m.contenu,
        m.date_envoi,
        m.lu,
        u.nom AS exp_nom,
        u.prenom AS exp_prenom,
        u.role AS exp_role
    FROM message m
    JOIN utilisateur u ON m.id_expediteur = u.id_utilisateur
    WHERE m.id_conversation = :id_conv
      AND m.id_message > :apres
    ORDER BY m.date_envoi ASC, m.id_message ASC
    LIMIT 50
');
$stmtMsg->bindValue(':id_conv', $idConversation, PDO::PARAM_INT);
$stmtMsg->bindValue(':apres', $apresIdMessage, PDO::PARAM_INT);
$stmtMsg->execute();
$lignesMessages = $stmtMsg->fetchAll(PDO::FETCH_ASSOC);

// 6. Marquage automatique des messages reçus comme lus
if (!empty($lignesMessages)) {
    $stmtLu = $pdo->prepare('
        UPDATE message 
        SET lu = 1 
        WHERE id_conversation = :id_conv 
          AND id_expediteur != :id_user 
          AND id_message > :apres 
          AND lu = 0
    ');
    $stmtLu->execute([
        'id_conv' => $idConversation,
        'id_user' => $idUtilisateurConnecte,
        'apres'   => $apresIdMessage,
    ]);
}

// 7. Formatage propre des données pour le client JavaScript
$nouveauDernierId = $apresIdMessage;
$messagesFormates = [];

// Calcul des URLs de photo
$avatarDefaut = url('assets/img/avatar-defaut.svg');
$photoProUrl = (!empty($conv['pro_photo']) && file_exists(__DIR__ . '/../' . $conv['pro_photo']))
    ? url($conv['pro_photo'])
    : $avatarDefaut;
$photoClientUrl = $avatarDefaut;

// Calcul du nom affiché du pro
$nomProBrut = trim((string) $conv['pro_nom']);
$initialePro = $nomProBrut !== '' ? mb_strtoupper(mb_substr($nomProBrut, 0, 1, 'UTF-8'), 'UTF-8') . '.' : '';
$prenomPro = mb_convert_case(trim((string) $conv['pro_prenom']), MB_CASE_TITLE, 'UTF-8');
$nomAffichePro = trim($prenomPro . ' ' . $initialePro);
if ($nomAffichePro === '') {
    $nomAffichePro = 'Professionnel';
}

$prenomClient = mb_convert_case(trim((string) $conv['client_prenom']), MB_CASE_TITLE, 'UTF-8');
$nomClient = mb_convert_case(trim((string) $conv['client_nom']), MB_CASE_TITLE, 'UTF-8');
$nomAfficheClient = trim($prenomClient . ' ' . $nomClient);

foreach ($lignesMessages as $msg) {
    $idMsg = (int) $msg['id_message'];
    if ($idMsg > $nouveauDernierId) {
        $nouveauDernierId = $idMsg;
    }

    $estMoi = ((int) $msg['id_expediteur'] === $idUtilisateurConnecte);
    
    // Détermination du nom de l'expéditeur et de sa photo
    if ($estMoi) {
        $nomAuteur = 'Vous';
        $photoAuteur = $estPro ? $photoProUrl : $photoClientUrl;
    } elseif ($msg['exp_role'] === 'professionnel') {
        $nomAuteur = $nomAffichePro . ' (Pro)';
        $photoAuteur = $photoProUrl;
    } else {
        $nomAuteur = $nomAfficheClient . ' (Client)';
        $photoAuteur = $photoClientUrl;
    }

    $timestamp = strtotime($msg['date_envoi']);
    $dateFormatee = $timestamp ? date('d/m/Y à H:i', $timestamp) : $msg['date_envoi'];

    $messagesFormates[] = [
        'id_message'     => $idMsg,
        'id_expediteur'  => (int) $msg['id_expediteur'],
        'nom_auteur'     => $nomAuteur,
        'photo_url'      => $photoAuteur,
        'contenu'        => (string) $msg['contenu'], // Texte brut
        'date_envoi'     => (string) $msg['date_envoi'],
        'date_formatee'  => $dateFormatee,
        'est_moi'        => $estMoi,
        'lu'             => (int) $msg['lu'],
    ];
}

// 8. Vérification de l'indicateur "En train d'écrire..." (Format WhatsApp exact)
$isTyping = false;
$typingTexte = '';
$typingPhoto = $avatarDefaut;
$typingFile = __DIR__ . '/../scratch/typing_conv_' . $idConversation . '.json';

if (file_exists($typingFile)) {
    $dataTyping = json_decode((string) file_get_contents($typingFile), true);
    if (is_array($dataTyping) && !empty($dataTyping['time'])) {
        // Valide si mis à jour dans les 3.5 dernières secondes et émis par l'autre participant
        if ((time() - (int) $dataTyping['time']) <= 3 && (int) ($dataTyping['user_id'] ?? 0) !== $idUtilisateurConnecte) {
            $isTyping = true;
            $typingPhoto = (string) ($dataTyping['photo_url'] ?? $avatarDefaut);
            $roleEmetteur = (string) ($dataTyping['role'] ?? '');
            if ($roleEmetteur === 'client' || $estPro) {
                $typingTexte = 'Le client écrit…';
            } else {
                $typingTexte = 'Le professionnel écrit…';
            }
        }
    }
}

// 9. IDs des messages récents envoyés par l'utilisateur connecté qui sont maintenant marqués comme lus (accusés de lecture temps réel)
$stmtLusParAutre = $pdo->prepare('
    SELECT id_message 
    FROM message 
    WHERE id_conversation = :id_conv 
      AND id_expediteur = :mon_id 
      AND lu = 1
    ORDER BY id_message DESC
    LIMIT 30
');
$stmtLusParAutre->execute([
    'id_conv' => $idConversation,
    'mon_id'  => $idUtilisateurConnecte,
]);
$idsLus = $stmtLusParAutre->fetchAll(PDO::FETCH_COLUMN);

// 10. Réponse JSON
echo json_encode([
    'succes'              => true,
    'id_conversation'     => $idConversation,
    'conversation_statut' => (string) $conv['conversation_statut'],
    'est_lecture_seule'   => ($conv['conversation_statut'] === 'lecture_seule'),
    'demande_statut'      => (string) $conv['demande_statut'],
    'dernier_id'          => $nouveauDernierId,
    'nb_messages'         => count($messagesFormates),
    'messages'            => $messagesFormates,
    'ids_lus'             => array_map('intval', $idsLus ?: []),
    'en_train_decrire'    => $isTyping,
    'typing_texte'        => $typingTexte,
    'typing_nom'          => $typingTexte,
    'typing_photo'        => $typingPhoto,
], JSON_UNESCAPED_UNICODE);
exit;
