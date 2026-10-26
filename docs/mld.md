# Modèle Logique de Données (MLD) — FindPro

Ce document présente la structure relationnelle détaillée de la base de données de **FindPro** (moteur MySQL/MariaDB InnoDB, encodage `utf8mb4_unicode_ci`), dérivée du dictionnaire de données et des règles de gestion (**RG01 à RG14**).

---

## 1. Schéma Relationnel Synthétique

*Les clés primaires (PK) sont soulignées, les clés étrangères (FK) sont préfixées par `#`.*

1. **`utilisateur`** (<u>`id_utilisateur`</u>, `nom`, `prenom`, `email`, `telephone`, `mot_de_passe_hash`, `role`, `statut_compte`, `motif_desactivation`, `date_inscription`)
2. **`categorie`** (<u>`id_categorie`</u>, `libelle`, `slug`, `description`, `icone`)
3. **`competence`** (<u>`id_competence`</u>, `libelle`, `#id_categorie`)
4. **`profil_professionnel`** (<u>`id_profil`</u>, `#id_utilisateur`, `#id_categorie`, `titre`, `description`, `zone`, `disponibilite`, `photo`, `statut_validation`, `date_soumission`, `date_validation`, `date_maj`)
5. **`profil_competence`** (<u>`#id_profil`</u>, <u>`#id_competence`</u>, `niveau_declare`)
6. **`projet_portfolio`** (<u>`id_projet`</u>, `#id_profil`, `titre`, `description`, `url_media`, `type_media`, `date_ajout`)
7. **`demande_service`** (<u>`id_demande`</u>, `#id_client`, `#id_profil`, `#id_categorie`, `titre`, `description`, `budget_indicatif`, `delai_souhaite`, `statut`, `date_creation`, `date_cloture`, `date_confirmation`)
8. **`conversation`** (<u>`id_conversation`</u>, `#id_demande`, `statut`, `date_creation`)
9. **`message`** (<u>`id_message`</u>, `#id_conversation`, `#id_expediteur`, `contenu`, `date_envoi`, `lu`)
10. **`avis`** (<u>`id_avis`</u>, `#id_demande`, `#id_client`, `note`, `commentaire`, `statut`, `motif_masquage`, `date_avis`)
11. **`validation_profil`** (<u>`id_validation`</u>, `#id_profil`, `#id_administrateur`, `decision`, `motif`, `date_decision`)
12. **`signalement`** (<u>`id_signalement`</u>, `#id_auteur`, `type_cible`, `id_cible`, `motif`, `description`, `statut`, `#id_traite_par`, `note_admin`, `date_creation`, `date_traitement`)
13. **`journal_moderation`** (<u>`id`</u>, `#id_admin`, `action`, `type_cible`, `id_cible`, `motif`, `date_action`)
14. **`tentative_connexion`** (<u>`id`</u>, `email`, `adresse_ip`, `succes`, `date_tentative`)

---

## 2. Définition Détaillée des Tables

### 2.1 Table `utilisateur`
Représente les comptes utilisateurs uniques (clients, professionnels et administrateurs).
* `id_utilisateur` : `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
* `nom` : `VARCHAR(100) NOT NULL`
* `prenom` : `VARCHAR(100) NOT NULL`
* `email` : `VARCHAR(190) NOT NULL UNIQUE`
* `telephone` : `VARCHAR(25) NULL`
* `mot_de_passe_hash` : `VARCHAR(255) NOT NULL` (empreinte BCRYPT / ARGON2, **RG08**)
* `role` : `ENUM('client', 'professionnel', 'admin') NOT NULL` (**RG09**)
* `statut_compte` : `ENUM('actif', 'desactive') NOT NULL DEFAULT 'actif'`
* `motif_desactivation` : `VARCHAR(255) NULL` (motif en cas de bannissement pour fraude)
* `date_inscription` : `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`

### 2.2 Table `categorie`
Définit les grands domaines d'activité du numérique.
* `id_categorie` : `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
* `libelle` : `VARCHAR(100) NOT NULL UNIQUE`
* `slug` : `VARCHAR(120) NOT NULL UNIQUE`
* `description` : `VARCHAR(255) NULL`
* `icone` : `VARCHAR(50) NULL`

