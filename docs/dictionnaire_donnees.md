# Dictionnaire de Données — FindPro

Ce document formalise la structure conceptuelle et logique des données de la plateforme **FindPro**.

### Conventions générales appliquées :
- **Nommage :** snake_case sans accent, noms au singulier pour les tables et colonnes.
- **Rôles autorisés :** `client`, `professionnel`, `admin`.
- **Statuts de profil professionnel :** `brouillon`, `soumis`, `en_verification`, `a_corriger`, `approuve`, `refuse`, `suspendu`.
- **Statuts de demande de service :** `envoyee`, `acceptee`, `refusee`, `en_cours`, `a_confirmer`, `terminee`, `annulee`.
- **Statuts d'avis :** `publie`, `masque`.
- **Règles de confidentialité :**
  - `publique` : Visible par les visiteurs et utilisateurs de la plateforme.
  - `privée` : Accessible uniquement aux parties prenantes concernées (propriétaire de la donnée ou interlocuteur direct dans une relation de service). L'e-mail et le téléphone ne sont **jamais publics**.
  - `admin` : Réservé exclusivement aux administrateurs de FindPro.
  - Le hachage du mot de passe n'est **jamais affiché**, quel que soit le niveau d'accès.
- **Monnaie :** Tous les montants financiers (budgets) sont exprimés en **FCFA sous forme d'entiers** (`INT`).

---

## 1. Entité : UTILISATEUR (`utilisateur`)
Représente le compte principal d'accès et d'authentification sur la plateforme.

| Donnée | Description | Type | Taille / Format | Obligatoire | Confidentialité | Source |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id_utilisateur` | Clé primaire unique | Entier (INT) | UNSIGNED AUTO_INCREMENT | Oui | privée | système |
| `nom` | Nom de famille de l'utilisateur | Chaîne (VARCHAR) | 100 caractères | Oui | privée | saisie |
| `prenom` | Prénom de l'utilisateur | Chaîne (VARCHAR) | 100 caractères | Oui | publique (initiale ou prénom seul côté client) | saisie |
| `email` | Adresse e-mail de connexion et notification | Chaîne (VARCHAR) | 191 caractères (format email valide) | Oui | privée (jamais public) | saisie |
| `telephone` | Numéro de téléphone pour contact direct après mise en relation | Chaîne (VARCHAR) | 20 caractères (ex: +225 0700000000) | Oui | privée (jamais public) | saisie |
| `mot_de_passe` | Empreinte sécurisée du mot de passe | Chaîne (VARCHAR) | 255 caractères (`password_hash` BCRYPT/ARGON2) | Oui | admin (non affiché) | saisie (haché par système) |
| `role` | Rôle et niveau d'accès | Énumération (ENUM) | `'client'`, `'professionnel'`, `'admin'` | Oui | privée | saisie / système |
| `est_actif` | Indicateur de compte actif ou suspendu | Booléen (TINYINT) | 1 (actif), 0 (suspendu) | Oui | admin | système |
| `date_creation` | Date et heure de création du compte | Date/Heure (DATETIME) | `YYYY-MM-DD HH:MM:SS` | Oui | privée | système |
| `date_modification` | Date et heure de dernière mise à jour | Date/Heure (DATETIME) | `YYYY-MM-DD HH:MM:SS` | Non | privée | système |

---

## 2. Entité : PROFIL_PROFESSIONNEL (`profil_professionnel`)
Contient la vitrine publique, les informations métier et l'état de vérification d'un professionnel du numérique.

| Donnée | Description | Type | Taille / Format | Obligatoire | Confidentialité | Source |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id_profil` | Clé primaire du profil pro | Entier (INT) | UNSIGNED AUTO_INCREMENT | Oui | publique | système |
| `id_utilisateur` | Clé étrangère liant au compte utilisateur | Entier (INT) | UNSIGNED UNIQUE | Oui | admin | système |
| `titre_professionnel` | Titre du métier ou spécialité principale (ex: Développeur Web, Monteur Vidéo) | Chaîne (VARCHAR) | 150 caractères | Oui | publique | saisie |
| `biographie` | Présentation détaillée du parcours, des compétences et méthodes de travail | Texte (TEXT) | Texte libre (recommandé max 2000 car.) | Non | publique | saisie |
| `photo_profil` | Nom du fichier image d'avatar sécurisé | Chaîne (VARCHAR) | 255 caractères | Non | publique | saisie |
| `annees_experience` | Nombre d'années d'expérience dans le domaine | Entier (TINYINT) | 0 à 50 (entier positif) | Oui | publique | saisie |
| `commune` | Commune de résidence / rattachement géographique | Chaîne (VARCHAR) | 100 caractères (ex: Cocody, Plateau) | Oui | publique | saisie |
| `adresse_complete` | Adresse physique ou quartier détaillé | Chaîne (VARCHAR) | 255 caractères | Non | privée | saisie |
| `disponibilite` | Statut opérationnel immédiat pour de nouvelles missions | Booléen (TINYINT) | 1 (disponible), 0 (occupé) | Oui | publique | saisie |
| `statut_profil` | Statut dans le cycle de vérification administrative | Énumération (ENUM) | `'brouillon'`, `'soumis'`, `'en_verification'`, `'a_corriger'`, `'approuve'`, `'refuse'`, `'suspendu'` | Oui | privée / admin | système |
| `note_moyenne` | Note globale sur 5 calculée à partir des avis reçus | Décimal (DECIMAL) | 3,2 (de 0.00 à 5.00) | Oui | publique | calcul |
| `nombre_avis` | Nombre total d'avis publics validés | Entier (INT) | UNSIGNED (défaut 0) | Oui | publique | calcul |
| `date_creation` | Date de création de la fiche | Date/Heure (DATETIME) | `YYYY-MM-DD HH:MM:SS` | Oui | admin | système |
| `date_modification` | Date de dernière mise à jour | Date/Heure (DATETIME) | `YYYY-MM-DD HH:MM:SS` | Non | admin | système |

