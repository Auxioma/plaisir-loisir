# Câblage back-end / front-end — lot 1 : authentification

Note de travail du 17/08/2026, après l'aval du CTO pour brancher le back sur le
front avant d'aborder le mode professionnel.

Le front était intégralement statique : les contrôleurs servaient des classes
`Static*` (111 appels au total). Le back, lui, existait déjà largement — quatorze
domaines avec entités, dépôts et services, hérités de la phase API Platform et
jamais retirés. Le travail n'est donc pas d'écrire le métier, mais de brancher
les écrans dessus, domaine par domaine.

Ce document couvre le lot 0 (environnement) et le lot 1 (authentification).

---

## 1. Lot 0 — remettre l'environnement en état

Trois blocages empêchaient toute vérification :

| Blocage | Correction |
| --- | --- |
| L'extension `gd` était commentée dans `C:\php8.4\php.ini`, ce qui faisait échouer les commandes console avec « Gd driver not installed » | Ligne `extension=gd` décommentée (la DLL était déjà présente) |
| Docker était arrêté, donc pas de PostgreSQL | Conteneurs `database` et `mailer` relancés |
| Le port publié par Docker avait changé (1904 → 3658) | `DATABASE_URL` mis à jour dans `.env.local` |

Le port de la base **change à chaque redémarrage du conteneur**, parce qu'un
autre PostgreSQL occupe déjà 5432 en natif sur la machine. Le relever avec :

```
docker compose port database 5432
```

État constaté ensuite : 21 migrations sur 21 appliquées, 30 tables, mapping et
schéma en accord (`doctrine:schema:validate`).

### Piège de commande

`doctrine:query:sql` est déprécié et pose une question interactive : la commande
paraît « bloquée » alors qu'elle attend une réponse. Utiliser `dbal:run-sql`.

---

## 2. Lot 1 — l'authentification

### 2.1 L'inscription ne créait aucun compte

C'était un défaut réel, pas un manque. `templates/security/register.html.twig`
postait des champs nommés `fullName`, `email`, `phone`, `password`,
`agreeTerms`, alors que `RegistrationFormType` déclarait `firstName`,
`lastName`, `email` et un `plainPassword` en `RepeatedType`. Les noms ne
correspondaient à rien : `$form->isSubmitted()` restait faux, la page se
rechargeait en silence et rien n'était enregistré. Le formulaire ne portait par
ailleurs aucun jeton CSRF, alors que l'écran de connexion en avait un.

**La maquette fait foi** : le type de formulaire a été aligné sur les quatre
champs de l'écran, pas l'inverse.

Le balisage n'a pas changé : chaque `<input>` écrit à la main est rendu par le
widget Symfony équivalent, auquel on repasse la même classe, le même `id` et le
même `placeholder`. `required => false` partout, pour que le HTML produit reste
celui de la maquette, qui ne porte aucun attribut `required` — la validation est
entièrement côté serveur.

Le rendu obtenu, attribut par attribut :

```html
<input type="text" id="inputFullName" name="registration_form[fullName]"
       class="auth-input__control" placeholder="martinthomas@gmail.com"
       autocomplete="name">
```

### 2.2 Un seul champ pour deux colonnes

La maquette propose un champ unique libellé « Nom & prénom », l'entité `User`
stocke `firstName` et `lastName`. On suit l'ordre du libellé : **le premier mot
est le nom, le reste le prénom**. « Durand Sophie » donne nom « Durand », prénom
« Sophie ». Saisie en un seul mot : il devient le nom, le prénom reste vide.

*À confirmer* : beaucoup d'utilisateurs saisissent spontanément « Prénom Nom ».
La règle tient en une ligne dans `RegistrationService::splitFullName()` et se
change sans rien casser.

### 2.3 L'espace compte n'était pas protégé

`security.yaml` protégeait `^/account` et `^/provider`, deux préfixes qui ne
correspondent à **aucune route**. Les vraies routes sont `/compte/favoris`,
`/compte/notifications`, `/compte/parrainage` : elles étaient consultables par
n'importe quel visiteur. Remplacé par `^/compte` avec `ROLE_USER`, plus
`#[IsGranted('ROLE_USER')]` sur le contrôleur.

L'espace professionnel n'a pas de règle : il n'a pas encore de routes, et
protéger un préfixe imaginaire ne protège rien.

### 2.4 L'état connecté vient enfin de la session

Une vingtaine de templates écrivaient `connected: true` en dur : un visiteur non
connecté voyait un avatar menant à un espace compte auquel il n'a pas accès.
L'état est désormais calculé dans les partials d'en-tête, à partir de `app.user`,
et nulle part ailleurs. La prévisualisation de développement `?connecte=1` est
conservée.

Sur les pages institutionnelles, la maquette ne montre que l'état connecté ; un
visiteur y voit maintenant le bouton « S'inscrire », qui est l'état visiteur déjà
maquetté sur les autres en-têtes.

