<?php

declare(strict_types=1);

namespace App\Review\Presenter;

use App\Review\Entity\Review;
use App\Review\Enum\ReviewStatus;
use App\Review\Repository\ReviewRepository;
use App\Stats\Service\PlatformFigures;

/**
 * Bandeau « Ce que disent les voyageurs » des pages Destinations, avec de
 * VRAIS avis (retours client du 07/10 : plus de faux chiffres). Il
 * affichait jusque-là deux avis inventés et « Basé sur 999.589 avis ».
 */
final class ReviewPresenter
{
    public function __construct(
        private readonly ReviewRepository $reviews,
        private readonly PlatformFigures $figures,
    ) {
    }

    /**
     * @return array{rating: ?float, count: int, cards: list<array<string, mixed>>}
     */
    public function band(int $limit = 2): array
    {
        $figures = $this->figures->all();
        $latest = $this->reviews->findBy(['status' => ReviewStatus::Published], ['createdAt' => 'DESC'], $limit * 3);
        // Un avis avec commentaire se lit mieux dans le bandeau.
        $latest = \array_slice(array_values(array_filter($latest, static fn (Review $r): bool => '' !== trim((string) $r->getComment()))), 0, $limit);

        return [
            'rating' => $figures['rating'],
            'count' => $figures['reviews'],
            'cards' => array_map(self::card(...), $latest),
        ];
    }

    /** @return array<string, mixed> format de activity/_review_card.html.twig */
    private static function card(Review $review): array
    {
        $author = $review->getAuthor();
        $comment = (string) $review->getComment();

        return [
            'id' => (string) $review->getId(),
            'stars' => $review->getRating(),
            'title' => $review->getService()?->getTitle() ?? 'Avis vérifié',
            'text' => $comment,
            'author' => trim($author?->getFirstName().' '.mb_substr((string) $author?->getLastName(), 0, 1).'.'),
            'meta' => $author?->getMainAddress()?->getCity() ?? 'Client vérifié',
            'date' => $review->getCreatedAt(),
            'avatar' => $author?->getAvatarPath() ?? 'images/account/avatar-default.svg',
            'reportable' => true,
        ];
    }
}
