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

## 7. Écart restant, hors CSS

La photo du héros Offres est cadrée environ 20 % plus serré que la maquette. Cela vient du
fichier source, pas de la mise en page.
