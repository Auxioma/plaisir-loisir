# CLAUDE.md — Plaisir-Loisir / TrouveMoi

## Le projet

Marketplace SaaS de mise en relation client / prestataire pour **activités & loisirs** en France
(nom commercial **TrouveMoi**, `trouvemoi.eu`, éditeur Auxioma). Inspiré Airbnb / Fiverr / Malt /
Booking / StarOfService.

Trois modèles de transaction, portés par `Service.bookingType` (≈80 % du modèle est partagé) :
- `service_product` — offre figée achetée directement — **actif au MVP**
- `calendar` — réservation de créneaux sur disponibilités — plus tard
- `quote` — demande de besoin → devis des prestataires — plus tard

Deux publics d'activités : **professionnelles** (payantes, prestataires vérifiés) et **entre
particuliers** (gratuites, avec album photo de groupe).

## Stack (décisions figées — voir `docs/architecture.md`)

- **Symfony 8.1 / PHP ≥ 8.4**, **PostgreSQL 16**
- **Tout en Twig**, server-rendered. Le CTO a *abandonné* le front Angular séparé + API Platform
  + JWT. Auth Symfony classique **session/cookie** (`form_login`, CSRF, `remember_me`, Voters).
- **Symfony UX** (Turbo + Stimulus), **Bootstrap 5 via AssetMapper** (importmap, **pas de Node**)
- **Doctrine ORM** + Migrations, **identifiants ULID** partout
- **Symfony Workflow** (`provider_verification`, `booking`), **Messenger** (emails/notifs async)
- **EasyAdmin 5** (back-office), **Stripe** (clés `test-`), **Elasticsearch** prévu plus tard
  derrière `SearchService` (impl. actuelle = PostgreSQL)

## Organisation du code — DDD léger par domaine

`src/<Domaine>/` avec la même structure interne : `Entity/ Enum/ Repository/ Service/ Controller/
Form/ Event/ Message/ Workflow/`. **La logique métier vit dans les Services**, les contrôleurs
restent fins, les entités ne font que stocker. Vues dans `templates/` racine, rangées par domaine.

Domaines : `Shared User Provider Catalog Booking Payment Quote Availability Messaging Notification
Review Favorite Event PrivateActivity Corporate Legal Support I18n Search Stats Admin`.

