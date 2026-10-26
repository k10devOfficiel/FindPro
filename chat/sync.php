<?php
/**
 * Endpoint de synchronisation temps réel globale — FindPro (chat/sync.php)
 * 
 * - Méthode : GET uniquement (réponse JSON ultralégère, exécution < 15ms).
 * - Fonctionnalités :
 *     * Compteur des messages non lus en direct pour la navbar et les listes.
 *     * Détection en direct des nouvelles demandes arrivant chez le professionnel (Scénario 3).
 *     * Synchronisation des statuts de demandes chez le client.
 * - Ne verrouille jamais la session (session_write_close immédiat).
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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
session_write_close(); // Libération immédiate de la session

if (!$utilisateurConnecte || empty($utilisateurConnecte['id'])) {
    http_response_code(401);
    echo json_encode(['succes' => false, 'message' => 'Non authentifié.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$idUser = (int) $utilisateurConnecte['id'];
$role = (string) ($utilisateurConnecte['role'] ?? '');
$pdo = db();

$nbMessagesNonLus = 0;
$totalDemandes = 0;
$nbNouvellesDemandes = 0;
$dernierIdDemande = 0;
$demandesRecentes = [];

if ($role === 'professionnel') {
    // 1. Récupération du profil professionnel
    $stmtPro = $pdo->prepare('SELECT id_profil FROM profil_professionnel WHERE id_utilisateur = :uid LIMIT 1');
    $stmtPro->execute(['uid' => $idUser]);
    $idProfil = (int) $stmtPro->fetchColumn();

    if ($idProfil > 0) {
        // 2. Messages non lus destinés à ce professionnel
        $stmtMsgNonLus = $pdo->prepare('
            SELECT COUNT(*) 
            FROM message m
            JOIN conversation c ON m.id_conversation = c.id_conversation
            JOIN demande_service d ON c.id_demande = d.id_demande
            WHERE d.id_profil = :pid
              AND m.id_expediteur != :uid
              AND m.lu = 0
        ');
        $stmtMsgNonLus->execute([
            'pid' => $idProfil,
            'uid' => $idUser,
        ]);
        $nbMessagesNonLus = (int) $stmtMsgNonLus->fetchColumn();

        // 3. Compteurs de demandes
        $stmtStatsDemandes = $pdo->prepare('
            SELECT 
                COUNT(*) AS total,
                COUNT(CASE WHEN statut = "envoyee" THEN 1 END) AS nouvelles,
                COALESCE(MAX(id_demande), 0) AS max_id
            FROM demande_service
            WHERE id_profil = :pid
        ');
        $stmtStatsDemandes->execute(['pid' => $idProfil]);
        $stats = $stmtStatsDemandes->fetch(PDO::FETCH_ASSOC);

        $totalDemandes       = (int) ($stats['total'] ?? 0);
        $nbNouvellesDemandes = (int) ($stats['nouvelles'] ?? 0);
        $dernierIdDemande    = (int) ($stats['max_id'] ?? 0);

        // 4. Les 5 demandes les plus récentes (pour injection directe dans le DOM sans recharger)
        $stmtRecent = $pdo->prepare('
            SELECT 
                d.id_demande,
                d.titre,
                d.description,
                d.statut,
                d.budget_indicatif,
                d.delai_souhaite,
                d.date_creation,
                c.nom AS client_nom,
                c.prenom AS client_prenom,
                cat.libelle AS categorie_libelle,
                conv.id_conversation
            FROM demande_service d
            JOIN utilisateur c ON d.id_client = c.id_utilisateur
            LEFT JOIN categorie cat ON d.id_categorie = cat.id_categorie
            LEFT JOIN conversation conv ON d.id_demande = conv.id_demande
            WHERE d.id_profil = :pid
            ORDER BY d.id_demande DESC
            LIMIT 5
        ');
        $stmtRecent->execute(['pid' => $idProfil]);
        $demandesRecentes = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);
    }
} elseif ($role === 'client') {
    // 1. Messages non lus reçus par le client
    $stmtMsgNonLus = $pdo->prepare('
        SELECT COUNT(*) 
        FROM message m
        JOIN conversation c ON m.id_conversation = c.id_conversation
        JOIN demande_service d ON c.id_demande = d.id_demande
        WHERE d.id_client = :cid
          AND m.id_expediteur != :uid
          AND m.lu = 0
    ');
    $stmtMsgNonLus->execute([
        'cid' => $idUser,
        'uid' => $idUser,
    ]);
    $nbMessagesNonLus = (int) $stmtMsgNonLus->fetchColumn();

    // 2. Compteurs de demandes
    $stmtStatsDemandes = $pdo->prepare('
        SELECT 
            COUNT(*) AS total,
            COALESCE(MAX(id_demande), 0) AS max_id
        FROM demande_service
        WHERE id_client = :cid
    ');
    $stmtStatsDemandes->execute(['cid' => $idUser]);
    $stats = $stmtStatsDemandes->fetch(PDO::FETCH_ASSOC);

    $totalDemandes    = (int) ($stats['total'] ?? 0);
    $dernierIdDemande = (int) ($stats['max_id'] ?? 0);

    // 3. Les 5 demandes récentes du client
    $stmtRecent = $pdo->prepare('
        SELECT 
            d.id_demande,
            d.titre,
            d.statut,
            d.budget_indicatif,
            d.delai_souhaite,
            d.date_creation,
            p.titre AS pro_titre,
            p.photo AS pro_photo,
            u_pro.nom AS pro_nom,
            u_pro.prenom AS pro_prenom,
            cat.libelle AS categorie_libelle,
            conv.id_conversation
        FROM demande_service d
        JOIN profil_professionnel p ON d.id_profil = p.id_profil
        JOIN utilisateur u_pro ON p.id_utilisateur = u_pro.id_utilisateur
        LEFT JOIN categorie cat ON d.id_categorie = cat.id_categorie
        LEFT JOIN conversation conv ON d.id_demande = conv.id_demande
        WHERE d.id_client = :cid
        ORDER BY d.id_demande DESC
        LIMIT 5
    ');
    $stmtRecent->execute(['cid' => $idUser]);
    $demandesRecentes = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);
}

echo json_encode([
    'succes'                => true,
    'role'                  => $role,
    'user_id'               => $idUser,
    'timestamp'             => time(),
    'nb_messages_non_lus'   => $nbMessagesNonLus,
    'total_demandes'        => $totalDemandes,
    'nb_nouvelles_demandes' => $nbNouvellesDemandes,
    'dernier_id_demande'    => $dernierIdDemande,
    'demandes'              => $demandesRecentes,
], JSON_UNESCAPED_UNICODE);
exit;
