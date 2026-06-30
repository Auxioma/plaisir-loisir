<?php

declare(strict_types=1);

namespace App\Review\Service;

use App\Review\Entity\Review;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Modération des avis : approbation/rejet et réponse de l'annonceur.
 */
final class ReviewModerationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function approve(Review $review): void
    {
        $review->approve();
        $this->entityManager->flush();
    }

    public function reject(Review $review): void
    {
        $review->reject();
        $this->entityManager->flush();
    }

    /**
     * @throws \InvalidArgumentException si l'auteur n'est pas l'annonceur de l'activité notée
     */
    public function reply(Review $review, User $author, string $text): void
    {
        $owner = $review->getService()?->getProvider()?->getUser();
        if ($owner !== $author) {
            throw new \InvalidArgumentException('Seul l\'annonceur de l\'activité peut répondre à l\'avis.');
        }

        $review->reply($text);
        $this->entityManager->flush();
    }
}
