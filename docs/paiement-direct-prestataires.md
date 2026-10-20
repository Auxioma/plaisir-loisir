# Paiement direct aux prestataires (20/10)

Maquettes : `docs/maquettes/parametre_configuration_paiement_prestataire.jpeg`,
`reservation_confirmee_client.jpeg`, `reservation_confirmee_prestataire.jpeg`,
`flux_reservation_paiment.jpeg`.

## Principe

Le client paie **directement** le prestataire ; rien ne transite par le compte de
TrouveMoi, **aucune commission** (décision du 20/10 : la ligne « frais de service
7 % » de la maquette n'est pas reprise).

| Moyen | Mécanisme | Confirmation |
|---|---|---|
| Stripe CB | Stripe Connect, compte **Standard** du pro ; session Checkout créée *sur son compte* (`stripe_account`, « direct charge ») | au retour du client **et** par webhook Connect → réservation confirmée |
| PayPal | API Orders v2, `payee.email_address` = adresse PayPal du pro | capture au retour du client → réservation confirmée |
| Espèces sur place | demande envoyée au pro | le pro confirme, puis déclare le règlement |

Facture / reçu PDF (`FACT-AAAA-NNNNN`) pour le client et le pro dès que c'est réglé ;
QR de réservation → page de contrôle pro `/pro/reservations/{id}/controle`.

Sans clés : Stripe et PayPal sont **simulés** en dev/test, **indisponibles en prod**
(jamais de faux paiement en production).

## Configuration Stripe (une fois, par TrouveMoi)

1. Activer Connect : https://dashboard.stripe.com/test/connect (puis en mode live).
   Type de compte : **Standard**. Renseigner nom, logo, couleurs de la plateforme.
2. Clés : https://dashboard.stripe.com/test/apikeys → `STRIPE_SECRET_KEY=sk_test_…`
   (et `STRIPE_PUBLISHABLE_KEY`) dans `.env.local` du serveur.
3. Webhooks (https://dashboard.stripe.com/test/webhooks), URL `https://trouvemoi.eu/webhook/stripe` :
   - point d'écoute « Votre compte » (existant : abonnements, bons cadeaux) → `STRIPE_WEBHOOK_SECRET`
   - **nouveau** point d'écoute « Comptes connectés », événements
     `checkout.session.completed` et `account.updated` → `STRIPE_CONNECT_WEBHOOK_SECRET`

Chaque pro clique ensuite « Relier mon compte Stripe » dans
`/pro/parametres?section=paiement` et termine son inscription chez Stripe.

## Configuration PayPal (une fois, par TrouveMoi)

1. https://developer.paypal.com/dashboard/applications → créer une application REST
   (doc API : https://developer.paypal.com/api/rest).
2. `.env.local` : `PAYPAL_CLIENT_ID`, `PAYPAL_CLIENT_SECRET`, `PAYPAL_MODE=sandbox`
   (puis `live` avec les identifiants live).
3. Chaque pro saisit l'adresse e-mail de **son** compte PayPal (Business conseillé).

## Déploiement

```bash
composer install --no-dev -o            # nouvelle dépendance : dompdf/dompdf
php bin/console doctrine:migrations:migrate -n   # Version20261020091255
php bin/console app:i18n:import          # nouvelles traductions EN
# + variables STRIPE_CONNECT_WEBHOOK_SECRET, PAYPAL_* dans .env.local
```

## Limites connues

- Remboursement : à faire par le pro depuis son compte Stripe / PayPal (la page
  l'indique quand une réservation payée est annulée).
- Facture : document établi par TrouveMoi au nom du pro (mentions SIRET / TVA tirées
  de son identité légale) — à faire valider (mentions obligatoires) par le client.
- Bon cadeau sur une activité pro : toujours à trancher (voir CLAUDE.md).
