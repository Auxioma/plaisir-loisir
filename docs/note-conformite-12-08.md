# Conformité à la maquette — suite du retour du 12/08

**12/08/2026** — réponse à « il faut que le HTML soit identique à la maquette Figma ».

Les trois écarts qui restaient ouverts dans la note du 11/08 (§5) sont traités. Méthode :
chaque valeur est **mesurée au pixel sur les exports Figma** (`assets/figma/*.png`), puis le
rendu local est capturé à 1440 et remesuré au même endroit. Aucune valeur n'est estimée à
l'œil.

## 1. Carte de recherche de l'accueil

Elle ne suit pas la grille de la page : Figma la pose à **x68 sur 1304** quand le reste du
contenu va de 110 à 1330. Elle est maintenant obtenue par la gouttière du conteneur, comme
la grille principale.

| Repère | Maquette | Rendu |
|---|---|---|
| Rangée des champs | x88 → x1351 | x88 → x1351 |
| Séparateurs | 356 / 639 / 934 | 356 / 639 / 934 |
| Bouton | x1170, 130 × 48 | x1171, 130 × 48 |

Deux points de fond qu'une lecture rapide manque :

- **La carte n'est pas blanche, elle est en verre.** Blanc à 20 % (alpha relevé à 0,22 sur
  les deux accueils) : au-dessus de la photo du héros elle laisse voir l'image, au-dessus du
  fond de page elle paraît blanche. Seule la rangée des champs est opaque. Un fond blanc
  plein ne peut pas produire le bandeau translucide de la maquette.
- **Le bouton est violet sur l'accueil plateforme et orange sur l'accueil connecté.** Les
  deux maquettes diffèrent : on ne les uniformise pas.

Corrigé aussi : chevron collé à la valeur (16px) et non aligné à droite du champ, valeur en
14px et non 12, couleurs `#9dabc7`, pictogrammes des champs conformes aux glyphes.

## 2. Héros Destinations

Bande de 430 (et non 436), titre de 45px sur 52 d'interligne (et non 42/1,25), paragraphe de
18px sur 28 (et non 16/24) **dans la couleur du texte principal** et non en gris secondaire.

Titre à y189 / y242 pour 188 / 241 dans la maquette ; paragraphe à 367 / 394 / 423 pour
367 / 395 / 424. Écart maximal : 1px.

## 3. Héros Offres du moment

Bande de 433, titre de 48px. La pastille « −50 % » était **un cercle** : sa forme organique
et ses quatre traits décoratifs sont désormais relevés sur la maquette, contour tracé point
par point (boîte de 299 × 252 à 95px du haut du héros et 49px du bord droit). Ni rotation ni
ombre portée : la maquette n'en a pas.

Titre à y223, paragraphe à 284 / 312, pastilles à 353 — la maquette donne 222, 283 / 311,
353.

## 4. Fil d'Ariane, sur toutes les pages

Le séparateur est une **barre verticale** `#e5e7eb` et les maillons sont en gris très clair
`#c1c7d1`. Nous avions un « / » en gris secondaire. Relevé identique sur les deux maquettes
mesurées.

## 5. Ce qui demande un arbitrage, pas du code

- **Libellés des onglets de la carte de recherche.** La maquette porte « Activités » et
  « Événements », le second actif. Le code portait « Consulter Vos Plaisirs et Loisirs » et
  « Crée, Consulter et Partager… », qui venaient d'un commentaire de Loïc du 27/07. Nous
  avons appliqué la maquette, conformément à la consigne. À confirmer.
- Même question, toujours ouverte depuis le 11/08 : le badge « Pro » des cartes et la bascule
  Client/Professionnel de l'inscription viennent de demandes postérieures qui contredisent
  Figma. **Quelle est la source de vérité dans ce cas ?**

## 6. Deuxième passe — les deux listings (13/08)

Audit page entière, bloc de texte par bloc de texte.

