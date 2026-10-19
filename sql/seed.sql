-- =============================================================================
-- Données Initiales de Référence (Seed) — FindPro (MVP)
-- Base de données : findpro
-- Description     : Insertion des 3 catégories de métiers et de 12 compétences
--                   techniques par catégorie (36 compétences au total).
-- Note            : Aucun compte utilisateur ni profil pro n'est inséré ici.
-- =============================================================================

USE findpro;

-- =============================================================================
-- 1. Insertion des Catégories de Référence
-- =============================================================================
INSERT INTO categorie (libelle, slug, description, icone) VALUES
(
    'Développement informatique',
    'developpement',
    'Conception, développement et maintenance de sites web, applications mobiles et solutions logicielles sur mesure.',
    'bi-code-slash'
),
(
    'Montage vidéo',
    'montage-video',
    'Montage, post-production, étalonnage, habillage sonore et motion design pour tous vos contenus audiovisuels.',
    'bi-camera-reels'
),
(
    'Cybersécurité',
    'cybersecurite',
    'Audit de sécurité préventif avec autorisation, durcissement de systèmes, protection des données et conformité.',
    'bi-shield-check'
);

-- =============================================================================
-- 2. Récupération des Identifiants des Catégories
-- =============================================================================
SET @id_cat_dev = (SELECT id_categorie FROM categorie WHERE slug = 'developpement');
SET @id_cat_video = (SELECT id_categorie FROM categorie WHERE slug = 'montage-video');
SET @id_cat_cyber = (SELECT id_categorie FROM categorie WHERE slug = 'cybersecurite');

-- =============================================================================
-- 3. Insertion des Compétences : Développement Informatique (12 compétences)
-- =============================================================================
INSERT INTO competence (libelle, id_categorie) VALUES
('PHP & Laravel', @id_cat_dev),
('JavaScript & TypeScript', @id_cat_dev),
('React & Next.js', @id_cat_dev),
('Vue.js & Nuxt', @id_cat_dev),
('Node.js & Express', @id_cat_dev),
('Python & Django', @id_cat_dev),
('Développement Mobile (Flutter / React Native)', @id_cat_dev),
('Conception d''API REST & GraphQL', @id_cat_dev),
('Bases de données SQL (MySQL / PostgreSQL)', @id_cat_dev),
('Intégration HTML5 / CSS3 / Bootstrap', @id_cat_dev),
('WordPress & WooCommerce sur mesure', @id_cat_dev),
('Gestion de version Git & Déploiement CI/CD', @id_cat_dev);

-- =============================================================================
-- 4. Insertion des Compétences : Montage Vidéo (12 compétences)
-- =============================================================================
INSERT INTO competence (libelle, id_categorie) VALUES
('Montage sous Adobe Premiere Pro', @id_cat_video),
('Montage sous DaVinci Resolve', @id_cat_video),
('Motion Design & Animation After Effects', @id_cat_video),
('Étalonnage et correction colorimétrique', @id_cat_video),
('Mixage et nettoyage audio (Audition)', @id_cat_video),
('Sous-titrage et habillage dynamique', @id_cat_video),
('Création de formats courts (Reels, TikTok, Shorts)', @id_cat_video),
('Montage institutionnel et corporate', @id_cat_video),
('Animation de logos et typographie cinétique', @id_cat_video),
('Effets visuels et compositing (VFX)', @id_cat_video),
('Encodage et optimisation multi-formats', @id_cat_video),
('Storyboarding et découpage rythmique', @id_cat_video);

-- =============================================================================
-- 5. Insertion des Compétences : Cybersécurité Défensive & Audit Autorisé (12 compétences)
-- Règle RG11 : Uniquement des compétences préventives, défensives et conformes.
-- =============================================================================
INSERT INTO competence (libelle, id_categorie) VALUES
('Audit de sécurité de code source', @id_cat_cyber),
('Analyse de vulnérabilités web avec autorisation', @id_cat_cyber),
('Durcissement de serveurs Linux & Windows', @id_cat_cyber),
('Configuration de pare-feu et WAF', @id_cat_cyber),
('Sauvegarde et plan de reprise d''activité (PRA)', @id_cat_cyber),
('Authentification forte et gestion des accès (MFA)', @id_cat_cyber),
('Sensibilisation au phishing et aux cyber-risques', @id_cat_cyber),
('Surveillance et analyse des journaux d''événements (Logs)', @id_cat_cyber),
('Sécurisation des bases de données et chiffrement', @id_cat_cyber),
('Mise en place de certificats SSL/TLS et HTTPS', @id_cat_cyber),
('Sécurisation et durcissement de CMS (WordPress)', @id_cat_cyber),
('Gestion des correctifs de sécurité (Patch Management)', @id_cat_cyber);
