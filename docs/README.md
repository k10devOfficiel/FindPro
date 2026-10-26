# Documentation du Projet FindPro

Plateforme web de mise en relation directe entre des professionnels indépendants des métiers du numérique et des particuliers ou entreprises.

---

## 1. Présentation de FindPro

1. **Mission & Cible :** FindPro facilite la découverte et la prise de contact directe avec des professionnels indépendants du numérique en Côte d'Ivoire, sans intermédiaire financier ni commission sur les prestations.
2. **Spécialisation Métiers :** La plateforme concentre son annuaire sur trois corps d'expertise clés : le Développement web & mobile, le Montage vidéo & Motion design, et la Cybersécurité & Audit technique.
3. **Architecture Légère & Robuste :** Conçu en PHP 8 procédural structuré avec PDO MySQL sous environnement WAMP, sans framework lourd, garantissant performance, maintenabilité et maîtrise totale du code.
4. **Contrôle d'Accès & Confidentialité :** Cloisonnement strict des espaces (Visiteur, Client, Professionnel, Administrateur), modération administrative préalable des profils et messagerie privée sécurisée (secret des correspondances RG04).
5. **Transparence & Confiance :** Les profils affichent des compétences certifiées, un portfolio de réalisations concrètes et des avis clients authentifiés, déposables uniquement après clôture effective d'une prestation.

---

## 2. Prérequis Système

- **Serveur Web :** Apache 2.4+ avec module `mod_rewrite` activé (inclus dans WAMP 3.3+).
- **Langage :** PHP 8.1 ou supérieur (PHP 8.2 / 8.3 recommandé).
  - Extensions PHP requises : `pdo`, `pdo_mysql`, `mbstring`, `fileinfo`, `gd` (ou `imagick`).
- **Base de Données :** MySQL 8.0+ ou MariaDB 10.5+ avec support complet du jeu de caractères `utf8mb4`.
- **Navigateur :** Navigateur web moderne compatible HTML5 / ES6 (Chrome, Firefox, Safari, Edge).

---

## 3. Installation Pas à Pas

### Étape 1 : Cloner ou copier le projet
Déposez les sources du projet dans le répertoire racine de votre serveur web WAMP :
```bash
cd c:\wamp64\www
git clone https://github.com/k10devOfficiel/FindPro.git findpro
cd findpro
```

### Étape 2 : Configurer les paramètres locaux
Dupliquez le modèle de configuration pour créer votre fichier de configuration locale (exclu de Git) :
```bash
cp config/config.example.php config/config.local.php
```
Ouvrez `config/config.local.php` et ajustez les accès à votre base de données locale :
```php
return [
    'db_host'         => 'localhost',
    'db_name'         => 'findpro',
    'db_user'         => 'root',       // Utilisateur MySQL local
    'db_pass'         => '',           // Mot de passe (vide par défaut sous WAMP)
    'db_charset'      => 'utf8mb4',
    'app_url'         => 'http://localhost/findpro',
    'app_env'         => 'dev',        // 'dev' en local, 'prod' en production
    'upload_max_size' => 2097152,      // 2 Mo
];
```

### Étape 3 : Créer la base de données et importer le schéma
Exécutez dans votre terminal MySQL (ou via phpMyAdmin) :
```sql
CREATE DATABASE findpro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```
Importez ensuite successivement les fichiers de structure et de données initiales :
```bash
mysql -u root -p findpro < sql/schema.sql
mysql -u root -p findpro < sql/seed.sql
```
- `sql/schema.sql` initialise l'ensemble des 14 tables relationnelles InnoDB.
- `sql/seed.sql` insère les 3 catégories maîtresses et les 36 compétences de référence.

### Étape 4 : Créer le compte Administrateur
Lancez le script interactif en ligne de commande (sécurité : CLI uniquement) :
```bash
php scripts/creer_admin.php
```
Renseignez le nom, prénom, email et un mot de passe robuste (minimum 12 caractères).  
*Alternative non interactive en ligne de commande :*
```bash
php scripts/creer_admin.php --nom="Kouame" --prenom="Dieudonne" --email="admin@findpro.ci" --password="MonSuperMotDePasse123"
```

### Étape 5 (Optionnel) : Générer les données de test
Pour tester immédiatement tous les rôles et scénarios en mode `dev` :
```bash
php scripts/donnees_test.php
```
Ce script crée 3 professionnels vérifiés (un par catégorie), 2 clients, une demande acceptée avec conversation active et une demande terminée avec avis certifié.
*(Pour nettoyer ultérieurement ces données : `php scripts/donnees_test.php --supprimer`).*