**Listing Activités.** La grille n'affichait que 8 cartes : une règle CSS coupait la
troisième rangée que la maquette porte bien. Deux sections étaient posées sur un fond gris
inventé — la maquette du listing est entièrement blanche ; la teinte grise ne subsiste que
là où la maquette la porte (détail Activité, Destinations), au `#fafafb` relevé. La pastille
« Pro » / « Gratuite » a été retirée : elle n'est dans aucune maquette. Les blocs 0 à 12 sont
maintenant alignés à 5 px près, contre une dérive qui atteignait 475 px.

**Listing Destinations.** Les cartes de catégorie étaient posées en ligne, avec bordure et
pastille pâle ; la maquette les empile (pastille de 48 en violet plein, titre, volume) sur
216 × 109. Libellés et volumes repris. La mosaïque ne débordait pas de 6 px comme les autres
grilles d'images (cartes de 293 au lieu de 296) ; villes, volumes et emplacements sont repris
case par case — les photos de Lille, Bordeaux et Toulouse manquaient et sont **extraites de
la maquette, à remplacer par des fichiers HD**. Pastille de compteur, bandeau « Partez à la
découverte du monde » (titre sur une ligne, bouton **bleu** et non orange) et rythme du haut
de page corrigés.

**Deux défauts trouvés en chemin.** `/offres/toutes` affichait deux cartes sans image (chemins
morts dans `StaticOffers`) ; sur les 191 références d'images du code, ces deux-là étaient les
seules cassées. Et le chip « Filtre » était violet plein alors que les maquettes le donnent
blanc — corrigé sur les cinq listings qui l'emploient.

**Reste à passer** : Activités filtré, détail et avis, Destinations par ville, Cadeaux,
Événements, Profil, Authentification.

**Un point d'état, pas un défaut** : toutes les maquettes d'écrans intérieurs montrent la
navbar **connectée** (cœur, messages, cloche, avatar). Le site montre la navbar visiteur tant
qu'on n'est pas connecté, ce qui est le comportement attendu. Comparer ces écrans à la
maquette suppose donc de regarder la version connectée.

## 7. Troisième passe (13/08) — photos de profil et écran filtré

**Les avatars étaient de mauvais recadrages** : quatre PNG de 40 × 40 où les visages étaient
coupés ou décentrés. Ils sont remplacés par les portraits **extraits des maquettes** — la
liste des participants d'un événement en fournit huit, non recouverts, en 57 px. La rangée
« +250.000 utilisateurs satisfaits » de l'accueil reprend maintenant les quatre personnes de
la maquette, dans le bon ordre, en 48 px avec 15 px de chevauchement comme la maquette. La
navbar et l'aperçu d'événement pointent désormais sur `images/account/avatar-thomas.jpg`,
le portrait déjà correct de l'espace compte : c'est le même utilisateur partout.

Corrigé aussi sur cette rangée : la maquette écrit « +250.000 utilisateurs satisfaits. » en
**une seule couleur et une seule graisse** (16 px, `#0f172a`) ; nous mettions « +250.000 » en
bleu et en gras.

**Écran Activités filtré (écran D).** La maquette fait démarrer la sidebar de filtres **tout
en haut**, à côté du fil d'Ariane et du titre ; nous la placions sous l'en-tête, qui occupait
toute la largeur. La page bascule maintenant en deux colonnes complètes (sidebar de 296 px
relevée de x103 à x398). Le libellé « Filtres appliqués » manquait. Le groupe « Type
d'activité » (professionnelles / entre particuliers) a été retiré : il venait de la même
demande client du 27/07 que la pastille « Pro » et n'est pas dans la maquette.

Pour voir cet écran sans cliquer : `/activites?demo=filtres`.

## 8. Écart restant, hors CSS

La photo du héros Offres est cadrée environ 20 % plus serré que la maquette. Cela vient du
fichier source, pas de la mise en page.

## 9. Quatrième passe (13/08) — Profil, Cadeaux, Authentification

Même méthode : chaque valeur est relevée au pixel sur l'export Figma, appliquée,
puis le rendu local est recapturé et remesuré au même endroit.

### 9.1 Espace compte (4 écrans mesurés)

