# Annexe de Suivi des Tests — FindPro

Ce document consigne l'historique et les procès-verbaux d'exécution des campagnes de tests fonctionnels et d'intégration de la plateforme **FindPro**.

---

## Synthèse des Campagnes de Tests

| Identifiant | Périmètre du test | Script / Protocole | Date d'exécution | Statut |
| :--- | :--- | :--- | :--- | :---: |
| **T01** | Inscription & connexion par rôle (`client`, `professionnel`) | `scratch/test_auth_flow.php` | 24/10/2026 | **PASS** |
| **T02** | Contrôle d'accès strict et redirections (403 / rôles) | `scratch/test_dashboards_access.php` | 24/10/2026 | **PASS** |
| **T03** | Téléversement sécurisé de photo & validation MIME | `scratch/test_upload_security.php` | 24/10/2026 | **PASS** |
| **T04** | Gestion des compétences & réalisations portfolio | `scratch/test_portfolio_full.php` | 24/10/2026 | **PASS** |
| **T05** | Workflow d'arbitrage admin : Demande de correction -> Resoumission pro -> Approbation | `scratch/test_t05_t06_and_rg05.php` | 24/10/2026 | **PASS** |
| **T06** | Workflow d'arbitrage admin : Soumission pro -> Rejet avec motif -> Notification pro | `scratch/test_t05_t06_and_rg05.php` | 24/10/2026 | **PASS** |
| **T07** | **Création, liaison stricte client/pro et cycle de vie complet de la demande** | `scratch/test_t07_and_request_new.php` & `scratch/execute_e2e_lifecycle_t07.php` | 24/10/2026 | **PASS** |
| **T08** | **Accès strict, isolation et messagerie interactive (`chat/conversation.php`)** | `scratch/test_chat_conversation_t08.php` | 24/10/2026 | **PASS** |
| **T13** | **Envoi de message API JSON (`chat/send.php`) & Règle anti-spam RG13** | `scratch/test_chat_send_t13.php` | 24/10/2026 | **PASS** |
| **T14** | **Polling AJAX (`chat/poll.php`), Page Visibility & Immunité XSS `textContent`** | `scratch/test_chat_poll.php` & `assets/js/chat.js` | 24/10/2026 | **PASS** |
| **T15** | **Fuzzing sécurité : XSS, SQLi, Buffer 10 000 car. & Uploads malveillants** | `scratch/test_fuzzing_security.php` | 24/10/2026 | **PASS** |
| **Annexe C** | **Matrice exhaustive de contrôle d'accès (5 rôles / 14 URLs) & Rejet CSRF** | `scratch/execute_annexe_c_tests.php` | 24/10/2026 | **PASS** |

---

## Fiche Détaillée du Test T07

### 1. Objectifs du Test
1. **Liaison stricte (RG02, RG03) :** Vérifier qu'une demande émise par un client est associée avec intégrité à son compte (`id_client`) et au bon professionnel approuvé (`id_profil`).
2. **Transaction atomique & Rollback :** Garantir que l'insertion de la demande, la création de la conversation liée et l'insertion du premier message se font dans une même transaction SQL. En cas d'erreur intermédiaire, aucun enregistrement orphelin n'est créé.
3. **Protection Anti-Spam :**
   - Refus strict d'une 4e demande en attente vers un même professionnel.
   - Refus strict au-delà de 10 demandes en 24 heures pour un même client.
4. **Cycle de vie complet et matrice de transition (RG14) :**
   - Étape 1 : Le client émet la demande (`envoyee`).
   - Étape 2 : Le professionnel accepte (`acceptee`).
   - Étape 3 : Le professionnel démarre les travaux (`en_cours`).
   - Étape 4 : Le professionnel marque les travaux terminés (`a_confirmer`).
   - Étape 5 : Le client confirme la prestation (`terminee`).
   - Étape 6 : Clôture avec enregistrement exact de `date_confirmation` et `date_cloture`.
5. **Permissions & Sécurité :**
   - Impossibilité pour un client d'usurper l'action du professionnel pour accepter sa propre demande.
   - Impossibilité de transitions illégitimes directes (ex: `envoyee` -> `terminee`).

