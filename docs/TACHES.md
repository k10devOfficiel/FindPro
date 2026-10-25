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
- [x] Ajout du statut de vérification (`en_attente`, `approuve`, `rejete`, `a_corriger`, `suspendu`) sur le profil professionnel (Jour 4)
- [ ] Upload des pièces justificatives / documents d'identité par le professionnel (Jour 4)
- [x] Interface d'examen des demandes de validation dans l'espace administration (`admin/validation.php`, `admin/profil_detail.php`) (Jour 4)
- [x] Actions d'approbation et de rejet avec motif notifié au professionnel (`pro/index.php`, `pro/profil.php`) (Jour 4)
- [x] Validation des tests T05 (correction -> resoumission -> approbation) et T06 (soumission -> refus avec motif -> consultation pro)
- [x] Traçabilité intégrale de toutes les décisions dans `validation_profil` (RG12)

### 5. Consultation Publique
- [ ] Règle obligatoire (RG05) : seul le statut `approuve` (avec compte utilisateur actif) est visible dans toute requête publique (`profils_publics()` dans `includes/functions.php`, `includes/carte_profil.php`) — *à vérifier dès l'étape 8*
- [ ] Page de profil public accessible aux visiteurs et clients (`/pro/{id}`) (Jour 4)
- [x] Affichage de la carte d'identité pro : badge vérifié ("Profil approuvé par FindPro"), badge de disponibilité coloré, note moyenne et avis, métiers exercés, zone couverte (`includes/carte_profil.php`) (Jour 4)
- [ ] Galerie interactive (lightbox) du portfolio de réalisations (Jour 4)
- [ ] Bouton d'action proéminent "Demander un service / Contacter" (Jour 4)

### 6. Recherche et Filtres Simples
- [ ] Moteur de recherche d'accueil avec redirection vers la page `/recherche` (Jour 5)
- [ ] Filtres de recherche fonctionnels : par catégorie / métier (Jour 5)
- [ ] Filtres par zone géographique / commune d'Abidjan (Jour 5)
- [ ] Pagination des résultats et tri (les profils vérifiés et les mieux notés en premier) (Jour 5)
- [ ] Correction des requêtes SQL d'agrégation et sécurisation anti-injection des clauses `LIMIT` / `OFFSET` (Jour 5)

### 7. Demande de Service
- [x] Modélisation de la table `demande_service` (id_client, id_profil, id_categorie, titre, description, commune, delai_souhaite, budget_indicatif, statut) (Jour 6)
- [x] Formulaire de demande de service initié depuis le profil public du pro (`request_new.php`) avec protection CSRF, anti-spam (3 en attente par pro, 10 par 24h) et rollback atomique (Jour 6)
- [x] Tableau de bord client : suivi des demandes émises avec statuts clairs (`envoyee`, `acceptee`, `refusee`, `en_cours`, `a_confirmer`, `terminee`, `annulee`) dans `client/demandes.php` et `demande_detail.php` (Jour 6)
- [x] Tableau de bord pro : réception des demandes avec options pour accepter ou refuser dans `pro/demandes.php` et `demande_detail.php` (Jour 6)
- [x] Mise à jour du cycle de vie de la prestation (Démarrer les travaux -> Marquer comme terminé -> Confirmation client) avec dates de clôture (Jour 6)
- [x] Validation intégrale du test T07 et cycle de vie consigné dans `docs/annexe_suivi_tests.md` (Jour 6)

### 8. Chat après Demande
- [x] Déclenchement automatique d'une conversation liée dès qu'une demande de service est créée (`request_new.php` / transaction atomique) (Jour 7)
- [x] Modélisation et tables `conversation` et `message` avec horodatage et indicateur de lecture (Jour 7)
- [x] Interface de messagerie complète dans `chat/conversation.php?id=[id]` avec pagination des 50 derniers messages (Jour 7)
- [x] Envoi de messages sécurisé avec jeton CSRF, validation de contenu, assainissement XSS et retours à la ligne conservés (Jour 7)
- [x] Endpoint API d'envoi JSON `chat/send.php` avec support `X-CSRF-Token`, anti-spam client RG13 (3 messages max avant acceptation) et rate limit (20 msg/min) (Jour 7)
- [x] Système de rafraîchissement automatique des nouveaux messages (polling AJAX via poll.php toutes les 5s, suspension document.hidden, envoi fetch vers send.php sans rechargement, rendu DOM sécurisé textContent anti-XSS) (Jour 7)