La colonne de gauche n'était pas la bonne **forme** : la maquette y pose un
panneau **collé sous la navbar**, à angles droits en haut et arrondi en bas
seulement (x102 → x400, jusqu'à y1047). Nous avions une carte flottante à
`border-radius: 16px` qui démarrait 26px plus bas.

| Repère | Maquette | Avant | Après |
|---|---|---|---|
| Panneau du menu | x102→400, y100→1047 | x104→391, y126→876 | conforme |
| Pas des entrées de menu | 80 (boîte 64 + 16) | 68,5 (boîte 65 + 4) | 80 |
| Pictogrammes du menu | 26 px | 20 px | 26 px |
| Retrait interne | 24 | 16 | 24 |
| Pastille « Membre depuis » | 150 × 24, graisse normale | 178 × 27, **gras** | conforme |
| Colonne de contenu | x416 → x1336 | x418 → x1336 | conforme |
| Titre de page | 48 px | 40 px | 48 px |
| Sous-titre | 16 px | 15,5 px | 16 px |
| Grille de favoris | cartes 296, gouttière 16 | cartes 294, gouttière 18 | conforme |
| Rangée de cartes | pas de 434 (carte 418 + 16) | pas de 403 | 434 |

Le **fil d'Ariane** était trop gros **sur tout le site** : la maquette écrit
« Accueil » sur 33 px d'encre, nous sur 41 — soit **10 px et non 12,5**, et en
graisse normale, pas en gras. Corrigé globalement, avec l'interligne figé à
19 px pour ne pas remonter le contenu des pages déjà calées (vérifié : le
listing Activités ne bouge pas de plus de 4 px).

Autres écarts corrigés sur ces écrans :

- **Notifications.** Toutes les lignes de la maquette sont sur fond `#f8fafc`
  **sans bordure** ; nous en grisions deux et laissions les autres blanches à
  bordure. Ligne de 88 px (nous étions à 101), pastille d'icône **ronde** de 56
  (nous avions un carré arrondi de 44), vignette de 72 (62). Les compteurs
  d'onglets sont des **cercles à simple filet** de la couleur de l'onglet, et
  les onglets inactifs sont **gris** — nous les mettions en texte principal sur
  une pastille grise pleine.
- **Déconnexion.** Illustration posée 72 px sous le titre (30), paragraphe en
  18 px gris (16 en texte principal), question de confirmation en gris et non
  en gras, boutons de 238 et 111 px occupant toute la colonne, « Besoin
  d'aide ? » en **bleu** `#2563eb`. Les pictogrammes des trois arguments
  sortaient en bleu nuit : une règle `.acc-logout__arg span` visait aussi leur
  pastille et écrasait le violet.
- **Onglets des favoris.** Grande roue (et non boussole) pour « Activités »,
  avion au décollage plein pour « Destinations », **entonnoir** et non doubles
  flèches pour « Trier par ». Le bouton des filtres est un carré plein `#f8fafc`
  sans bordure, celui de la liste n'a ni fond ni bordure.

### 9.2 Photos : pastilles et cœurs cuits dans les images

Défaut systématique : **16 photos** du dépôt sont des recadrages de maquette qui
contiennent déjà le bouton cœur (et parfois la pastille « Bestseller » /
« Nouveau » / « Tendance »). Comme nous dessinons les nôtres par-dessus, ils
apparaissaient **en double** sur les cartes — visible sur les favoris, la liste
Alsace, les notifications, le listing Cadeaux et deux écrans Événements. La
bande haute qui les porte a été retirée de chaque fichier.

Conséquence assumée : ces photos sont désormais plus serrées (elles perdent
~55 px de hauteur et le cadrage remonte). **Il faut les remplacer par les
fichiers HD**, comme déjà demandé pour Lille, Bordeaux et Toulouse.

Le fond du bouton « Afficher la carte » avait le même défaut : la vignette de
carte contenait le libellé, qui se doublait avec le nôtre. Elle est reconstruite
à partir des seules bandes sans texte de la maquette.

### 9.3 Listing Cadeaux

| Repère | Maquette | Avant | Après |
|---|---|---|---|
| Sous-titre | coupé à x1288 | coupé à 1100 | pleine colonne |
| Barre de recherche | y356 → 411 | y343 → 398 | conforme |
| Barre d'outils | y444 → 483 | y432 → 472 | conforme |
| Chips de villes | y518 → 553, fond `#f8fafc` sans bordure | y491 → 526, blanc à bordure | conforme |
| Ligne de résultats | y588 | y553 | conforme |
| 1re rangée de cartes | y692 | y656 | y692 |
| 2e rangée | y1130 | y1156 | y1141 |
| « Top catégories » | y1722 | y1696 | y1724 |

Le bouton « Afficher la carte » porte la vignette de carte de la maquette, et
c'est le bouton **des filtres** qui est posé sur le carré gris, pas celui de la
liste.

**Bandeau d'avis** (partagé avec Destinations et le détail Activité) : la
maquette pose chaque étoile sur une **pastille ronde de 40 px** `#fafafb`, étoile
pleine `#f4a911` et dernière en gris-bleu ; nous n'affichions que de petites
étoiles nues. « Traduire » est en texte principal souligné, pas en violet.

### 9.4 Écran d'entrée Pro / Client

| Repère | Maquette | Avant | Après |
|---|---|---|---|
| Carte | 1146 de large (x147 → 1292) | 1222 (x109 → 1330) | conforme |
| Panneaux | 446 chacun, 176 entre eux | 553 chacun, 50 entre eux | conforme |
| Retrait interne | 39 | 32 | 39 |
| Titre | 44 px | 40 px | 44 px |
| Sous-titre | 18 px | 16 px | 18 px |
| Nom du panneau | 16 px | 18 px | 16 px |
| Intitulé d'un avantage | 16 px | 14 px | 16 px |

Les quatre autres écrans d'authentification (inscription, connexion, mot de
passe oublié en trois étapes) **n'ont pas d'export local** : seul `register01`
est disponible dans le dépôt. Ils n'ont donc pas pu être mesurés — il faut les
exports 1440 px pour les traiter comme les autres.

