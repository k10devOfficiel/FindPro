<?php
/**
 * Script de gestion des données de test — FindPro
 *
 * Usage :
 *   php scripts/donnees_test.php             -> Crée ou réinitialise les données de test
 *   php scripts/donnees_test.php --supprimer -> Supprime toutes les données de test
 *
 * Sécurité :
 *   - Ligne de commande (CLI) uniquement.
 *   - Refuse catégoriquement de s'exécuter si app_env n'est pas 'dev'.
 *   - Tous les noms commencent par "TEST" et les emails se terminent par "@test.local".
 */

declare(strict_types=1);

// 1. Contrôle SAPI : Ligne de commande uniquement
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    die("Accès interdit : ce script peut uniquement être exécuté en ligne de commande (CLI).\n");
}

// 2. Chargement de la configuration et contrôle strict de l'environnement
$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    die("Erreur critique : Fichier config/config.php introuvable.\n");
}

$config = require $configFile;
$appEnv = strtolower(trim((string) ($config['app_env'] ?? '')));

if ($appEnv !== 'dev') {
    fwrite(STDERR, "====================================================================\n");
    fwrite(STDERR, " ERREUR DE SÉCURITÉ : ENVIRONNEMENT NON AUTORISÉ\n");
    fwrite(STDERR, "====================================================================\n");
    fwrite(STDERR, "Ce script génère ou supprime des données de démonstration.\n");
    fwrite(STDERR, "Il refuse formellement de tourner hors de l'environnement 'dev'.\n");
    fwrite(STDERR, "Valeur actuelle de app_env : " . ($appEnv === '' ? '[non définie]' : "'$appEnv'") . "\n");
    fwrite(STDERR, "====================================================================\n");
    exit(1);
}

require_once __DIR__ . '/../includes/db.php';
$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 3. Détection de l'option --supprimer
$options = getopt('s', ['supprimer']);
$modeSuppression = isset($options['supprimer']) || isset($options['s']);

// Mot de passe unique pour tous les comptes de test
$motDePasseTest = 'Test123456789!';
$motDePasseHash = password_hash($motDePasseTest, PASSWORD_DEFAULT);

/**
 * Fonction de nettoyage complet des données de test existantes.
 * Respecte scrupuleusement l'ordre des contraintes de clés étrangères.
 *
 * @param PDO $pdo
 * @return array Compteurs d'éléments supprimés
 */
