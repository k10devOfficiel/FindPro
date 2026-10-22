# CHARTE GRAPHIQUE — FINDPRO

Version : 1.0  
Statut : proposition de référence à valider avant intégration

## 1. Identité visuelle

**Nom :** FindPro

**Positionnement :** plateforme de présentation de compétences et de mise en relation entre professionnels du numérique et particuliers.

**Valeurs :** confiance, compétence, simplicité et professionnalisme.

**Public cible :** particuliers, développeurs, monteurs vidéo et spécialistes en cybersécurité.

**Concept visuel :** un curseur géométrique construit avec des formes angulaires et des pixels carrés, associé à un dégradé rouge-orange. Le symbole évoque la navigation numérique, la sélection d’un professionnel et l’action.

## 2. Logo et favicon

- **Logo principal :** `assets/img/logo.svg`
- **Logo pour fond foncé :** `assets/img/logo-blanc.svg`
- **Favicon :** `assets/img/favicon.png`

### Principes du logo

- Le symbole principal est un curseur géométrique pixelisé.
- La couleur du symbole utilise un dégradé rouge-orange.
- Le nom **FindPro** adopte une typographie carrée, inspirée du pixel art, mais doit rester lisible.
- Le slogan « Trouvez le bon professionnel » peut accompagner le logo dans les supports suffisamment larges. Il est omis dans les petits formats.
- Prévoir une version monochrome pour les usages qui ne permettent pas la couleur.
- Ne jamais étirer, incliner, recolorer arbitrairement ou déformer le logo.
- Conserver une zone de respiration autour du logo.
- Tester sa lisibilité dans la barre de navigation, sur smartphone et sur fond clair ou foncé.

### Fichiers et exports

- Conserver le logo principal en SVG.
- Prévoir une version claire adaptée aux fonds sombres.
- Exporter le favicon en PNG, au minimum en 64 × 64 px, et vérifier son rendu à 32 × 32 px.
- Vérifier que le symbole reste reconnaissable sans le nom ni le slogan.

## 3. Palette de couleurs

La palette ci-dessous traduit le concept rouge-orange du logo. Les couleurs de statut restent distinctes de la couleur de marque pour éviter les ambiguïtés dans l’interface.

| Rôle | Couleur | Code HEX | Utilisation |
|---|---|---|---|
| Rouge de marque | Rouge vif | `#E11D2E` | Partie rouge du logo, détails et accents |
| Orange de marque | Orange | `#F97316` | Partie orange du logo et accents visuels |
| Accent lumineux | Orange doré | `#F59E0B` | Mise en évidence ponctuelle, badges non critiques |
| Couleur principale de l’interface | Bleu nuit | `#111827` | Navigation, titres et éléments structurants |
| Fond général | Blanc cassé | `#F8FAFC` | Fond des pages |
| Surfaces et cartes | Blanc | `#FFFFFF` | Cartes de profils, formulaires et panneaux |
| Texte principal | Gris très foncé | `#1F2937` | Paragraphes, libellés et contenus |
| Texte secondaire | Gris | `#64748B` | Descriptions et informations secondaires |
| Bordures | Gris clair | `#E2E8F0` | Délimitation des cartes et champs |
| Succès | Vert | `#15803D` | Confirmation d’action et statut positif |
| Erreur | Rouge foncé | `#B91C1C` | Erreurs de formulaire et alertes critiques |
| Avertissement | Ambre foncé | `#B45309` | Avertissements et actions nécessitant attention |

### Règles d’utilisation

- Le dégradé rouge-orange est réservé principalement au logo et à des accents de marque. Dégradé recommandé : `#E11D2E` vers `#F97316`.
- Le bleu nuit structure l’interface et garantit une identité professionnelle sans surcharger les pages.
- Les grands fonds restent blancs ou blanc cassé pour préserver la lisibilité.
- Les boutons prioritaires peuvent utiliser le bleu nuit. Les boutons de mise en avant de marque peuvent utiliser le dégradé, à condition que le texte présente un contraste suffisant.
- Le rouge de marque ne doit pas être utilisé automatiquement pour signaler une erreur : les couleurs de statut conservent leur signification propre.
- Ne pas communiquer un statut uniquement par sa couleur : ajouter un libellé ou une icône.
- Vérifier les contrastes des textes et des boutons avant livraison, notamment pour le texte blanc sur orange ou rouge.

## 4. Typographie

**Police des titres :** `Oxanium` — style géométrique et numérique, à utiliser avec modération pour les titres et éléments de marque.

**Police du texte :** `Inter` — police neutre et lisible pour les paragraphes, formulaires, menus et tableaux.

**Règle :** deux familles de polices au maximum.

### Utilisation