### 9.5 Ce qui reste à trancher, pas à coder

- La maquette écrit « **Acceuil** » dans tous les fils d'Ariane et
  « Atelier céraniques », « Procince-Alpes-Côte d'Azur », « Ardècge » sur les
  cartes. Nous écrivons les formes correctes. À confirmer.
- Les compteurs d'onglets des notifications sont incohérents dans la maquette
  (Toutes 3 / Non lues 3 / Lues 7 pour 6 éléments affichés). Nous affichons
  6 / 3 / 3.
- Devant « Tout marquer comme lu » et « Paramètres », la maquette affiche un
  glyphe « ③ » — un **pictogramme non résolu** dans Figma, pas une icône. Nous
  gardons une coche et un engrenage.
- Les avis de la maquette sont du **lorem ipsum**, deux fois le même. Nous
  affichons des avis rédigés.
- Le paragraphe de l'écran de déconnexion porte un **retour à la ligne manuel**
  après « & » dans la maquette : sans lui, toute la colonne remonte de 25 px.
  Il a été reproduit.

## 10. Cinquième passe (13/08) — flow d'authentification (6 écrans)

Les exports manquants ont été fournis (`assets/auth-flow/`). Les six écrans sont
mesurés bloc par bloc à l'intérieur de la carte du formulaire.

### 10.1 Connexion — deux écarts de fond

**La maquette ne comporte pas de bascule Client / Professionnel.** Nous en
posions une en haut du formulaire (demande client du 27/07). Elle est retirée,
comme elle l'avait déjà été sur l'inscription le 10/08 : dans la maquette,
l'accès professionnel passe par un lien « Vous êtes un professionnel ? / Accéder
à votre espace » sur deux lignes centrées, sous la ligne de bascule — bloc qui
manquait chez nous. Le lien d'inscription s'y lit « Créer un compte » et non
« S'inscrire ».

