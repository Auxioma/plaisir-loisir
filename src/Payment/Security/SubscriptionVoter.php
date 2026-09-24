<?php

declare(strict_types=1);

namespace App\Payment\Security;

use App\Payment\Entity\Subscription;
use App\Provider\Repository\ProviderProfileRepository;
use App\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur un abonnement professionnel (§4 du CDC : des données
 * financières, réservées au titulaire — jamais consultables ou modifiables
 * par un autre prestataire, même sur son propre compte).
 *
 * - VIEW / MANAGE : le prestataire titulaire de l'abonnement, et lui seul.
 *
 * @extends Voter<string, Subscription>
 */
final class SubscriptionVoter extends Voter
{
    public const VIEW = 'VIEW';
    public const MANAGE = 'MANAGE';

    public function __construct(
        private readonly ProviderProfileRepository $providerProfiles,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Subscription && \in_array($attribute, [self::VIEW, self::MANAGE], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        return $this->providerProfiles->findOneByUser($user) === $subject->getProvider();
    }
}