### 9. Administration de Base
- [ ] Tableau de bord d'administration avec indicateurs clés (nombre d'inscrits, pros vérifiés, demandes actives) (Jour 8)
- [ ] Gestion des utilisateurs : liste, recherche, activation / suspension de comptes (Jour 8)
- [ ] Gestion des catégories de métiers : listing, ajout (correction de la route 404), modification et activation (Jour 8)
- [ ] Vue globale des demandes de services avec filtre de statut pour supervision (Jour 8)

### 10. Sécurité et Tests
- [ ] Harmonisation complète du schéma SQL unique et suppression des requêtes orphelines (Jour 9)
- [ ] Élimination des identifiants en clair dans la documentation et sécurisation du fichier `.env` (Jour 9)
- [x] Remplacement de toutes les concaténations SQL brutes par des paramètres liés PDO préparés (Jour 9)
- [x] Audit XSS sur toutes les vues d'administration et d'espaces privés (`Security::e()`) (Jour 9)
- [x] Vérification du blocage strict d'exécution PHP dans le répertoire des téléversements (`uploads/.htaccess`) (Jour 9)
- [x] Contrôle d'accès exhaustif (Annexe C : 14 URLs x 5 rôles) et rejet strict des formulaires sans jeton CSRF (403) (Jour 9)
- [x] Tests fonctionnels complets des parcours d'inscription, connexion, recherche, demande et chat (Jour 9)

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

---

## Audit Recette Mobile (360px) & Cas Limites

### 1. Parcours Utilisateur Mobile (360px)
- **Inscription (`register.php`)** : formulaire compact, champs empilés, pas de débordement horizontal (`scrollWidth <= innerWidth`).
- **Recherche & Filtres (`search.php`)** : filtres et listes de cartes pro en colonne unique fluide.
- **Fiche Profil (`profile.php`)** : avatar responsive, badges flexibles, portfolio et avis adaptés au viewport 360px.
- **Demande de service (`request_new.php`)** : carte de rappel du professionnel compacte, saisie adaptée au clavier tactile.
- **Messagerie / Chat (`chat/conversation.php`)** : bulles de messages adaptées à l'écran, polling actif, pas d'écrasement.
- **Avis (`profile.php#avis`)** : étoiles de notation et commentaires cadrés sans déformation.
- **Tableaux de bord (`client/demandes.php`, `pro/demandes.php`)** : cartes d'opportunités et boutons d'actions tactiles pleine largeur.

### 2. Test Smartphone sur Réseau Local
- URL locale vérifiée : `http://192.168.100.12/findpro/`.
- La configuration `.htaccess` autorise l'accès local (`Require all granted`) tout en verrouillant strictement `/config/`, `/sql/`, `/logs/`, `/includes/`, etc.
- Fonction `url()` dynamique respectant l'en-tête `Host` du terminal mobile.

### 3. Matrice des Cas Limites Testés
| Cas limite | Données de test | Résultat observé | Statut |
| :--- | :--- | :--- | :--- |
| **Nom avec accent ou apostrophe** | `O'Brien`, `Koné`, `D'Almeida`, `Kouamé` | Échappement anti-XSS `htmlspecialchars(ENT_QUOTES, 'UTF-8')` respecté, accents et apostrophes affichés sans régression | **Conforme** |
| **Chaîne continue sans espaces** | 200 caractères `AAA...` dans titre/description | Risque de débordement horizontal hors conteneur sur petit écran | **Corrigé** (`text-break`) |
| **Double clic rapide sur Envoyer** | 2 clics successifs < 300ms sur bouton Submit | Risque de double POST / conflit jeton CSRF | **Corrigé** (`main.js` anti-double submit) |
| **Retour arrière navigateur** | Bouton "Précédent" après soumission | Pattern PRG (Post-Redirect-Get) actif partout (`redirect()`), pas d'alerte de re-soumission | **Conforme** |
| **Session expirée en plein formulaire** | Formulaire envoyé avec session détruite | Détection CSRF avec HTTP 403 explicite et lien direct de retour | **Conforme** |
| **Profil sans photo** | Compte sans photo de profil (`photo = NULL`) | Bascule fluide sur `avatar-defaut.svg` et bloc réservé neutre | **Conforme** |
| **Professionnel sans projet** | Profil sans réalisations dans `projet_portfolio` | Message informatif clair « Aucune réalisation enregistrée... » | **Conforme** |
| **Demande sans budget** | `budget_indicatif = NULL` | Affichage harmonisé « Non précisé » sur toutes les vues | **Corrigé** |

### 4. Registre des Défauts UI Identifiés et Résolus

