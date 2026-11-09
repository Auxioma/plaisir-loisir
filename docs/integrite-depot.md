# Contrôle d’intégrité du dépôt

## Incidents

| Date | Commit | Ce qui s’est passé |
|---|---|---|
| 13/10 – 22/10/2026 | `3b49639`, `49892e3` | Retrait d’un premier code malveillant : tâche VS Code `runOn: folderOpen` qui lance `node` sur une fausse police, et code caché derrière une longue suite d’espaces. Ajout du contrôle d’intégrité. |
| 25/10/2026 | `8b26f7d`, `e4fdf8f` | Nouveau retrait (tâches d’éditeur, fausse police `.orc`, chargeur dans `booking_checkout_controller.js`). |
| 26/10/2026 | `4ac8d94`, `78bc3b9` | **Réinjection** dans un commit présenté comme une correction de `.env` et `.gitignore` : `.vscode/tasks.json`, `.vscode/settings.json` (`task.allowAutomaticTasks`), `assets/lib/leaflet/images/public/fonts/fa-brand-400.orc`, code obfusqué dans `assets/controllers/booking_checkout_controller.js`, retrait de `/.vscode/` du `.gitignore`. La documentation de sécurité avait été supprimée juste avant (`4ac8d94`). Fusionné dans `master` par la PR #69. La CI a échoué sur ce contrôle, le CD n’a donc pas déployé ; la production servait encore la version saine au 05/11. |
| 05/11/2026 | branche `fix/securite-nettoyage` | Nouveau retrait, `/.vscode/` remis dans le `.gitignore`, hooks activés par `composer install`, `CODEOWNERS`, script de détection pour les postes. |

Le code revient parce qu’**un poste de développement infecté le réécrit** dans les fichiers du projet, puis il est commité avec le reste. Nettoyer le dépôt ne suffit pas : il faut nettoyer les postes.

## Dans le dépôt

Les hooks versionnés sont activés automatiquement par `composer install` (script `hooks-git`) ; à défaut :

```sh
git config core.hooksPath .githooks
```

Le hook `pre-commit` contrôle le contenu de l’index Git, même si la copie de travail a été nettoyée après l’indexation. Le hook `pre-push` contrôle les versions effectivement envoyées. La CI répète le contrôle avant l’installation des dépendances, et le CD ne déploie que si la CI est verte.

Les configurations personnelles `.vscode/` et `.idea/`, les faux répertoires de polices et les fichiers `.orc` sont exclus de Git. Le contrôle continue de les détecter si un ajout forcé réintroduit les emplacements interdits, une tâche automatique ou une charge utile connue.

Vérification manuelle :

```sh
php bin/check-repo-integrity.php
php bin/check-repo-integrity.php --staged
php bin/check-repo-integrity.php --ref=HEAD
php bin/phpunit tests/Security/RepoIntegrityTest.php
```

## Réglages GitHub (administrateur du dépôt)

Le contrôle ne sert à rien si une PR peut être fusionnée alors qu’il est rouge (cas de la PR #69). Dans *Settings > Branches > master* :

- *Require a pull request before merging*, avec au moins 1 approbation et *Dismiss stale approvals* ;
- *Require review from Code Owners* (voir `.github/CODEOWNERS`) ;
- *Require status checks to pass* : « Qualité & tests » ;
- *Do not allow bypassing the above settings* ;
- pas de push direct ni de force-push.

## Sur chaque poste de développement

1. **Ne pas ouvrir le projet dans VS Code** tant que le poste n’a pas été vérifié ; sinon en mode restreint. Dans les paramètres *utilisateur* de VS Code : `"task.allowAutomaticTasks": "off"`, et garder la confiance des espaces de travail activée.
2. Lancer la détection (lecture seule, ne modifie rien) :
   ```powershell
   powershell -ExecutionPolicy Bypass -File bin\detecter-infection.ps1
   ```
3. Si une trace est trouvée, ou si le projet a été ouvert dans VS Code depuis le 22/10 : considérer le poste comme compromis. Analyse hors ligne Microsoft Defender, idéalement réinstallation.
4. **Depuis un autre appareil, sain** : changer les mots de passe (GitHub, e-mail, Plesk), révoquer les jetons GitHub, clés SSH et sessions, puis changer les secrets du projet (`APP_SECRET`, clés Stripe, mot de passe SSH du CD, base de données, OAuth).
5. Recloner le dépôt dans un dossier neuf après la fusion du nettoyage, puis rebaser les branches en cours sur `master` (ne jamais fusionner une ancienne branche dans `master`).

Ne jamais exécuter les anciennes tâches ni les fichiers de fausses polices présents dans l’historique Git.