### 2.3 Table `competence`
Technologies et savoir-faire rattachés optionnellement à une catégorie.
* `id_competence` : `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
* `libelle` : `VARCHAR(100) NOT NULL UNIQUE`
* `id_categorie` : `INT UNSIGNED NULL`
* *FK* `id_categorie` REFERENCES `categorie(id_categorie)`

### 2.4 Table `profil_professionnel`
Vitrine publique, métadonnées opérationnelles et état de vérification administrative du professionnel.
* `id_profil` : `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
* `id_utilisateur` : `INT UNSIGNED NOT NULL UNIQUE`
* `id_categorie` : `INT UNSIGNED NOT NULL`
* `titre` : `VARCHAR(120) NOT NULL`
* `description` : `TEXT NOT NULL`
* `zone` : `VARCHAR(120) NOT NULL` (commune ou zone d'intervention)
* `disponibilite` : `ENUM('disponible', 'limitee', 'indisponible') NOT NULL DEFAULT 'disponible'`
* `photo` : `VARCHAR(255) NULL`
* `statut_validation` : `ENUM('brouillon', 'soumis', 'en_verification', 'a_corriger', 'approuve', 'refuse', 'suspendu') NOT NULL DEFAULT 'brouillon'` (**RG01, RG05, RG06**)
* `date_soumission` : `DATETIME NULL`
* `date_validation` : `DATETIME NULL`
* `date_maj` : `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`
* *FK* `id_utilisateur` REFERENCES `utilisateur(id_utilisateur)`
* *FK* `id_categorie` REFERENCES `categorie(id_categorie)`
* *Index :* `(statut_validation, id_categorie)`

### 2.5 Table `profil_competence` (Résolution de la relation N:N)
Associe les professionnels aux compétences techniques déclarées.
* `id_profil` : `INT UNSIGNED NOT NULL`
* `id_competence` : `INT UNSIGNED NOT NULL`
* `niveau_declare` : `ENUM('debutant', 'intermediaire', 'avance', 'expert') NOT NULL DEFAULT 'intermediaire'`
* *PRIMARY KEY :* `(id_profil, id_competence)`
* *FK* `id_profil` REFERENCES `profil_professionnel(id_profil)`
* *FK* `id_competence` REFERENCES `competence(id_competence)`

### 2.6 Table `projet_portfolio`
Réalisations passées illustrant le savoir-faire du professionnel.
* `id_projet` : `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
* `id_profil` : `INT UNSIGNED NOT NULL`
* `titre` : `VARCHAR(150) NOT NULL`
* `description` : `TEXT NULL`
* `url_media` : `VARCHAR(500) NOT NULL`
* `type_media` : `ENUM('lien', 'image') NOT NULL DEFAULT 'image'`
* `date_ajout` : `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
* *FK* `id_profil` REFERENCES `profil_professionnel(id_profil)`

### 2.7 Table `demande_service`
Commandes de prestations initiées par les clients (**RG02, RG03, RG10, RG11, RG14**).
* `id_demande` : `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
* `id_client` : `INT UNSIGNED NOT NULL`
* `id_profil` : `INT UNSIGNED NOT NULL`
* `id_categorie` : `INT UNSIGNED NOT NULL`
* `titre` : `VARCHAR(150) NOT NULL`
* `description` : `TEXT NOT NULL`
* `budget_indicatif` : `INT UNSIGNED NULL` (en Francs CFA, **RG10 : aucun paiement simulé**)
* `delai_souhaite` : `DATE NULL`
* `statut` : `ENUM('envoyee', 'acceptee', 'refusee', 'en_cours', 'a_confirmer', 'terminee', 'annulee') NOT NULL DEFAULT 'envoyee'`
* `date_creation` : `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
* `date_cloture` : `DATETIME NULL`
* `date_confirmation` : `DATETIME NULL` (**RG14 : horodate la validation finale du client**)
* *FK* `id_client` REFERENCES `utilisateur(id_utilisateur)`
* *FK* `id_profil` REFERENCES `profil_professionnel(id_profil)`
* *FK* `id_categorie` REFERENCES `categorie(id_categorie)`
* *Index :* `(id_client)` et `(id_profil, statut)`

### 2.8 Table `conversation`
Canal de discussion privé lié à une demande de service (**RG04**).
* `id_conversation` : `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
* `id_demande` : `INT UNSIGNED NOT NULL UNIQUE`
* `statut` : `ENUM('active', 'lecture_seule') NOT NULL DEFAULT 'active'`
* `date_creation` : `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
* *FK* `id_demande` REFERENCES `demande_service(id_demande)`

### 2.9 Table `message`
Messages individuels échangés au sein d'une conversation (**RG13**).
* `id_message` : `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
* `id_conversation` : `INT UNSIGNED NOT NULL`
* `id_expediteur` : `INT UNSIGNED NOT NULL`
* `contenu` : `TEXT NOT NULL`
* `date_envoi` : `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
* `lu` : `TINYINT(1) NOT NULL DEFAULT 0`
* *FK* `id_conversation` REFERENCES `conversation(id_conversation)`
* *FK* `id_expediteur` REFERENCES `utilisateur(id_utilisateur)`
* *Index :* `(id_conversation, date_envoi)`

### 2.10 Table `avis`
Évaluations déposées après confirmation de la prestation (**RG07**).
* `id_avis` : `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
* `id_demande` : `INT UNSIGNED NOT NULL UNIQUE`
* `id_client` : `INT UNSIGNED NOT NULL`
* `note` : `TINYINT NOT NULL` (`CHECK (note BETWEEN 1 AND 5)`)
* `commentaire` : `TEXT NULL`
* `statut` : `ENUM('publie', 'masque') NOT NULL DEFAULT 'publie'`
* `motif_masquage` : `VARCHAR(255) NULL`
* `date_avis` : `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
* *FK* `id_demande` REFERENCES `demande_service(id_demande)`
* *FK* `id_client` REFERENCES `utilisateur(id_utilisateur)`

### 2.11 Table `validation_profil`
Historique d'instruction administrative des profils professionnels (**RG06, RG12**).
* `id_validation` : `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
* `id_profil` : `INT UNSIGNED NOT NULL`
* `id_administrateur` : `INT UNSIGNED NOT NULL`
* `decision` : `ENUM('approuve', 'refuse', 'a_corriger', 'suspendu') NOT NULL`
* `motif` : `TEXT NULL`
* `date_decision` : `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
* *FK* `id_profil` REFERENCES `profil_professionnel(id_profil)`
* *FK* `id_administrateur` REFERENCES `utilisateur(id_utilisateur)`

### 2.12 Table `signalement`
Dispositif de modération et d'alerte pour abus ou fraude.
* `id_signalement` : `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
* `id_auteur` : `INT UNSIGNED NOT NULL`
* `type_cible` : `ENUM('profil', 'portfolio', 'conversation', 'avis') NOT NULL`
* `id_cible` : `INT UNSIGNED NOT NULL`
* `motif` : `ENUM('fraude', 'usurpation', 'harcelement', 'contenu_interdit', 'autre') NOT NULL`
* `description` : `TEXT NULL`
* `statut` : `ENUM('nouveau', 'en_cours', 'traite', 'rejete') NOT NULL DEFAULT 'nouveau'`
* `id_traite_par` : `INT UNSIGNED NULL`
* `note_admin` : `VARCHAR(255) NULL`
* `date_creation` : `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
* `date_traitement` : `DATETIME NULL`
* *FK* `id_auteur` REFERENCES `utilisateur(id_utilisateur)`
* *FK* `id_traite_par` REFERENCES `utilisateur(id_utilisateur)`
* *Index :* `(statut)`

### 2.13 Table `journal_moderation`
Audit trail centralisé des décisions de modération (**RG12 : qui, quand, quoi, pourquoi**).
* `id` : `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
* `id_admin` : `INT UNSIGNED NOT NULL`
* `action` : `VARCHAR(60) NOT NULL`
* `type_cible` : `VARCHAR(30) NOT NULL`
* `id_cible` : `INT UNSIGNED NOT NULL`
* `motif` : `TEXT NULL`
* `date_action` : `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
* *FK* `id_admin` REFERENCES `utilisateur(id_utilisateur)`

### 2.14 Table `tentative_connexion`
Journal de sécurité anti-bruteforce (limitation du nombre de tentatives par IP et par compte).
* `id` : `INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
* `email` : `VARCHAR(190) NOT NULL`
* `adresse_ip` : `VARCHAR(45) NOT NULL`
* `succes` : `TINYINT(1) NOT NULL`
* `date_tentative` : `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
* *Index :* `(email, date_tentative)`

---

## 3. Matrice des Clés Étrangères et Politiques de Suppression

| Table source | Clé étrangère | Table cible | Règle `ON DELETE` | Justification métier |
| :--- | :--- | :--- | :--- | :--- |
| `competence` | `id_categorie` | `categorie(id_categorie)` | **`SET NULL`** | La suppression ou réorganisation d'une catégorie ne doit pas détruire les compétences existantes, mais les détacher temporairement. |
| `profil_professionnel` | `id_utilisateur` | `utilisateur(id_utilisateur)` | **`CASCADE`** | Un profil professionnel n'a aucun sens sans son compte d'authentification principal ; la suppression physique du compte entraîne celle du profil. |
| `profil_professionnel` | `id_categorie` | `categorie(id_categorie)` | **`RESTRICT`** | Interdiction de supprimer une catégorie si des professionnels y sont actuellement rattachés. |
| `profil_competence` | `id_profil` | `profil_professionnel(id_profil)` | **`CASCADE`** | Si un profil professionnel est supprimé, ses associations de compétences sont automatiquement purgées. |
| `profil_competence` | `id_competence` | `competence(id_competence)` | **`RESTRICT`** | Une compétence déclarée sur des profils professionnels ne peut pas être supprimée pour éviter d'altérer les profils en cours. |
| `projet_portfolio` | `id_profil` | `profil_professionnel(id_profil)` | **`CASCADE`** | Les réalisations d'un portfolio appartiennent au professionnel ; elles disparaissent avec son profil. |
| `demande_service` | `id_client` | `utilisateur(id_utilisateur)` | **`RESTRICT`** | Protection légale et contractuelle : un compte client ayant émis des demandes de service ne peut être supprimé en cascade pour préserver l'historique des travaux. |
| `demande_service` | `id_profil` | `profil_professionnel(id_profil)` | **`RESTRICT`** | L'historique d'une prestation réalisée ou en cours ne doit pas être effacé si un profil pro venait à être ciblé par une suppression. |
| `demande_service` | `id_categorie` | `categorie(id_categorie)` | **`RESTRICT`** | Empêche la suppression d'une catégorie si des demandes de services y sont encore historisées. |
| `conversation` | `id_demande` | `demande_service(id_demande)` | **`CASCADE`** | Le canal de chat est indissociable de la demande qui l'a engendré ; supprimer la demande supprime le fil. |
| `message` | `id_conversation` | `conversation(id_conversation)` | **`CASCADE`** | La suppression d'un fil de discussion entraîne obligatoirement le nettoyage de tous ses messages. |
| `message` | `id_expediteur` | `utilisateur(id_utilisateur)` | **`RESTRICT`** | Préserve l'attribution et l'intégrité probatoire des échanges textuels au sein d'une conversation. |
| `avis` | `id_demande` | `demande_service(id_demande)` | **`RESTRICT`** | Un avis certifié doit toujours être adossé à une demande de service réelle et terminée. |
| `avis` | `id_client` | `utilisateur(id_utilisateur)` | **`RESTRICT`** | Empêche la perte de l'auteur d'une évaluation déposée sur la plateforme. |
| `validation_profil` | `id_profil` | `profil_professionnel(id_profil)` | **`CASCADE`** | Les pièces et décisions d'instruction sont purgées si le profil professionnel disparaît. |
| `validation_profil` | `id_administrateur` | `utilisateur(id_utilisateur)` | **`RESTRICT`** | L'identité de l'administrateur ayant statué sur un dossier est conservée pour l'auditabilité (**RG12**). |
| `signalement` | `id_auteur` | `utilisateur(id_utilisateur)` | **`RESTRICT`** | L'auteur d'un signalement doit être conservé pour éviter les faux signalements anonymes et permettre le suivi. |
| `signalement` | `id_traite_par` | `utilisateur(id_utilisateur)` | **`SET NULL`** | Si le compte d'un administrateur traitant est supprimé, le signalement reste archivé avec ses notes sans bloquer. |
| `journal_moderation` | `id_admin` | `utilisateur(id_utilisateur)` | **`RESTRICT`** | Registre d'audit légal immuable (**RG12**) : aucune ligne de journalisation ne peut perdre son auteur. |

---

## 4. Écarts et Enrichissements par rapport au Cahier des Charges Initial

1. **Unification des comptes (`CLIENT` comme rôle d'utilisateur) :**
   * *Au cahier des charges :* Il était parfois évoqué deux parcours d'inscription séparés.
   * *Dans le MLD :* Une seule table `utilisateur` unifiée porte le champ `role ENUM('client', 'professionnel', 'admin')`, garantissant une connexion centralisée et un modèle de session robuste sans duplication de tables d'authentification (**RG09**).
2. **Double jalonnement de fin de prestation (`date_confirmation` & `date_cloture`) :**
   * *Au cahier des charges :* La demande passait simplement à `'terminee'`.
   * *Dans le MLD :* Conformément à **RG14**, l'ajout de `date_confirmation` matérialise l'accord exprès du client après marquage `a_confirmer` par le professionnel, condition *sine qua non* pour l'évaluation (**RG07**).
3. **Table d'audit dédiée `journal_moderation` (Option B retenue) :**
   * *Au cahier des charges :* La traçabilité n'était qu'un énoncé textuel (**RG12**).
   * *Dans le MLD :* Une table dédiée et immuable consigne chaque action d'administration avec son auteur, son horodatage et son motif obligatoire.
4. **Table de sécurité anti-bruteforce `tentative_connexion` :**
   * *Au cahier des charges :* Fonctionnalité non spécifiée explicitement au niveau base de données.
   * *Dans le MLD :* Ajout stratégique pour sécuriser l'authentification sans framework, permettant de limiter les tentatives à 5 échecs par tranche de 15 minutes.
5. **Résolution rigoureuse N:N pour les compétences :**
   * Utilisation de la table d'association `profil_competence` avec attribut de niveau de maîtrise déclaré (`debutant` à `expert`).
