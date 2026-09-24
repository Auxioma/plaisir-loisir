<?php

declare(strict_types=1);

namespace App\Payment\Stripe;

/**
 * Résultat du démarrage d'un abonnement : où rediriger le prestataire, à
 * quel client Stripe l'abonnement sera rattaché, et s'il est déjà actif.
 *
 * `alreadyActive` distingue les deux implémentations sans que
 * SubscriptionService ait à connaître laquelle est en service :
 *   - Stripe réel : false — l'abonnement reste INCOMPLETE tant que le
 *     webhook `checkout.session.completed` ne l'a pas confirmé.
 *   - Mock : true — il n'y a pas de webhook à attendre, l'abonnement est
 *     déjà considéré actif au retour de cet appel.
 */
final readonly class SubscriptionCheckoutResult
{
    public function __construct(
        public string $stripeCustomerId,
        public string $redirectUrl,
        public bool $alreadyActive,
        public ?string $stripeSubscriptionId = null,
    ) {
    }
}
