<?php
/**
 * Page de Conversation Privée (Chat) — FindPro.
 * 
 * - Accès : exiger_connexion(). L'utilisateur DOIT être le client OU le professionnel
 *   de la demande liée à cette conversation (vérification SQL tripartite : conversation + demande_service + profil_professionnel).
 * - L'administrateur n'a PAS d'accès par cette page (HTTP 403 si un administrateur non partie-prenante tente d'accéder).
 * - En-tête : Titre de la demande, statut de la demande, statut du canal et profil de l'autre participant.
 * - Liste des messages : Chronologique (du plus ancien au plus récent), expéditeur, date, contenu échappé avec nl2br.
 * - Pagination : 50 derniers messages chargés avec bouton "Voir les messages précédents".
 * - Zone de saisie en bas : Protégée par CSRF, validation de contenu, désactivée et masquée si lecture_seule avec explication.
 * - Rappel des règles d'usage : Respect, légalité, confidentialité des mots de passe.
 * - Respect strict de docs/CHARTE_GRAPHIQUE_FINDPRO.md.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/errors.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';

// 1. Accès sécurisé : utilisateur connecté requis
demarrer_session();
$utilisateurConnecte = exiger_connexion();
$idUtilisateurConnecte = (int) $utilisateurConnecte['id'];

// 2. Validation de l'identifiant de la conversation
$idConversation = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($idConversation <= 0) {
    http_response_code(404);
    require __DIR__ . '/../404.php';
    exit;
}

$pdo = db();

// 3. Récupération de la conversation et contrôle d'accès strict (T08)
// Requête joignant obligatoirement conversation, demande_service et profil_professionnel
$stmtConv = $pdo->prepare('
    SELECT 
        c.id_conversation,
        c.id_demande,
        c.statut AS conversation_statut,
        c.date_creation AS conversation_date_creation,
        d.titre AS demande_titre,
        d.statut AS demande_statut,
        d.id_client,
        d.id_profil,
        d.budget_indicatif,
        d.delai_souhaite,
        d.date_creation AS demande_date_creation,
        p.id_utilisateur AS pro_id_utilisateur,
        p.titre AS pro_titre,
        p.photo AS pro_photo,
        p.zone AS pro_zone,
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
    require __DIR__ . '/../404.php';
    exit;
}

// Vérification d'autorisation : uniquement le client OU le professionnel concerné
$estClient = ($idUtilisateurConnecte === (int) $conv['id_client']);
$estPro    = ($idUtilisateurConnecte === (int) $conv['pro_id_utilisateur']);

if (!$estClient && !$estPro) {
    // RÈGLE OBLIGATOIRE (T08) : un tiers ou un administrateur obtient un 403 strict
    http_response_code(403);
    $titre403 = 'Accès non autorisé à cette conversation';
    $message403 = 'Le contenu de cette conversation est strictement privé et confidentiel entre le client demandeur et le professionnel sollicité.';
    require __DIR__ . '/../includes/403.php';
    exit;
}

$estLectureSeule = ($conv['conversation_statut'] === 'lecture_seule');

// 4. Traitement du formulaire d'envoi de message (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($estLectureSeule) {
        flash_set('danger', 'Cette conversation est clôturée en lecture seule. Vous ne pouvez plus envoyer de message.');
        redirect(url('chat/conversation.php?id=' . $idConversation));
    }

    csrf_verifier();

    $contenuMessage = trim((string) ($_POST['contenu'] ?? ''));

    if ($contenuMessage === '') {
        flash_set('warning', 'Votre message ne peut pas être vide.');
        redirect(url('chat/conversation.php?id=' . $idConversation));
    }

    if (mb_strlen($contenuMessage, 'UTF-8') > 3000) {
        flash_set('warning', 'Votre message est trop volumineux (maximum 3 000 caractères).');
        redirect(url('chat/conversation.php?id=' . $idConversation));
    }

    // Insertion du message
    $stmtInsert = $pdo->prepare('
        INSERT INTO message (id_conversation, id_expediteur, contenu, date_envoi, lu)
        VALUES (:id_conv, :id_exp, :contenu, NOW(), 0)
    ');
    $stmtInsert->execute([
        'id_conv'  => $idConversation,
        'id_exp'   => $idUtilisateurConnecte,
        'contenu'  => $contenuMessage,
    ]);

    flash_set('success', 'Message envoyé avec succès.');
    redirect(url('chat/conversation.php?id=' . $idConversation . '#dernier-message'));
}

// 5. Marquage des messages reçus comme lus
$stmtLu = $pdo->prepare('
    UPDATE message 
    SET lu = 1 
    WHERE id_conversation = :id_conv 
      AND id_expediteur != :id_user 
      AND lu = 0
');
$stmtLu->execute([
    'id_conv' => $idConversation,
    'id_user' => $idUtilisateurConnecte,
]);

// 6. Pagination des messages : 50 derniers messages avec bouton "Voir les messages précédents"
$stmtCountMsg = $pdo->prepare('SELECT COUNT(*) FROM message WHERE id_conversation = :id_conv');
$stmtCountMsg->execute(['id_conv' => $idConversation]);
$totalMessages = (int) $stmtCountMsg->fetchColumn();

$limiteDemandee = isset($_GET['tous']) ? 1000 : (isset($_GET['limite']) ? max(50, min(1000, (int) $_GET['limite'])) : 50);

$stmtMessages = $pdo->prepare('
    SELECT * FROM (
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
        ORDER BY m.date_envoi DESC, m.id_message DESC
        LIMIT :limite
    ) sub
    ORDER BY sub.date_envoi ASC, sub.id_message ASC
');
$stmtMessages->bindValue(':id_conv', $idConversation, PDO::PARAM_INT);
$stmtMessages->bindValue(':limite', $limiteDemandee, PDO::PARAM_INT);
$stmtMessages->execute();
$messages = $stmtMessages->fetchAll(PDO::FETCH_ASSOC);

// Identifiant du dernier message affiché pour le polling incrémental
$dernierIdMessage = !empty($messages) ? (int) end($messages)['id_message'] : 0;

// 7. Formatage de l'autre participant
if ($estClient) {
    // L'autre participant est le Professionnel
    $nomPro = trim((string) $conv['pro_nom']);
    $initialePro = $nomPro !== '' ? mb_strtoupper(mb_substr($nomPro, 0, 1, 'UTF-8'), 'UTF-8') . '.' : '';
    $prenomPro = mb_convert_case(trim((string) $conv['pro_prenom']), MB_CASE_TITLE, 'UTF-8');
    $autreNom = trim($prenomPro . ' ' . $initialePro);
    if ($autreNom === '') {
        $autreNom = 'Professionnel FindPro';
    }
    $autreSousTitre = (string) $conv['pro_titre'];
    $autreBadge = 'Professionnel FindPro';
    $autreBadgeClass = 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25';
    $autrePhotoUrl = !empty($conv['pro_photo']) && file_exists(__DIR__ . '/../' . $conv['pro_photo'])
        ? url($conv['pro_photo'])
        : url('assets/img/avatar-defaut.svg');
    $urlRetourTableau = url('client/demandes.php');
} else {
    // L'autre participant est le Client
    $prenomClient = mb_convert_case(trim((string) $conv['client_prenom']), MB_CASE_TITLE, 'UTF-8');
    $nomClient = mb_convert_case(trim((string) $conv['client_nom']), MB_CASE_TITLE, 'UTF-8');
    $autreNom = trim($prenomClient . ' ' . $nomClient);
    $autreSousTitre = 'Client donneur d\'ordres';
    $autreBadge = 'Client demandeur';
    $autreBadgeClass = 'bg-success bg-opacity-10 text-success border border-success border-opacity-25';
    $autrePhotoUrl = url('assets/img/avatar-defaut.svg');
    $urlRetourTableau = url('pro/demandes.php');
}

$proPhotoUrl = !empty($conv['pro_photo']) && file_exists(__DIR__ . '/../' . $conv['pro_photo'])
    ? url($conv['pro_photo'])
    : url('assets/img/avatar-defaut.svg');
$clientPhotoUrl = url('assets/img/avatar-defaut.svg');
$monPhotoUrl = $estClient ? $clientPhotoUrl : $proPhotoUrl;

// 8. Configuration des libellés de statut de la demande
$statutDemandeConfig = [
    'envoyee'     => ['badge' => 'bg-info bg-opacity-10 text-info border border-info border-opacity-25', 'label' => 'Envoyée'],
    'acceptee'    => ['badge' => 'bg-success bg-opacity-10 text-success border border-success border-opacity-25', 'label' => 'Acceptée'],
    'refusee'     => ['badge' => 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25', 'label' => 'Refusée'],
    'en_cours'    => ['badge' => 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25', 'label' => 'En cours'],
    'a_confirmer' => ['badge' => 'bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25', 'label' => 'À confirmer'],
    'terminee'    => ['badge' => 'bg-dark bg-opacity-10 text-dark border border-dark border-opacity-25', 'label' => 'Terminée'],
    'annulee'     => ['badge' => 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25', 'label' => 'Annulée'],
];
$badgeDemande = $statutDemandeConfig[$conv['demande_statut']] ?? ['badge' => 'bg-secondary', 'label' => ucfirst($conv['demande_statut'])];

$titre_page = 'Conversation — ' . $conv['demande_titre'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4 py-lg-5">

    <!-- Fil d'Ariane -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= url('index.php') ?>" class="text-decoration-none">Accueil</a></li>
            <li class="breadcrumb-item">
                <a href="<?= $urlRetourTableau ?>" class="text-decoration-none">
                    <?= $estClient ? 'Mes demandes' : 'Demandes reçues' ?>
                </a>
            </li>
            <li class="breadcrumb-item">
                <a href="<?= url('demande_detail.php?id=' . (int) $conv['id_demande']) ?>" class="text-decoration-none">
                    Détail de la demande
                </a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">Conversation</li>
        </ol>
    </nav>

    <!-- En-tête de la conversation -->
    <div class="card shadow-sm border mb-4">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <img src="<?= e($autrePhotoUrl) ?>" 
                         alt="Avatar de <?= e($autreNom) ?>" 
                         class="rounded-circle object-fit-cover border shadow-2xs flex-shrink-0" 
                         style="width: 56px; height: 56px;">
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <h1 class="h5 fw-bold text-dark mb-0"><?= e($autreNom) ?></h1>
                            <span class="badge <?= e($autreBadgeClass) ?> rounded-pill small"><?= e($autreBadge) ?></span>
                        </div>
                        <p class="text-secondary small mb-0"><?= e($autreSousTitre) ?></p>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2">
                    <a href="<?= url('demande_detail.php?id=' . (int) $conv['id_demande']) ?>" 
                       class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1.5">
                        <i class="bi bi-file-text" aria-hidden="true"></i>
                        <span>Fiche de la demande</span>
                    </a>
                    <span class="badge <?= e($badgeDemande['badge']) ?> px-2.5 py-1.5 small">
                        Demande : <?= e($badgeDemande['label']) ?>
                    </span>
                    <span class="badge <?= $estLectureSeule ? 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25' : 'bg-success bg-opacity-10 text-success border border-success border-opacity-25' ?> px-2.5 py-1.5 small">
                        <i class="bi <?= $estLectureSeule ? 'bi-lock-fill' : 'bi-chat-dots-fill' ?> me-1"></i>
                        Canal <?= e($estLectureSeule ? 'lecture seule' : 'actif') ?>
                    </span>
                </div>
            </div>

            <!-- Titre et résumé de la demande liée -->
            <div class="mt-3 pt-3 border-top d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 small">
                <div class="text-secondary">
                    <strong class="text-dark">Objet de la prestation :</strong> <?= e($conv['demande_titre']) ?>
                </div>
                <?php if ($conv['budget_indicatif'] !== null): ?>
                    <div class="text-secondary">
                        <strong class="text-dark">Budget indicatif :</strong> <?= number_format((float) $conv['budget_indicatif'], 0, ',', ' ') ?> FCFA
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Zone principale de messagerie -->
    <div class="card shadow-sm border mb-4" id="chat-app"
         data-conversation-id="<?= (int) $idConversation ?>"
         data-last-id="<?= (int) $dernierIdMessage ?>"
         data-poll-url="<?= url('chat/poll.php') ?>"
         data-send-url="<?= url('chat/send.php') ?>"
         data-typing-url="<?= url('chat/typing.php') ?>"
         data-user-photo="<?= e($monPhotoUrl) ?>"
         data-pro-photo="<?= e($proPhotoUrl) ?>"
         data-client-photo="<?= e($clientPhotoUrl) ?>"
         data-current-user-id="<?= (int) $idUtilisateurConnecte ?>"
         data-is-read-only="<?= $estLectureSeule ? '1' : '0' ?>">
        
        <!-- En-tête de la boîte de discussion -->
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h2 class="h6 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-chat-square-text text-primary" aria-hidden="true"></i>
                <span>Historique des échanges</span>
                <span class="badge bg-light text-secondary border small font-monospace">
                    <span id="messages-count-badge"><?= count($messages) ?></span> / <?= $totalMessages ?>
                </span>
            </h2>
            <small class="text-muted">
                <i class="bi bi-shield-check text-success me-1"></i>Échange direct sécurisé
            </small>
        </div>

        <!-- Corps des messages avec défilement -->
        <div class="card-body p-3 p-md-4 bg-light" style="min-height: 380px; max-height: 520px; overflow-y: auto;" id="messages-container">
            
            <!-- Bouton de pagination (Voir les messages précédents) -->
            <?php if ($totalMessages > count($messages)): ?>
                <div class="text-center mb-4" id="messages-debut">
                    <a href="<?= url('chat/conversation.php?id=' . $idConversation . '&limite=' . ($limiteDemandee + 50)) ?>#messages-debut" 
                       class="btn btn-outline-primary btn-sm px-3 shadow-2xs">
                        <i class="bi bi-arrow-up-circle me-1"></i>
                        Voir les messages précédents (<?= $totalMessages - count($messages) ?> restants)
                    </a>
                </div>
            <?php endif; ?>

            <?php if (empty($messages)): ?>
                <div class="text-center py-5 text-muted" id="chat-empty-placeholder">
                    <i class="bi bi-chat-dots fs-1 text-secondary opacity-50 d-block mb-2"></i>
                    <p class="fw-medium mb-1">Aucun message échangé pour le moment</p>
                    <small>Envoyez votre premier message ci-dessous pour démarrer la discussion.</small>
                </div>
                <div class="d-flex flex-column gap-3" id="messages-list"></div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3" id="messages-list">
                    <?php 
                    $dernierIndex = count($messages) - 1;
                    foreach ($messages as $idx => $msg): 
                        $estMonMessage = ((int) $msg['id_expediteur'] === $idUtilisateurConnecte);
                        $nomAuteur = $estMonMessage 
                            ? 'Vous' 
                            : ($msg['exp_role'] === 'professionnel' ? e($autreNom . ' (Pro)') : e($autreNom . ' (Client)'));
                        $photoMsg = ($msg['exp_role'] === 'professionnel') ? $proPhotoUrl : $clientPhotoUrl;
                    ?>
                        <div class="d-flex <?= $estMonMessage ? 'justify-content-end' : 'justify-content-start' ?>" 
                             data-message-id="<?= (int) $msg['id_message'] ?>"
                             <?= ($idx === $dernierIndex) ? 'id="dernier-message"' : '' ?>>
                            <div class="card <?= $estMonMessage ? 'bg-primary text-white border-0' : 'bg-white border text-dark' ?> shadow-2xs p-3" 
                                 style="max-width: 82%; min-width: 220px; border-radius: 12px;">
                                
                                <div class="d-flex justify-content-between align-items-center gap-2 mb-1.5" style="font-size: 0.75rem;">
                                    <div class="d-flex align-items-center gap-1.5">
                                        <img src="<?= e($photoMsg) ?>" alt="Avatar" class="chat-avatar-bubble <?= $estMonMessage ? 'is-me' : '' ?>" width="28" height="28">
                                        <span class="fw-semibold <?= $estMonMessage ? 'text-white-50' : 'text-primary' ?>">
                                            <?= $nomAuteur ?>
                                        </span>
                                    </div>
                                    <span class="<?= $estMonMessage ? 'text-white-50' : 'text-muted' ?>">
                                        <i class="bi bi-clock me-1"></i><?= date('d/m/Y à H:i', strtotime($msg['date_envoi'])) ?>
                                    </span>
                                </div>

                                <div class="message-content small" style="white-space: pre-wrap; word-break: break-word; line-height: 1.5;">
                                    <?= nl2br(e($msg['contenu'])) ?>
                                </div>

                                <?php if ($estMonMessage): ?>
                                    <div class="text-end mt-1 text-white-50" style="font-size: 0.68rem;">
                                        <i class="bi <?= $msg['lu'] ? 'bi-check2-all text-white' : 'bi-check2' ?>" title="<?= $msg['lu'] ? 'Message lu' : 'Message distribué' ?>"></i>
                                    </div>
                                <?php endif; ?>

                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Bulle indicateur de frappe "En train d'écrire..." -->
            <div id="typing-indicator" class="d-none mt-2">
                <div class="d-flex align-items-center gap-2">
                    <img src="<?= e($autrePhotoUrl) ?>" id="typing-avatar" class="chat-avatar-bubble" alt="Avatar" width="28" height="28">
                    <div class="bg-white border rounded-pill px-3 py-1.5 shadow-2xs d-inline-flex align-items-center gap-2">
                        <i class="bi bi-chat-dots-fill text-primary" aria-hidden="true"></i>
                        <span class="small text-secondary fw-semibold" id="typing-text"><?= $estClient ? 'Le professionnel écrit…' : 'Le client écrit…' ?></span>
                        <span class="typing-dots">
                            <span class="dot"></span>
                            <span class="dot"></span>
                            <span class="dot"></span>
                        </span>
                    </div>
                </div>
            </div>

        </div>

        <!-- Zone de saisie en bas OU explication lecture seule -->
        <div class="card-footer bg-white p-3 p-md-4 border-top">
            <?php if ($estLectureSeule): ?>
                <!-- Avertissement lecture seule -->
                <div class="alert alert-secondary border d-flex align-items-start gap-3 p-3 rounded-3 shadow-2xs mb-0" role="alert">
                    <i class="bi bi-lock-fill fs-4 text-secondary flex-shrink-0 mt-0.5" aria-hidden="true"></i>
                    <div>
                        <strong class="d-block mb-1 text-dark">Zone de saisie verrouillée (Lecture seule)</strong>
                        <p class="small text-secondary mb-0">
                            Cette conversation est désormais clôturée car la demande de service associée est 
                            <strong><?= e($badgeDemande['label']) ?></strong>. L'historique complet reste accessible en consultation mais l'envoi de nouveaux messages n'est plus autorisé.
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <!-- Formulaire de saisie d'un nouveau message -->
                <form method="POST" action="<?= url('chat/conversation.php?id=' . $idConversation) ?>" id="form-message">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label for="contenu" class="form-label visually-hidden">Votre message</label>
                        <textarea name="contenu" 
                                  id="contenu" 
                                  rows="3" 
                                  class="form-control" 
                                  placeholder="Écrivez votre message ici... (retours à la ligne pris en charge, max 2 000 car.)" 
                                  maxlength="2000" 
                                  required></textarea>
                    </div>
                    <div id="chat-feedback" class="d-none"></div>
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mt-2">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>Appuyez sur <kbd class="bg-light text-dark border">Ctrl</kbd> + <kbd class="bg-light text-dark border">Entrée</kbd> ou cliquez sur Envoyer.
                        </small>
                        <button type="submit" class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2 px-4 py-2 shadow-2xs">
                            <i class="bi bi-send-fill" aria-hidden="true"></i>
                            <span class="fw-semibold">Envoyer</span>
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

    </div>

    <!-- Rappel des règles d'usage et de sécurité (sans mention technique RG) -->
    <div class="card bg-light border p-3.5 rounded-3 shadow-2xs">
        <div class="d-flex align-items-start gap-3">
            <i class="bi bi-shield-lock-fill text-primary fs-4 flex-shrink-0" aria-hidden="true"></i>
            <div class="small">
                <strong class="text-dark d-block mb-1">Règles d'usage et sécurité des échanges</strong>
                <span class="text-secondary leading-relaxed">
                    Les échanges sur FindPro doivent demeurer courtois, respectueux et strictement professionnels. 
                    Toute activité illégale, tentative d'intrusion ou prestation non autorisée est formellement prohibée. 
                    Pour garantir votre sécurité, <strong>ne partagez jamais de mots de passe</strong>, clés privées ou informations bancaires confidentielles dans cette conversation.
                </span>
            </div>
        </div>
    </div>

</div>

<!-- Script de messagerie instantanée FindPro (Polling & Sécurité anti-XSS textContent) -->
<script src="<?= url('assets/js/chat.js') ?>?v=<?= file_exists(__DIR__ . '/../assets/js/chat.js') ? filemtime(__DIR__ . '/../assets/js/chat.js') : '1.0' ?>"></script>

<?php
require_once __DIR__ . '/../includes/footer.php';