### Étape 6 : Démarrer l'application
Démarrez les services WAMP (Apache et MySQL) puis rendez-vous sur :  
👉 **`http://localhost/findpro/`**

---

## 4. Structure des Dossiers

```text
findpro/
├── admin/               # Espace d'administration (validation profils, utilisateurs, signalements)
│   ├── index.php        # Tableau de bord administratif
│   ├── validation.php   # File d'attente d'arbitrage des profils professionnels
│   ├── profil_detail.php# Examen détaillé d'un profil et actions d'approbation/rejet/correction
│   └── utilisateurs.php # Liste et suspension/activation des comptes utilisateurs
├── assets/              # Fichiers statiques distribués
│   ├── css/style.css    # Thème FindPro et surcharges graphiques
│   ├── js/main.js       # Scripts transversaux (anti-double submit, utilitaires UI)
│   ├── js/chat.js       # Client messagerie AJAX (polling 5s, DOM textContent anti-XSS)
│   └── img/             # Logo, favicons et illustrations SVG par défaut
├── backups/             # Sauvegardes SQL manuelles et exports (exclu de Git)
├── chat/                # Module de messagerie privée en direct
│   ├── conversation.php # Interface de conversation paginée
│   ├── poll.php         # Endpoint JSON de polling différentiel
│   └── send.php         # Endpoint API POST JSON d'envoi de messages
├── client/              # Espace dédié aux clients
│   ├── index.php        # Tableau de bord client
│   └── demandes.php     # Suivi des demandes de services émises
├── config/              # Fichiers de configuration
│   ├── config.php       # Chargeur centralisé de configuration
│   ├── config.example.php # Modèle de configuration versionné
│   └── config.local.php # Configuration de la machine courante (ignoré par Git)
├── docs/                # Documentation technique, juridique, dictionnaire et tests
├── includes/            # Cœur applicatif procédural et modules réutilisables
│   ├── auth.php         # Contrôles de session et middlewares de rôles
│   ├── csrf.php         # Génération et vérification des jetons CSRF
│   ├── db.php           # Connexion singleton PDO MySQL (UTF-8, exceptions strictes)
│   ├── functions.php    # Utilitaires globaux (échappement e(), redirections, formatages)
│   ├── securite.php     # En-têtes HTTP de sécurité (CSP, X-Frame-Options, nosniff)
│   ├── upload.php       # Téléversement sécurisé (finfo MIME, redimensionnement GD)
│   ├── carte_profil.php # Composant d'affichage de carte professionnelle
│   ├── header.php       # En-tête HTML commun et barre de navigation responsive
│   └── footer.php       # Pied de page commun avec liens légaux
├── logs/                # Fichiers journaux système (erreurs.log, exclu de Git)
├── pro/                 # Espace dédié aux professionnels
│   ├── index.php        # Tableau de bord professionnel
│   ├── profil.php       # Édition des informations, photo et zone d'intervention
│   ├── competences.php  # Sélection et niveaux des compétences déclarées
│   ├── portfolio.php    # Gestion des réalisations de chantiers/projets
│   └── demandes.php     # Gestion des demandes de prestations reçues
├── scripts/             # Scripts d'administration en ligne de commande (CLI uniquement)
│   ├── creer_admin.php  # Création d'un administrateur
│   └── donnees_test.php # Création et purge des comptes de test
├── sql/                 # Schéma et scripts SQL
│   ├── schema.sql       # Structure complète des 14 tables InnoDB
│   └── seed.sql         # Données de référence initiales (catégories, compétences)
├── uploads/             # Répertoire de stockage des fichiers utilisateurs (exclu de Git)
│   ├── avatars/         # Photos de profil
│   ├── portfolio/       # Photos de réalisations
│   └── .htaccess        # Interdiction stricte d'exécution de scripts PHP
├── index.php            # Page d'accueil publique (recherche et professionnels mis en avant)
├── search.php           # Page de recherche et de filtres avancés
├── category.php         # Annuaire par catégorie de métier
├── profile.php          # Fiche publique du professionnel approuvé
├── request_new.php      # Formulaire de demande de prestation
├── demande_detail.php   # Fiche de suivi d'une demande de prestation
├── login.php            # Connexion unifiée avec protection anti-brute force
├── register.php         # Inscription distincte Client ou Professionnel
├── logout.php           # Déconnexion et destruction de session
├── mentions.php         # Mentions légales de l'éditeur
├── confidentialite.php  # Politique de protection des données personnelles
├── regles.php           # Règles d'utilisation et cadre déontologique
├── 403.php, 404.php, 500.php # Pages d'erreur HTTP personnalisées
└── .htaccess            # Règles Apache de réécriture, sécurité et blocage d'accès
```

---

## 5. Rôles et Statuts

