<?php
/**
 * Script d'inscription et de configuration de 10 professionnels internationaux
 * avec coordonnées mondiales, compétences, portfolios et photos réelles.
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Accès interdit : ce script s'exécute uniquement en ligne de commande (CLI).\n");
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

echo "============================================================\n";
echo " INSCRIPTION DE 10 PROFESSIONNELS INTERNATIONAUX (FINDPRO) \n";
echo "============================================================\n\n";

// 1. Récupération des catégories
$cats = $pdo->query("SELECT id_categorie, slug, libelle FROM categorie")->fetchAll(PDO::FETCH_ASSOC);
$catMap = [];
foreach ($cats as $c) {
    $catMap[$c['slug']] = (int) $c['id_categorie'];
}

$idCatDev = $catMap['developpement'] ?? 1;
$idCatVideo = $catMap['montage-video'] ?? 2;
$idCatCyber = $catMap['cybersecurite'] ?? 3;

// 2. Récupération des compétences par catégorie
$stmtComp = $pdo->prepare("SELECT id_competence, libelle FROM competence WHERE id_categorie = ?");

$stmtComp->execute([$idCatDev]);
$compsDev = $stmtComp->fetchAll(PDO::FETCH_ASSOC);

$stmtComp->execute([$idCatVideo]);
$compsVideo = $stmtComp->fetchAll(PDO::FETCH_ASSOC);

$stmtComp->execute([$idCatCyber]);
$compsCyber = $stmtComp->fetchAll(PDO::FETCH_ASSOC);

function trouverIdComp(array $liste, string $motCle): ?int {
    foreach ($liste as $c) {
        if (stripos($c['libelle'], $motCle) !== false) {
            return (int) $c['id_competence'];
        }
    }
    return !empty($liste) ? (int) $liste[0]['id_competence'] : null;
}

// 3. Fonction pour générer une vraie image JPEG d'avatar
function genererAvatarJpeg(string $cheminRel, string $initiales, array $couleurFond, array $couleurTexte): string {
    $cheminAbs = __DIR__ . '/../' . $cheminRel;
    $dossier = dirname($cheminAbs);
    if (!is_dir($dossier)) {
        @mkdir($dossier, 0777, true);
    }

    $largeur = 256;
    $hauteur = 256;
    $image = imagecreatetruecolor($largeur, $hauteur);

    // Activer l'antialiasing si disponible
    if (function_exists('imageantialias')) {
        imageantialias($image, true);
    }

    // Fond avec dégradé subtil
    $fond = imagecolorallocate($image, $couleurFond[0], $couleurFond[1], $couleurFond[2]);
    imagefill($image, 0, 0, $fond);

    // Cercles décoratifs modernes
    $cercle1 = imagecolorallocatealpha($image, 255, 255, 255, 115);
    $cercle2 = imagecolorallocatealpha($image, 255, 255, 255, 100);
    imagefilledellipse($image, 128, 128, 220, 220, $cercle1);
    imagefilledellipse($image, 128, 128, 190, 190, $fond);
    imagefilledellipse($image, 128, 128, 160, 160, $cercle2);

    // Dessin du texte (initiales)
    $texteCol = imagecolorallocate($image, $couleurTexte[0], $couleurTexte[1], $couleurTexte[2]);
    $font = 5; // Police intégrée GD 5 (taille max standard)
    $charW = imagefontwidth($font);
    $charH = imagefontheight($font);
    $textW = strlen($initiales) * $charW * 2;
    $textH = $charH * 2;
    
    // Zoom manuel pour avoir de grandes initiales nettes
    $temp = imagecreatetruecolor(strlen($initiales) * $charW, $charH);
    $tFond = imagecolorallocate($temp, 0, 0, 0);
    $tCol = imagecolorallocate($temp, 255, 255, 255);
    imagefill($temp, 0, 0, $tFond);
    imagestring($temp, $font, 0, 0, $initiales, $tCol);

    // Redimensionner les initiales au centre de l'avatar
    $destX = (int) (($largeur - (strlen($initiales) * $charW * 3.5)) / 2);
    $destY = (int) (($hauteur - ($charH * 3.5)) / 2);
    imagecopyresized($image, $temp, $destX, $destY, 0, 0, (int)(strlen($initiales) * $charW * 3.5), (int)($charH * 3.5), strlen($initiales) * $charW, $charH);
    imagedestroy($temp);

    // Sauvegarde en JPEG haute qualité
    imagejpeg($image, $cheminAbs, 92);
    imagedestroy($image);

    return $cheminRel;
}

// 4. Définition des 10 profils internationaux
$profilsInternationaux = [
    [
        'prenom'      => 'Jean-Marc',
        'nom'         => 'Dupont',
        'email'       => 'jean-marc.dupont@findpro-world.com',
        'telephone'   => '+33 1 42 68 55 00',
        'pays'        => 'France 🇫🇷',
        'zone'        => 'Paris, France (Full remote mondial)',
        'id_cat'      => $idCatDev,
        'titre'       => 'Architecte Logiciel Fullstack Senior — PHP 8, Laravel & Vue.js',
        'dispo'       => 'disponible',
        'photo'       => 'uploads/avatars/pro_intl_1_dupont.jpg',
        'initiales'   => 'JD',
        'fond'        => [30, 64, 175], // Bleu royal
        'texte'       => [255, 255, 255],
        'description' => "Fort de plus de 10 ans d'expérience dans l'architecture et le développement d'applications web d'envergure, j'accompagne les entreprises et startups dans la modernisation de leurs socles applicatifs. Spécialiste reconnu de l'écosystème Laravel, Vue.js / Nuxt et des bases de données SQL à haute volumétrie, je privilégie le Clean Code, les tests automatisés et la haute performance.",
        'competences' => ['Laravel', 'JavaScript', 'Vue.js', 'Bases de données SQL', 'Git'],
        'projets'     => [
            [
                'titre'       => 'Refonte de plateforme e-commerce B2B internationale',
                'description' => 'Architecture modulaire sous Laravel & PostgreSQL supportant plus de 150 000 transactions mensuelles avec temps de réponse inférieur à 95ms.',
                'lien'        => 'https://demo-b2b.findpro-world.com',
            ],
            [
                'titre'       => 'Application SaaS de facturation automatisée multi-devises',
                'description' => 'Conception d\'API REST sécurisée, connecteurs bancaires et synchronisation ERP en temps réel sous Nuxt.js.',
                'lien'        => 'https://saas-billing.findpro-world.com',
            ]
        ],
    ],
    [
        'prenom'      => 'Amina',
        'nom'         => 'Diallo',
        'email'       => 'amina.diallo@findpro-world.com',
        'telephone'   => '+221 77 450 12 34',
        'pays'        => 'Sénégal 🇸🇳',
        'zone'        => 'Dakar, Sénégal (Disponible à l\'international)',
        'id_cat'      => $idCatDev,
        'titre'       => 'Développeuse Mobile Flutter & Fullstack Node.js / React',
        'dispo'       => 'disponible',
        'photo'       => 'uploads/avatars/pro_intl_2_diallo.jpg',
        'initiales'   => 'AD',
        'fond'        => [13, 148, 136], // Teal
        'texte'       => [255, 255, 255],
        'description' => "Ingénieure logicielle passionnée par les applications mobiles cross-platform et le web interactif moderne. J'ai conçu et déployé plus d'une vingtaine d'applications sous Flutter et React, avec un focus particulier sur la fluidité des interfaces, le mode hors-ligne résilient et l'intégration des passerelles de paiement africaines et internationales.",
        'competences' => ['Flutter', 'React', 'Node.js', 'Conception d\'API REST', 'HTML5'],
        'projets'     => [
            [
                'titre'       => 'Application FinTech de transfert d\'argent instantané',
                'description' => 'Application mobile iOS / Android cumulant +90 000 utilisateurs actifs avec intégration Wave, Orange Money et cartes Visa.',
                'lien'        => 'https://pay-instant.findpro-world.com',
            ],
            [
                'titre'       => 'Plateforme de téléconsultation médicale avec visio temps réel',
                'description' => 'Client mobile sous Flutter et backend Node.js / WebSockets certifié HDS (Hébergement Données de Santé).',
                'lien'        => 'https://telemed.findpro-world.com',
            ]
        ],
    ],
    [
        'prenom'      => 'Kenji',
        'nom'         => 'Takahashi',
        'email'       => 'kenji.takahashi@findpro-world.com',
        'telephone'   => '+81 3 5555 0142',
        'pays'        => 'Japon 🇯🇵',
        'zone'        => 'Tokyo, Japon (Audits & conseil à distance)',
        'id_cat'      => $idCatCyber,
        'titre'       => 'Expert Cybersécurité Défensive & Architecture WAF / Zero Trust',
        'dispo'       => 'limitee',
        'photo'       => 'uploads/avatars/pro_intl_3_takahashi.jpg',
        'initiales'   => 'KT',
        'fond'        => [225, 29, 72], // Rose vif / rouge
        'texte'       => [255, 255, 255],
        'description' => "Consultant senior en sécurité des systèmes d'information certifié CISSP et CISM avec 12 ans d'expérience. Spécialiste de la détection proactive des menaces, du durcissement d'infrastructures cloud et hybrides, de la configuration avancée de WAF et de la gouvernance de sécurité selon les normes internationales (ISO 27001, NIST).",
        'competences' => ['pare-feu', 'vulnérabilités', 'Surveillance', 'Durcissement', 'Authentification forte'],
        'projets'     => [
            [
                'titre'       => 'Audit défensif et durcissement d\'infrastructure bancaire',
                'description' => 'Analyse approfondie de 40 applications web et API REST avec mise en œuvre de contre-mesures WAF et réduction de 98% des tentatives d\'intrusion.',
                'lien'        => 'https://cyber-audit.findpro-world.com',
            ]
        ],
    ],
    [
        'prenom'      => 'Sophia',
        'nom'         => 'Tremblay',
        'email'       => 'sophia.tremblay@findpro-world.com',
        'telephone'   => '+1 514 890 2345',
        'pays'        => 'Canada 🇨🇦',
        'zone'        => 'Montréal, QC, Canada (Projets internationaux)',
        'id_cat'      => $idCatVideo,
        'titre'       => 'Réalisatrice & Monteuse Vidéo Documentaire / Commerciale 4K',
        'dispo'       => 'disponible',
        'photo'       => 'uploads/avatars/pro_intl_4_tremblay.jpg',
        'initiales'   => 'ST',
        'fond'        => [124, 58, 237], // Violet / Indigo
        'texte'       => [255, 255, 255],
        'description' => "Monteuse et coloriste professionnelle avec 8 années d'expérience en post-production cinématographique, documentaires pour la télévision et films de marque. Maîtrise experte de DaVinci Resolve Studio pour l'étalonnage HDR, de Premiere Pro pour le rythme narratif et d'Audition pour la clarté acoustique.",
        'competences' => ['DaVinci', 'Étalonnage', 'Mixage', 'Storyboarding', 'institutionnel'],
        'projets'     => [
            [
                'titre'       => 'Mini-série documentaire sur les forêts boréales (4K HDR)',
                'description' => 'Montage intégral, étalonnage colorimétrique cinématographique et mixage sonore 5.1 récompensé en festival.',
                'lien'        => 'https://docu-boreal.findpro-world.com',
            ],
            [
                'titre'       => 'Campagne publicitaire globale pour marque technologique',
                'description' => 'Spots 60s, 30s et déclinaisons social media 16:9 et 9:16 avec habillage dynamique percutant.',
                'lien'        => 'https://commercial-reel.findpro-world.com',
            ]
        ],
    ],
    [
        'prenom'      => 'David',
        'nom'         => 'O\'Connor',
        'email'       => 'david.oconnor@findpro-world.com',
        'telephone'   => '+44 20 7946 0912',
        'pays'        => 'Royaume-Uni 🇬🇧',
        'zone'        => 'Londres, Royaume-Uni (Full remote Europe & Monde)',
        'id_cat'      => $idCatDev,
        'titre'       => 'Lead Développeur Python & Django / React — Systèmes Haute Performance',
        'dispo'       => 'disponible',
        'photo'       => 'uploads/avatars/pro_intl_5_oconnor.jpg',
        'initiales'   => 'DO',
        'fond'        => [5, 150, 105], // Vert émeraude
        'texte'       => [255, 255, 255],
        'description' => "Ingénieur logiciel chevronné spécialisé dans le développement d'architectures orientées données sous Python, Django et PostgreSQL. J'aide les fintechs et entreprises de la tech à optimiser leurs requêtes SQL complexes, concevoir des APIs robustes et créer des interfaces de visualisation sous React / Next.js.",
        'competences' => ['Python', 'React', 'Bases de données SQL', 'Conception d\'API REST', 'Git'],
        'projets'     => [
            [
                'titre'       => 'Plateforme d\'analytique financière prédictive en temps réel',
                'description' => 'Ingestion de millions d\'événements boursiers quotidiens et moteur d\'agrégation temps réel sous Python et Django Rest Framework.',
                'lien'        => 'https://fintech-analytics.findpro-world.com',
            ]
        ],
    ],
    [
        'prenom'      => 'Yasmine',
        'nom'         => 'Benali',
        'email'       => 'yasmine.benali@findpro-world.com',
        'telephone'   => '+212 522 34 56 78',
        'pays'        => 'Maroc 🇲🇦',
        'zone'        => 'Casablanca, Maroc (Missions Afrique & Europe)',
        'id_cat'      => $idCatCyber,
        'titre'       => 'Auditrice Cybersécurité Applicative & Conformité ISO 27001',
        'dispo'       => 'disponible',
        'photo'       => 'uploads/avatars/pro_intl_6_benali.jpg',
        'initiales'   => 'YB',
        'fond'        => [217, 119, 6], // Ambre / Orange chaud
        'texte'       => [255, 255, 255],
        'description' => "Consultante en sécurité applicative et auditrice certifiée. J'interviens auprès des institutions financières, e-commerçants et éditeurs de logiciels pour réaliser des revues de code source sécurisées, des tests d'intrusion autorisés d'applications web selon l'OWASP Top 10 et sensibiliser les équipes de développement aux bonnes pratiques de codage défensif.",
        'competences' => ['code source', 'vulnérabilités', 'chiffrement', 'phishing', 'SSL/TLS'],
        'projets'     => [
            [
                'titre'       => 'Audit de sécurité web complet pour une néobanque panafricaine',
                'description' => 'Identification et remédiation de 12 failles critiques, durcissement des API et mise en conformité RGPD / CNDP.',
                'lien'        => 'https://sec-audit.findpro-world.com',
            ]
        ],
    ],
    [
        'prenom'      => 'Lucas',
        'nom'         => 'Silva',
        'email'       => 'lucas.silva@findpro-world.com',
        'telephone'   => '+55 11 98765 4321',
        'pays'        => 'Brésil 🇧🇷',
        'zone'        => 'São Paulo, Brésil (Disponible worldwide)',
        'id_cat'      => $idCatVideo,
        'titre'       => 'Motion Designer Senior & Spécialiste After Effects / 3D',
        'dispo'       => 'disponible',
        'photo'       => 'uploads/avatars/pro_intl_7_silva.jpg',
        'initiales'   => 'LS',
        'fond'        => [37, 99, 235], // Bleu électrique
        'texte'       => [255, 255, 255],
        'description' => "Motion designer et animateur graphique avec 9 ans d'expertise dans la création d'animations vectorielles cinétiques, l'habillage graphique de chaînes de divertissement et la production de vidéos explicatives pour startups. Passionné par l'alliance du rythme musical, de la typographie et des transitions percutantes.",
        'competences' => ['After Effects', 'logos', 'VFX', 'Premiere Pro', 'institutionnel'],
        'projets'     => [
            [
                'titre'       => 'Vidéo promotionnelle animée pour lancement de produit SaaS',
                'description' => 'Animation 2D/3D complète en motion design de 90 secondes ayant généré plus de 600 000 vues lors du lancement mondial.',
                'lien'        => 'https://motion-saas.findpro-world.com',
            ]
        ],
    ],
    [
        'prenom'      => 'Elena',
        'nom'         => 'Rostova',
        'email'       => 'elena.rostova@findpro-world.com',
        'telephone'   => '+49 30 1234 5678',
        'pays'        => 'Allemagne 🇩🇪',
        'zone'        => 'Berlin, Allemagne (Remote Europe & Monde)',
        'id_cat'      => $idCatDev,
        'titre'       => 'Experte WordPress & Headless CMS — Architectures E-Commerce Performantes',
        'dispo'       => 'limitee',
        'photo'       => 'uploads/avatars/pro_intl_8_rostova.jpg',
        'initiales'   => 'ER',
        'fond'        => [109, 40, 217], // Violet foncé
        'texte'       => [255, 255, 255],
        'description' => "Développeuse web spécialisée dans l'architecture WordPress sur mesure, WooCommerce à fort catalogue et les solutions Headless (Next.js / Nuxt). J'accorde une importance capitale à l'accessibilité numérique, à l'optimisation des temps de chargement (Core Web Vitals à 95+) et au référencement naturel technique.",
        'competences' => ['WordPress', 'HTML5', 'JavaScript', 'Laravel', 'Git'],
        'projets'     => [
            [
                'titre'       => 'Boutique e-commerce internationale multilingue sous WooCommerce',
                'description' => 'Catalogue de 14 000 produits avec recherche instantanée Algolia et temps de chargement sous 1 seconde.',
                'lien'        => 'https://ecommerce-berlin.findpro-world.com',
            ]
        ],
    ],
    [
        'prenom'      => 'Kwame',
        'nom'         => 'Mensah',
        'email'       => 'kwame.mensah@findpro-world.com',
        'telephone'   => '+233 24 123 4567',
        'pays'        => 'Ghana 🇬🇭',
        'zone'        => 'Accra, Ghana (Collaborations à distance mondiales)',
        'id_cat'      => $idCatVideo,
        'titre'       => 'Monteur Vidéo Formats Courts Viraux (Reels, TikTok) & Social Ads',
        'dispo'       => 'disponible',
        'photo'       => 'uploads/avatars/pro_intl_9_mensah.jpg',
        'initiales'   => 'KM',
        'fond'        => [180, 83, 9], // Ocre / Brun doré
        'texte'       => [255, 255, 255],
        'description' => "Spécialiste du montage court à fort taux de rétention pour créateurs de contenu, influenceurs et marques digitales. Maîtrise des techniques de montage rythmé, du sous-titrage stylisé animé, du sound design immersif et de l'optimisation pour les algorithmes TikTok, Instagram Reels et YouTube Shorts.",
        'competences' => ['formats courts', 'Sous-titrage', 'Premiere Pro', 'Encodage', 'Storyboarding'],
        'projets'     => [
            [
                'titre'       => 'Production de 60 capsules courtes pour marque de prêt-à-porter',
                'description' => 'Plus de 11 millions de vues cumulées et un taux de rétention moyen supérieur à 68% sur TikTok et Instagram.',
                'lien'        => 'https://shorts-viral.findpro-world.com',
            ]
        ],
    ],
    [
        'prenom'      => 'Christian',
        'nom'         => 'Weber',
        'email'       => 'christian.weber@findpro-world.com',
        'telephone'   => '+41 22 555 88 99',
        'pays'        => 'Suisse 🇨🇭',
        'zone'        => 'Genève, Suisse (Interventions sur site et à distance)',
        'id_cat'      => $idCatCyber,
        'titre'       => 'Ingénieur Sécurité Systèmes, Durcissement Linux & Plans de Continuité (PRA)',
        'dispo'       => 'disponible',
        'photo'       => 'uploads/avatars/pro_intl_10_weber.jpg',
        'initiales'   => 'CW',
        'fond'        => [15, 118, 110], // Vert sombre / Canard
        'texte'       => [255, 255, 255],
        'description' => "Ingénieur en cybersécurité certifié ISO 27001 Lead Implementer avec 14 ans de métier dans la sécurisation d'environnements d'hébergement critiques Linux et Windows. Expert en chiffrement des données, durcissement des protocoles réseau et conception de Plans de Reprise d'Activité (PRA) à haute résilience.",
        'competences' => ['Durcissement', 'reprise d\'activité', 'chiffrement', 'SSL/TLS', 'correctifs'],
        'projets'     => [
            [
                'titre'       => 'Plan de Reprise d\'Activité (PRA) et haute résilience pour datacenter',
                'description' => 'Architecture de réplication continue et bascule automatisée avec RTO < 10 min et RPO quasi-nul validé par audit tiers.',
                'lien'        => 'https://pra-datacenter.findpro-world.com',
            ]
        ],
    ],
];

// Mot de passe sécurisé universel pour ces comptes de démonstration : ProPass2026!
$motDePasseHash = password_hash('ProPass2026!', PASSWORD_BCRYPT);

// 5. Client test pour les avis
$clientTest = $pdo->query("SELECT id_utilisateur, prenom, nom FROM utilisateur WHERE role = 'client' ORDER BY id_utilisateur ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$idClientAvis = $clientTest ? (int) $clientTest['id_utilisateur'] : null;

$compteurInscrits = 0;

foreach ($profilsInternationaux as $index => $proData) {
    // A. Générer la photo JPEG
    $photoChemin = genererAvatarJpeg(
        $proData['photo'], 
        $proData['initiales'], 
        $proData['fond'], 
        $proData['texte']
    );

    // B. Créer ou mettre à jour l'utilisateur
    $stmtUser = $pdo->prepare("SELECT id_utilisateur FROM utilisateur WHERE email = ?");
    $stmtUser->execute([$proData['email']]);
    $existingUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if ($existingUser) {
        $idUser = (int) $existingUser['id_utilisateur'];
        $stmtUpUser = $pdo->prepare("
            UPDATE utilisateur 
            SET prenom = ?, nom = ?, telephone = ?, statut_compte = 'actif' 
            WHERE id_utilisateur = ?
        ");
        $stmtUpUser->execute([$proData['prenom'], $proData['nom'], $proData['telephone'], $idUser]);
    } else {
        $stmtInsUser = $pdo->prepare("
            INSERT INTO utilisateur (nom, prenom, email, mot_de_passe_hash, role, telephone, statut_compte)
            VALUES (?, ?, ?, ?, 'professionnel', ?, 'actif')
        ");
        $stmtInsUser->execute([
            $proData['nom'], 
            $proData['prenom'], 
            $proData['email'], 
            $motDePasseHash, 
            $proData['telephone']
        ]);
        $idUser = (int) $pdo->lastInsertId();
    }

    // C. Créer ou mettre à jour le profil professionnel (statut 'approuve' pour affichage immédiat)
    $estCyber = ($proData['id_cat'] === $idCatCyber);

    $stmtProf = $pdo->prepare("SELECT id_profil FROM profil_professionnel WHERE id_utilisateur = ?");
    $stmtProf->execute([$idUser]);
    $existingProf = $stmtProf->fetch(PDO::FETCH_ASSOC);

    if ($existingProf) {
        $idProfil = (int) $existingProf['id_profil'];
        $stmtUpProf = $pdo->prepare("
            UPDATE profil_professionnel 
            SET id_categorie = ?, titre = ?, description = ?, zone = ?, disponibilite = ?, photo = ?,
                statut_validation = 'approuve', date_soumission = NOW(),
                engagement_cyber = ?, date_engagement_cyber = ?
            WHERE id_profil = ?
        ");
        $stmtUpProf->execute([
            $proData['id_cat'],
            $proData['titre'],
            $proData['description'],
            $proData['zone'],
            $proData['dispo'],
            $photoChemin,
            $estCyber ? 1 : 0,
            $estCyber ? date('Y-m-d H:i:s') : null,
            $idProfil
        ]);
    } else {
        $stmtInsProf = $pdo->prepare("
            INSERT INTO profil_professionnel (
                id_utilisateur, id_categorie, titre, description, zone, disponibilite, photo,
                statut_validation, date_soumission, engagement_cyber, date_engagement_cyber
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'approuve', NOW(), ?, ?)
        ");
        $stmtInsProf->execute([
            $idUser,
            $proData['id_cat'],
            $proData['titre'],
            $proData['description'],
            $proData['zone'],
            $proData['dispo'],
            $photoChemin,
            $estCyber ? 1 : 0,
            $estCyber ? date('Y-m-d H:i:s') : null
        ]);
        $idProfil = (int) $pdo->lastInsertId();
    }

    // D. Associer les compétences
    $pdo->prepare("DELETE FROM profil_competence WHERE id_profil = ?")->execute([$idProfil]);

    $listeCompetencesCat = match ($proData['id_cat']) {
        $idCatDev => $compsDev,
        $idCatVideo => $compsVideo,
        $idCatCyber => $compsCyber,
        default => $compsDev
    };

    $stmtInsComp = $pdo->prepare("
        INSERT IGNORE INTO profil_competence (id_profil, id_competence, niveau_declare)
        VALUES (?, ?, 'expert')
    ");

    foreach ($proData['competences'] as $motCle) {
        $idC = trouverIdComp($listeCompetencesCat, $motCle);
        if ($idC) {
            $stmtInsComp->execute([$idProfil, $idC]);
        }
    }

    // E. Ajouter les projets de portfolio
    $pdo->prepare("DELETE FROM projet_portfolio WHERE id_profil = ?")->execute([$idProfil]);
    $stmtInsPort = $pdo->prepare("
        INSERT INTO projet_portfolio (id_profil, titre, description, lien_externe, type_media, date_projet)
        VALUES (?, ?, ?, ?, 'lien', CURDATE() - INTERVAL 1 MONTH)
    ");

    foreach ($proData['projets'] as $proj) {
        $stmtInsPort->execute([
            $idProfil,
            $proj['titre'],
            $proj['description'],
            $proj['lien']
        ]);
    }

    // F. Ajouter un avis positif pour les profils pairs si un client test existe
    if ($idClientAvis && ($index % 2 === 0)) {
        // Vérifier si une demande terminée existe déjà
        $stmtDem = $pdo->prepare("SELECT id_demande FROM demande_service WHERE id_client = ? AND id_profil = ? LIMIT 1");
        $stmtDem->execute([$idClientAvis, $idProfil]);
        $existingDem = $stmtDem->fetch(PDO::FETCH_ASSOC);

        if (!$existingDem) {
            $stmtInsDem = $pdo->prepare("
                INSERT INTO demande_service (id_client, id_profil, id_categorie, titre, description, statut, date_creation, date_cloture)
                VALUES (?, ?, ?, ?, 'Prestation réalisée avec succès et satisfaction totale.', 'terminee', NOW() - INTERVAL 10 DAY, NOW() - INTERVAL 2 DAY)
            ");
            $stmtInsDem->execute([$idClientAvis, $idProfil, $proData['id_cat'], 'Mission internationale : ' . $proData['titre']]);
            $idDemande = (int) $pdo->lastInsertId();

            $stmtInsAvis = $pdo->prepare("
                INSERT INTO avis (id_demande, id_client, note, commentaire, statut, date_avis)
                VALUES (?, ?, 5, 'Prestation de classe mondiale ! Respect rigoureux du cahier des charges, communication fluide et rendu technique irréprochable. Hautement recommandé.', 'publie', NOW() - INTERVAL 1 DAY)
            ");
            $stmtInsAvis->execute([$idDemande, $idClientAvis]);
        }
    }

    $compteurInscrits++;
    echo sprintf("[%02d/10] %s %s (%s)\n", $compteurInscrits, $proData['prenom'], $proData['nom'], $proData['pays']);
    echo "       Titre : {$proData['titre']}\n";
    echo "       Zone  : {$proData['zone']}\n";
    echo "       Email : {$proData['email']}\n";
    echo "       Photo : {$photoChemin}\n\n";
}

echo "============================================================\n";
echo " {$compteurInscrits} PROFESSIONNELS INTERNATIONAUX INSCRITS AVEC SUCCÈS !\n";
echo " Mot de passe commun pour les tests : ProPass2026!\n";
echo "============================================================\n";