- Le mot-symbole du logo peut employer un dessin pixelisé personnalisé ou une police pixel adaptée, distincte des polices de l’interface si nécessaire.
- Ne pas utiliser une police pixel pour les longs paragraphes : elle réduit le confort de lecture.
- Utiliser des tailles, graisses et espacements cohérents sur toutes les pages.
- Prévoir des polices de remplacement sans empattement si les polices principales ne chargent pas.

## 5. Composants et mise en page

### Boutons

- Forme : rectangulaire avec des coins légèrement arrondis (`8 px`).
- Bouton principal : fond bleu nuit `#111827`, texte blanc.
- Bouton de marque : dégradé rouge-orange, texte blanc uniquement si le contraste est suffisant.
- Bouton secondaire : fond blanc, texte bleu nuit et bordure `#E2E8F0`.
- États survolé, focus, désactivé et chargement clairement différenciés.
- Les boutons doivent avoir une zone cliquable confortable sur mobile.

### Cartes de profils

- Fond blanc, bordure fine `#E2E8F0`, coins arrondis `12 px`.
- Ombre légère uniquement lorsque nécessaire pour distinguer les niveaux de contenu.
- Afficher clairement le nom, le métier, les compétences principales et l’accès au portfolio.
- Présenter le statut d’approbation avec un libellé explicite.
- Seuls les profils approuvés par la plateforme peuvent être présentés comme validés.
- Ne pas utiliser un badge de validation sur un profil en attente ou refusé.

### Formulaires

- Libellés explicites au-dessus des champs.
- Espacement suffisant entre les champs et les boutons.
- Bordures visibles et état de focus facilement identifiable.
- Messages d’erreur rédigés en langage simple, placés près du champ concerné.
- Les champs obligatoires sont indiqués clairement.

### Navigation

- Navigation responsive, simple et cohérente sur toutes les pages.
- Fond clair, texte bleu nuit et accent rouge-orange pour l’action principale ou l’élément actif.
- Menu mobile accessible au clavier et utilisable au toucher.
- Les espaces administrateur, professionnel et client doivent conserver les mêmes repères visuels.

### Icônes et ombres

- Bibliothèque d’icônes : **Bootstrap Icons**.
- Garder une épaisseur et un style d’icônes cohérents.
- Ombres légères, utilisées avec parcimonie.
- Ne pas multiplier les effets lumineux, dégradés et ombres au détriment de la lisibilité.

## 6. Direction artistique

**Ambiance :** numérique, géométrique, énergique et professionnelle.

**Mots-clés :** pixel, précision, compétence, connexion, confiance, simplicité.

L’interface doit privilégier la lisibilité, la cohérence, l’accessibilité et la rapidité d’utilisation. Le style pixel est un signe distinctif de la marque, pas un effet à appliquer à tous les éléments de l’interface.

Les profils non approuvés ne doivent jamais être présentés comme validés. Les statuts doivent être compréhensibles avec du texte, même lorsque la couleur n’est pas perçue.

## 7. Références visuelles

**Dossier des captures :** `assets/design/references/`

Les références servent à guider la mise en page et les composants. Elles ne doivent pas être reproduites à l’identique.

Les pistes d’inspiration à documenter peuvent porter sur :
- les profils et portfolios de professionnels ;
- la recherche et les filtres de compétences ;
- la clarté des fiches de service ;
- la lisibilité des états de validation ;
- les interfaces responsive et les formulaires.

## 8. Responsive et accessibilité

- Concevoir en mobile-first.
- Vérifier l’affichage sur petit smartphone, grand smartphone, tablette et ordinateur.
- Vérifier le contraste du texte, des boutons et des états.
- Rendre la navigation et les formulaires utilisables au clavier.
- Conserver un indicateur de focus visible.
- Ajouter des libellés accessibles aux contrôles et aux icônes interactives.
- Ne jamais dépendre uniquement de la couleur pour transmettre une information.
- Réduire les animations si l’utilisateur préfère limiter les mouvements.
- Vérifier que le logo et les boutons restent lisibles aux petites tailles.

## 9. Récapitulatif des décisions

- **Logo :** curseur géométrique pixelisé.
- **Couleurs de marque :** dégradé rouge `#E11D2E` vers orange `#F97316`.
- **Typographie de marque :** style pixel, réservé au mot-symbole.
- **Titres d’interface :** Oxanium.
- **Texte courant :** Inter.
- **Structure de l’interface :** bleu nuit, fonds clairs, cartes blanches.
- **Boutons :** rectangulaires, coins de 8 px.
- **Cartes :** coins de 12 px, bordure légère, ombre discrète.
- **Icônes :** Bootstrap Icons.
- **Priorités :** lisibilité, accessibilité, cohérence et confiance.

