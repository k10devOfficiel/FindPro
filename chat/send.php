<?php
/**
 * Endpoint API d'envoi de message (Chat) — FindPro.
 * 
 * - Méthode : POST uniquement (réponse JSON).
 * - Vérifications :
 *     * Authentification de l'utilisateur (401 si anonyme).
 *     * Protection CSRF (en-tête X-CSRF-Token ou champ csrf_token).
 *     * Appartenance : l'utilisateur doit être participant (client ou pro) de la conversation (403).
 *     * Statut de conversation : active (403/409 si lecture_seule).
 *     * Contenu après trim : de 1 à 2 000 caractères (422 si vide ou > 2000).
 * - Règle RG13 : Si demande au statut 'envoyee' ET expéditeur = client, maximum 3 messages au total.
 *   Au 4e envoi : HTTP 429 avec message dédié. Le pro n'est jamais limité.
 * - Limite de débit : Maximum 20 messages par minute par utilisateur (HTTP 429).
 * - Enregistrement : Requête préparée, stockage en texte brut (échappement à l'affichage).
 * - Retour : Message créé en JSON.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// 1. Contrôle strict de la méthode HTTP (POST uniquement)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode([
        'succes'  => false,
        'message' => 'Méthode non autorisée. Seules les requêtes POST sont acceptées.',
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

if (!$utilisateurConnecte || empty($utilisateurConnecte['id'])) {
    http_response_code(401);
    echo json_encode([
        'succes'  => false,
        'message' => 'Authentification requise pour envoyer un message.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$idUtilisateurConnecte = (int) $utilisateurConnecte['id'];

// 3. Récupération et parsing des données soumises (support POST classique et JSON)
$donnees = $_POST;
$rawBody = file_get_contents('php://input');

if (!empty($rawBody)) {
    $jsonDecoded = json_decode($rawBody, true);
    if (is_array($jsonDecoded)) {
        $donnees = array_merge($donnees, $jsonDecoded);
    }
}

// 4. Contrôle de sécurité CSRF (X-CSRF-Token ou champ csrf_token)
$jetonSession = $_SESSION['csrf_token'] ?? null;
$jetonSoumis  = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($donnees['csrf_token'] ?? null);

$csrfValide = is_string($jetonSession)
    && is_string($jetonSoumis)
    && $jetonSession !== ''
    && hash_equals($jetonSession, $jetonSoumis);

if (!$csrfValide) {
    http_response_code(403);
    echo json_encode([
        'succes'  => false,
        'message' => 'Action non autorisée : jeton CSRF manquant ou invalide.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 5. Validation de l'identifiant de conversation
$idConversation = (int) ($donnees['id_conversation'] ?? ($donnees['id'] ?? 0));
if ($idConversation <= 0) {
    http_response_code(400);
    echo json_encode([
        'succes'  => false,
        'message' => 'Identifiant de conversation invalide ou manquant.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 6. Validation du contenu (de 1 à 2 000 caractères après trim)
$contenuBrut = (string) ($donnees['contenu'] ?? ($donnees['message'] ?? ''));
$contenu = trim($contenuBrut);
$longueurContenu = mb_strlen($contenu, 'UTF-8');

if ($longueurContenu < 1) {
    http_response_code(422);
    echo json_encode([
        'succes'  => false,
        'message' => 'Le contenu du message ne peut pas être vide.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($longueurContenu > 2000) {
    http_response_code(422);
    echo json_encode([
        'succes'  => false,
        'message' => 'Le contenu du message ne peut pas dépasser 2 000 caractères.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = db();

// 7. Vérification de la conversation et des participants (jointure conversation, demande_service, profil_professionnel)
$stmtConv = $pdo->prepare('
    SELECT 
        c.id_conversation,
        c.id_demande,
        c.statut AS conversation_statut,
        d.statut AS demande_statut,
        d.id_client,
        d.id_profil,
        p.id_utilisateur AS pro_id_utilisateur
    FROM conversation c
    JOIN demande_service d ON c.id_demande = d.id_demande
    JOIN profil_professionnel p ON d.id_profil = p.id_profil
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

// Contrôle de participation : uniquement le client ou le professionnel assigné
$estClient = ($idUtilisateurConnecte === (int) $conv['id_client']);
$estPro    = ($idUtilisateurConnecte === (int) $conv['pro_id_utilisateur']);

if (!$estClient && !$estPro) {
    http_response_code(403);
    echo json_encode([
        'succes'  => false,
        'message' => 'Action non autorisée. Vous n\'êtes pas participant de cette conversation.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Contrôle du statut de la conversation (active uniquement)
if ($conv['conversation_statut'] !== 'active') {
    http_response_code(403);
    echo json_encode([
        'succes'  => false,
        'message' => 'Cette conversation est clôturée en lecture seule. L\'envoi de messages n\'est plus autorisé.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 8. RÈGLE RG13 : Limitation client avant acceptation de la demande
// Si la demande est au statut 'envoyee' et que l'expéditeur est le client : maximum 3 messages
// Au 4e envoi : HTTP 429
if ($conv['demande_statut'] === 'envoyee' && $estClient) {
    $stmtCountClient = $pdo->prepare('
        SELECT COUNT(*) 
        FROM message 
        WHERE id_conversation = :id_conv 
          AND id_expediteur = :id_client
    ');
    $stmtCountClient->execute([
        'id_conv'   => $idConversation,
        'id_client' => $idUtilisateurConnecte,
    ]);
    $nbMessagesClient = (int) $stmtCountClient->fetchColumn();

    if ($nbMessagesClient >= 3) {
        http_response_code(429);
        echo json_encode([
            'succes'  => false,
            'message' => "Le professionnel n'a pas encore répondu à votre demande. Vous pourrez écrire à nouveau après son acceptation.",
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 9. Limite supplémentaire de débit : Pas plus de 20 messages par minute par utilisateur
$stmtDebit = $pdo->prepare('
    SELECT COUNT(*) 
    FROM message 
    WHERE id_expediteur = :id_user 
      AND date_envoi >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
');
$stmtDebit->execute(['id_user' => $idUtilisateurConnecte]);
$messagesDerniereMinute = (int) $stmtDebit->fetchColumn();

if ($messagesDerniereMinute >= 20) {
    http_response_code(429);
    echo json_encode([
        'succes'  => false,
        'message' => 'Limite de débit atteinte : pas plus de 20 messages par minute. Veuillez patienter un instant.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 10. Enregistrement avec requête préparée en texte brut (pas d\'échappement en BDD)
$stmtInsert = $pdo->prepare('
    INSERT INTO message (id_conversation, id_expediteur, contenu, date_envoi, lu)
    VALUES (:id_conv, :id_exp, :contenu, NOW(), 0)
');
$stmtInsert->execute([
    'id_conv'  => $idConversation,
    'id_exp'   => $idUtilisateurConnecte,
    'contenu'  => $contenu,
]);

$idNouveauMessage = (int) $pdo->lastInsertId();

// Récupération de l'enregistrement créé
$stmtSelect = $pdo->prepare('
    SELECT id_message, id_conversation, id_expediteur, contenu, date_envoi, lu
    FROM message 
    WHERE id_message = :id
');
$stmtSelect->execute(['id' => $idNouveauMessage]);
$messageCree = $stmtSelect->fetch(PDO::FETCH_ASSOC);

// Conversion des types pour un JSON propre
if ($messageCree) {
    $messageCree['id_message']      = (int) $messageCree['id_message'];
    $messageCree['id_conversation'] = (int) $messageCree['id_conversation'];
    $messageCree['id_expediteur']   = (int) $messageCree['id_expediteur'];
    $messageCree['lu']              = (int) $messageCree['lu'];
}

// 11. Réponse JSON de succès (HTTP 201 Created)
http_response_code(201);
echo json_encode([
    'succes'  => true,
    'message' => 'Message envoyé avec succès.',
    'donnees' => $messageCree,
    'data'    => $messageCree,
], JSON_UNESCAPED_UNICODE);
exit;