Règles d'archi : argent en `decimal` NUMERIC(12,2) jamais `float` ; dates `TIMESTAMPTZ` ; **soft
delete** (`deletedAt`) ; snapshots (BookingItem fige libellé + prix à l'achat).

## Lancer en local (WSL — ce projet vit dans `~/projects/plaisir-loisir`, PAS sur /mnt/d)

> Ne jamais remettre ce projet sur `/mnt/d` : le montage 9P casse `composer install` (erreurs
> « Could not delete … symfony/intl ») et rend `cache:clear` / tests très lents.

```bash
# PostgreSQL natif WSL (pas de systemd → à relancer après chaque reboot WSL)
sudo service postgresql start
# rôle/base : app / app / app  (déjà créés ; recréer si besoin :)
#   sudo -u postgres psql <<'EOF'
#   CREATE ROLE app WITH LOGIN PASSWORD 'app' CREATEDB;
#   CREATE DATABASE app OWNER app;
#   EOF

composer install                                   # composer natif dans ~/.local/bin
php bin/console doctrine:migrations:migrate -n
php bin/console doctrine:fixtures:load -n
php bin/console importmap:install

php -S 127.0.0.1:8000 -t public/ router.php          # → http://127.0.0.1:8000
```

`.env.local` (non versionné) pointe la base : `postgresql://app:app@127.0.0.1:5432/app`.
`APP_SECRET` vient de `.env.dev`. Pas de Symfony CLI installée dans ce WSL.

### Comptes
- Prestataire vérifié : `annonceur@trouvemoi.test` / `Password123` (fixtures)
- Admin : `php bin/console app:admin:grant EMAIL --mot-de-passe=... --prenom=... --nom=...`
- Client : s'inscrire via `/inscription`
- `?connecte=1` sur une URL simule l'en-tête connecté sans login

### Tests & qualité (comme la CI, `.github/workflows/ci.yaml`)
```bash
php bin/console --env=test doctrine:database:create --if-not-exists -n
php bin/console --env=test doctrine:schema:create -n
php bin/console --env=test doctrine:fixtures:load -n
php bin/phpunit
vendor/bin/php-cs-fixer fix --dry-run --diff
vendor/bin/phpstan analyse
php bin/check-css-tokens.php     # une var CSS --pl-* non déclarée casse la mise en page en silence
```

## Pièges

- **E-mails** : routés vers le transport async. Sans `php bin/console messenger:consume async`,
  aucun mail ne part (reste dans `messenger_messages`). En local `--limit=N` suffit.
- **i18n maison FR/EN** : la langue vit **dans l'URL** (`/en/...`), pas en session ; le FR n'a
  pas de préfixe. Source de vérité runtime = table `translation` ; `config/i18n/messages.en.yaml`
  = graine + filet de secours (`app:i18n:import`). Les règles `access_control` de `security.yaml`
  portent sur des **chemins** → toute page protégée doit y figurer **dans ses deux langues**.
- **`doctrine:query:sql`** est déprécié + pose une question interactive (paraît figé) → utiliser
  `dbal:run-sql`.
- **Serveur local, toujours `router.php`, jamais `public/index.php`** : sans lui, `php -S` fait
  passer même les fichiers statiques réels (CSS, JS d'EasyAdmin) par le noyau Symfony, qui perd
  la détection de type MIME → CSS ignoré par le navigateur, icônes et mise en page cassées
  (typiquement en plein écran dans `/admin`). Si ce bug reapparaît, c'est presque toujours qu'un
  `php -S` a été relancé avec `public/index.php` au lieu de `router.php` à la racine.
- **Prestataire, double verrou volontaire** : `ROLE_PROVIDER` = accès espace pro ; statut
  `verified` = droit de publier (`ActivityPublishingService` bloque sinon).
- **Déploiement prod** : purger `var/cache/prod` + `public/assets` AVANT tout (sinon
  « ApiPlatformBundle not found »). Séquence dans `docs/note-revue-11-08.md` §6.
- **Conformité Figma au pixel** : exigence forte du client. `docs/grille-figma.md` (quota
  connecteur Figma limité — lire le doc avant tout nouvel appel). Question non tranchée : source
  de vérité quand une demande client postérieure contredit Figma.

## Git

- Commits en **français**, Conventional Commits : `feat(scope): ...`, `fix(scope): ...`,
  `chore/docs/test(scope): ...`. Scope = domaine ou zone (`pro`, `activites`, `back-office`…).
  Sujet en minuscule, concret.
- GitHub Flow : `master` toujours déployable, PR + CI verte, pas de commit direct sur `master`.
- Ne committer / pusher **que si l'utilisateur le demande**.
- Note : ce code vient d'un ZIP client. Le remote `github.com/Auxioma/plaisir-loisir` est celui
  du zip ; vérifier l'accès en écriture avant de supposer qu'on peut pousser.
- **Préprod** `preprod.trouvemoi.eu` : **n'existe pas** (DNS absent, branche `develop` jamais
  créée). C'est une proposition dans `docs/deploiement-preproduction.md`, pas un existant.

## État d'avancement

Le back-end (14 domaines : entités/repos/services) existait déjà depuis la phase API Platform.
Le travail = **brancher les écrans Twig (jadis statiques via classes `Static*`) sur ce back**.

**Fait** : auth complète, catalogue activités/destinations (pagination + tri), favoris, socle
juridique versionné, OAuth Google/Facebook/Apple (identifiants encore `test-`), back-office
EasyAdmin, parcours d'authentification pro (écrans `/pro`), pages du menu (activités,
destinations, albums photo, « Mes favoris »), **espace professionnel complet** (`/pro/*`,
maquettes `docs/maquettes/profil_professionnel`, contrôleurs `src/Provider/Controller/Space/`,
lecture des données dans `ProviderSpace`) : activités soumises puis validées dans le back-office,
réservations (workflow), calendrier/créneaux, messagerie + notes clients, avis, revenus
(commission 12 %), offres (`Promotion`), statistiques (`Stats\PageView`), paramètres, documents,
tickets support (`SupportTicket`, réponse depuis EasyAdmin). Démo : `ProviderSpaceFixtures`.

**Reste** : tunnel réservation + Stripe réel (la date de séance `Booking::startsAt` doit y être
saisie), page publique « Offres du moment » encore statique (`StaticOffers`, pas branchée sur
`Promotion`), domaine `Event/` (encore statique), liens réseaux sociaux officiels à confirmer
(`ProviderSupportController::SOCIALS`, pied de page),
vérif d'email, pages politique de confidentialité / CGV / cookies. Détail : fin de
`docs/cablage-back-front.md`.

### Chantier en cours : spec « employé / employeur » (agents de sécurité)

Le client a fourni une spec d'un produit **différent** : marketplace de staffing d'agents de
sécurité (missions, candidatures, carte professionnelle, taux horaire, paiement à la mission,
notifications push, mode hors-ligne, PayPal/Mobile Money). Ce n'est PAS la marketplace loisirs
actuelle, mais ça se pose sur ≈70 % de la plateforme : le modèle `quote`
(`ServiceRequest` + `Quote`) = mission + candidature ; `Provider` = agent ; `Availability`,
`ProviderDocument`, `Notification`, `Messaging`, `Payment`, `I18n`, Leaflet existent déjà.
Points à trancher avec le client avant de coder : pivot ou 2e produit ? web ou appli mobile ?
Stripe ou PayPal/Mobile Money ?
