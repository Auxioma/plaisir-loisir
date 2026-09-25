<?php

declare(strict_types=1);

namespace App\Shared\Service;

use App\Messaging\Repository\MessageRepository;
use App\Notification\Repository\NotificationRepository;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Repository\ProviderProfileRepository;
use App\Quote\Repository\ServiceRequestRepository;
use App\Review\Repository\ReviewRepository;
use App\User\Entity\User;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Compteurs des badges (messages, notifications, demandes et avis pro),
 * calculés UNE fois par requête HTTP.
 *
 * Le header (UnreadCountsExtension) et le menu du compte
 * (AccountIdentityPresenter) affichent les mêmes badges sur la même page :
 * chacun lançait ses propres requêtes, et le profil prestataire était relu
 * trois fois — onze requêtes sur n'importe quelle page connectée, rien que
 * pour ces pastilles. Les résultats sont gardés en mémoire pour la durée de
 * la requête ; `reset()` (appelé par Symfony entre deux requêtes, et entre
 * deux messages d'un worker Messenger) les oublie.
 */
final class AccountBadgeCounter implements ResetInterface
{
    /**
     * Par instance d'utilisateur : la table d'identité de Doctrine garantit
     * un seul objet User par compte au sein d'une requête.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $cache = [];

    public function __construct(
        private readonly MessageRepository $messages,
        private readonly NotificationRepository $notifications,
        private readonly ProviderProfileRepository $providerProfiles,
        private readonly ServiceRequestRepository $serviceRequests,
        private readonly ReviewRepository $reviews,
    ) {
    }

    public function unreadMessages(User $user): int
    {
        return $this->remember($user, 'messages', fn (): int => $this->messages->countUnreadForUser($user));
    }

    public function unreadNotifications(User $user): int
    {
        return $this->remember($user, 'notifications', fn (): int => $this->notifications->countUnread($user));
    }

    /**
     * Demandes ouvertes du métier du prestataire qu'il n'a pas encore
     * devisées ; 0 pour un client (pas de ProviderProfile).
     */
    public function pendingProviderRequests(User $user): int
    {
        return $this->remember($user, 'requests', function () use ($user): int {
            $profile = $this->providerProfile($user);
            $category = $profile?->getMainCategory();

            if (null === $profile || null === $category) {
                return 0;
            }

            return $this->serviceRequests->countOpenUnquotedForProvider($category, $profile);
        });
    }

    public function pendingProviderReviews(User $user): int
    {
        return $this->remember($user, 'reviews', function () use ($user): int {
            $profile = $this->providerProfile($user);

            return null !== $profile ? $this->reviews->countAwaitingReplyForProvider($profile) : 0;
        });
    }

    public function reset(): void
    {
        $this->cache = [];
    }

    private function providerProfile(User $user): ?ProviderProfile
    {
        return $this->remember($user, 'profile', fn (): ?ProviderProfile => $this->providerProfiles->findOneByUser($user));
    }

    /**
     * @template T
     *
     * @param callable(): T $compute
     *
     * @return T
     */
    private function remember(User $user, string $key, callable $compute): mixed
    {
        $userKey = spl_object_id($user);

        if (!\array_key_exists($key, $this->cache[$userKey] ?? [])) {
            $this->cache[$userKey][$key] = $compute();
        }

        return $this->cache[$userKey][$key];
    }
}