### 2.5 Mot de passe oublié — les trois écrans sont branchés

Les trois écrans n'avaient aucun `<form>` : les boutons étaient des liens qui
passaient au suivant. Ils postent désormais réellement.

Décisions :

- **Le code fait huit caractères alphanumériques**, comme le gabarit `7759BK5X`
  de la maquette — et non six chiffres. Les caractères ambigus (0/O, 1/I/L) sont
  exclus : un code se recopie à la main, souvent depuis un téléphone.
- Le code n'est **jamais stocké en clair** : seule son empreinte l'est, dans
  trois colonnes ajoutées à `user` (migration `Version20260817224629`). Valable
  15 minutes, cinq essais, puis destruction.
- Ces colonnes sont portées par l'utilisateur et non par la session, sinon il
  suffirait de vider ses cookies pour rejouer le compteur de tentatives.
- **Une adresse inconnue ne provoque aucune erreur visible** : répondre « ce
  compte n'existe pas » permettrait de savoir qui est inscrit sur la plateforme.
- Chaque étape refuse de s'afficher si la précédente n'a pas été franchie, et le
  code est **revérifié** au moment d'enregistrer le mot de passe : atteindre le
  troisième écran ne suffit pas à changer le mot de passe d'un compte.
- « Renvoyer » ramène à l'étape 1, adresse pré-remplie. Un lien qui enverrait
  lui-même un e-mail serait une route GET aux effets de bord, déclenchable par un
  simple préchargement du navigateur.
- L'e-mail est en texte brut, comme les notifications existantes : aucun gabarit
  d'e-mail n'a été maquetté, en inventer un figerait une mise en forme que
  personne n'a validée.

### 2.6 Le compte professionnel — le choix était perdu en silence

L'écran d'entrée envoyait bien vers `/register?type=pro`, mais **le paramètre
n'était lu nulle part**. Ni rôle, ni dossier prestataire : un compte créé par la
tuile « Pro Prestataire » était rigoureusement identique à un compte client en
base. L'utilisateur croyait s'inscrire comme professionnel et obtenait un compte
client, sans le moindre message. C'est le pire des cas — un défaut silencieux.

Corrigé le 18/08, **sans ajouter le moindre élément visible** :

- Une énumération `App\User\Enum\AccountType` (`client` / `pro`) reprend les
  valeurs qui circulent déjà dans les URL de la maquette. Toute valeur inconnue
  retombe sur « client » : un paramètre bricolé à la main ne doit jamais
  provoquer d'erreur, et le compte le moins privilégié est le bon défaut.
- Le type voyage dans un **champ caché** de `RegistrationFormType`. Il le fallait :
  le formulaire poste vers `/register` sans la chaîne de requête, le `?type=pro`
  disparaissait donc à l'envoi. `form_end()` le rend avec le jeton CSRF, la carte
  n'est pas touchée — vérifié par mesure, les six écrans sont inchangés.
- Un compte professionnel reçoit `ROLE_PROVIDER` et un `ProviderProfile` ouvert
  **en brouillon**, via une nouvelle méthode `startDraftProfile()`.

Pourquoi une nouvelle méthode plutôt que `becomeProvider()` : cette dernière
applique aussitôt la transition `submit`, qui envoie le dossier en vérification.
Or à l'inscription on ne connaît que le nom — ni raison sociale, ni statut
juridique, ni présentation. Soumettre un dossier vide à l'administration n'aurait
aucun sens. Le dossier reste donc en `draft` ; c'est le futur espace
professionnel qui le complétera puis déclenchera `submit`.

**Le rôle n'ouvre aucun droit de publication.** N'importe qui peut ajouter
`?type=pro` à l'URL : c'est sans conséquence, parce que
`ActivityPublishingService` refuse déjà toute mise en ligne tant que le dossier
n'est pas `Verified`. Le rôle donne accès à l'espace professionnel, la
vérification donne le droit de publier. Ce sont deux verrous distincts, et c'est
volontaire.

Le message de confirmation diffère pour un professionnel — « complétez votre
dossier prestataire » — sans quoi il croirait pouvoir publier immédiatement.

Au passage : le prestataire de démonstration `annonceur@trouvemoi.test` avait un
dossier **vérifié** mais **aucun rôle**. Il n'aurait pas pu entrer dans l'espace
professionnel. Corrigé dans `CatalogFixtures` et dans la base locale.

`templates/security/_account_type.html.twig` (bascule Client/Pro + statut
juridique) reste **inclus nulle part** : la maquette ne le montre pas. Il est
conservé parce que le statut juridique devra bien être collecté quelque part,
vraisemblablement dans le formulaire de l'espace professionnel.

### 2.7 Les e-mails ne partent pas tout seuls — point d'exploitation

`messenger.yaml` route `SendEmailMessage` vers le transport `async`. **Sans
worker Messenger en service, aucun e-mail n'est envoyé** : le message reste dans
la table `messenger_messages`. Le code de réinitialisation n'arriverait jamais.

