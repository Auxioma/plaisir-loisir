<?php

declare(strict_types=1);

namespace App\Review\Event;

use App\Review\Entity\Review;

/**
 * Événement de domaine émis lorsqu'un avis vient d'être publié.
 */
final class ReviewAdded
{
    public function __construct(
        private readonly Review $review,
    ) {
    }

    public function getReview(): Review
    {
        return $this->review;
    }
}