---

## 3. Entité : CATEGORIE (`categorie`)
Définit les grands domaines d'activité du numérique (Développement web/mobile, Création vidéo & motion design, Cybersécurité, etc.).

| Donnée | Description | Type | Taille / Format | Obligatoire | Confidentialité | Source |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id_categorie` | Clé primaire | Entier (INT) | UNSIGNED AUTO_INCREMENT | Oui | publique | système |
| `nom` | Intitulé officiel de la catégorie | Chaîne (VARCHAR) | 100 caractères | Oui | publique | saisie |
| `slug` | Identifiant textuel normalisé pour les URL | Chaîne (VARCHAR) | 120 caractères (a-z, 0-9, tirets) | Oui | publique | saisie / calcul |
| `description` | Description explicative des compétences rattachées | Chaîne (VARCHAR) | 255 caractères | Non | publique | saisie |
| `icone` | Classe d'icône d'affichage (ex: bi-code-slash) | Chaîne (VARCHAR) | 50 caractères | Non | publique | saisie |
| `est_active` | Visibilité dans les filtres de recherche | Booléen (TINYINT) | 1 (active), 0 (inactive) | Oui | publique | saisie |
| `ordre_affichage` | Priorité d'ordonnancement dans les menus | Entier (SMALLINT) | Entier positif (défaut 0) | Oui | publique | saisie |

---

## 4. Entité : COMPETENCE (`competence`)
Représente les compétences techniques spécifiques rattachées à un domaine (ex: PHP, React, Première Pro, Audit de code).

| Donnée | Description | Type | Taille / Format | Obligatoire | Confidentialité | Source |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id_competence` | Clé primaire | Entier (INT) | UNSIGNED AUTO_INCREMENT | Oui | publique | système |
| `id_categorie` | Catégorie parente de rattachement | Entier (INT) | UNSIGNED (FK categorie) | Oui | publique | saisie |
| `nom` | Intitulé de la compétence ou technologie | Chaîne (VARCHAR) | 100 caractères | Oui | publique | saisie |
| `slug` | Identifiant d'URL normalisé | Chaîne (VARCHAR) | 120 caractères | Oui | publique | saisie / calcul |

---

## 5. Entité : PROFIL_COMPETENCE (`profil_competence`)
Table de liaison associant un professionnel aux compétences techniques qu'il maîtrise.