### Rôles Utilisateurs (`utilisateur.role`)
- **`client` :** Recherche des prestataires, consulte les profils publics, émet des demandes de services, échange via la messagerie privée, confirme la fin de prestation et dépose un avis noté.
- **`professionnel` :** Rédige et gère sa fiche professionnelle, ajoute des réalisations à son portfolio, choisit ses compétences, soumet son profil à vérification, accepte ou refuse les demandes, gère l'avancement des travaux.
- **`admin` :** Modère et arbitre les profils professionnels (approbation, rejet avec motif, demande de correction), active ou suspend les comptes utilisateurs, supervise les demandes et traite les signalements. *Règle RG04 : L'administrateur n'a aucun accès aux conversations privées.*

### Cycle de Validation du Profil Pro (`profil_professionnel.statut_validation`)
1. **`brouillon` :** Profil en cours de création, invisible du public.
2. **`soumis` :** Profil envoyé par le professionnel pour examen par l'administration.
3. **`en_verification` :** Pris en charge par un administrateur pour vérification.
4. **`a_corriger` :** Renvoyé au professionnel avec motif précis pour mise en conformité.
5. **`approuve` :** Validé par l'administrateur. **Seul ce statut est visible publiquement (RG05).**
6. **`refuse` :** Rejet définitif avec motif communiqué au professionnel.
7. **`suspendu` :** Désactivé temporairement par la modération suite à incident.

### Cycle de Vie d'une Demande de Service (`demande_service.statut`)
1. **`envoyee` :** Demande émise par le client. Conversation liée créée. Anti-harcèlement RG13 : le client est limité à 3 messages tant que le pro n'a pas répondu.
2. **`acceptee` :** Acceptée par le professionnel. Le chat devient illimité pour les deux parties.
3. **`refusee` :** Déclinée par le professionnel.
4. **`en_cours` :** Prestation démarrée par le professionnel.
5. **`a_confirmer` :** Travaux marqués comme terminés par le professionnel.
6. **`terminee` :** Prestation confirmée par le client. Conversation verrouillée en lecture seule (`statut = lecture_seule`). Dépôt d'avis certifié débloqué.
7. **`annulee` :** Annulée d'un commun accord.

---

## 6. Liste des Fonctionnalités Livrées

- **Authentification & Gestion des Rôles :** Inscription dédiée (Client / Professionnel), connexion unifiée, détection automatique du rôle avec redirection, protection contre le brute-force (`tentative_connexion`), hachage Bcrypt via `password_hash()`.
- **Profil Professionnel Complet :** Bio, années d'expérience, zone d'intervention, disponibilité, photo avec validation binaire MIME, sélection de compétences et niveaux, galerie portfolio avec photos et liens.
- **Charte Déontologique Cybersécurité :** Signature d'engagement obligatoire horodatée pour les professionnels de la cybersécurité.
- **Moteur de Recherche & Filtres :** Recherche par mots-clés, filtrage par métier / catégorie, filtrage par zone géographique, restriction stricte aux profils approuvés (RG05).
- **Demandes de Prestation Atomiques :** Formulaire de demande sécurisé, création conjointe de la conversation dans une même transaction SQL, plafonnement anti-spam (3 demandes en attente par pro, 10 demandes max par 24h).
- **Messagerie Privée Sécurisée :** Interface conversationnelle chronologique, pagination des 50 derniers messages, polling différentiel AJAX toutes les 5s, envoi asynchrone sans rechargement, rendu DOM via `textContent` (protection anti-XSS intégrale), suspension si onglet masqué (`document.hidden`), verrouillage automatique en lecture seule à la clôture.
- **Évaluations & Avis Certifiés :** Dépôt d'avis (note 1 à 5 + commentaire) conditionné à la clôture effective de la prestation (`terminee`), recalcul dynamique de la note moyenne.
- **Back-Office d'Arbitrage :** Tableau de bord admin, examen des fiches en attente, approbation ou rejet motivé, gestion des comptes utilisateurs, traçabilité des décisions dans `validation_profil`.
- **Sécurité Globale :** 100% requêtes préparées PDO, jeton CSRF sur tous les formulaires POST, échappement systématique `e()`, en-têtes HTTP défensifs (CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy), gestion centralisée des erreurs avec journalisation dans `logs/erreurs.log`, pages d'erreurs 403, 404, 500 propres.
- **Pages Légales Conformité :** Mentions légales (`mentions.php`), Politique de confidentialité (`confidentialite.php`), Règles d'usage (`regles.php`).

---

## 7. Liste des Fonctionnalités Non Livrées (Hors Périmètre MVP)