function supprimer_donnees_test(PDO $pdo): array
{
    $compteurs = [];

    // Récupération des IDs utilisateurs de test
    $stmtUsers = $pdo->query("SELECT id_utilisateur FROM utilisateur WHERE email LIKE '%@test.local' OR nom LIKE 'TEST%'");
    $testUserIds = $stmtUsers->fetchAll(PDO::FETCH_COLUMN);

    if (empty($testUserIds)) {
        return ['total' => 0];
    }

    $inUsers = implode(',', array_map('intval', $testUserIds));

    // Récupération des profils associés
    $stmtProfils = $pdo->query("SELECT id_profil FROM profil_professionnel WHERE id_utilisateur IN ($inUsers)");
    $testProfilIds = $stmtProfils->fetchAll(PDO::FETCH_COLUMN);
    $inProfils = !empty($testProfilIds) ? implode(',', array_map('intval', $testProfilIds)) : '0';

    // Récupération des demandes associées
    $stmtDemandes = $pdo->query("SELECT id_demande FROM demande_service WHERE id_client IN ($inUsers) OR id_profil IN ($inProfils)");
    $testDemandeIds = $stmtDemandes->fetchAll(PDO::FETCH_COLUMN);
    $inDemandes = !empty($testDemandeIds) ? implode(',', array_map('intval', $testDemandeIds)) : '0';

    // Récupération des conversations associées
    $stmtConvs = $pdo->query("SELECT id_conversation FROM conversation WHERE id_demande IN ($inDemandes)");
    $testConvIds = $stmtConvs->fetchAll(PDO::FETCH_COLUMN);
    $inConvs = !empty($testConvIds) ? implode(',', array_map('intval', $testConvIds)) : '0';

    // 1. Suppression des avis liés
    $stmt = $pdo->query("DELETE FROM avis WHERE id_demande IN ($inDemandes) OR id_client IN ($inUsers)");
    $compteurs['avis'] = $stmt->rowCount();

    // 2. Suppression des messages liés
    $stmt = $pdo->query("DELETE FROM message WHERE id_conversation IN ($inConvs) OR id_expediteur IN ($inUsers)");
    $compteurs['messages'] = $stmt->rowCount();

    // 3. Suppression des conversations liées
    $stmt = $pdo->query("DELETE FROM conversation WHERE id_demande IN ($inDemandes)");
    $compteurs['conversations'] = $stmt->rowCount();

    // 4. Suppression des demandes de service
    $stmt = $pdo->query("DELETE FROM demande_service WHERE id_demande IN ($inDemandes)");
    $compteurs['demandes'] = $stmt->rowCount();

    // 5. Suppression des projets de portfolio
    $stmt = $pdo->query("DELETE FROM projet_portfolio WHERE id_profil IN ($inProfils)");
    $compteurs['projets_portfolio'] = $stmt->rowCount();

    // 6. Suppression des compétences associées
    $stmt = $pdo->query("DELETE FROM profil_competence WHERE id_profil IN ($inProfils)");
    $compteurs['profil_competences'] = $stmt->rowCount();

    // 7. Suppression des historiques de validation
    $stmt = $pdo->query("DELETE FROM validation_profil WHERE id_profil IN ($inProfils)");
    $compteurs['validations'] = $stmt->rowCount();

    // 8. Suppression des profils professionnels
    $stmt = $pdo->query("DELETE FROM profil_professionnel WHERE id_profil IN ($inProfils)");
    $compteurs['profils'] = $stmt->rowCount();

    // 9. Suppression des comptes utilisateurs de test
    $stmt = $pdo->query("DELETE FROM utilisateur WHERE id_utilisateur IN ($inUsers)");
    $compteurs['utilisateurs'] = $stmt->rowCount();

    $compteurs['total'] = array_sum($compteurs);

    return $compteurs;
}

// ============================================================================
// BRANCHE 1 : EXÉCUTION EN MODE --supprimer
// ============================================================================
if ($modeSuppression) {
    echo "====================================================================\n";
    echo "         SUPPRESSION DES DONNÉES DE TEST (@test.local / TEST*)     \n";
    echo "====================================================================\n";

    $suppr = supprimer_donnees_test($pdo);

    if ($suppr['total'] === 0) {
        echo "Aucune donnée de test détectée en base de données.\n";
    } else {
        echo "Données de test supprimées avec succès :\n";
        echo " - Comptes utilisateurs  : " . ($suppr['utilisateurs'] ?? 0) . "\n";
        echo " - Profils professionnels: " . ($suppr['profils'] ?? 0) . "\n";
        echo " - Projets portfolio     : " . ($suppr['projets_portfolio'] ?? 0) . "\n";
        echo " - Compétences liées     : " . ($suppr['profil_competences'] ?? 0) . "\n";
        echo " - Demandes de service   : " . ($suppr['demandes'] ?? 0) . "\n";
        echo " - Conversations & Fils  : " . ($suppr['conversations'] ?? 0) . "\n";
        echo " - Messages échangés     : " . ($suppr['messages'] ?? 0) . "\n";
        echo " - Avis & Évaluations    : " . ($suppr['avis'] ?? 0) . "\n";
        echo "Total des enregistrements purgés : {$suppr['total']}\n";
    }

    echo "====================================================================\n";
    exit(0);
}