| Donnée | Description | Type | Taille / Format | Obligatoire | Confidentialité | Source |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id_profil` | Clé étrangère vers le profil pro | Entier (INT) | UNSIGNED (FK profil_professionnel) | Oui | publique | système |
| `id_competence` | Clé étrangère vers la compétence | Entier (INT) | UNSIGNED (FK competence) | Oui | publique | saisie |
| `niveau` | Degré de maîtrise déclaré | Énumération (ENUM) | `'debutant'`, `'intermediaire'`, `'avance'`, `'expert'` | Non | publique | saisie |

---

## 6. Entité : PROJET_PORTFOLIO (`projet_portfolio`)
Illustre les réalisations passées du professionnel (chantiers numériques, maquettes, applications, vidéos).

| Donnée | Description | Type | Taille / Format | Obligatoire | Confidentialité | Source |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id_projet` | Clé primaire | Entier (INT) | UNSIGNED AUTO_INCREMENT | Oui | publique | système |
| `id_profil` | Profil professionnel propriétaire | Entier (INT) | UNSIGNED (FK profil_professionnel) | Oui | publique | système |
| `titre` | Nom ou intitulé de la réalisation | Chaîne (VARCHAR) | 150 caractères | Oui | publique | saisie |
| `description` | Détail du contexte, des défis et des technologies employées | Texte (TEXT) | Texte libre | Non | publique | saisie |
| `lien_externe` | URL vers le projet en ligne (site live, GitHub, Vimeo, etc.) | Chaîne (VARCHAR) | 255 caractères (format URL valide) | Non | publique | saisie |
| `fichier_image` | Image ou capture d'écran d'illustration | Chaîne (VARCHAR) | 255 caractères (chemin relatif) | Non | publique | saisie |
| `est_couverture` | Mis en avant en tant que visuel principal du projet | Booléen (TINYINT) | 1 (oui), 0 (non) | Oui | publique | saisie |
| `date_creation` | Date d'ajout au portfolio | Date/Heure (DATETIME) | `YYYY-MM-DD HH:MM:SS` | Oui | publique | système |

---

## 7. Entité : DEMANDE_SERVICE (`demande_service`)
Matérialise une commande de prestation initiée par un client auprès d'un professionnel.

| Donnée | Description | Type | Taille / Format | Obligatoire | Confidentialité | Source |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id_demande` | Clé primaire | Entier (INT) | UNSIGNED AUTO_INCREMENT | Oui | privée | système |
| `id_client` | Client émetteur du besoin | Entier (INT) | UNSIGNED (FK utilisateur) | Oui | privée | système |
| `id_profil` | Professionnel sollicité | Entier (INT) | UNSIGNED (FK profil_professionnel) | Oui | privée | système |
| `id_categorie` | Domaine d'intervention concerné | Entier (INT) | UNSIGNED (FK categorie) | Oui | privée | saisie |
| `titre` | Objet court de la prestation | Chaîne (VARCHAR) | 150 caractères | Oui | privée | saisie |
| `description` | Cahier des charges ou détail des besoins | Texte (TEXT) | Texte libre | Oui | privée | saisie |
| `budget` | Montant budgétaire estimé en Francs CFA | Entier (INT) | Nombre entier en FCFA (ex: 75000) | Non | privée | saisie |
| `date_souhaitee` | Date de réalisation ou démarrage espérée | Date (DATE) | `YYYY-MM-DD` | Non | privée | saisie |
| `statut` | Étape du cycle de vie de la mission | Énumération (ENUM) | `'envoyee'`, `'acceptee'`, `'refusee'`, `'en_cours'`, `'a_confirmer'`, `'terminee'`, `'annulee'` | Oui | privée | système / saisie |
| `motif_refus` | Raison invoquée en cas de rejet par le professionnel | Chaîne (VARCHAR) | 255 caractères | Non | privée | saisie |
| `date_confirmation` | Date de validation de bonne exécution marquant la fin des travaux | Date/Heure (DATETIME) | `YYYY-MM-DD HH:MM:SS` | Non | privée | système / saisie |
| `date_creation` | Date et heure d'envoi de la demande | Date/Heure (DATETIME) | `YYYY-MM-DD HH:MM:SS` | Oui | privée | système |
| `date_modification` | Date et heure de dernière mise à jour de statut | Date/Heure (DATETIME) | `YYYY-MM-DD HH:MM:SS` | Non | privée | système |

---

## 8. Entité : CONVERSATION (`conversation`)
Fil de discussion sécurisé créé automatiquement entre un client et un professionnel pour une demande de service.

| Donnée | Description | Type | Taille / Format | Obligatoire | Confidentialité | Source |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id_conversation` | Clé primaire | Entier (INT) | UNSIGNED AUTO_INCREMENT | Oui | privée | système |
| `id_demande` | Demande de service génératrice du fil de chat | Entier (INT) | UNSIGNED (FK demande_service) | Oui | privée | système |
| `id_client` | Compte utilisateur du client participant | Entier (INT) | UNSIGNED (FK utilisateur) | Oui | privée | système |
| `id_professionnel` | Compte utilisateur du pro participant | Entier (INT) | UNSIGNED (FK utilisateur) | Oui | privée | système |
| `date_dernier_message` | Horodatage du dernier message pour ordonner la boîte de réception | Date/Heure (DATETIME) | `YYYY-MM-DD HH:MM:SS` | Oui | privée | système |
| `date_creation` | Date d'initialisation du canal | Date/Heure (DATETIME) | `YYYY-MM-DD HH:MM:SS` | Oui | privée | système |