| Réf | Défaut d'interface | Gravité | Cause | Modification minimale |
| :--- | :--- | :--- | :--- | :--- |
| **DEF-UI-01** | Risque de double soumission sur clic/tap rapide | **Moyenne** | Bouton `submit` non désactivé immédiatement au clic | Création de `assets/js/main.js` inclus dans `includes/footer.php` : désactive le bouton `submit` et affiche un spinner de chargement dès l'envoi valide |
| **DEF-UI-02** | Débordement horizontal sur texte long sans espace | **Faible** | Absence de règle de rupture de mot (`text-break`) sur les conteneurs dynamiques | Ajout de la classe Bootstrap `text-break` sur titres et descriptions dans `profile.php`, `demande_detail.php`, `client/demandes.php`, `pro/demandes.php` |
| **DEF-UI-03** | Affichage incomplet du budget indicatif nul | **Faible** | Absence de bloc `else` lorsque `budget_indicatif === null` dans les listes de demandes | Ajout du texte de repli `<span class="text-muted"><i class="bi bi-cash me-1 text-muted"></i>Non précisé</span>` dans `client/demandes.php` et `pro/demandes.php` |
| **DEF-UI-04** | Bouton d'action étroit sur mobile 360px | **Faible** | Bouton d'action aligné à droite sans dimension responsive | Ajout des classes `w-100 w-md-auto` sur les conteneurs et liens d'action dans `client/demandes.php` et `pro/demandes.php` pour faciliter la cible tactile |

---

## Suivi Juridique & Pages Légales

### 1. Pages Créées & Reliées
- **Mentions légales (`mentions.php`)** : identité de l'éditeur, coordonnées complètes, hébergement sécurisé, propriété intellectuelle et mise en garde juridique.
- **Politique de confidentialité (`confidentialite.php`)** : détail transparent des données collectées (identité, contact, demandes, messages, compétences pro), finalités, destinataires, durées de conservation (3 ans inactif / 3 ans après clôture prestation / 1 an logs) et modalités d'exercice des droits (accès, rectification, suppression).
- **Règles d'utilisation (`regles.php`)** : courtoisie, prohibition stricte de toute activité illégale, **encadrement strict des prestations de cybersécurité (autorisation écrite préalable obligatoire du propriétaire du système)**, interdiction formelle des faux avis, protection des identifiants et sanctions applicables (avertissement, suspension, radiation).
- **Maillage & Accessibilité** : reliées depuis le pied de page (`includes/footer.php`) et depuis le formulaire d'inscription (`register.php`).

### 2. Actions à faire valider par un Juriste Spécialisé (Avant lancement commercial)
- [ ] Confirmation de la forme sociale définitive et immatriculation au RCCM d'Abidjan (remplacement du numéro temporaire).
- [ ] Déclaration préalable de conformité du traitement des données personnelles auprès de l'**ARTCI** (Autorité de Régulation des Télécommunications/TIC de Côte d'Ivoire - loi n° 2013-450).
- [ ] Validation de l'opposabilité contractuelle des CGU et des clauses exonératoires de responsabilité de FindPro en tant qu'intermédiaire technique (droit OHADA et commerce électronique ivoirien).
- [ ] Revue du mandat écrit type obligatoire pour les prestations de tests d'intrusion et audits de cybersécurité.

---

## Campagne d'Audit Annexe C (25/10/2026) — Contrôle d'Accès & Sécurité

### 1. Protocole Réalisé
- Réinitialisation complète de la base de données : `sql/schema.sql` exécuté, `sql/seed.sql` injecté, administrateur créé (`k10developpeur@gmail.com`), données de test générées (`scripts/donnees_test.php`).
- Exécution des 14 tests de l'Annexe C via 5 sessions isolées (Visiteur, Client auteur, Professionnel assigné, Administrateur, Tiers).
- Validation du rejet HTTP 403 systématique sur soumissions POST sans jeton CSRF valide.

### 2. Classification des Bugs par Gravité

| Niveau de Gravité | Définition | Nombre de bugs détectés | Liste & Observations |
| :--- | :--- | :---: | :--- |
| **Bloquant** | Empêche la démonstration ou compromet directement l'intégrité du système | **0** | Aucun bug bloquant. Toutes les fonctions maîtresses opèrent normalement. |
| **Important** | Anomalie fonctionnelle notable sans rupture du service critique | **0** | Aucun bug important. Contrôle d'accès et isolation validés à 100%. |
| **Mineur** | Imperfection cosmétique ou ergonomique sans impact de sécurité | **0** | Aucun bug mineur constaté sur les routes et flux audités. |

> **Bilan global :** 14/14 tests validés avec succès (**OK**). L'annexe C est entièrement remplie et consignée dans `docs/annexe_suivi_tests.md`.

