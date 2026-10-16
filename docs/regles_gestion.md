# Règles de Gestion — FindPro

Ce document consigne les règles de gestion (RG) applicables à la plateforme **FindPro**. La colonne *Où elle est appliquée* sera renseignée au fil du développement pour mapper chaque règle à son implémentation concrète dans le code (fichiers, contrôles de formulaires, requêtes SQL).

| Code | Règle de gestion | Où elle est appliquée (page ou fichier concerné) |
| :--- | :--- | :--- |
| **RG01** | Un visiteur peut consulter les profils approuves sans creer de compte. | |
| **RG02** | Seul un client authentifie peut envoyer une demande de service. | |
| **RG03** | Une demande est liee a un seul client et a un seul professionnel. | |
| **RG04** | La conversation privee est accessible apres l'envoi de la demande et uniquement a ses participants autorises. | |
| **RG05** | Seuls les profils approuves sont affiches dans la recherche publique. | |
| **RG06** | Un professionnel peut modifier son profil, mais l'administration peut demander une nouvelle verification. | |
| **RG07** | Un avis est possible uniquement apres une demande terminee et confirmee par le client. | |
| **RG08** | Les mots de passe ne sont jamais stockes en clair. | |
| **RG09** | Les pages et actions sont controlees cote serveur selon le role. | |
| **RG10** | Les paiements ne sont pas actives dans le MVP ; aucun paiement ne doit etre simule. | |
| **RG11** | Les services de cybersecurite doivent etre explicitement autorises par le proprietaire du systeme cible. | |
| **RG12** | Les decisions de moderation importantes doivent garder une trace : qui, quand, quoi, pourquoi. | |
| **RG13** | Tant qu'une demande n'est pas acceptee, le client est limite a 3 messages dans la conversation associee. | |
| **RG14** | Une demande passe a terminee uniquement apres marquage a_confirmer par le professionnel puis confirmation du client. | |