---

### 2. Environnement de Test
- **Plateforme :** FindPro MVP (PHP 8.2 WAMP / MySQL 8.0).
- **Client de test :** `client@findpro.ci` (`id_utilisateur` = 3, rôle `client`).
- **Professionnel approuvé :** `pro@findpro.ci` (`id_utilisateur` = 2, `id_profil` = 65, rôle `professionnel`, statut `approuve`).
- **Outil d'exécution :** Double session HTTP isolée (`CURL` avec gestion de cookies distincts et jetons CSRF dynamiques) + Interface Web interactive.

---

### 3. Matrice d'Exécution & Résultats

| N° | Étape / Cas de test | Action réalisée | Résultat attendu | Résultat obtenu | Statut |
| :---: | :--- | :--- | :--- | :--- | :---: |
| 1 | Authentification Client | POST `/login.php` avec identifiants client | Code 302, redirection `client/index.php`, session initialisée | HTTP 302 vers `client/index.php` | **PASS** |
| 2 | Authentification Pro | POST `/login.php` dans une session isolée | Code 302, redirection `pro/index.php`, session initialisée | HTTP 302 vers `pro/index.php` | **PASS** |
| 3 | Émission de la demande | POST `/request_new.php?profil=65` | Code 302 vers `demande_detail.php?id=...`, enregistrement en base | HTTP 302 vers `demande_detail.php?id=92` | **PASS** |
| 4 | Intégrité T07 en base | SELECT `demande_service` | `id_client=3`, `id_profil=65`, `statut='envoyee'`, conversation créée | `id_client=3`, `id_profil=65`, `statut='envoyee'`, conversation active #28 | **PASS** |
| 5 | Anti-spam (même pro) | Tentative d'envoi d'une 4e demande 'envoyee' | Formulaire réaffiché avec message d'erreur clair, rejet en base | Erreur *"Vous avez déjà 3 demandes en attente auprès de ce professionnel"*, 3 en base | **PASS** |
| 6 | Anti-spam (24h) | Tentative de dépassement de 10 demandes / 24h | Blocage avec message d'erreur, 0 insertion | Erreur *"Limite quotidienne atteinte (10 demandes par 24h)"* | **PASS** |
| 7 | Rollback atomique | Déclenchement d'une exception après insertion demande | Annulation intégrale, 0 demande orpheline, 0 conversation orpheline | Rollback confirmé : 0 ligne orpheline en base | **PASS** |
| 8 | Transition : Accepter | Pro clique sur *« Accepter la demande »* | POST statut `acceptee`, redirection 302 | Statut mis à jour à `acceptee` en base | **PASS** |
| 9 | Transition : En cours | Pro clique sur *« Démarrer la prestation »* | POST statut `en_cours`, redirection 302 | Statut mis à jour à `en_cours` en base | **PASS** |
| 10 | Transition : À confirmer | Pro clique sur *« Marquer les travaux comme terminés »* | POST statut `a_confirmer`, redirection 302 | Statut mis à jour à `a_confirmer` en base | **PASS** |
| 11 | Transition : Confirmer | Client clique sur *« Confirmer la fin de la prestation »* | POST statut `terminee`, renseignement dates | Statut mis à jour à `terminee` en base | **PASS** |
| 12 | Vérification Clôture | Contrôle des horodatages | `date_confirmation` non vide, `date_cloture` non vide, identiques | `date_confirmation` = `date_cloture` (horodatage serveur exact) | **PASS** |
| 13 | Protection anti-usurpation | Client tente d'accepter sa propre demande via POST direct | Rejet serveur, message d'erreur, statut inchangé | Rejet strict 403 / flash erreur, statut inchangé | **PASS** |

---

### 4. Conclusion du Test T07
- **Taux de succès :** 100% (23/23 contrôles unitaires, fonctionnels et E2E validés).
- **Validation :** Le cycle de vie complet de la demande de service répond parfaitement au cahier des charges et aux règles de gestion (RG02, RG03, RG04, RG10, RG14).

---

## Fiche Détaillée du Test T08