---

## 9. Entité : MESSAGE (`message`)
Message individuel échangé dans le cadre d'une conversation.

| Donnée | Description | Type | Taille / Format | Obligatoire | Confidentialité | Source |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id_message` | Clé primaire | Entier (INT) | UNSIGNED AUTO_INCREMENT | Oui | privée | système |
| `id_conversation` | Conversation d'appartenance | Entier (INT) | UNSIGNED (FK conversation) | Oui | privée | système |
| `id_expediteur` | Utilisateur ayant rédigé le message | Entier (INT) | UNSIGNED (FK utilisateur) | Oui | privée | système |
| `contenu` | Corps textuel du message | Texte (TEXT) | Texte libre assaini | Oui | privée | saisie |
| `est_lu` | Indicateur d'acquittement de lecture par le destinataire | Booléen (TINYINT) | 1 (lu), 0 (non lu) | Oui | privée | système |
| `date_envoi` | Date et heure précises d'envoi | Date/Heure (DATETIME) | `YYYY-MM-DD HH:MM:SS` | Oui | privée | système |

---

## 10. Entité : AVIS (`avis`)
Évaluation vérifiée déposée par le client une fois la mission terminée.

| Donnée | Description | Type | Taille / Format | Obligatoire | Confidentialité | Source |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id_avis` | Clé primaire | Entier (INT) | UNSIGNED AUTO_INCREMENT | Oui | publique | système |
| `id_demande` | Demande de service vérifiée ayant permis le dépôt d'avis | Entier (INT) | UNSIGNED UNIQUE (FK demande_service) | Oui | privée | système |
| `id_client` | Client auteur de l'avis | Entier (INT) | UNSIGNED (FK utilisateur) | Oui | publique (prénom seul affiché) | système |
| `id_profil` | Profil professionnel évalué | Entier (INT) | UNSIGNED (FK profil_professionnel) | Oui | publique | système |
| `note` | Note chiffrée sur 5 étoiles | Entier (TINYINT) | Entier de 1 à 5 | Oui | publique | saisie |
| `commentaire` | Témoignage détaillé sur la qualité du travail rendu | Texte (TEXT) | Texte libre | Non | publique | saisie |
| `statut` | Statut de modération et de publication | Énumération (ENUM) | `'publie'`, `'masque'` | Oui | admin | système / saisie |
| `date_creation` | Date de publication de l'avis | Date/Heure (DATETIME) | `YYYY-MM-DD HH:MM:SS` | Oui | publique | système |

---

## 11. Entité : VALIDATION_PROFIL (`validation_profil`)
Dossier d'instruction et justificatifs soumis par le professionnel pour obtenir la certification administrative.

