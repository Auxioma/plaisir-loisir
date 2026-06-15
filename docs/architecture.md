# Architecture — Plaisir-Loisir

> Document de référence de l'architecture. Sert de base à l'équipe et aux phases
> suivantes du projet.
>
> **Révision majeure (CTO Guillaume)** : le site est développé **entièrement en Twig**
> (application web server-rendered), et non avec un front Angular séparé. On abandonne
> **API Platform** et **Lexik JWT** au profit de **contrôleurs Symfony + Twig** et d'une
> **authentification Symfony classique par session/cookie**.

## 1. Vision produit

Marketplace SaaS de mise en relation client / prestataire, inspirée d'Airbnb, Fiverr, Malt,
Booking, ComeUp et StarOfService.

La plateforme combine à terme trois modèles de transaction :

| Modèle | Inspiration | Principe |
|---|---|---|
| `service_product` | Fiverr, ComeUp | Offre figée achetée directement (**actif au MVP**) |
| `calendar` | Airbnb, Booking | Réservation de créneaux/dates sur disponibilités (plus tard) |
| `quote` | StarOfService, Malt | Demande de besoin → devis des prestataires (plus tard) |

Les trois partagent ~80 % du modèle (utilisateurs, prestations, paiements, avis) ; ils sont
distingués par le champ `bookingType` sur l'entité `Service`.

## 2. Stack technique (décisions figées)

- **Symfony 8.1** / **PHP >= 8.4**
- **PostgreSQL 16** (via Docker)
- **Twig** — moteur de templates, génère directement le HTML des pages
- **Symfony UX (Turbo + Stimulus)** — navigations fluides et interactivité progressive sans SPA
- **Bootstrap 5** via **AssetMapper** (importmap, servi localement) — habillage sans build Node
- **Sécurité Symfony par session/cookie** — `form_login` (stateful), CSRF, `remember_me`, Voters
- **Doctrine ORM** + Doctrine Migrations
- **Services métier** — la logique vit dans des services, pas dans contrôleurs/entités
- **Symfony Forms** — saisie et validation des données en entrée (remplacent les DTO Input)
- **Symfony Workflow** — transitions de statuts (booking, paiement, prestataire)
- **Symfony Messenger** — traitements asynchrones (emails, indexation…)
- **Elasticsearch** — recherche avancée (plus tard, derrière une interface `SearchService`)

> **Abandonné** : API Platform, LexikJWTAuthenticationBundle, NelmioCorsBundle, et les couches
> `ApiResource/`, `Dto/Input`, `Dto/Output`, `State/`. CORS devient inutile : le front (Twig) et
> le back sont servis par la **même application sur le même domaine**.

### Identifiants : ULID

Toutes les entités utilisent des **ULID** (`symfony/uid`, type Doctrine `ulid`) plutôt que des
entiers auto-incrémentés ou des UUID v4 :

