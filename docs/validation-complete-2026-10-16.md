# Validation de gafour-v3 — 16 octobre 2026

## Environnement

- Branche `gafour-v3`, avec les corrections client en cours dans le répertoire de travail.
- PostgreSQL 16 dans le conteneur Docker du projet, port local fixe `5432`.
- Base dédiée `app_validation_1610_test`, créée avec le schéma des entités V3 et toutes les fixtures, comme dans la CI. La base de développement n'a pas été purgée.
- PHP 8.4.26 et PHPUnit 13.1.14.

## Contrôles terminés

| Contrôle | Résultat |
| --- | --- |
| Composer : validation stricte | Réussi |
| Jetons CSS | Réussi, 73 jetons déclarés |
| Intégrité du dépôt | Réussi, 1 223 fichiers suivis contrôlés |
| Syntaxe Twig | Réussie, 224 templates |
| Syntaxe YAML | Réussie, 34 fichiers |
| PHPStan, configuration du projet | Aucune erreur |
| Style PHP | 540 fichiers contrôlés ; un ordre d'imports corrigé dans HomeController, puis revérifié |
| Injection de services Symfony | Réussie |
| Syntaxe JavaScript du projet hors bibliothèques tierces | Aucune erreur |
| Compilation AssetMapper | Réussie, 345 assets |

## Navigateur

Chromium a ouvert l'accueil, le choix « Déposer une activité » et une fiche gratuite, en 1 600 × 900 puis en 390 × 844. Les six consultations répondent en HTTP 200, sans débordement horizontal ni erreur JavaScript détectée.

Sur la page de dépôt à 1 600 × 900, le bas du footer se trouve exactement à 900 pixels : aucune bande blanche ne reste dessous. La barre de débogage Symfony est exclue de cette mesure.

La fiche gratuite affiche quatre cartes d'informations. Un texte de matériel long, avec saut de ligne, a été injecté uniquement dans la copie de base utilisée pour le navigateur : il reste dans un bloc séparé et ne déforme pas les quatre cartes.

Les ressources HTTPS externes ont été bloquées pour cette vérification locale : le rendu des fonds OpenStreetMap et la collecte Analytics externe ne sont pas validés par ces captures. Le bandeau de consentement reste affiché dans les captures.

Résultats détaillés : `var/browser-check/results.json`. Captures : `var/browser-check/1600-create.png`, `1600-home.png`, `1600-detail.png`, et leurs équivalents `390-*`.

## Suite fonctionnelle et unitaire

**689 tests réussis, 2 481 assertions, aucune erreur, aucun échec et aucun test ignoré.** Code de sortie PHPUnit : `0`. Durée de l'exécution complète avec journal détaillé : environ 16 minutes 30 secondes.

Journal détaillé : `var/validation-phpunit-debug.log`. Rapport JUnit : `var/validation-junit.xml`.

Le serveur PHP lancé pour les vérifications navigateur a été arrêté. Les assets compilés uniquement pour ces vérifications ont été retirés pour rendre à AssetMapper son fonctionnement de développement habituel. Les captures, journaux et bases de test dédiées sont conservés.

## Limites et point de déploiement

- La validation du schéma de la base de développement `app` échoue : elle n'est pas encore synchronisée avec les entités de la V3. Cela ne modifie pas le résultat des tests sur la base dédiée, mais doit être traité avant d'utiliser la V3 sur cette base. Aucun changement de schéma n'y a été appliqué pendant cette validation.
- Les tests Stripe utilisent les doublures prévues par le projet. Aucun débit réel, remboursement réel ni versement vers un prestataire n'a été exécuté.
- L'identifiant GA4 local est vide : aucune réception effective des événements dans la propriété Analytics du client n'a été confirmée.
- Le paiement direct à un compte prestataire et les billets individuels avec scan à usage unique restent absents des parcours examinés ; une suite verte ne signifie pas que ces fonctionnalités existent.
