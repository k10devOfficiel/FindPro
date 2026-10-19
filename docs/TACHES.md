# Feuille de Route & Suivi des Tâches — FindPro

> **Légende des priorités :**
> - **P0 (MVP - Indispensable) :** Cœur fonctionnel pour lancer la plateforme FindPro.
> - **P1 (Important) :** Consolidation de l'expérience utilisateur, confiance et engagement.
> - **P2 (Évolutions futures) :** Monétisation, fonctionnalités avancées et mise à l'échelle.
>
> **Règle de gouvernance de la base de données :**
> - Le schéma ne change que par modification de `sql/schema.sql`, après décision écrite.

---

## P0 — Cœur Fonctionnel (MVP)

### 1. Authentification et Rôles
- [ ] Modélisation de la table `users` unifiée avec rôles (`client`, `pro`, `admin`) et statut actif (Jour 1)
- [ ] Système d'inscription dédié Client (nom, prénom, email, téléphone, commune, mot de passe) (Jour 1)
- [ ] Système d'inscription dédié Professionnel (informations de base, corps de métier, zone d'intervention) (Jour 1)
- [ ] Système de connexion sécurisé unifié avec détection automatique du rôle et redirection adaptée (Jour 1)
- [ ] Déconnexion sécurisée et destruction de session (Jour 1)
- [ ] Mise en place des middlewares de contrôle d'accès strict par rôle (`ClientMiddleware`, `ProMiddleware`, `AdminMiddleware`) (Jour 2)

### 2. Profils Professionnels
- [ ] Création du modèle et de la table `pro_profiles` (ou `professionnels`) liée à l'utilisateur (Jour 2)
- [ ] Formulaire d'édition du profil pro (biographie, années d'expérience, coordonnées professionnelles, commune de base) (Jour 2)
- [ ] Téléversement sécurisé de la photo de profil avec validation MIME stricte côté serveur et redimensionnement (Jour 2)
- [ ] Gestion des coordonnées de contact et informations de localisation (Jour 2)

### 3. Compétences et Portfolio
- [ ] Modélisation des tables `categories` (métiers) et `pro_categories` (liaison multi-métiers) (Jour 3)
- [ ] Sélection et association des compétences / métiers par le professionnel dans son espace (Jour 3)
- [ ] Modélisation de la table `portfolio_photos` (réalisations passées des professionnels) (Jour 3)
- [ ] Interface d'upload de photos de chantiers / réalisations avec légende et sélection de photo de couverture (Jour 3)
- [ ] Suppression et gestion de l'ordre d'affichage des photos de réalisations (Jour 3)

### 4. Validation Manuelle
- [ ] Ajout du statut de vérification (`en_attente`, `approuve`, `rejete`) sur le profil professionnel (Jour 4)
- [ ] Upload des pièces justificatives / documents d'identité par le professionnel (Jour 4)
- [ ] Interface d'examen des demandes de validation dans l'espace administration (Jour 4)
- [ ] Actions d'approbation et de rejet avec motif notifié au professionnel (Jour 4)

### 5. Consultation Publique
- [ ] Page de profil public accessible aux visiteurs et clients (`/pro/{id}`) (Jour 4)
- [ ] Affichage de la carte d'identité pro : badge vérifié, note moyenne, métiers exercés, zone couverte (Jour 4)
- [ ] Galerie interactive (lightbox) du portfolio de réalisations (Jour 4)
- [ ] Bouton d'action proéminent "Demander un service / Contacter" (Jour 4)

### 6. Recherche et Filtres Simples
- [ ] Moteur de recherche d'accueil avec redirection vers la page `/recherche` (Jour 5)
- [ ] Filtres de recherche fonctionnels : par catégorie / métier (Jour 5)
- [ ] Filtres par zone géographique / commune d'Abidjan (Jour 5)
- [ ] Pagination des résultats et tri (les profils vérifiés et les mieux notés en premier) (Jour 5)
- [ ] Correction des requêtes SQL d'agrégation et sécurisation anti-injection des clauses `LIMIT` / `OFFSET` (Jour 5)

### 7. Demande de Service
- [ ] Modélisation de la table `service_requests` (client_id, pro_id, category_id, titre, description, commune, date_souhaitee, budget, statut) (Jour 6)
- [ ] Formulaire de demande de service initié depuis le profil public du pro (Jour 6)
- [ ] Tableau de bord client : suivi des demandes émises avec statuts clairs (`en_attente`, `acceptee`, `refusee`, `en_cours`, `terminee`, `annulee`) (Jour 6)
- [ ] Tableau de bord pro : réception des demandes avec options pour accepter ou refuser (Jour 6)
- [ ] Mise à jour du cycle de vie de la prestation (Démarrer les travaux -> Marquer comme terminé) (Jour 6)

### 8. Chat après Demande
- [ ] Déclenchement automatique d'une conversation liée dès qu'une demande de service est créée ou acceptée (Jour 7)
- [ ] Modélisation et tables `conversations` et `messages` avec horodatage et indicateur de lecture (Jour 7)
- [ ] Interface de messagerie intégrée au tableau de bord (Jour 7)
- [ ] Envoi de messages sécurisé avec jeton CSRF et assainissement XSS (Jour 7)
- [ ] Système de rafraîchissement automatique des nouveaux messages (polling AJAX) (Jour 7)

### 9. Administration de Base
- [ ] Tableau de bord d'administration avec indicateurs clés (nombre d'inscrits, pros vérifiés, demandes actives) (Jour 8)
- [ ] Gestion des utilisateurs : liste, recherche, activation / suspension de comptes (Jour 8)
- [ ] Gestion des catégories de métiers : listing, ajout (correction de la route 404), modification et activation (Jour 8)
- [ ] Vue globale des demandes de services avec filtre de statut pour supervision (Jour 8)

