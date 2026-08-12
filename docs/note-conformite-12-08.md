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

## 6. Écart restant, hors CSS

La photo du héros Offres est cadrée environ 20 % plus serré que la maquette. Cela vient du
fichier source, pas de la mise en page.
