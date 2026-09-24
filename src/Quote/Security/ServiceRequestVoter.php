<?php

declare(strict_types=1);

namespace App\Quote\Security;

use App\Provider\Enum\ProviderStatus;
use App\Provider\Repository\ProviderProfileRepository;
use App\Quote\Entity\ServiceRequest;
use App\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur une demande de devis (§4, §33.2 du CDC : « le masquage d'un
 * bouton dans Twig ne constitue jamais une autorisation suffisante »).
 *
 * - VIEW         : le client qui l'a postée, ou tout titulaire d'un dossier
 *                  prestataire — vérifié ou non. Même double verrou que
 *                  partout ailleurs dans Provider (voir CLAUDE.md) : le rôle
 *                  ouvre l'espace professionnel (ici, consulter la demande
 *                  avant de décider d'y répondre), la vérification ouvre le
 *                  droit d'agir (SUBMIT_QUOTE).
 * - MANAGE       : accepter/refuser un devis reçu — réservé au client qui a
 *                  posté la demande.
 * - SUBMIT_QUOTE : répondre par un devis — réservé aux prestataires VÉRIFIÉS.
 *                  Le reste (demande déjà close, devis déjà déposé) est une
 *                  règle métier, pas un droit d'accès : elle reste dans
 *                  QuoteService, qui la fait déjà respecter.
 *
 * @extends Voter<string, ServiceRequest>
 */
final class ServiceRequestVoter extends Voter
{
    public const VIEW = 'VIEW';
    public const MANAGE = 'MANAGE';
    public const SUBMIT_QUOTE = 'SUBMIT_QUOTE';

    public function __construct(
        private readonly ProviderProfileRepository $providerProfiles,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof ServiceRequest
            && \in_array($attribute, [self::VIEW, self::MANAGE, self::SUBMIT_QUOTE], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::MANAGE => $subject->getClient() === $user,
            self::VIEW => $subject->getClient() === $user || null !== $this->providerProfiles->findOneByUser($user),
            self::SUBMIT_QUOTE => $this->isVerifiedProvider($user),
            default => false,
        };
    }

    private function isVerifiedProvider(User $user): bool
    {
        $profile = $this->providerProfiles->findOneByUser($user);

        return null !== $profile && ProviderStatus::Verified === $profile->getStatus();
    }
}
