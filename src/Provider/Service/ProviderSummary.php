<?php

declare(strict_types=1);

namespace App\Provider\Service;

use App\PrivateActivity\Service\MemberReputation;
use App\Provider\Entity\ProviderProfile;
use App\Review\Enum\ReviewStatus;
use App\Review\Repository\ReviewRepository;

/**
 * Résumé public d'un professionnel (13/10) : ce que montrent l'encadré
 * « Votre organisateur » de la fiche activité et l'en-tête de sa fiche
 * professionnelle, sur le modèle du profil membre. Chiffres réels uniquement.
 */
final class ProviderSummary
{
    public function __construct(
        private readonly ProviderDirectory $directory,
        private readonly ReviewRepository $reviews,
        private readonly MemberReputation $reputation,
    ) {
    }

    /**
     * @return array{card: array<string, mixed>, rating: ?float, reviews_count: int, positive_rate: ?int, activities: int, since: ?\DateTimeImmutable, response_hours: ?float}
     */
    public function of(ProviderProfile $provider): array
    {
        $card = $this->directory->card($provider);
        $published = array_values(array_filter(
            $this->reviews->findForProvider($provider),
            static fn ($review): bool => ReviewStatus::Published === $review->getStatus(),
        ));
        $count = max((int) $card['reviews'], \count($published));
        $positive = [] !== $published
            ? (int) round(100 * \count(array_filter($published, static fn ($r): bool => $r->getRating() >= 4)) / \count($published))
            : null;
        $user = $provider->getUser();

        return [
            'card' => $card,
            'rating' => $card['rating'] ?? $this->reviews->averageRatingForProvider($provider),
            'reviews_count' => $count,
            'positive_rate' => $positive,
            'activities' => \count($card['activities']),
            'since' => $provider->getCreatedAt(),
            'response_hours' => null !== $user ? $this->reputation->of($user)['response_hours'] : null,
        ];
    }
}
