<?php

declare(strict_types=1);

namespace App\Quote\Security;

use App\Provider\Repository\ProviderProfileRepository;
use App\Quote\Entity\Quote;
use App\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur un devis proposé (§4, §33.2 du CDC).
 *
 * - VIEW   : le client qui a posté la demande, ou le prestataire qui a
 *            proposé ce devis précis.
 * - DECIDE : accepter/refuser — réservé au client, propriétaire de la demande.
 *
 * @extends Voter<string, Quote>
 */
final class QuoteVoter extends Voter
{
    public const VIEW = 'VIEW';
    public const DECIDE = 'DECIDE';

    public function __construct(
        private readonly ProviderProfileRepository $providerProfiles,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Quote && \in_array($attribute, [self::VIEW, self::DECIDE], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $isOwner = $subject->getServiceRequest()?->getClient() === $user;

        return match ($attribute) {
            self::DECIDE => $isOwner,
            self::VIEW => $isOwner || $this->providerProfiles->findOneByUser($user) === $subject->getProvider(),
            default => false,
        };
    }
}