En production il faut donc un service qui exécute en permanence :

```
php bin/console messenger:consume async
```

En local, `messenger:consume async --limit=1` suffit à vider la file.

`MAILER_SENDER` a par ailleurs été ajouté : sans en-tête `From`, Symfony refuse
d'envoyer (« An email must have a From or a Sender header »).

---

## 3. Conformité : une régression trouvée et corrigée

Entourer les champs d'un `<form>` a un coût géométrique. Sur les trois écrans de
mot de passe, `.auth-card--pw` espace ses blocs de 38 px ; `.auth-form` de 10 px.
Les champs et le bouton, devenus enfants du formulaire, ont perdu **exactement
28 px** — mesuré, pas supposé.

Corrigé en rendant au formulaire l'espacement de la carte
(`.auth-card--pw .auth-form { gap: 38px; }`). Le formulaire n'ayant ni marge ni
bordure, la géométrie de la maquette est restituée au pixel près.

**Vérification après correction** : les six écrans d'authentification (position
et taille de la carte, position et taille du bouton, hauteur de page à 1440 px)
sont identiques à l'état d'avant le câblage. Quatorze pages supplémentaires
mesurées en état connecté : identiques elles aussi.

Une seule différence volontaire subsiste, sur le landing `/evenements` : la
maquette le montre en état visiteur, un utilisateur connecté y voit désormais son
avatar. La hauteur de page ne change pas. **À valider.**

---

## 4. Vérifications passées

- Inscription : compte créé en base, e-mail déjà pris renvoyé en message
  d'erreur (et non plus en page d'erreur HTTP 409), formulaire vide rejeté avec
  les quatre messages attendus.
- Connexion : session ouverte, mauvais mot de passe refusé, déconnexion
  effective.
- Réinitialisation : e-mail reçu, code correct accepté, code faux refusé, accès
  direct à l'étape 3 refusé, mot de passe trop court et confirmation divergente
  refusés, ancien mot de passe invalidé, code détruit après usage.
- Parcours complet du site en suivant les liens, deux passes : anonyme
  (63 URL) et connecté (62 URL). Aucune anomalie ; les seules redirections sont
  les deux routes de changement de langue.
  L'anonyme atteint les sept écrans d'authentification et jamais l'espace
  compte ; le connecté atteint les six pages du compte et est redirigé hors des
  écrans d'authentification.
- `lint:twig`, `lint:yaml`, `php-cs-fixer` et PHPStan niveau 6 : verts.

---

## 5. Ce qui reste

- **Lot 2 — catalogue** : jeu de données reprenant mot pour mot le contenu des
  classes `Static*`, puis `ActivityController` et `DestinationController` lisant
  les dépôts. Le rendu doit rester identique au pixel.
- **Lot 3 — espace compte réel** : favoris, listes, notifications, parrainage.
  Aujourd'hui seule l'**identité** est réelle ; le contenu et le compteur de
  notifications non lues restent ceux de la démonstration.
- **Lot 4 — réservation et paiement** : `BookingService` et Stripe existent, le
  tunnel côté front est à faire.
- **Lot 5 — événements et groupes** : seul domaine sans aucune entité, et plus
  gros consommateur de statique (29 appels).
- **Espace professionnel** : le fond est prêt (rôle, dossier en brouillon,
  workflow de vérification, service de publication conditionné au statut
  vérifié), mais **aucun écran n'existe et aucune route `/pro` n'est déclarée**.
  Il n'y a pas non plus de règle `access_control` pour ce préfixe : protéger un
  chemin inexistant ne protège rien, elle sera ajoutée avec les routes.
  Restent à collecter dans ces écrans : raison sociale, statut juridique,
  adresse fiscale, présentation, puis la transition `submit`.
  Les liens « En savoir plus sur l'espace pro / client » de l'écran de choix
  sont encore inertes (`href="#"`).
- **Connexion par Google / « Se connecter avec Apple » / Facebook** : les
  boutons existent sur la maquette, désactivés. En attente de l'aval du CTO sur
  les identifiants d'application. Trois dépendances à ne pas découvrir trop
  tard : Apple exige une adhésion payante au programme développeur et une
  vérification de domaine ; Google et Meta réclament une **URL de politique de
  confidentialité publiée** — la page n'existe pas encore ; et il faudra
  trancher le cas d'une connexion Google sur une adresse déjà inscrite par mot
  de passe.
- **Photo de profil** : l'entité `User` n'en porte aucune ; l'avatar de la
  maquette est encore affiché pour tout le monde.
- **Vérification de l'adresse e-mail** : les comptes sont actifs immédiatement.
- **Écran de succès après inscription** : non maquetté ; on redirige vers la
  connexion avec un message.
- **Politique de confidentialité** : la page n'existe pas, le lien des
  conditions générales pointe volontairement dans le vide plutôt que vers les
  mentions légales, qui sont un autre document.
