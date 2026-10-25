# Contrôle d’intégrité du dépôt

Sur chaque clone de développement, activer les contrôles Git versionnés :

```sh
git config core.hooksPath .githooks
```

Le hook `pre-commit` contrôle le contenu de l’index Git, même si la copie de travail a été nettoyée après l’indexation. Le hook `pre-push` contrôle les versions effectivement envoyées. La CI répète le contrôle avant l’installation des dépendances.

Les configurations personnelles `.vscode/` et `.idea/`, les faux répertoires de polices et les fichiers `.orc` sont exclus de Git. Le contrôle continue de les détecter si un ajout forcé réintroduit les emplacements interdits, une tâche automatique ou une charge utile connue.

Vérification manuelle :

```sh
php bin/check-repo-integrity.php
php bin/check-repo-integrity.php --staged
php bin/check-repo-integrity.php --ref=HEAD
php bin/phpunit tests/Security/RepoIntegrityTest.php
```

Ces contrôles protègent le dépôt contre les motifs détectés ; ils ne désinfectent pas un poste ou une extension d’éditeur qui modifierait de nouveau les fichiers. Ne jamais exécuter les anciennes tâches ni les fichiers de fausses polices présents dans l’historique Git.