// ============================================================================
// BRANCHE 2 : CRÉATION / RÉINITIALISATION COMPLÈTE DES DONNÉES DE TEST
// ============================================================================
echo "====================================================================\n";
echo "       GÉNÉRATION DES COMPTES & DONNÉES DE TEST FINDPRO (DEV)       \n";
echo "====================================================================\n\n";

// Nettoyage préalable pour garantir l'idempotence
supprimer_donnees_test($pdo);

// ----------------------------------------------------------------------------
// A. CRÉATION DES 3 PROFESSIONNELS (1 PAR CATÉGORIE)
// ----------------------------------------------------------------------------
$prosData = [
    [
        'categorie_id' => 1,
        'nom'          => 'TEST_Dev',
        'prenom'       => 'Alexandre',
        'email'        => 'pro.dev@test.local',
        'tel'          => '+225 07 10 20 30 01',
        'titre'        => 'TEST Développeur Web Fullstack & Architecte Cloud',
        'desc'         => "TEST Ingénieur logiciel spécialisé dans les architectures web modernes, API RESTful et solutions e-commerce évolutives. Maîtrise avancée des frameworks PHP/Laravel et React.",
        'zone'         => 'Abidjan (Cocody, Plateau, Marcory)',
        'dispo'        => 'disponible',
        'is_cyber'     => 0,
        'competences'  => [
            ['id' => 1, 'niveau' => 'expert'],       // PHP & Laravel
            ['id' => 3, 'niveau' => 'avance'],       // React & Next.js
            ['id' => 8, 'niveau' => 'expert'],       // Conception d'API REST & GraphQL
        ],
        'projets'      => [
            [
                'titre'       => 'TEST Plateforme E-commerce B2B',
                'description' => 'TEST Conception de l\'architecture backend, intégration passerelle de paiement et synchronisation d\'inventaire temps réel.',
                'lien'        => 'https://example.com/demo-ecommerce-test',
                'date'        => '2026-05-15',
            ],
            [
                'titre'       => 'TEST Portail de Gestion RH & Paie',
                'description' => 'TEST API REST sécurisée par jetons JWT, gestion des plannings et export automatisé de rapports comptables.',
                'lien'        => 'https://example.com/demo-rh-test',
                'date'        => '2026-08-20',
            ],
        ],
    ],
    [
        'categorie_id' => 2,
        'nom'          => 'TEST_Video',
        'prenom'       => 'Sarah',
        'email'        => 'pro.video@test.local',
        'tel'          => '+225 07 10 20 30 02',
        'titre'        => 'TEST Monteuse Vidéo & Motion Designer Senior',
        'desc'         => "TEST Spécialiste du montage narratif et dynamique, étalonnage cinématique et habillage motion design. Réalisation de formats courts percutants et films institutionnels.",
        'zone'         => 'Abidjan et à distance',
        'dispo'        => 'disponible',
        'is_cyber'     => 0,
        'competences'  => [
            ['id' => 13, 'niveau' => 'expert'],      // Montage sous Adobe Premiere Pro
            ['id' => 15, 'niveau' => 'expert'],      // Motion Design & Animation After Effects
            ['id' => 19, 'niveau' => 'avance'],      // Formats courts (Reels, TikTok)
        ],
        'projets'      => [
            [
                'titre'       => 'TEST Film Corporate Entreprise Tech',
                'description' => 'TEST Montage 4K, mixage sonore multi-pistes et animations graphiques 2D sous After Effects.',
                'lien'        => 'https://example.com/demo-film-corporate-test',
                'date'        => '2026-06-10',
            ],
            [
                'titre'       => 'TEST Campagne de 5 Vidéos Publicitaires Reels',
                'description' => 'TEST Déclinaison au format vertical 9:16 avec sous-titrage dynamique et sound design percutant.',
                'lien'        => 'https://example.com/demo-reels-test',
                'date'        => '2026-09-01',
            ],
        ],
    ],
    [
        'categorie_id' => 3,
        'nom'          => 'TEST_Cyber',
        'prenom'       => 'Ibrahim',
        'email'        => 'pro.cyber@test.local',
        'tel'          => '+225 07 10 20 30 03',
        'titre'        => 'TEST Auditeur en Cybersécurité & Consultant Pentest',
        'desc'         => "TEST Auditeur en sécurité des systèmes d'information. Tests d'intrusion avec mandat préalable, analyse de vulnérabilités, durcissement d'infrastructures et conformité.",
        'zone'         => 'Abidjan et International',
        'dispo'        => 'limitee',
        'is_cyber'     => 1,
        'competences'  => [
            ['id' => 25, 'niveau' => 'expert'],      // Audit de sécurité de code source
            ['id' => 27, 'niveau' => 'expert'],      // Durcissement serveurs Linux
            ['id' => 33, 'niveau' => 'expert'],      // Sécurisation bases de données
        ],
        'projets'      => [
            [
                'titre'       => 'TEST Audit de Sécurité Web Application',
                'description' => 'TEST Identification de vulnérabilités critiques, analyse OWASP Top 10 et plan d\'action correctif.',
                'lien'        => 'https://example.com/demo-pentest-test',
                'date'        => '2026-04-12',
            ],
            [
                'titre'       => 'TEST Durcissement d\'Infrastructure Cloud',
                'description' => 'TEST Sécurisation de bastions SSH, mise en place de pare-feux applicatifs et chiffrement des sauvegardes.',
                'lien'        => 'https://example.com/demo-hardening-test',
                'date'        => '2026-07-18',
            ],
        ],
    ],
];

