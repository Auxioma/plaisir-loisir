<?php

declare(strict_types=1);

namespace App\Shared\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Lien d'invitation signé (retours client du 07/10 : inviter par lien,
 * partage et QR code aux activités et événements PRIVÉS).
 *
 * La clé est un HMAC du secret applicatif (aucune colonne en base) ; ouvrir
 * le lien mémorise l'accès dans la session : on peut consulter la page, se
 * connecter, puis répondre. Portée : « private-activity », « event ».
 */
final class InviteLink
{
    private const SESSION_KEY = 'invite_link_access';

    public function __construct(
        #[Autowire('%kernel.secret%')] private readonly string $secret,
        private readonly RequestStack $requests,
    ) {
    }

    public function key(string $scope, string $id): string
    {
        return substr(hash_hmac('sha256', $scope.':'.$id, $this->secret), 0, 24);
    }

    /** Clé valide : l'accès est mémorisé pour la session. */
    public function redeem(string $scope, string $id, string $key): bool
    {
        if ('' === $key || !hash_equals($this->key($scope, $id), $key)) {
            return false;
        }

        $session = $this->requests->getSession();
        $granted = (array) $session->get(self::SESSION_KEY, []);
        $granted[$scope.':'.$id] = true;
        $session->set(self::SESSION_KEY, $granted);

        return true;
    }

    public function hasAccess(string $scope, string $id): bool
    {
        $request = $this->requests->getCurrentRequest();
        if (null === $request || !$request->hasSession()) {
            return false;
        }

        return isset(((array) $request->getSession()->get(self::SESSION_KEY, []))[$scope.':'.$id]);
    }
}
