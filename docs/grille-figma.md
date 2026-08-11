# Grille et mesures de référence — relevées dans Figma

Fichier Figma `pS2lc91GKOByFkhzx0qqp8`, frame **`591:61677` « Acceuil »** (1440 × 4561).

Ces valeurs viennent de l'**inspecteur Figma** (coordonnées et dimensions réelles des
calques), et non d'une mesure sur une image exportée. Elles font foi, conformément à la
spécification du CTO (§6, §37, §61).

Le quota du connecteur Figma est limité ; consulter ce document avant de refaire un appel.

## Grille

| Élément | Valeur Figma |
|---|---|
| Largeur de la frame | **1440** |
| Blocs de texte (« Sub Container ») | **x = 110, largeur = 1220** |
| Grilles d'images (« Frame 1597885537 ») | **x = 104, largeur = 1232** |
| Carte de grille | **296** de large |
| Gouttière entre cartes | **16** |

Les grilles d'images débordent donc volontairement de **6 px de chaque côté** des blocs de
texte. C'est cet écart que reproduisent `.card-grid` et la classe `.row--bleed` du thème
Bootstrap.

Correspondance Bootstrap (voir `assets/styles/bootstrap-theme.css`) :

- `.container` → gouttière de 220 px, soit 110 px de marge : identique au « Sub Container »
- `.row` → gouttière de 16 px
- `.row--bleed` → ajoute le débord de 6 px, ce qui donne 4 × `col-3` de 296 px exactement

## En-tête et héros

| Élément | Valeur Figma |
|---|---|
| Navbar (« Header » `595:56643`) | y = 0, hauteur **100** |
| Contenu de la navbar | **x = 108 → x = 1336** (identique sur Accueil, Inscription et Activités) |
| Héros (« Header » `925:80729`) | y = 101, hauteur **579** |
| Contenu du héros | x = 110, y = 86 (relatif), largeur 577 |
| Titre du héros | largeur 571, hauteur **128** (deux lignes) |
| Paragraphe du héros | y = 168, largeur 512, hauteur **56** (deux lignes de 28) |
| Boutons du héros | hauteur **40**, largeurs **216** et **261**, écart **29** |
| Pastilles d'avatars | 4 × **48**, positionnées à x 0 / 33 / 66 / 99 → chevauchement de **15** |

## Carte de recherche — hors grille

« Container - Renseignement » `700:78360` : **x = 68, y = 610, largeur 1304, hauteur 238**.

⚠️ Elle ne suit **pas** la grille 110/1220. Elle déborde de 42 px de chaque côté.

- Cadre intérieur : x = 20, y = 20, largeur 1264, hauteur 198
- Rangée de recherche : y = 80, hauteur 118 ; champs de 48 de haut à y = 35
- Champs à x = 12 / 306 / 589 / 884, séparateurs à x = 268 / 551 / 846
- Bouton de recherche : x = 1082, **130 × 48**

## Sections de contenu

Toutes bâties sur le même patron :

| Élément | Valeur Figma |
|---|---|
| Hauteur de section | 644 (deux premières), 616, 744 (avis) |
| En-tête de section | x = 110, y = 32, largeur 1220, hauteur 130 |
| Titre | hauteur **72** (48 px d'interligne 1,5) |
| Paragraphe | y = 82, hauteur **48** (deux lignes de 24) |
| Bouton « Voir tout » | hauteur **48**, largeur 207 à 229 selon le libellé |
| Grille de cartes | y = 194, x = 104, largeur 1232, hauteur **418** (390 pour la 3ᵉ) |

## Avis

- Cartes de **605** de large, à x = 0 et x = 621 → gouttière **16**
- Contenu interne : x = 32, y = 32, largeur 541 → **padding de 32**
- Photo de profil : ellipse de **48**
- Pied de carte : y = 274, hauteur 32

## Pied de page

Instance `591:61757` : y = 4117, hauteur **444**.