- triés chronologiquement (les 48 premiers bits = timestamp) ;
- insertions quasi séquentielles → index PostgreSQL compact et performant (vs fragmentation de
  l'UUID v4 aléatoire) ;
- n'exposent pas le volume de données dans les URLs ;
- compatibles avec une future architecture distribuée.

## 3. Organisation du code — DDD léger par domaine

Le code est organisé **par domaine métier**, pas par type technique. Tout ce qui concerne un
sujet (ex. la réservation) est regroupé au même endroit.

```
src/
├── Shared/        # code transverse réutilisé par tous les domaines
├── User/          # Identité & comptes
├── Provider/      # Prestataires
├── Catalog/       # Catégories & prestations
├── Booking/       # Réservations
├── Payment/       # Paiements (plus tard)
├── Messaging/     # Conversations & messages (plus tard)
├── Notification/  # Notifications (plus tard)
├── Review/        # Avis & favoris (plus tard)
├── Search/        # Recherche PostgreSQL → Elasticsearch (plus tard)
└── Admin/         # Modération & administration (plus tard)
```

Règle de dépendance : un domaine peut dépendre de `Shared/` ; les dépendances entre domaines
sont gardées minimales et conscientes (ex. `Booking` → `Catalog`).

### Structure interne d'un domaine

Chaque domaine suit la même structure (convention). La **couche métier est inchangée** par la
révision ; seule la **couche de présentation** passe d'API Platform à Twig :

```
src/<Domaine>/
├── Entity/          # données stockées en base                          (inchangé)
├── Enum/            # valeurs fermées typées (statuts, types)            (inchangé)
├── Repository/      # requêtes vers la base                             (inchangé)
├── Service/         # logique métier                                    (inchangé)
├── Event/           # faits accomplis diffusés                          (inchangé)
├── Message/         # tâches asynchrones (+ MessageHandler/)            (inchangé)
├── Workflow/        # transitions de statut (déclarées en config)       (inchangé)
├── Controller/      # 🆕 contrôleurs fins : reçoivent la requête, rendent du Twig
├── Form/            # 🆕 FormType : saisie + validation des écritures
└── Security/        # 🆕 Voters (autorisations fines), au besoin
```

Les vues vivent dans le dossier `templates/` racine (convention Symfony), rangées par domaine :

```
templates/
├── base.html.twig          # layout : header, navigation, footer, messages flash, Turbo
├── home/                   # accueil
├── security/               # login, register
├── catalog/                # listing, fiche prestation
├── provider/               # espace annonceur
└── account/                # espace utilisateur connecté
```

## 4. Principes d'architecture

1. **La logique métier vit dans des Services.** Les contrôleurs ne font que recevoir et
   déléguer ; les entités ne font que stocker. Un contrôleur reste **fin**.
2. **Flux d'une requête** :
   - *Lecture* : HTTP `GET` → Contrôleur → Repository/Service → **template Twig** → HTML.
   - *Écriture* : `POST` formulaire → Contrôleur → **Form** (valide la saisie) → **Service**
     (logique métier) → Doctrine → redirection (pattern POST-Redirect-Get) + message flash.
3. **Validation en entrée par Symfony Forms** : un `FormType` mappe la requête, applique les
   contraintes (`Assert`, `UniqueEntity`…) et protège du CSRF. Les Forms remplacent les
   anciens DTO Input ; la validation n'est plus un souci de couche API mais de formulaire.
4. **Coder contre des interfaces** pour les briques remplaçables : `PaymentProcessor`
   (mock → Stripe), `SearchService` (PostgreSQL → Elasticsearch). On change l'implémentation
   sans toucher au reste.
5. **Symfony Workflow** déclare les transitions de statut autorisées et bloque les états
   illégaux (ex. rembourser une réservation jamais payée).
6. **Event vs Messenger** :
   - *Event* = « quelque chose vient de se passer », synchrone, même requête.
   - *Message* (Messenger) = « il y a un travail à faire », asynchrone, en tâche de fond.
   - Enchaînement type : Workflow émet un Event → un listener dispatche des Messages (email,
     notif, indexation) → réponse HTTP immédiate, tâches lentes en arrière-plan.
7. **Argent** : type `decimal` (`NUMERIC(12,2)`), jamais `float` ; devise stockée à côté.
8. **Snapshots** : les lignes transactionnelles (`BookingItem`) figent le libellé et le prix au
   moment de l'achat ; elles ne suivent pas les modifications ultérieures de la prestation.
9. **Dates** : `TIMESTAMPTZ` (avec fuseau horaire).
10. **Soft delete** : `deletedAt` plutôt que suppression physique (avis, factures en dépendent).

## 5. Sécurité — authentification par session/cookie

Authentification **Symfony classique, stateful** (et non plus JWT) :

- **`form_login`** : l'utilisateur soumet un formulaire HTML (route `app_login`). Symfony vérifie
  le mot de passe (hash `auto` = bcrypt/argon), crée une **session serveur** et dépose un
  **cookie de session**. Les requêtes suivantes sont reconnues via ce cookie, sans effort côté
  client.
- **CSRF** activé sur le formulaire de connexion et les formulaires d'écriture.
- **`remember_me`** : cookie persistant (7 jours) signé avec `kernel.secret`.
- **`logout`** : route `app_logout` interceptée par le firewall.
- **Rôles** : `ROLE_USER` (`/account`), `ROLE_PROVIDER` (`/provider`), `ROLE_ADMIN` (`/admin`),
  appliqués par `access_control` (grain grossier, par préfixe d'URL).
- **Voters** : autorisations fines orientées objet (ex. « cet annonceur peut-il modifier *cette*
  prestation ? »). Mécanisme idiomatique Symfony, remplace la logique de sécurité qui aurait
  vécu dans les State Processors d'API Platform.

L'utilisateur est chargé depuis l'entité `App\User\Entity\User`, identifié par son `email`
(provider Doctrine).

## 6. Modèle de données (MVP)

Périmètre MVP : 9 entités. Enrichissement progressif ensuite.

| Domaine | Entités |
|---|---|
| `User/` | **User**, **Address** |
| `Provider/` | **ProviderProfile** |
| `Catalog/` | **Category**, **Service**, **ServicePackage**, **Media** |
| `Booking/` | **Booking**, **BookingItem** |

### Relations et cardinalités

| A | Relation | B | Type Doctrine (côté A) | Règle métier |
|---|---|---|---|---|
| User | 1 — N | Address | OneToMany | Un user a plusieurs adresses |
| User | 1 — 0..1 | ProviderProfile | OneToOne (optionnel) | Un humain = un seul profil pro |
| User | 1 — N | Booking | OneToMany (client) | Un client passe plusieurs réservations |
| ProviderProfile | 1 — N | Service | OneToMany | Un prestataire publie plusieurs prestations |
| Category | 1 — N | Category | OneToMany auto-référence | Hiérarchie catégorie → sous-catégories |
| Category | 1 — N | Service | OneToMany | Une catégorie classe plusieurs prestations |
| Service | 1 — N | ServicePackage | OneToMany | Formules Basic/Standard/Premium |
| Service | 1 — N | Media | OneToMany | Plusieurs images/vidéos par prestation |
| Service | 1 — N | Booking | OneToMany | Une prestation est réservée plusieurs fois |
| Booking | 1 — N | BookingItem | OneToMany | Lignes détaillées de la réservation |
| ServicePackage | 1 — N | BookingItem | OneToMany | Un package figure dans plusieurs réservations |

Rappel Doctrine : le côté « Many » porte la clé étrangère en base ; le côté « OneToMany » sert
uniquement à naviguer en PHP.

### Enums du MVP

| Enum | Valeurs | Entité |
|---|---|---|
| `UserStatus` | active, pending, suspended | User |
| `ProviderStatus` | draft, pending_verification, verified, suspended | ProviderProfile |
| `ServiceStatus` | draft, published, archived | Service |
| `BookingType` | service_product *(MVP)*, calendar, quote | Service |
| `BookingStatus` | pending, confirmed, in_progress, completed, cancelled, refunded | Booking |

### Cycle de vie d'une réservation (Workflow `booking`)

```
pending → confirmed → in_progress → completed
   │           │
   │           └──→ cancelled / refunded
   └──→ cancelled
```

## 7. Pages & routes (Twig)

Le site est un ensemble de pages HTML rendues par des contrôleurs. URLs en langage métier,
servies sur le même domaine que l'application.

```
GET   /                         accueil (catégories racines)
GET   /login   POST /login      connexion (form_login)
GET   /register POST /register  inscription
POST  /logout                   déconnexion
GET   /account                  espace utilisateur                     (ROLE_USER)
GET   /categories               liste des catégories
GET   /services                 listing des prestations
GET   /services/{id}            fiche d'une prestation
GET   /provider                 espace annonceur                       (ROLE_PROVIDER)
POST  /provider/services        publier une prestation                 (ROLE_PROVIDER vérifié)
GET   /admin/...                back-office                            (ROLE_ADMIN)
```

## 8. Recherche & Elasticsearch (plus tard)

PostgreSQL reste la **source de vérité** ; Elasticsearch n'est qu'un **index de lecture
reconstructible**. Synchronisation via événement Doctrine → message Messenger → indexation
asynchrone. Le code parle à une interface `SearchService` (impl. `DatabaseSearch` au MVP, puis
`ElasticsearchSearch`).

## 9. Stratégie Git

- **GitHub Flow** : `master` toujours déployable et protégée ; une branche par fonctionnalité
  (`feature/<domaine>-<sujet>`, `fix/<sujet>`).
- **Pull Request obligatoire** + CI verte avant fusion ; pas de commit direct sur `master`.
- **Conventional Commits** : `feat(...)`, `fix(...)`, `chore(...)`, `docs(...)`, `test(...)`.
- **CI GitHub Actions** : PHPUnit + PHPStan + PHP-CS-Fixer à chaque PR.

## 10. Roadmap

Le découpage métier des phases est conservé ; seule la **couche de présentation** de chaque phase
passe d'endpoints JSON à des **pages Twig**.

| Phase | Contenu | Jalon |
|---|---|---|
| **0 — Fondations** | Git/qualité/CI, `APP_SECRET`, retrait API Platform/JWT/CORS, socle **Twig + Bootstrap 5 (AssetMapper) + Turbo**, sécurité **session** (`form_login`), `base.html.twig` + pages d'erreur, `src/Shared/` (traits), mapping Doctrine par domaine | Le site répond en HTML, base connectée, migrations OK, CI verte |
| **1 — Identité** | `User`, `Address`, pages **inscription / login / compte** | On s'inscrit et on se connecte via le site |
| **2 — Catalogue** | `ProviderProfile` (+ workflow vérif), `Category`, `Service`, `ServicePackage`, `Media` ; pages accueil/listing/fiche + **formulaire de publication** annonceur | Un prestataire publie une prestation affichée sur le site |
| **3 — Réservations** | `Booking`, `BookingItem`, workflow booking, paiement mock, premiers Events/Messages ; **tunnel de réservation** | Un client réserve et « paie » (mock) |
| **4 — Engagement** | `Review` (anti-faux-avis), `Favorite`, `Messaging`, `Notification` | Avis, favoris, messagerie, notifications |
| **5 — Recherche & Admin** | `SearchService` PG puis Elasticsearch, back-office, dashboard prestataire | Recherche avancée + administration |
| **6 — Avancé** | `Availability` (calendrier), `ServiceRequest`+`Quote` (devis), Stripe réel | Modèles de transaction complets |

À chaque phase : tests PHPUnit écrits **en même temps** que le code (la plateforme manipule de
l'argent et des engagements entre personnes).