$prosCrees = [];

foreach ($prosData as $pData) {
    // 1. Insertion Utilisateur
    $stmt = $pdo->prepare('
        INSERT INTO utilisateur (nom, prenom, email, telephone, mot_de_passe_hash, role, statut_compte)
        VALUES (:nom, :prenom, :email, :tel, :hash, "professionnel", "actif")
    ');
    $stmt->execute([
        'nom'    => $pData['nom'],
        'prenom' => $pData['prenom'],
        'email'  => $pData['email'],
        'tel'    => $pData['tel'],
        'hash'   => $motDePasseHash,
    ]);
    $userId = (int) $pdo->lastInsertId();

    // 2. Insertion Profil Professionnel (Approuvé avec profil complet)
    $stmtProfil = $pdo->prepare('
        INSERT INTO profil_professionnel (
            id_utilisateur, id_categorie, titre, description, zone, disponibilite, 
            statut_validation, engagement_cyber, date_engagement_cyber, date_soumission, date_validation
        ) VALUES (
            :uid, :cat, :titre, :desc, :zone, :dispo, 
            "approuve", :cyber, :date_cyber, NOW(), NOW()
        )
    ');
    $stmtProfil->execute([
        'uid'        => $userId,
        'cat'        => $pData['categorie_id'],
        'titre'      => $pData['titre'],
        'desc'       => $pData['desc'],
        'zone'       => $pData['zone'],
        'dispo'      => $pData['dispo'],
        'cyber'      => $pData['is_cyber'],
        'date_cyber' => $pData['is_cyber'] ? date('Y-m-d H:i:s') : null,
    ]);
    $profilId = (int) $pdo->lastInsertId();

    // 3. Insertion Compétences
    $stmtComp = $pdo->prepare('INSERT INTO profil_competence (id_profil, id_competence, niveau_declare) VALUES (:pid, :cid, :niv)');
    foreach ($pData['competences'] as $c) {
        $stmtComp->execute(['pid' => $profilId, 'cid' => $c['id'], 'niv' => $c['niveau']]);
    }

    // 4. Insertion Projets de Portfolio
    $stmtProjet = $pdo->prepare('
        INSERT INTO projet_portfolio (id_profil, titre, description, lien_externe, date_projet, type_media)
        VALUES (:pid, :titre, :desc, :lien, :date, "lien")
    ');
    foreach ($pData['projets'] as $proj) {
        $stmtProjet->execute([
            'pid'   => $profilId,
            'titre' => $proj['titre'],
            'desc'  => $proj['description'],
            'lien'  => $proj['lien'],
            'date'  => $proj['date'],
        ]);
    }

    $prosCrees[] = [
        'id_user'   => $userId,
        'id_profil' => $profilId,
        'nom'       => $pData['nom'],
        'prenom'    => $pData['prenom'],
        'email'     => $pData['email'],
        'categorie' => $pData['categorie_id'],
        'titre'     => $pData['titre'],
    ];

    echo " [✓] Professionnel créé (Catégorie {$pData['categorie_id']}) :\n";
    echo "     - Nom / Prénom : {$pData['prenom']} {$pData['nom']}\n";
    echo "     - Email        : {$pData['email']}\n";
    echo "     - ID Profil    : #{$profilId} (Statut : approuvé)\n\n";
}

// ----------------------------------------------------------------------------
// B. CRÉATION DES 2 CLIENTS
// ----------------------------------------------------------------------------
$clientsData = [
    [
        'nom'    => 'TEST_Client1',
        'prenom' => 'Aya',
        'email'  => 'client1@test.local',
        'tel'    => '+225 05 01 02 03 04',
    ],
    [
        'nom'    => 'TEST_Client2',
        'prenom' => 'Jean',
        'email'  => 'client2@test.local',
        'tel'    => '+225 05 05 06 07 08',
    ],
];

$clientsCrees = [];

foreach ($clientsData as $cData) {
    $stmt = $pdo->prepare('
        INSERT INTO utilisateur (nom, prenom, email, telephone, mot_de_passe_hash, role, statut_compte)
        VALUES (:nom, :prenom, :email, :tel, :hash, "client", "actif")
    ');
    $stmt->execute([
        'nom'    => $cData['nom'],
        'prenom' => $cData['prenom'],
        'email'  => $cData['email'],
        'tel'    => $cData['tel'],
        'hash'   => $motDePasseHash,
    ]);
    $userId = (int) $pdo->lastInsertId();

    $clientsCrees[] = [
        'id_user' => $userId,
        'nom'     => $cData['nom'],
        'prenom'  => $cData['prenom'],
        'email'   => $cData['email'],
    ];

    echo " [✓] Client créé :\n";
    echo "     - Nom / Prénom : {$cData['prenom']} {$cData['nom']}\n";
    echo "     - Email        : {$cData['email']}\n\n";
}

// ----------------------------------------------------------------------------
// C. DEMANDE 1 : ACCEPTEE AVEC QUELQUES MESSAGES (Client 1 -> Pro Dev)
// ----------------------------------------------------------------------------
$client1 = $clientsCrees[0];
$proDev = $prosCrees[0];

$stmtDemande1 = $pdo->prepare('
    INSERT INTO demande_service (
        id_client, id_profil, id_categorie, titre, description, budget_indicatif, delai_souhaite, statut, date_creation
    ) VALUES (
        :cid, :pid, :cat, :titre, :desc, :budget, DATE_ADD(CURRENT_DATE, INTERVAL 21 DAY), "acceptee", DATE_SUB(NOW(), INTERVAL 3 DAY)
    )
');
$stmtDemande1->execute([
    'cid'    => $client1['id_user'],
    'pid'    => $proDev['id_profil'],
    'cat'    => 1,
    'titre'  => 'TEST Refonte de l\'interface client & API sécurisée',
    'desc'   => "TEST Bonjour, nous souhaitons moderniser notre portail client en intégrant un frontend React et une API REST robuste avec authentification par jetons.",
    'budget' => 450000,
]);
$idDemande1 = (int) $pdo->lastInsertId();

// Création du fil de conversation
$stmtConv1 = $pdo->prepare('INSERT INTO conversation (id_demande, statut, date_creation) VALUES (:did, "active", DATE_SUB(NOW(), INTERVAL 3 DAY))');
$stmtConv1->execute(['did' => $idDemande1]);
$idConv1 = (int) $pdo->lastInsertId();

// Messages de la conversation 1
$messages1 = [
    [
        'expediteur' => $client1['id_user'],
        'contenu'    => "TEST Bonjour Alexandre, j'aimerais échanger avec vous sur les spécifications techniques de notre API.",
        'date'       => date('Y-m-d H:i:s', strtotime('-3 days 2 hours')),
    ],
    [
        'expediteur' => $proDev['id_user'],
        'contenu'    => "TEST Bonjour Aya, avec plaisir ! J'ai bien étudié votre besoin et j'ai accepté votre demande. Quels sont les endpoints prioritaires pour votre premier jalon ?",
        'date'       => date('Y-m-d H:i:s', strtotime('-2 days 18 hours')),
    ],
    [
        'expediteur' => $client1['id_user'],
        'contenu'    => "TEST Les modules d'authentification JWT et la gestion des abonnements sont prioritaires pour notre lancement.",
        'date'       => date('Y-m-d H:i:s', strtotime('-1 day 10 hours')),
    ],
    [
        'expediteur' => $proDev['id_user'],
        'contenu'    => "TEST C'est noté. Je prépare un schéma d'architecture et nous calons un premier point technique.",
        'date'       => date('Y-m-d H:i:s', strtotime('-4 hours')),
    ],
];

$stmtMsg1 = $pdo->prepare('INSERT INTO message (id_conversation, id_expediteur, contenu, date_envoi, lu) VALUES (:cid, :exp, :txt, :date, 1)');
foreach ($messages1 as $m) {
    $stmtMsg1->execute([
        'cid'  => $idConv1,
        'exp'  => $m['expediteur'],
        'txt'  => $m['contenu'],
        'date' => $m['date'],
    ]);
}

echo " [✓] Demande #{$idDemande1} (Statut : acceptee) créée :\n";
echo "     - Client demandeur : {$client1['prenom']} {$client1['nom']}\n";
echo "     - Pro destinataire : {$proDev['prenom']} {$proDev['nom']}\n";
echo "     - Conversation #{$idConv1} : " . count($messages1) . " messages échangés.\n\n";

// ----------------------------------------------------------------------------
// D. DEMANDE 2 : TERMINEE AVEC UN AVIS (Client 2 -> Pro Vidéo)
// ----------------------------------------------------------------------------
$client2 = $clientsCrees[1];
$proVideo = $prosCrees[1];

$stmtDemande2 = $pdo->prepare('
    INSERT INTO demande_service (
        id_client, id_profil, id_categorie, titre, description, budget_indicatif, delai_souhaite, 
        statut, date_creation, date_cloture, date_confirmation
    ) VALUES (
        :cid, :pid, :cat, :titre, :desc, :budget, DATE_SUB(CURRENT_DATE, INTERVAL 3 DAY), 
        "terminee", DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)
    )
');
$stmtDemande2->execute([
    'cid'    => $client2['id_user'],
    'pid'    => $proVideo['id_profil'],
    'cat'    => 2,
    'titre'  => 'TEST Montage de 3 capsules vidéo pour lancement de produit',
    'desc'   => "TEST Montage dynamique au format 16:9 et déclinaisons 9:16 pour réseaux sociaux avec sous-titrage et sound design.",
    'budget' => 200000,
]);
$idDemande2 = (int) $pdo->lastInsertId();

// Conversation terminée
$stmtConv2 = $pdo->prepare('INSERT INTO conversation (id_demande, statut, date_creation) VALUES (:did, "lecture_seule", DATE_SUB(NOW(), INTERVAL 10 DAY))');
$stmtConv2->execute(['did' => $idDemande2]);
$idConv2 = (int) $pdo->lastInsertId();

$messages2 = [
    [
        'expediteur' => $client2['id_user'],
        'contenu'    => "TEST Bonjour Sarah, les rushes vidéo et la charte graphique sont déposés sur le lien de partage.",
        'date'       => date('Y-m-d H:i:s', strtotime('-10 days')),
    ],
    [
        'expediteur' => $proVideo['id_user'],
        'contenu'    => "TEST Bien reçu Jean ! Le premier montage sera prêt sous 48h pour votre retour.",
        'date'       => date('Y-m-d H:i:s', strtotime('-9 days')),
    ],
    [
        'expediteur' => $proVideo['id_user'],
        'contenu'    => "TEST Bonjour Jean, voici les exports finaux étalonnés et mixés en haute définition. Merci pour votre collaboration !",
        'date'       => date('Y-m-d H:i:s', strtotime('-3 days')),
    ],
];

$stmtMsg2 = $pdo->prepare('INSERT INTO message (id_conversation, id_expediteur, contenu, date_envoi, lu) VALUES (:cid, :exp, :txt, :date, 1)');
foreach ($messages2 as $m) {
    $stmtMsg2->execute([
        'cid'  => $idConv2,
        'exp'  => $m['expediteur'],
        'txt'  => $m['contenu'],
        'date' => $m['date'],
    ]);
}

// Dépôt de l'avis vérifié
$stmtAvis = $pdo->prepare('
    INSERT INTO avis (id_demande, id_client, note, commentaire, statut, date_avis)
    VALUES (:did, :cid, :note, :com, "publie", DATE_SUB(NOW(), INTERVAL 2 DAY))
');
$stmtAvis->execute([
    'did'  => $idDemande2,
    'cid'  => $client2['id_user'],
    'note' => 5,
    'com'  => "TEST Travail exceptionnel ! Respect scrupuleux des délais et finitions très professionnelles. Nous referons appel à Sarah sans hésiter.",
]);
$idAvis = (int) $pdo->lastInsertId();

echo " [✓] Demande #{$idDemande2} (Statut : terminee) créée :\n";
echo "     - Client demandeur : {$client2['prenom']} {$client2['nom']}\n";
echo "     - Pro destinataire : {$proVideo['prenom']} {$proVideo['nom']}\n";
echo "     - Conversation #{$idConv2} : " . count($messages2) . " messages échangés.\n";
echo "     - Avis certifié #{$idAvis} : 5/5 étoiles avec commentaire vérifié.\n\n";

// ----------------------------------------------------------------------------
// SYNTHÈSE DES IDENTIFIANTS DE TEST
// ----------------------------------------------------------------------------
echo "====================================================================\n";
echo "               RÉCAPITULATIF DES COMPTES DE TEST CRÉÉS             \n";
echo "====================================================================\n";
echo "Mot de passe unique pour TOUS les comptes : {$motDePasseTest}\n\n";

echo "1. Professionnels (Profils approuvés) :\n";
foreach ($prosCrees as $p) {
    echo "   - [Catégorie {$p['categorie']}] {$p['email']} | Profil: #{$p['id_profil']}\n";
}

echo "\n2. Clients :\n";
foreach ($clientsCrees as $c) {
    echo "   - {$c['email']} ({$c['prenom']} {$c['nom']})\n";
}

echo "\n3. Demandes générées :\n";
echo "   - Demande #{$idDemande1} : Statut 'acceptee' (Client: {$client1['email']} -> Pro: {$proDev['email']})\n";
echo "   - Demande #{$idDemande2} : Statut 'terminee' avec Avis 5/5 (Client: {$client2['email']} -> Pro: {$proVideo['email']})\n";

echo "\nPour supprimer l'ensemble de ces données de test, exécutez :\n";
echo "  php scripts/donnees_test.php --supprimer\n";
echo "====================================================================\n";