### 1. Objectifs du Test
1. **Contrôle d'accès & Authentification :** Redirection d'un visiteur non connecté vers `login.php?retour=...`.
2. **Accès des participants :** Le client demandeur et le professionnel assigné accèdent tous deux à la page `chat/conversation.php?id=[id]`.
3. **Isolation stricte (T08) :** Un tiers connecté (client ou professionnel non partie prenante) reçoit un code **HTTP 403**.
4. **Gouvernance administrative :** Un administrateur non-participant reçoit un code **HTTP 403** (aucune consultation des messages privés par l'administration).
5. **Messagerie interactive :** Envoi POST, échappement HTML avec `nl2br`, validation de taille, pagination (50 derniers messages + bouton précédents), rappel des règles d'usage et verrouillage automatique en mode lecture seule.

### 2. Matrice d'Exécution & Résultats (T08)

| N° | Scénario de test | Résultat attendu | Résultat obtenu | Statut |
| :---: | :--- | :--- | :--- | :---: |
| 1 | Visiteur non authentifié | Code 302 vers `login.php?retour=...` | HTTP 302 vers `login.php?retour=%2Ffindpro%2Fchat%2Fconversation.php%3Fid%3D...` | **PASS** |
| 2 | Accès Client participant | Code 200, fiche pro visible, titre demande affiché | HTTP 200, nom du pro, titre demande et messages affichés | **PASS** |
| 3 | Accès Pro participant | Code 200, fiche client visible, badge demandeur affiché | HTTP 200, nom du client et badge demandeur affichés | **PASS** |
| 4 | Accès Utilisateur Tiers | Code 403 (Forbidden), 0 fuite de message | HTTP 403 strict, page d'interdiction affichée | **PASS** |
| 5 | Accès Administrateur non-partie | Code 403 (Forbidden) | HTTP 403 strict, confidentialité garantie | **PASS** |
| 6 | Envoi de message POST | Code 302, message inséré en base, rendu avec `nl2br` | HTTP 302, message stocké, retours à la ligne conservés | **PASS** |
| 7 | Canal en lecture seule | Formulaire masqué, explication de verrouillage affichée | Formulaire absent, bannière explicative présente | **PASS** |
| 8 | Pagination des messages | 50 messages max au chargement initial, bouton "Voir les messages précédents" | Bouton affiché avec compteur restant, rechargement étendu | **PASS** |
| 9 | Règles d'usage & sécurité | Rappel affiché (respect, pas d'activité illégale, pas de mots de passe) | Bloc de sécurité présent, aucune mention technique RG | **PASS** |

- **Taux de succès T08 :** 100% (30/30 contrôles validés avec succès).

---

## Fiche Détaillée du Test T13

### 1. Objectifs du Test
1. **Endpoint API POST JSON (`chat/send.php`) :** Seules les requêtes POST authentifiées sont acceptées avec réponse JSON normalisée.
2. **Contrôle CSRF :** Support transparent du jeton CSRF via en-tête `X-CSRF-Token` ou corps `csrf_token`.
3. **Limites de contenu :** Validation stricte après `trim()` de 1 à 2 000 caractères (rejet HTTP 422 si vide ou > 2000 car.).
4. **Règle RG13 (Protection anti-harcèlement) :**
   - Lorsque la demande est au statut `envoyee` (non encore acceptée par le pro), le client ne peut pas envoyer plus de 3 messages au total.
   - Au 4e envoi : réponse **HTTP 429** avec le message exact : *« Le professionnel n'a pas encore répondu à votre demande. Vous pourrez écrire à nouveau après son acceptation. »*.
   - Le professionnel n'est jamais restreint par cette limite.
5. **Déblocage post-acceptation :** Dès que le statut de la demande passe à `acceptee` (ou en cours), le client peut à nouveau transmettre ses messages.
6. **Stockage en texte brut :** Conservation du texte brut en base de données sans encodage HTML prématuré (l'échappement étant garanti à l'affichage).
7. **Rate Limit :** Plafond strict de 20 messages par minute par utilisateur (HTTP 429).

### 2. Matrice d'Exécution & Résultats (T13)

