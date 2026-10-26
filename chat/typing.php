<?php
/**
 * Endpoint de notification de saisie ("Est en train d'écrire...") — FindPro.
 * 
 * - Méthode : POST uniquement (réponse JSON).
 * - Actions gérées :
 *     * action = 'start' (défaut) : enregistre l'état de frappe avec horodatage et rôle
 *     * action = 'stop'           : supprime immédiatement l'état de frappe
 * - Ne bloque jamais la session PHP (appel immédiat de session_write_close).
 * - Sécurisé par contrôle d'appartenance strict à la conversation (403 si non participant).
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['succes' => false, 'message' => 'Méthode non autorisée.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

demarrer_session();
$utilisateurConnecte = utilisateur_courant();

if (!$utilisateurConnecte || empty($utilisateurConnecte['id'])) {
    session_write_close();
    http_response_code(401);
    echo json_encode(['succes' => false, 'message' => 'Non authentifié.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$idUtilisateurConnecte = (int) $utilisateurConnecte['id'];

// Récupération des données POST ou JSON
$rawBody = file_get_contents('php://input');
$donnees = $_POST;
if (!empty($rawBody)) {
    $jsonDecoded = json_decode($rawBody, true);
    if (is_array($jsonDecoded)) {
        $donnees = array_merge($donnees, $jsonDecoded);
    }
}

// Libération immédiate du verrou de session pour ne jamais ralentir les requêtes concurrentes
session_write_close();

$idConversation = (int) ($donnees['id_conversation'] ?? ($donnees['id'] ?? 0));
$action = (string) ($donnees['action'] ?? 'start');

if ($idConversation <= 0) {
    http_response_code(400);
    echo json_encode(['succes' => false, 'message' => 'ID conversation manquant.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$scratchDir = __DIR__ . '/../scratch';
if (!is_dir($scratchDir)) {
    @mkdir($scratchDir, 0755, true);
}
$typingFile = $scratchDir . '/typing_conv_' . $idConversation . '.json';

// Si l'utilisateur a arrêté d'écrire ou a envoyé son message : suppression instantanée
if ($action === 'stop') {
    if (file_exists($typingFile)) {
        @unlink($typingFile);
    }
    echo json_encode([
        'succes' => true,
        'action' => 'stop',
        'typing' => false
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = db();

// Vérification de la conversation et de l'habilitation des participants
$stmtConv = $pdo->prepare('
    SELECT 
        c.id_conversation,
        d.id_client,
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
    echo json_encode(['succes' => false, 'message' => 'Conversation introuvable.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$estClient = ($idUtilisateurConnecte === (int) $conv['id_client']);
$estPro    = ($idUtilisateurConnecte === (int) $conv['pro_id_utilisateur']);

if (!$estClient && !$estPro) {
    http_response_code(403);
    echo json_encode(['succes' => false, 'message' => 'Accès refusé.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Construction du libellé et de l'avatar de l'émetteur de la frappe
$avatarDefaut = url('assets/img/avatar-defaut.svg');

if ($estPro) {
    $role = 'professionnel';
    $nomProBrut = trim((string) $conv['pro_nom']);
    $initialePro = $nomProBrut !== '' ? mb_strtoupper(mb_substr($nomProBrut, 0, 1, 'UTF-8'), 'UTF-8') . '.' : '';
    $prenomPro = mb_convert_case(trim((string) $conv['pro_prenom']), MB_CASE_TITLE, 'UTF-8');
    $nomAffiche = trim($prenomPro . ' ' . $initialePro);
    if ($nomAffiche === '') {
        $nomAffiche = 'Le professionnel';
    }
    $photoUrl = !empty($conv['pro_photo']) && file_exists(__DIR__ . '/../' . $conv['pro_photo'])
        ? url($conv['pro_photo'])
        : $avatarDefaut;
} else {
    $role = 'client';
    $prenomClient = mb_convert_case(trim((string) $conv['client_prenom']), MB_CASE_TITLE, 'UTF-8');
    $nomClient = mb_convert_case(trim((string) $conv['client_nom']), MB_CASE_TITLE, 'UTF-8');
    $nomAffiche = trim($prenomClient . ' ' . $nomClient);
    if ($nomAffiche === '') {
        $nomAffiche = 'Le client';
    }
    $photoUrl = $avatarDefaut;
}

// Enregistrement de l'état de frappe avec horodatage haute précision
$payload = [
    'user_id'   => $idUtilisateurConnecte,
    'role'      => $role,
    'user_name' => $nomAffiche,
    'photo_url' => $photoUrl,
    'time'      => time(),
];

file_put_contents($typingFile, json_encode($payload, JSON_UNESCAPED_UNICODE));

echo json_encode([
    'succes' => true,
    'action' => 'start',
    'typing' => true
], JSON_UNESCAPED_UNICODE);
exit;
