# Liaison PayPal des prestataires

L'application utilise PayPal Partner Referrals v2 pour la liaison et Orders v2 pour le paiement direct au marchand. Une adresse e-mail seule ne valide plus un compte lorsque les identifiants PayPal réels sont configurés.

## Configuration

1. Obtenir l'approbation PayPal Complete Payments pour une plateforme partenaire et activer les autorisations de paiement des marchands. Une application REST standard sans accès partenaire ne suffit pas.
2. Renseigner dans les secrets du déploiement ou `.env.local` :

   ```dotenv
   PAYPAL_MODE=sandbox
   PAYPAL_CLIENT_ID=<client ID de l’application partenaire>
   PAYPAL_CLIENT_SECRET=<secret de la même application>
   PAYPAL_PARTNER_ID=<Merchant ID du compte partenaire>
   PAYPAL_PARTNER_ATTRIBUTION_ID=<BN code fourni par PayPal>
   PAYPAL_WEBHOOK_ID=<ID du webhook de cette application>
   ```

3. Dans Apps & Credentials de cette application PayPal, créer le webhook HTTPS `https://<domaine>/webhook/paypal`, puis sélectionner `MERCHANT.ONBOARDING.COMPLETED` et `MERCHANT.PARTNER-CONSENT.REVOKED`. Reporter son ID dans `PAYPAL_WEBHOOK_ID`. L'application vérifie les signatures via l'API PayPal ; elle rejette un événement non vérifié.
4. Appliquer la migration `Version20261021100000` avant de charger le nouveau code. Sur une base dont l'historique des migrations est à jour : `php bin/console doctrine:migrations:migrate --no-interaction`.
5. Dans Paramètres → Paiement → Modifier mes paramètres, cliquer sur « Relier mon compte PayPal ». Autoriser la liaison chez PayPal et revenir sur le site. Le serveur vérifie le marchand à partir du tracking ID enregistré, son e-mail confirmé, sa capacité à recevoir des paiements et les permissions accordées à notre client ID.
6. Tester avec deux comptes Business sandbox distincts et un acheteur sandbox : paiement au premier marchand, déliaison, refus des nouvelles réservations PayPal sur l'ancien compte, nouvelle liaison au second marchand, puis paiement au second marchand. Un paiement déjà commencé conserve son bénéficiaire initial, comme Stripe.
7. Pour passer en production, remplacer ensemble le mode, les clés, le Merchant ID partenaire, le BN code et l'ID du webhook par leurs valeurs live, puis répéter le parcours.

La déliaison supprime le compte bénéficiaire et désactive PayPal sur TrouveMoi. Le compte PayPal lui-même reste actif. Toute nouvelle liaison passe à nouveau par PayPal. Le tracking ID change à chaque reprise et est supprimé lors de la déliaison : un ancien retour ne réactive pas le compte.

Sans clés réelles, le comportement de simulation existant reste disponible en développement/test, jamais en production. Les tests HTTP simulés ne remplacent pas la recette sandbox avec l'application partenaire approuvée.

Documentation officielle : [onboarding avant paiement](https://developer.paypal.com/platforms/seller-onboarding/before-payment), [en-têtes des appels partenaires](https://developer.paypal.com/api/make-api-requests).