| N° | Scénario de test | Résultat attendu | Résultat obtenu | Statut |
| :---: | :--- | :--- | :--- | :---: |
| 1 | Message vide | Rejet HTTP 422 avec JSON `succes: false` | Code HTTP 422, message d'erreur clair | **PASS** |
| 2 | Message de 2 001 caractères | Rejet HTTP 422 avec JSON `succes: false` | Code HTTP 422, message d'erreur clair | **PASS** |
| 3 | Message de 2 000 caractères | Accepté avec HTTP 201 Created | Code HTTP 201, `id_message` créé | **PASS** |
| 4 | Envoi avec `X-CSRF-Token` | Validation de l'en-tête AJAX | Code HTTP 201, jeton validé | **PASS** |
| 5 | Stockage texte brut | Enregistrement de balises et caractères spéciaux | Chaîne brute intacte en base de données | **PASS** |
| 6 | 3e message du client | Accepté au statut `envoyee` | Code HTTP 201, 3 messages en base | **PASS** |
| 7 | 4e message du client (RG13) | Rejet HTTP 429 avec message explicatif | Code HTTP 429, message officiel retourné | **PASS** |
| 8 | Message du professionnel | Autorisé sans limitation | Code HTTP 201, message enregistré | **PASS** |
| 9 | Déblocage post-acceptation | Client débloqué dès acceptation | Code HTTP 201, message transmis | **PASS** |

- **Taux de succès T13 :** 100% (13/13 contrôles validés avec succès).

---

## Test T14 — Polling AJAX & Sécurité DOM (Anti-XSS textContent)

### 1. Spécifications & Objectifs
1. **Endpoint Polling (`chat/poll.php?id=[id]&apres=[dernier_id]`) :**
   - Contrôle d'accès strict : seuls le client ou le professionnel assigné de la demande liée peuvent interroger la conversation (HTTP 403 pour tout tiers ou administrateur non participant ; HTTP 401 si anonyme).
   - Récupération incrémentale : seuls les messages avec `id_message > apres` sont retournés, classés par ordre chronologique.
   - Accusé de lecture automatique : les messages reçus de l'autre participant sont automatiquement passés à `lu = 1`.
2. **Client JavaScript (`assets/js/chat.js`) :**
   - Cycle de polling toutes les 5 secondes (`fetch`) vers `poll.php`.
   - Suspension du rafraîchissement lorsque l'onglet est masqué (`document.hidden`) avec Page Visibility API.
   - Envoi asynchrone par `fetch` vers `send.php` sans rechargement de page.
   - Gestion silencieuse des erreurs réseau avec reprise automatique.
   - **Règle de sécurité critique :** Insertion exclusive des contenus de messages via `textContent` (zéro `innerHTML`) pour empêcher l'exécution de tout code malveillant.
   - Aucun WebSocket utilisé.

### 2. Matrice d'Exécution & Résultats (T14)

| N° | Scénario de test | Résultat attendu | Résultat obtenu | Statut |
| :---: | :--- | :--- | :--- | :--- |
| 1 | Appel anonyme à `poll.php` | Rejet HTTP 401 Unauthorized | Code HTTP 401, message d'authentification requis | **PASS** |
| 2 | Appel par administrateur non participant | Rejet HTTP 403 Forbidden | Code HTTP 403, accès non autorisé | **PASS** |
| 3 | Appel par client tiers non participant | Rejet HTTP 403 Forbidden | Code HTTP 403, accès non autorisé | **PASS** |
| 4 | Appel par client participant | Réponse HTTP 200 avec JSON valide | Code HTTP 200, liste des messages et statut conversation | **PASS** |
| 5 | Appel par pro participant | Réponse HTTP 200 avec JSON valide | Code HTTP 200, liste des messages | **PASS** |
| 6 | Filtrage avec `apres = dernier_id` | 0 nouveau message retourné | Code HTTP 200, tableau `messages: []` vide | **PASS** |
| 7 | Nouveau message transmis par l'autre participant | Message détecté par `poll.php` | Message reçu avec ID, auteur, date et contenu brut | **PASS** |
| 8 | Mise à jour de l'indicateur de lecture | `lu = 1` automatique en base de données | Champ `lu` passé à 1 après interrogation | **PASS** |
| 9 | Envoi sans rechargement de page (AJAX) | Message envoyé via `fetch` vers `send.php` | Envoi asynchrone fluide sans rechargement | **PASS** |
| 10 | Test injection XSS (`<script>alert(1)</script>`) | Affiché littéralement comme du texte pur, aucune boîte d'alerte | Texte affiché brut dans la bulle DOM, aucun script exécuté | **PASS** |
| 11 | Suspension si onglet masqué (`document.hidden`) | Arrêt total des requêtes réseau d'arrière-plan | Timer annulé, aucune requête envoyée quand `document.hidden` | **PASS** |
| 12 | Délai de synchronisation entre deux acteurs | Message envoyé reçu en moins de 6 secondes | Polling toutes les 5s : réception garantie < 6s (vérifié en 0.015s en local) | **PASS** |

