# Préproduction (staging) — suivre l'intégration en direct

Objectif : offrir à toute l'équipe (développement, **design**, **CTO**) une **URL
unique et toujours à jour** — par exemple `https://preprod.trouvemoi.eu` — où
l'on voit l'avancement de l'intégration des interfaces **en temps réel**, sans
rien installer et **sans toucher à la production**.

## Principe

```
branche feature  ──PR──▶  develop  ──▶ déploiement AUTO ──▶ preprod.trouvemoi.eu   (toute l'équipe regarde)
                             │
                             └──PR──▶  master  ──▶ déploiement ──▶ trouvemoi.eu (production)
```

- On travaille sur des branches `feature/*`, fusionnées dans **`develop`** via Pull Request.
- Chaque fusion dans `develop` **redéploie automatiquement la préprod** (workflow `.github/workflows/deploy.yaml`).
- La **production ne bouge que** lorsqu'on fusionne `develop → master`.

## Ce que le CTO doit mettre en place (côté serveur)

Le serveur portant déjà le backend, il reste à ajouter un environnement de préprod isolé :

1. **Un sous-domaine** `preprod.trouvemoi.eu` (DNS + vhost/serveur web) pointant vers un dossier dédié, ex. `/var/www/preprod`.
2. **Une base de données de préproduction** distincte de la prod (mêmes migrations, données de test).
3. **Un clone du dépôt** dans ce dossier, sur la branche `develop` :
   ```bash
   git clone -b develop https://github.com/Auxioma/plaisir-loisir.git /var/www/preprod
   ```
4. **Un fichier `.env.local`** dans ce dossier avec la config de préprod :
   ```dotenv
   APP_ENV=prod
   APP_SECRET=... (propre à la préprod)
   DATABASE_URL="postgresql://user:pass@127.0.0.1:5432/preprod?serverVersion=16&charset=utf8"
   MAILER_DSN=...   # un vrai transport (ou Mailtrap) pour tester les e-mails du reset plus tard
   # + Redis / Elasticsearch / Stripe (clés test) selon besoin
   ```
5. **Un utilisateur SSH de déploiement** dont la **clé publique** est ajoutée à `~/.ssh/authorized_keys`, et qui a le droit d'exécuter les commandes de déploiement (git, composer, php bin/console, reload PHP).

## Ce qui est déjà prêt côté dépôt

Le workflow **`.github/workflows/deploy.yaml`** se déclenche à chaque push sur `develop`
et exécute sur le serveur : `git reset --hard origin/develop`, `composer install --no-dev`,
`doctrine:migrations:migrate`, `importmap:install`, `asset-map:compile`, `cache:clear`,
puis (à décommenter) le reload PHP.

Il ne reste qu'à définir **5 secrets GitHub** (Settings ▸ Secrets and variables ▸ Actions) :

| Secret | Exemple | Rôle |
|---|---|---|
| `STAGING_SSH_HOST` | `preprod.trouvemoi.eu` | Hôte du serveur |
| `STAGING_SSH_USER` | `deploy` | Utilisateur SSH |
| `STAGING_SSH_KEY`  | *(clé privée)* | Clé privée de déploiement |
| `STAGING_SSH_PORT` | `22` | Port SSH |
| `STAGING_PATH`     | `/var/www/preprod` | Dossier du projet sur le serveur |

Et une fois `develop` créée : **la protéger** (Settings ▸ Branches) pour exiger une PR + une CI verte avant fusion → seul du code validé part en préprod.

## Résultat

Dès que tout est branché, il suffit que la designer et le CTO ouvrent
`https://preprod.trouvemoi.eu` : ils voient la dernière version intégrée,
mise à jour automatiquement à chaque fusion dans `develop`, avec un vrai
back-end et une vraie base (les formulaires et interactions fonctionnent).