| Donnée | Description | Type | Taille / Format | Obligatoire | Confidentialité | Source |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id_validation` | Clé primaire | Entier (INT) | UNSIGNED AUTO_INCREMENT | Oui | admin | système |
| `id_profil` | Profil professionnel soumis à examen | Entier (INT) | UNSIGNED (FK profil_professionnel) | Oui | admin | système |
| `id_admin` | Administrateur ayant statué sur la demande | Entier (INT) | UNSIGNED (FK utilisateur) | Non | admin | système |
| `type_document` | Nature du document produit (CNI, Passeport, Diplôme, Attestation) | Chaîne (VARCHAR) | 80 caractères | Oui | admin | saisie |
| `fichier_justificatif` | Chemin d'accès au fichier téléversé et chiffré/sécurisé | Chaîne (VARCHAR) | 255 caractères | Oui | admin | saisie |
| `statut_decision` | Décision administrative | Énumération (ENUM) | `'en_attente'`, `'approuve'`, `'refuse'`, `'a_corriger'` | Oui | admin | saisie |
| `commentaire_admin` | Consignes de correction ou motif du refus notifié au pro | Texte (TEXT) | Texte libre | Non | privée (communiqué au pro) / admin | saisie |
| `date_soumission` | Date de dépôt du dossier par le professionnel | Date/Heure (DATETIME) | `YYYY-MM-DD HH:MM:SS` | Oui | admin | système |
| `date_decision` | Date et heure de la décision administrative | Date/Heure (DATETIME) | `YYYY-MM-DD HH:MM:SS` | Non | admin | système |

---

## 12. Entité : SIGNALEMENT (`signalement`)
Procédure d'alerte pour abus, comportement frauduleux ou non-conformité.

| Donnée | Description | Type | Taille / Format | Obligatoire | Confidentialité | Source |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `id_signalement` | Clé primaire | Entier (INT) | UNSIGNED AUTO_INCREMENT | Oui | admin | système |
| `id_auteur` | Utilisateur ayant signalé l'élément | Entier (INT) | UNSIGNED (FK utilisateur) | Oui | admin | système |
| `type_cible` | Entité faisant l'objet du signalement | Énumération (ENUM) | `'profil'`, `'message'`, `'avis'`, `'demande'` | Oui | admin | saisie / système |
| `id_cible` | Identifiant de l'élément incriminé | Entier (INT) | UNSIGNED | Oui | admin | système |
| `motif` | Catégorisation du problème (harcèlement, arnaque, travail non livré, fausse identité) | Chaîne (VARCHAR) | 100 caractères | Oui | admin | saisie |
| `description` | Explications fournies par le plaignant | Texte (TEXT) | Texte libre | Oui | admin | saisie |
| `statut` | Étape de traitement administratif | Énumération (ENUM) | `'nouveau'`, `'en_cours'`, `'traite'`, `'rejete'` | Oui | admin | système / saisie |
| `decision_admin` | Action corrective retenue (ex: avertissement, suspension de compte, classement sans suite) | Chaîne (VARCHAR) | 255 caractères | Non | admin | saisie |
| `date_signalement` | Date et heure du dépôt du signalement | Date/Heure (DATETIME) | `YYYY-MM-DD HH:MM:SS` | Oui | admin | système |
| `date_traitement` | Date et heure de clôture du dossier | Date/Heure (DATETIME) | `YYYY-MM-DD HH:MM:SS` | Non | admin | système |

---

## Propositions (Éléments complémentaires suggérés)

*Ces données sont recommandées pour le cycle de vie de la plateforme mais n'ont pas été insérées dans les tableaux ci-dessus afin de respecter strictement les périmètres initiaux :*

1. **Pour l'authentification et la sécurité (`utilisateur`) :**
   - `jeton_reinitialisation` (`VARCHAR(100)`, NULL) : Jeton aléatoire à usage unique pour la procédure "Mot de passe oublié".
   - `date_expiration_jeton` (`DATETIME`, NULL) : Limite temporelle de validité du jeton de réinitialisation (ex : 1 heure).
   - `derniere_connexion` (`DATETIME`, NULL) : Horodatage utile pour détecter l'inactivité d'un compte.

2. **Pour les favoris (P1 - `favori`) :**
   - Création d'une table d'association `favori` (`id_client`, `id_profil`, `date_ajout`) permettant aux clients de sauvegarder leurs profils préférés sans passer par une demande.

3. **Pour le suivi des notifications in-app (`notification`) :**
   - Création d'une table `notification` (`id_notification`, `id_utilisateur`, `titre`, `message`, `lien`, `est_lu`, `date_creation`) pour informer en temps réel le professionnel et le client des changements d'états de leurs demandes sans dépendre uniquement des e-mails.

4. **Pour les échanges dans la messagerie (`message`) :**
   - `fichier_joint` (`VARCHAR(255)`, NULL) : Permettre aux clients et professionnels du numérique de transmettre des maquettes, captures d'écrans ou extraits de code directement dans le chat.
