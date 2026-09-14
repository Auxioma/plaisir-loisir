<?php

declare(strict_types=1);

namespace App\Payment\Enum;

/**
 * États d'un abonnement professionnel, calqués sur ceux d'un abonnement
 * Stripe (§17.2 du CDC : « suivi de l'abonnement »).
 */
enum SubscriptionStatus: string
{
    /** En attente du premier paiement (session de paiement ouverte, pas encore confirmée). */
    case Incomplete = 'incomplete';

    case Active = 'active';

    /** Un paiement a échoué ; Stripe relance avant résiliation automatique. */
    case PastDue = 'past_due';

    case Cancelled = 'cancelled';
}