L'attribut `autofocus` du champ e-mail est retiré : il posait l'anneau de focus
violet dès l'ouverture, alors que la maquette montre le champ au repos.

Rythme de la carte, propre à cet écran (elle n'a que deux champs, elle est donc
plus aérée que l'inscription) : 50px du sous-titre au premier libellé (35),
31 du dernier champ à « Se souvenir de moi » (16), 56 avant le bouton (14),
51 avant les boutons sociaux (35). Les treize blocs de la carte sont désormais
alignés à **5px près**.

### 10.2 Un bug de fond : les marges fantômes du Reboot Bootstrap

`.auth-switch` est posé sur des `<p>`, et le Reboot de Bootstrap leur donne
`margin-bottom: 1rem`. Ces **16px fantômes** décalaient tout ce qui suit : sur
l'écran « Vérifier le code », l'écart entre la ligne « Vous n'avez pas reçu de
code ? » et le bouton sortait à 58 pour 42 attendus.

Un balayage de **tout le projet** a été fait pour ce motif — chaque `<p>`,
`<h1..h6>`, `<ul>`, `<ol>`, `<figure>`, `<fieldset>` portant une classe, croisé
avec le CSS pour vérifier qu'une marge est bien posée. Trois fuites au total
(`.auth-switch`, `.ev-dropzone__or`, `.gift-acc__label`), toutes corrigées. Le
balayage ne remonte plus rien.

Dans la foulée : le style inline `style="justify-content: flex-start;"` de
l'écran du code est remplacé par une classe `.auth-switch--start`.

### 10.3 Mot de passe oublié — les trois étapes

Ces cartes sont nettement plus aérées que la connexion : **38px** entre les
blocs (23 ailleurs) et 55 entre le lien de retour et le titre.

Le lien « Retour à la page de connexion » était en 13px gris ; la maquette le
donne en **16px, en texte principal**, mesuré à 247px d'encre contre 218, et
retrait de 11px par rapport au bord de contenu. Relevé identique sur les trois
écrans.

Le bouton principal sortait à **48px de haut** au lieu de 40 : une ancre
`.pl-btn--block` posée hors de `.auth-actions` échappait à la règle de hauteur.
Corrigé pour toutes les cartes d'authentification.

Après correction, les trois écrans sont alignés à **5px près** (8 au maximum sur
« Définir un mot de passe », par accumulation).

### 10.4 Navbar réduite (tous les écrans d'authentification et l'accueil)

| Repère | Maquette | Avant | Après |
|---|---|---|---|
| « Découvrez » | x305, 81px d'encre | x313, 72px (14px) | x305, 82px (16px) |
| Sélecteur de langue | drapeau **rond** de 16, **sans chevron** | rectangle 20×14 + chevron | conforme |
| Écart entre les deux menus | 18 | 28 | 20 |
| « Déposer une annonce » | 192 de large | 200 | 196 |
| « S'incrire » | 104 de large | 129 | 118 |

Le bouton « S'inscrire » reste 14px plus large que la maquette : celle-ci écrit
« S'incrire » (une lettre en moins) et emploie l'apostrophe typographique.

### 10.5 Écran d'entrée Pro / Client

Le panneau n'a pas un écart uniforme dans la maquette : 19px du pictogramme au
nom, 12 du nom au texte, 45 avant la liste d'avantages, 40 avant le bouton et 19
avant le lien — nous appliquions 12 partout. Le bouton y fait 40px de haut (48
chez nous) et le lien « En savoir plus » 15,5px (14).

### 10.6 Coquilles de la maquette, non reproduites

« Acceuil », « S'incrire », « Vous n'avez pas reçu de code? », « Atelier
céraniques », « Procince-Alpes-Côte d'Azur », « Ardècge », « dasn ». Nous
écrivons les formes correctes — à confirmer.

## 11. Sixième passe (14/08) — tunnel Offrir et listing filtré des cadeaux

### 11.1 Deux bugs trouvés en chemin

**Les chips actifs étaient invisibles sur le listing filtré.** La règle de fond
plein posée le 13/08 (`.gift-listing .act-chip`) a la même spécificité que
`.act-chip.is-active` et passe après dans la feuille : les chips sélectionnés
perdaient leur violet mais gardaient leur **texte blanc**. La ville filtrée et
le filtre appliqué s'affichaient donc en pastilles grises vides.

**La grille filtrée n'occupait pas sa colonne.** Son `.act-section > .container`
gardait la gouttière de 220 du conteneur standard : les cartes tombaient à
224 px au lieu de 294 et une colonne vide restait à droite. Le chip « Filtre »
ne passait pas non plus en violet quand le rail est ouvert.

### 11.2 Tunnel « Offrir un cadeau » (écran 5)

Le tunnel n'avait jamais été mesuré. Il occupe la grille des **images**
(x104 → x1336) et non celle des blocs de texte.

| Repère | Maquette | Avant | Après |
|---|---|---|---|
| Panneau gris | 812 de large, retrait 36 | 804, retrait 40 | conforme |
| Récapitulatif | 396 (x942 → 1338) | 400 (x930 → 1330) | conforme |
| Fil d'Ariane | y145 | y130 | y145 |
| Titre de carte | 26 px | 28 px | 26 px |
| Intitulé | 18 px | 16 px | 18 px |
| « Champ obligatoire* » | 16 px | 14 px | 16 px |
| Libellés de champ | 16 px, graisse **normale** | 15 px, demi-gras | conforme |
| Hauteur de champ | 48 | 52 | 48 |
| Écart libellé → libellé | 91 | 104 | 91 |
| Bouton « Suivant » | 107 × 48 | 107 × 42 | conforme |

Deux points de structure : la carte « A qui vous l'offrez » **n'a pas de gros
titre** dans la maquette — son intitulé est au corps de « Saisissez vos
informations ici » — et seul son premier champ porte un grand écart (105 px
contre 91), le second retrouvant l'écart courant avant la zone de message.

Récapitulatif : l'aperçu de la carte cadeau mesure 332 px de large et l'encart
de validité le suit immédiatement ; nous étirions l'image sur toute la largeur,
ce qui décalait tout le bloc de 27 px. Encart de 123 px de haut sur `#fafafb`
(nous avions 106 sur `#f4f5f8`), et 76 px du total au premier accordéon (61).

**Les vingt-et-un repères de l'écran sont alignés à 4 px près.**

### 11.3 Écran de paiement (écran 8)

Options de 84 px de haut espacées de 31, retrait interne de 27, libellé en
18 px — nous avions 70 / 18 / 20 / 16. Titre reposé à y246.

### 11.4 Listing filtré

`Cadeaux - filtre.png` est **strictement identique** à `Cadeaux par villes.png`
(diff binaire nulle) : c'est le listing plein, déjà traité le 13/08. La vraie
variante filtrée est `Cadeaux- filtre.png`.

Rail de filtres : contenu à x140 et « Filtres » à y181 (nous étions à x121 et
y126), écarts internes irréguliers dans la maquette (83, 33, 48, 25, 65)
reproduits par des marges explicites. Écart rail → contenu de 19 (32 hérités du
listing Activités).

Colonne de contenu : la maquette du listing **filtré** est plus resserrée que
celle du listing plein — titre y200 (210), recherche y336 (356), outils y427
(444), chips y476 (518), résultats y552 (588), première rangée y617 (692). Les
sept repères sont maintenant exacts.

## 12. Septième passe (14/08) — flow Événements

### 12.1 Les pastilles cuites dans les photos, suite

Le contrôle du 13/08 avait retiré la bande haute (pastille de catégorie + cœur)
de quatre photos. Le flow Événements en révèle deux autres formes :

- **Le badge de date est cuit lui aussi**, en bas à droite : la carte
  « Barbecue entre amis » affichait « 18 Mai 2026 » **deux fois**.
- Le dépôt contenait déjà des variantes **`-clean`** de ces photos, extraites
  d'une autre planche où les cartes n'ont pas d'habillage — mais la liste
  « Événements à venir » pointait encore sur les versions habillées. Les quatre
  entrées sont repointées.

Il manquait la version propre de la photo de rafting (première carte de la
landing et couverture du détail). Elle existe **dans la maquette « Détails
événement »**, où la même photo est posée sans pastille, sans date et sans
cœur : `ev-raft-clean.jpg` en est extraite (x1045→1339, y364→581).

Un détecteur balaie désormais toutes les photos de cartes pour ces trois
motifs (pastille en haut à gauche, badge de date en bas à droite, disque du
cœur en haut à droite). Il ne remonte plus que les fichiers **non référencés**.

### 12.2 Landing Événements

| Repère | Maquette | Avant | Après |
|---|---|---|---|
| Titre de section | 48 px (498 d'encre) | 40 px (421) | 48 px |
| Tuiles de catégorie | blocs **pleins** `#f8fafc`, sans bordure ni ombre | blanches, bordure + ombre | conforme |
| Largeur des tuiles | variable (139 pour « Canoë / Kayak », 93 pour « Bien-être ») | toutes égales | variable |
| Pastille d'icône | 47 | 52 | 47 |
| Hauteur de tuile | 111 | 126 | 111 |
| Écart en-tête → contenu | 39 | 26 | 39 |

Dérive maximale du haut de page ramenée de **plus de 400 px à 30**.

### 12.3 Listing « Tous les événements »

| Repère | Maquette | Avant | Après |
|---|---|---|---|
| Fil d'Ariane | y142 | y127 | y142 |
| Titre → sous-titre | 48 | 32 | 48 |
| Barre d'outils | alignée à **droite** | alignée à gauche | à droite |
| Sous-titre | pleine colonne | coupé à 1100 | pleine colonne |
| Corps de carte | 32 lieu→titre, 34 titre→horaires | 28 et 27 | conforme |
| Rangée → rangée | 410 | 388 | 410 |
| Résultats → 1re rangée | 35 | 48 | 35 |
| Bouton → section suivante | 125 | 85 | 125 |

**Les trente repères de la page sont alignés à 5 px près.**

La barre d'outils était collée à gauche parce que `.act-toolbar` est en
`space-between` et que cet écran n'a pas de groupe gauche — un défaut qui se
voyait sur tous les listings Événements.

### 12.4 Les six routes restantes

| Écran | Écart maximal après |
|---|---|
| Détail d'un événement | 13 px |
| Liste des participants | 20 px |
| Listing Groupes | 9 px |
| Calendrier | en-tête exact, grille calée |
| Événements privés | 20 px (voir ci-dessous) |
| Détail d'un groupe | 5 px |

Corrections structurelles trouvées en chemin :

- **Le fil d'Ariane des pages sans en-tête de listing** (détail, groupes,
  participants) démarrait à y106 au lieu de y143 : elles n'ont pas de
  `.act-head` et échappaient au retrait posé pour les listings.
- **Le sous-titre s'affichait sur « Événements privés »** alors que le gabarit
  reçoit bien `intro: false` : `intro|default(true)` renvoie **TRUE** avec
  `false`, parce que le filtre `default` de Twig se déclenche sur toute valeur
  *vide* — et `false` en est une. Corrigé en `intro is defined ? intro : true`.
- **La description du groupe s'ouvrait dépliée** ; la maquette de référence la
  donne repliée (« En savoir plus »), l'état déplié étant une autre planche.
- Titre du groupe en **34 px** (« Cours » y mesure 97 px d'encre contre 114 en
  40 px), 193 px entre la barre d'onglets et le titre de l'onglet actif (117).
- Calendrier : 65 px du mois aux noms de jours et 38 jusqu'à la grille (52 et
  26 chez nous).

**Un désaccord entre planches, pas un défaut de code** : le bandeau « Créez
votre propre groupe » est à y216 sur « Tous les événements » et à y193 sur
« Événement privée », pour un gabarit identique. Nous suivons la première ; le
second écran est donc uniformément 20 px plus bas que sa planche.
