CONTEXTE PERMANENT - PROJET FINDPRO
 
Tu m'aides a developper FindPro, une plateforme web de mise en relation entre des professionnels du numerique (developpeur, monteur video, specialiste en cybersecurite) et des particuliers. MVP a realiser en 14 jours par un seul developpeur. Pas de paiement integre.
 
STACK : PHP 8 (code procedural structure, sans framework), PDO, MySQL/MariaDB sous WAMP, HTML5, CSS3, Bootstrap 5 (CDN), JavaScript leger. Encodage UTF-8 (utf8mb4).
 
STRUCTURE DES DOSSIERS : racine du projet = www/findpro ; config/ ; includes/ ; sql/ ; assets/ (css, js, img) ; uploads/ ; logs/ ; admin/ ; pro/ ; client/ ; docs/.
 
CONVENTIONS : tables et colonnes en snake_case sans accent (ex : profil_professionnel, id_utilisateur). Roles : client, professionnel, admin. Statuts de profil : brouillon, soumis, en_verification, a_corriger, approuve, refuse, suspendu. Statuts de demande : envoyee, acceptee, refusee, en_cours, a_confirmer, terminee, annulee.
 
REGLES TECHNIQUES OBLIGATOIRES :
1. Requetes SQL preparees PDO uniquement, jamais de concatenation de variables dans le SQL.
2. Toute sortie HTML est echappee avec la fonction e().
3. Validation cote serveur de toutes les entrees.
4. Jeton CSRF sur tout formulaire POST.
5. Mots de passe avec password_hash / password_verify.
6. Controle du role et de la propriete des donnees cote serveur au debut de chaque page protegee.
7. Aucun secret (mot de passe BDD, cle) dans Git.
8. Messages d'erreur generiques pour l'utilisateur ; details techniques dans logs/.
9. Ne simule jamais un paiement.
 
REGLES DE TRAVAIL :
- Travaille uniquement sur la tache que je te demande.
- Examine d'abord les fichiers existants et explique-moi leur fonctionnement actuel.
- Propose un plan court avant de modifier quoi que ce soit.
- Ne reecris pas les fichiers non concernes.
- Apres modification, indique : les fichiers modifies, les tests manuels a executer, les risques restants.
- N'affirme jamais qu'un test a reussi si tu ne l'as pas reellement execute.
- Si une information manque, pose-moi la question au lieu d'inventer.
- Commente le code en francais, simplement.
- Avant toute commande terminal destructive (suppression de fichiers, DROP, git reset, git push), demande-moi confirmation.
- Ne lis, n'affiche et ne copie jamais config/config.local.php ni aucun mot de passe.
- Ne lance jamais git push toi-meme : c'est moi qui pousse apres relecture.

---

### Résumé de compréhension (5 lignes) :
1. **Mission & Cible :** Création d'un MVP en 14 jours pour FindPro, plateforme reliant particuliers et professionnels du numérique (dev, vidéo, cyber), sans système de paiement.
2. **Stack technique :** PHP 8 procédural bien structuré (sans framework), PDO MySQL/MariaDB (WAMP, utf8mb4), HTML5/CSS3, Bootstrap 5 CDN et JS léger.
3. **Organisation & Conventions :** Dossiers étanches (`admin/`, `pro/`, `client/`, `config/`, etc.), nommage `snake_case`, rôles stricts (`client`, `professionnel`, `admin`) et cycles d'états précis pour profils et demandes.
4. **Sécurité intransigeante :** 100 % requêtes préparées PDO, échappement systématique avec `e()`, CSRF sur tous les POST, hachage sécurisé des mots de passe, contrôle d'accès/propriété en tête de page et zéro secret exposé.
5. **Cadre de travail :** Tâches unitaires avec plan préalable, explications et commentaires en français, aucune action destructive ou `git push` sans ton accord formel.