### 10. Sécurité et Tests
- [ ] Harmonisation complète du schéma SQL unique et suppression des requêtes orphelines (Jour 9)
- [ ] Élimination des identifiants en clair dans la documentation et sécurisation du fichier `.env` (Jour 9)
- [ ] Remplacement de toutes les concaténations SQL brutes par des paramètres liés PDO préparés (Jour 9)
- [ ] Audit XSS sur toutes les vues d'administration et d'espaces privés (`Security::e()`) (Jour 9)
- [ ] Vérification du blocage strict d'exécution PHP dans le répertoire des téléversements (`uploads/.htaccess`) (Jour 9)
- [ ] Tests fonctionnels complets des parcours d'inscription, connexion, recherche, demande et chat (Jour 9)

---

## P1 — Engagement, Confiance et Outils

### 1. Avis après Prestation
- [ ] Autorisation de dépôt d'avis débloquée uniquement une fois la prestation marquée comme `terminee` (Jour 10)
- [ ] Formulaire de notation par étoiles (1 à 5) et commentaire textuel du client (Jour 10)
- [ ] Recalcul automatique de la note moyenne et du compteur d'avis du professionnel (Jour 10)
- [ ] Affichage des avis clients sur le profil public avec date et prénom du client (Jour 10)

### 2. Signalements
- [ ] Modélisation de la table `reports` (signaleur_id, cible_type, cible_id, motif, description, statut) (Jour 11)
- [ ] Bouton "Signaler ce profil" sur la fiche publique et "Signaler un abus" dans le chat (Jour 11)
- [ ] File de modération des signalements dans le panneau d'administration (Jour 11)
- [ ] Actions de modération admin : avertissement, suspension temporaire ou bannissement (Jour 11)

### 3. Disponibilité
- [ ] Commutateur d'état "Disponible immédiatement" / "Occupé" dans l'espace pro (Jour 12)
- [ ] Affichage d'un badge visuel de disponibilité en temps réel sur la fiche et dans la recherche (Jour 12)
- [ ] Filtre de recherche exclusif "Afficher uniquement les professionnels disponibles" (Jour 12)

### 4. Favoris
- [ ] Table `favorites` reliant les clients aux professionnels enregistrés (Jour 12)
- [ ] Bouton d'ajout / retrait des favoris en un clic (AJAX) depuis les cartes et fiches pros (Jour 12)
- [ ] Page dédiée "Mes professionnels favoris" dans l'espace client (Jour 12)

### 5. Notifications E-mail
- [ ] Intégration d'un service d'envoi d'e-mails transactionnels (SMTP / PHPMailer) (Jour 13)
- [ ] E-mail de confirmation d'inscription et de réinitialisation réelle de mot de passe (Jour 13)
- [ ] Notification e-mail au pro lors de la réception d'une nouvelle demande (Jour 13)
- [ ] Notification e-mail au client lors de l'acceptation ou du changement de statut de sa demande (Jour 13)

### 6. Statistiques Simples
- [ ] Tableau de bord pro : vues du profil public sur les 30 derniers jours (Jour 14)
- [ ] Indicateur du taux d'acceptation et délai moyen de réponse du pro (Jour 14)
- [ ] Tableau de bord client : total dépensé estimé et historique des prestations menées à terme (Jour 14)

---

## P2 — Monétisation et Évolutions Avancées

- [ ] Intégration d'une passerelle de paiement Mobile Money (Wave, Orange Money, MTN MoMo, Moov) (Jour 15+)
- [ ] Prélèvement de commissions automatiques ou système d'entiercement (escrow) sur les prestations (Jour 15+)
- [ ] Formules d'abonnement mensuel / annuel pour les professionnels (Jour 15+)
- [ ] Options payantes de mise en avant (badges "Recommandé / Sponsorisé", affichage prioritaire en tête de recherche) (Jour 15+)
- [ ] Générateur de devis formels et de factures téléchargeables en PDF (Jour 15+)
- [ ] Moteur de recommandations intelligentes basé sur la géolocalisation précise et l'historique de recherche (Jour 15+)
- [ ] Module d'appels vidéo intégrés pour pré-diagnostic ou devis à distance (WebRTC) (Jour 15+)
- [ ] Application mobile dédiée (Android / iOS via React Native ou PWA avancée) (Jour 15+)

---

## Blocages

*Aucun blocage actuellement.*

---

## Décisions à prendre

1. **Délai de réponse à une demande :**
   - Quel délai maximal accorder à un professionnel pour accepter ou refuser une demande de service avant expiration automatique (ex. : 24 heures, 48 heures) ?
   - Quel comportement déclencher à l'expiration : annulation pure et simple, notification de relance, ou réaffectation suggérée à d'autres professionnels de la même commune ?

2. **Règle de nouvelle vérification après modification d'un profil approuvé :**
   - Si un professionnel modifie ses informations critiques (identité, commune principale, pièces justificatives, corps de métier principal), son badge de vérification doit-il être automatiquement révoqué pour repasser en validation manuelle par l'administrateur ?
   - Les modifications mineures (photos du portfolio, biographie, ajustement des tarifs indicatifs) doivent-elles être appliquées instantanément sans revalidation ?

3. **Durée avant clôture manuelle d'une demande à confirmer :**
   - Lorsqu'un professionnel marque une prestation comme "terminée", quel délai accorder au client pour confirmer la clôture ou signaler un litige (ex. : 72 heures, 5 jours) ?
   - Passé ce délai sans action du client, la demande doit-elle être clôturée automatiquement par le système avec autorisation de laisser un avis ?
