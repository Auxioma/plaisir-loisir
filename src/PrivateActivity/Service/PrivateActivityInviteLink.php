<?php

declare(strict_types=1);

namespace App\PrivateActivity\Service;

use App\PrivateActivity\Entity\PrivateActivity;
use App\Shared\Service\InviteLink;

/**
 * Lien d'invitation d'une activité PRIVÉE (07/10) : une activité privée
 * n'était visible que des membres invités un par un ; le lien signé
 * (InviteLink) ouvre l'annonce à qui le reçoit.
 */
final class PrivateActivityInviteLink
{
    private const SCOPE = 'private-activity';

    public function __construct(private readonly InviteLink $links)
    {
    }

    public function key(PrivateActivity $activity): string
    {
        return $this->links->key(self::SCOPE, (string) $activity->getId());
    }

    public function redeem(PrivateActivity $activity, string $key): bool
    {
        return $this->links->redeem(self::SCOPE, (string) $activity->getId(), $key);
    }

    public function hasAccess(PrivateActivity $activity): bool
    {
        return $this->links->hasAccess(self::SCOPE, (string) $activity->getId());
    }
}
