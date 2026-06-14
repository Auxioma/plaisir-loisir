# Architecture — Plaisir-Loisir

> Document de référence de l'architecture backend. Sert de base à l'équipe et aux phases
> suivantes du projet. Rédigé en phase de conception (MVP), avant implémentation.

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
- **API Platform** — expose l'API REST + documentation OpenAPI auto
- **JWT** via **LexikJWTAuthenticationBundle** — authentification stateless
- **Doctrine ORM** + Doctrine Migrations
- **DTO** Input/Output — découplage API ↔ base de données
- **Services métier** — la logique vit dans des services, pas dans contrôleurs/entités
- **Symfony Workflow** — transitions de statuts (booking, paiement, prestataire)
- **Symfony Messenger** — traitements asynchrones (emails, indexation…)
- **Elasticsearch** — recherche avancée (plus tard, derrière une interface `SearchService`)
- **Frontend Angular** — projet séparé, développé plus tard ; le backend l'anticipe via l'API

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

Chaque domaine suit la même structure (convention) :

```
src/<Domaine>/
├── Entity/          # données stockées en base
├── Enum/            # valeurs fermées typées (statuts, types)
├── Repository/      # requêtes vers la base
├── Dto/Input/       # données reçues de l'API (validées)
├── Dto/Output/      # données renvoyées par l'API (champs choisis)
├── State/           # pont API Platform : Provider (GET) / Processor (POST/PATCH)
├── Service/         # logique métier
├── Event/           # faits accomplis diffusés
├── Message/         # tâches asynchrones (+ MessageHandler/)
└── Workflow/        # transitions de statut (déclarées en config)
```

## 4. Principes d'architecture

1. **La logique métier vit dans des Services.** Les contrôleurs / State API Platform ne font que
   recevoir et déléguer ; les entités ne font que stocker.
2. **L'API n'expose jamais les entités Doctrine** — uniquement des **DTO** (Input validés en
   entrée, Output contrôlés en sortie). Le contrat d'API reste stable même si la base évolue ;
   le futur frontend Angular n'est pas couplé à la structure de la base.
3. **Coder contre des interfaces** pour les briques remplaçables : `PaymentProcessor`
   (mock → Stripe), `SearchService` (PostgreSQL → Elasticsearch). On change l'implémentation sans
   toucher au reste.
4. **Symfony Workflow** déclare les transitions de statut autorisées et bloque les états illégaux
   (ex. rembourser une réservation jamais payée).
5. **Event vs Messenger** :
   - *Event* = « quelque chose vient de se passer », synchrone, même requête.
   - *Message* (Messenger) = « il y a un travail à faire », asynchrone, en tâche de fond.
   - Enchaînement type : Workflow émet un Event → un listener dispatche des Messages (email,
     notif, indexation) → réponse HTTP immédiate, tâches lentes en arrière-plan.
6. **Argent** : type `decimal` (`NUMERIC(12,2)`), jamais `float` ; devise stockée à côté.
7. **Snapshots** : les lignes transactionnelles (`BookingItem`) figent le libellé et le prix au
   moment de l'achat ; elles ne suivent pas les modifications ultérieures de la prestation.
8. **Dates** : `TIMESTAMPTZ` (avec fuseau horaire).
9. **Soft delete** : `deletedAt` plutôt que suppression physique (avis, factures en dépendent).

## 5. Modèle de données (MVP)

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

## 6. API (API Platform + JWT)

- Préfixe versionné `/api/v1`.
- Authentification JWT (Lexik), firewall `/api` **stateless**.
- CORS (`nelmio/cors-bundle`) pour autoriser le futur domaine Angular.
- Ressources décrites par attributs `#[ApiResource]` ; lecture via **State Provider**, écriture
  via **State Processor** qui délèguent aux services métier.

Endpoints cibles (extrait) :

```
POST  /api/v1/auth/register        POST  /api/v1/bookings
POST  /api/v1/auth/login (JWT)     GET   /api/v1/bookings/{id}
GET   /api/v1/me                   PATCH /api/v1/bookings/{id}/cancel
GET   /api/v1/categories           POST  /api/v1/services            (ROLE_PROVIDER)
GET   /api/v1/services?...         PATCH /api/v1/services/{id}
GET   /api/v1/services/{id}        GET   /api/v1/search?q=...         (Elasticsearch plus tard)
```

## 7. Recherche & Elasticsearch (plus tard)

PostgreSQL reste la **source de vérité** ; Elasticsearch n'est qu'un **index de lecture
reconstructible**. Synchronisation via événement Doctrine → message Messenger → indexation
asynchrone. Le code parle à une interface `SearchService` (impl. `DatabaseSearch` au MVP, puis
`ElasticsearchSearch`).

## 8. Stratégie Git

- **GitHub Flow** : `master` toujours déployable et protégée ; une branche par fonctionnalité
  (`feature/<domaine>-<sujet>`, `fix/<sujet>`).
- **Pull Request obligatoire** + CI verte avant fusion ; pas de commit direct sur `master`.
- **Conventional Commits** : `feat(...)`, `fix(...)`, `chore(...)`, `docs(...)`, `test(...)`.
- **CI GitHub Actions** : PHPUnit + PHPStan + PHP-CS-Fixer à chaque PR.

## 9. Roadmap

| Phase | Contenu | Jalon |
|---|---|---|
| **0 — Fondations** | Git/qualité/CI, `APP_SECRET`, deps (uid, api-platform, jwt, cors), `src/Shared/` (traits), mapping Doctrine par domaine, squelette sécurité+JWT, test de fumée | API documentée répond, base connectée, migrations OK, CI verte |
| **1 — Identité** | `User`, `Address`, inscription, login JWT, `/me` | On s'inscrit et se connecte |
| **2 — Catalogue** | `ProviderProfile` (+ workflow vérif), `Category`, `Service`, `ServicePackage`, `Media` | Un prestataire publie une prestation listée par l'API |
| **3 — Réservations** | `Booking`, `BookingItem`, workflow booking, paiement mock, premiers Events/Messages | Un client réserve et « paie » (mock) |
| **4 — Engagement** | `Review` (anti-faux-avis), `Favorite`, `Messaging`, `Notification` | Avis, favoris, messagerie, notifications |
| **5 — Recherche & Admin** | `SearchService` PG puis Elasticsearch, back-office, dashboard prestataire | Recherche avancée + administration |
| **6 — Avancé** | `Availability` (calendrier), `ServiceRequest`+`Quote` (devis), Stripe réel | Modèles de transaction complets |

À chaque phase : tests PHPUnit écrits **en même temps** que le code (la plateforme manipule de
l'argent et des engagements entre personnes).