- **Paiement en ligne & Séquestre bancaire :** Volontairement exclu. FindPro est une plateforme de mise en relation pure ; la rémunération s'effectue directement entre le client et le professionnel hors plateforme.
- **Messagerie temps réel WebSockets :** Non implémenté (remplacé par un polling AJAX léger toutes les 5 secondes).
- **Notifications par E-mail ou SMS :** Non implémenté dans le MVP (suivi d'état in-app via les tableaux de bord).
- **Système de Favoris / Mise en mémoire de profils :** Non implémenté.
- **Pièces jointes dans le Chat :** Les messages sont au format texte brut uniquement (pas d'envoi de fichiers dans le chat).
- **Génération automatique de devis et factures PDF :** Non implémenté.

---

## 8. Limites Connues

1. **Latence du Polling :** En l'absence de WebSockets, un délai maximal de 5 secondes peut s'écouler avant la réception d'un nouveau message sur un onglet actif.
2. **Environnement Local WAMP :** Les chemins de fichiers et configurations serveur sont optimisés pour Windows/Apache ; le déploiement Linux nécessitera de vérifier les permissions de dossiers (`chmod 755/775`).
3. **Taille maximale des fichiers téléversés :** Plafonnée par défaut à 2 Mo par image (ajustable dans `config.local.php` et dépendant des directives `upload_max_filesize` / `post_max_size` de PHP).
4. **Stockage Local des Médias :** Les images sont hébergées sur le système de fichiers local (`uploads/`) plutôt que sur un stockage objet cloud type S3.

---

## 9. Procédure de Sauvegarde et Restauration

### Procédure de Sauvegarde
1. **Sauvegarde de la base de données :**
   ```bash
   mysqldump -u root -p findpro > backups/findpro_AAAA-MM-JJ.sql
   ```
2. **Sauvegarde des fichiers médias et configuration :**
   Copiez hors du dépôt Git :
   - Le dossier complet `uploads/` (photos de profil et réalisations).
   - Le fichier `config/config.local.php`.

### Procédure de Restauration (5 étapes)
1. **Créez la base de données :**
   ```bash
   mysql -u root -p -e "CREATE DATABASE findpro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```
2. **Importez la sauvegarde SQL :**
   ```bash
   mysql -u root -p findpro < backups/findpro_AAAA-MM-JJ.sql
   ```
3. **Restaurez la configuration :**  
   Copiez votre sauvegarde de `config.local.php` dans le dossier `config/` et vérifiez les accès MySQL.
4. **Restaurez les fichiers médias :**  
   Copiez les dossiers sauvegardés `avatars/` et `portfolio/` dans `uploads/`.
5. **Validez le déploiement :**  
   Accédez à `http://localhost/findpro/` et vérifiez la connexion avec votre compte administrateur.

---

## 10. À Faire Avant Mise en Ligne (Checklist Production)

Avant d'exposer l'application sur un serveur public de production, réalisez impérativement les opérations suivantes :

- [ ] **HTTPS obligatoire :** Installer un certificat SSL/TLS valide (Let's Encrypt) et forcer la redirection HTTP vers HTTPS dans Apache.
- [ ] **Désactiver l'affichage des erreurs :** Configurer `display_errors = Off` et `display_startup_errors = Off` dans le `php.ini` de production.
- [ ] **Passer l'environnement en production :** Définir `'app_env' => 'prod'` dans `config/config.local.php` pour masquer tout détail technique et activer les alertes de sécurité.
- [ ] **Sécuriser les cookies de session :** Activer la directive `session.cookie_secure = 1` dans `includes/session.php` afin que le cookie de session ne transite que sur des liaisons chiffrées HTTPS.
- [ ] **Utilisateur MySQL dédié avec mot de passe fort :** Bannir le compte `root` sans mot de passe ; créer un utilisateur MySQL aux privilèges strictement restreints à la base `findpro`.
- [ ] **Supprimer ou protéger le dossier `scripts/` :** Interdire tout accès web et restreindre l'exécution système des scripts CLI aux administrateurs autorisés.
- [ ] **Purger les données de test :** Exécuter `php scripts/donnees_test.php --supprimer` pour éliminer tous les comptes et demandes de démonstration.
- [ ] **Revue juridique des pages légales :** Faire valider [mentions.php](file:///c:/wamp64/www/FindPro/mentions.php), [confidentialite.php](file:///c:/wamp64/www/FindPro/confidentialite.php) et [regles.php](file:///c:/wamp64/www/FindPro/regles.php) par un juriste spécialisé (déclaration ARTCI en Côte d'Ivoire).
- [ ] **Hébergement avec sauvegardes automatiques :** Programmer une sauvegarde quotidienne automatisée de la base de données et du dossier `uploads/` sur un stockage distant sécurisé.
