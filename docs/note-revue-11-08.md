# Note de synthèse — retour de revue

**11/08/2026** — pour Guillaume et Loïc.

## 1. La revue portait sur une version périmée

`trouvemoi.eu` n'avait pas été redéployé depuis le **05/08**. Vérifié en comparant le CSS
servi en production à notre code : la prod contenait encore `.gift-why{border-radius:16px}`,
`.home-container{padding-inline:100px}`, `.home-section__title{38px}`.

La capture « Pour offrir un cadeau ? — non conforme » montre exactement l'encadré arrondi
supprimé le 07/08. **Merci de redéployer avant la prochaine revue.**

## 2. Corrigé depuis

- **Cause racine des « marges qui manquent »** : cinq variables CSS employées mais jamais
  déclarées. En CSS une variable non résolue invalide *toute* la déclaration, en silence.
  `.act-section` perdait ainsi tout son espacement — soit le rythme vertical de cinq
  parcours. Garde-fou ajouté en CI.
- **Navbar décalée sur toutes les pages** (logo 55 px trop à gauche, boutons 30 px trop à
  droite). Désormais x108 → x1336, conforme aux trois maquettes.
- **Inscription** : navbar réduite, formulaire aligné sur la maquette. Dérive 107 → 9 px.
- **Listing Activités** : dérive 1728 → 9 px. Cartes corrigées dans le composant partagé
  (photo 218 px, bordure #fafafa, prix aligné à droite, pas de 426 px).
- **Filtres, détail, réservation, avis** conformes.

## 3. Sur Bootstrap

Il était chargé (226 Ko) mais quasi inutilisé : 7 classes réelles sur 104 templates. En
revanche **nos feuilles ne déclarent `box-sizing` nulle part** — c'est son Reboot qui le
fournit. Le retirer aurait tout cassé.

Il est maintenant calé sur la grille d'Agnès : `.container > .row.row--bleed > 4 × .col-3`
rend exactement 4 cartes de 296 px de x104 à x1335. Les modales sont passées sur le
composant Bootstrap (elles gagnent le piège à focus, qu'elles n'avaient pas). Inter est
hébergée par le projet : plus de clignotement de police, plus de requête vers Google.

## 4. Deux points à arbitrer

- **Le badge « Pro » des cartes** n'est pas dans la maquette, mais le code l'annote comme
  une demande de Loïc. De même, la bascule Client/Professionnel de l'inscription venait
  d'une demande client du 27/07 ; elle a été retirée pour coller à la maquette. **Quelle est
  la source de vérité quand une demande postérieure contredit Figma ?**
- **Le détail Activité** reste plus court que la maquette : celle-ci compte 64 blocs de
  texte, notre page 35. C'est du **contenu manquant**, pas un défaut de mise en page.

## 5. Reste à faire

Carte de recherche de l'accueil (Figma la place hors grille, à x68 sur 1304), héros
Destinations et Offres, migration des formulaires et boutons vers les classes Bootstrap.

## 6. Procédure de déploiement

```
rm -rf var/cache/prod public/assets
APP_ENV=prod php bin/console importmap:install
APP_ENV=prod php bin/console asset-map:compile
APP_ENV=prod php bin/console cache:clear
APP_ENV=prod php bin/console doctrine:migrations:migrate --no-interaction
```

Les deux premières lignes sont **obligatoires** : un cache de production périmé fait planter
le noyau avec « ApiPlatformBundle not found ».