- **Taux de succès T14 :** 100% (12/12 contrôles validés avec succès).

---

## Annexe C — Matrice de Contrôle d'Accès et Protection CSRF

### 1. Objectifs & Méthodologie
- **Vérification de l'isolation :** Évaluation exhaustive du comportement des URLs sensibles face aux 5 états d'utilisateurs (**Visiteur**, **Client**, **Professionnel**, **Administrateur**, **Tiers**).
- **Règles attendues :**
  - **Visiteur** non authentifié : Redirection immédiate vers `/login.php?retour=...` (ou HTTP 401 pour les endpoints JSON). Aucune donnée privée n'est affichée.
  - **Mauvais rôle** : Code **HTTP 403 Forbidden** formel sans fuite d'informations métier.
  - **Ressource d'un tiers** : Code **HTTP 403 Forbidden** ou **HTTP 404 Not Found** (anti-énumération pour les profils non approuvés).
  - **Requête POST sans jeton CSRF** : Rejet immédiat par code **HTTP 403 Forbidden**.

---

### 2. Tableau d'Arbitrage et d'Accès par Rôle (Annexe C)

| URL sensible testée | Visiteur (anonyme) | Client (auteur/autorisé) | Professionnel (assigné/autorisé) | Administrateur | Tiers (utilisateur externe) |
| :--- | :---: | :---: | :---: | :---: | :---: |
| `admin/index.php` | 302 -> `/login.php` | 403 (Accès refusé) | 403 (Accès refusé) | 200 (Autorisé) | 403 (Accès refusé) |
| `admin/validation.php` | 302 -> `/login.php` | 403 (Accès refusé) | 403 (Accès refusé) | 200 (Autorisé) | 403 (Accès refusé) |
| `admin/profil_detail.php?id=[id]` | 302 -> `/login.php` | 403 (Accès refusé) | 403 (Accès refusé) | 200 (Autorisé) | 403 (Accès refusé) |
| `pro/profil.php` | 302 -> `/login.php` | 403 (Accès refusé) | 200 (Autorisé) | 403 (Accès refusé) | 403 (Accès refusé) |
| `pro/portfolio.php` | 302 -> `/login.php` | 403 (Accès refusé) | 200 (Autorisé) | 403 (Accès refusé) | 403 (Accès refusé) |
| `pro/demandes.php` | 302 -> `/login.php` | 403 (Accès refusé) | 200 (Autorisé) | 403 (Accès refusé) | 403 (Accès refusé) |
| `client/demandes.php` | 302 -> `/login.php` | 200 (Autorisé) | 403 (Accès refusé) | 403 (Accès refusé) | 200 (Autorisé pour son compte propre) |
| `demande_detail.php?id=[id]` | 302 -> `/login.php` | 200 (Client auteur) | 200 (Pro destinataire) | 200 (Supervision lecture seule) | 403 (Accès refusé) |
| `chat/conversation.php?id=[id]` | 302 -> `/login.php` | 200 (Client participant) | 200 (Pro participant) | 403 (Accès refusé - canal privé) | 403 (Accès refusé) |
| `chat/poll.php?id=[id]` | 401 (Non authentifié) | 200 (Client participant) | 200 (Pro participant) | 403 (Accès refusé) | 403 (Accès refusé) |
| `chat/send.php` | 401 (POST) / 405 (GET) | 201 (POST participant) | 201 (POST participant) | 403 (Accès refusé) | 403 (Accès refusé) |
| `client/avis_nouveau.php?demande=[id]` | 404 (Introuvable) | 404 (Introuvable) | 404 (Introuvable) | 404 (Introuvable) | 404 (Introuvable) |
| `request_new.php?profil=[id]` | 302 -> `/login.php` | 200 (Autorisé) | 403 (Réservé aux clients) | 403 (Réservé aux clients) | 200 (Autorisé pour son compte propre) |
| `profile.php?id=[profil non approuvé]` | 404 (Introuvable) | 404 (Introuvable) | 404 (Introuvable) | 200 (Aperçu d'arbitrage) | 404 (Introuvable) |

*Note sur `profile.php?id=[profil non approuvé]` : Le propriétaire du profil non approuvé peut également consulter son aperçu personnel (code 200 avec bandeau d'avertissement), mais tout autre utilisateur ou visiteur reçoit un code 404 strict pour empêcher toute énumération d'identifiants.*

---

### 3. Exécution Détaillée des 14 Tests de l'Annexe C (Campagne du 25/10/2026)

| N° | URL testée | Périmètre & Scénario | Statut | Date | Note d'analyse & Conformité |
| :---: | :--- | :--- | :---: | :---: | :--- |
| **1** | `admin/index.php` | Tableau de bord administration | **OK** | 25/10/2026 | Visiteur redirigé (302), Client, Pro et Tiers bloqués (403), Admin autorisé (200). Isolation parfaite du back-office. |
| **2** | `admin/validation.php` | File d'attente de validation des profils | **OK** | 25/10/2026 | Visiteur redirigé (302), rôles non-admin bloqués en 403, Admin accède à la file (200). 0 fuite de données de profils en attente. |
| **3** | `admin/profil_detail.php?id=1` | Examen d'un profil pro par l'administration | **OK** | 25/10/2026 | Visiteur 302, Client/Pro/Tiers 403, Admin 200. Décisions d'arbitrage strictement réservées à l'administrateur. |
| **4** | `pro/profil.php` | Espace professionnel : édition profil | **OK** | 25/10/2026 | Visiteur 302, Client/Admin/Tiers bloqués avec 403, Pro autorisé (200). Espace réservé aux professionnels. |
| **5** | `pro/portfolio.php` | Espace professionnel : gestion portfolio | **OK** | 25/10/2026 | Visiteur 302, Client/Admin/Tiers bloqués (403), Pro autorisé (200). Protection complète du formulaire de portfolio. |
| **6** | `pro/demandes.php` | Espace professionnel : opportunités reçues | **OK** | 25/10/2026 | Visiteur 302, Client/Admin/Tiers bloqués (403), Pro autorisé (200). Seul le pro voit les demandes qui lui sont adressées. |
| **7** | `client/demandes.php` | Espace client : suivi des demandes émises | **OK** | 25/10/2026 | Visiteur 302, Pro et Admin bloqués (403), Clients autorisés (200). Chaque client ne voit strictement que ses propres demandes. |
| **8** | `demande_detail.php?id=1` | Fiche détaillée d'une demande de service | **OK** | 25/10/2026 | Visiteur 302, Client auteur (200), Pro assigné (200), Admin supervision (200), Tiers strictement bloqué (403). Confidentialité respectée. |
| **9** | `chat/conversation.php?id=1` | Canal de messagerie privée en direct | **OK** | 25/10/2026 | Visiteur 302, Client participant (200), Pro assigné (200), Admin bloqué (403 - RG04 secret des correspondances), Tiers bloqué (403). |
| **10** | `chat/poll.php?id=1` | Endpoint de polling AJAX des messages | **OK** | 25/10/2026 | Visiteur 401 (JSON API), Participants 200, Admin 403 (RG04), Tiers 403. Zéro fuite de messages privés par polling. |
| **11** | `chat/send.php` | Endpoint API d'envoi de message | **OK** | 25/10/2026 | Visiteur 401, Participants 201 avec CSRF, Admin 403 (RG04), Tiers 403. Requêtes GET rejetées en 405 Method Not Allowed. |
| **12** | `client/avis_nouveau.php?demande=1` | Route inexistante / dépréciée d'avis | **OK** | 25/10/2026 | Réponse HTTP 404 Not Found uniforme pour l'ensemble des 5 rôles. Aucun contournement possible. |
| **13** | `request_new.php?profil=1` | Création d'une nouvelle demande de prestation | **OK** | 25/10/2026 | Visiteur 302 vers login, Clients (auteur ou tiers) autorisés (200), Pro et Admin bloqués en 403 (action réservée aux clients). |
| **14** | `profile.php?id=4` | Consultation publique de profil NON approuvé | **OK** | 25/10/2026 | Visiteur, Client, Pro et Tiers reçoivent HTTP 404 strict (anti-énumération). Seul l'Admin peut prévisualiser (200). |

---

### 4. Matrice de Rejet CSRF (Formulaires POST sans Jeton)

| Formulaire POST sensible testé | Action / Méthode | Jeton CSRF | Statut HTTP attendu | Statut HTTP obtenu | Statut |
| :--- | :--- | :---: | :---: | :---: | :---: |
| `demande_detail.php?id=[id]` | Transition de statut (`action=demarrer_travaux`) | Supprimé | 403 Forbidden | HTTP 403 | **PASS** |
| `chat/send.php` | Envoi d'un message (`POST JSON`) | Supprimé | 403 Forbidden | HTTP 403 | **PASS** |
| `pro/profil.php` | Mise à jour des informations du profil | Supprimé | 403 Forbidden | HTTP 403 | **PASS** |
| `request_new.php?profil=[id]` | Soumission d'une nouvelle demande de prestation | Supprimé | 403 Forbidden | HTTP 403 | **PASS** |
| `admin/profil_detail.php?id=[id]` | Décision d'arbitrage admin (`action=approuver`) | Supprimé | 403 Forbidden | HTTP 403 | **PASS** |
| `admin/validation.php` | Validation d'un profil professionnel | Supprimé | 403 Forbidden | HTTP 403 | **PASS** |

---

### 5. Bilan et Synthèse de Conformité
- **Résultat de l'audit Annexe C :** 14 / 14 tests **OK** (100% de réussite).
- **Fuite de données non autorisées :** 0 faille constatée (0 fuite).
- **Taux de blocage des accès illégitimes :** 100%.
- **Taux de rejet des formulaires sans CSRF :** 100% (HTTP 403 systématique).
- **Liste des bugs :** Aucun bug bloquant, aucun bug important, aucun bug mineur détecté sur le périmètre Annexe C.

---

## Test T15 — Fuzzing de Robustesse (XSS, SQLi, Buffer/Longueur & Téléversements)

### 1. Objectifs & Vecteurs d'Attaque Testés
1. **Injections XSS :**
   - Vecteurs : `<script>alert(1)</script>` et `<img src=x onerror=alert(1)>`.
   - Cibles : inscription (`register.php`), profil pro (`pro/profil.php`), réalisations portfolio (`pro/portfolio.php`), création de demande (`request_new.php`), messagerie en direct (`chat/send.php` / `chat/conversation.php`), moteur de recherche (`search.php`).
   - Comportement attendu : le texte apparaît tel quel à l'écran, aucune alerte ne s'exécute, assainissement automatique à l'affichage (`e()`) ou insertion exclusive par `textContent`.
2. **Injections SQL (SQLi) :**
   - Vecteurs : `' OR '1'='1`, `" OR 1=1 --`, `profile.php?id=1 OR 1=1`, `search.php?q=%25%27`.
   - Comportement attendu : aucune erreur SQL générée, traitement via requêtes préparées PDO et échappement des caractères joker `LIKE`.
3. **Dépassement de longueur (Buffer / Overflow) :**
   - Vecteur : chaîne de 10 000 caractères.
   - Comportement attendu : rejet côté serveur avec message d'erreur clair indiquant le plafond autorisé.
4. **Téléversements malveillants & dangereux (`includes/upload.php`) :**
   - Vecteur 1 : script PHP renommé en image (`test.php` en `test.jpg`).
   - Vecteur 2 : fichier image surdimensionné de 6 Mo (plafond de 2 Mo).
   - Vecteur 3 : fichier SVG contenant du code exécutable `<script>alert(1)</script>`.
   - Comportement attendu : rejet immédiat avec exception explicative.

---

### 2. Matrice d'Exécution & Résultats (T15)

| N° | Périmètre / Point d'entrée | Charge utile (Payload) | Résultat attendu | Résultat obtenu | Statut |
| :---: | :--- | :--- | :--- | :--- | :---: |
| 1 | URL `profile.php?id=1 OR 1=1` | SQLi dans paramètre `GET id` | Code HTTP 404 strict, aucune erreur SQL | HTTP 404, page introuvable propre | **PASS** |
| 2 | URL `search.php?q=%25%27` | Caractères spéciaux SQL (`%'`) | Code HTTP 200, échappement LIKE, aucune erreur SQL | HTTP 200, affiché `« %&#039; »`, 0 erreur SQL | **PASS** |
| 3 | Recherche `search.php?q=...` | `<script>alert(1)</script>` | Texte échappé dans le DOM, aucune exécution | Aucun script exécuté, texte échappé | **PASS** |
| 4 | Recherche `search.php?q=...` | `<img src=x onerror=alert(1)>` | Balise neutralisée dans le HTML | Aucun événement JavaScript déclenché | **PASS** |
| 5 | Inscription `register.php` | Nom de 10 000 caractères | Rejet avec message de plafond (max 100 car.) | Rejet : *"Le nom ne peut pas dépasser 100 caractères"* | **PASS** |
| 6 | Inscription `register.php` | XSS `<script>` & SQLi `' OR '1'='1` | Paramétrage PDO, échappement à l'affichage | Enregistré sans altération SQL, 0 script exécuté | **PASS** |
| 7 | Demande `request_new.php` | Description de 10 000 caractères | Rejet avec message de plafond (max 3000 car.) | Rejet : *"La description ne doit pas dépasser 3 000 caractères"* | **PASS** |
| 8 | Demande `request_new.php` | XSS `<script>` & SQLi `' OR '1'='1` | Traitement préparé PDO, affichage protégé | 0 exécution XSS, intégrité SQL préservée | **PASS** |
| 9 | Chat direct `chat/send.php` | Message de 10 000 caractères | Rejet HTTP 422 avec message d'erreur | HTTP 422 : *"Le contenu ne peut pas dépasser 2 000 caractères"* | **PASS** |
| 10 | Chat direct `chat/send.php` | Message `<script>alert(1)</script>` | Stocké en texte brut, inséré via `textContent` | Affiché comme texte littéral, 0 alerte | **PASS** |
| 11 | Chat direct `chat/send.php` | Message `<img src=x onerror=alert(1)>` | Inséré via `textContent` dans le DOM | Affiché comme texte littéral, 0 exécution | **PASS** |
| 12 | Chat direct `chat/send.php` | Message `' OR '1'='1` | Requête préparée PDO | Message stocké sans faille SQL | **PASS** |
| 13 | Portfolio `pro/portfolio.php` | Titre de 10 000 caractères | Rejet avec message de plafond (max 120 car.) | Rejet : *"Le titre ne peut pas dépasser 120 caractères"* | **PASS** |
| 14 | Portfolio `pro/portfolio.php` | Titre avec XSS & SQLi | Traitement sécurisé, 0 exécution | Enregistré sans faille SQL, affiché échappé | **PASS** |
| 15 | Upload `includes/upload.php` | `test.php` renommé en `test.jpg` | Rejet par inspection binaire finfo (MIME non image) | Rejet : *"Format de fichier non autorisé"* | **PASS** |
| 16 | Upload `includes/upload.php` | Fichier image de 6 Mo | Rejet par contrôle de taille (> 2 Mo) | Rejet : *"Le fichier est trop volumineux (max 2 Mo)"* | **PASS** |
| 17 | Upload `includes/upload.php` | Fichier SVG avec `<script>alert(1)</script>` | Rejet par liste blanche MIME (MIME non JPG/PNG/WEBP) | Rejet : *"Format de fichier non autorisé"* | **PASS** |

- **Taux de succès T15 :** 100% (17/17 contrôles validés avec succès).





