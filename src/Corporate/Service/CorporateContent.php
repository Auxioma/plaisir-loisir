<?php

declare(strict_types=1);

namespace App\Corporate\Service;

use App\Corporate\Entity\JobOffer;
use App\Corporate\Entity\PageBlock;
use App\Corporate\Enum\PageBlockSection;
use App\Corporate\Repository\JobOfferRepository;
use App\Corporate\Repository\PageBlockRepository;

/**
 * Contenu des pages institutionnelles lu en base (09/11), rendu dans la forme
 * qu'attendaient les gabarits du temps de StaticCorporate.
 */
final class CorporateContent
{
    private const FALLBACK_PHOTO = 'images/account/avatar-default.svg';

    public function __construct(
        private readonly PageBlockRepository $blocks,
        private readonly JobOfferRepository $jobs,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function section(PageBlockSection $section): array
    {
        return array_map(static fn (PageBlock $b): array => [
            'title' => $b->getTitle(),
            'name' => $b->getTitle(),
            'text' => (string) $b->getText(),
            'quote' => (string) $b->getText(),
            'author' => $b->getAuthor() ?? $b->getTitle(),
            'role' => (string) $b->getRole(),
            'photo' => $b->getImage() ?? self::FALLBACK_PHOTO,
            'icon' => $b->getIcon() ?? 'star',
            'tone' => $b->getTone() ?? 'blue',
            'featured' => $b->isFeatured(),
        ], $this->blocks->findPublished($section));
    }

    /**
     * @return list<array<string, string>>
     */
    public function jobs(?string $department = null): array
    {
        return array_map($this->card(...), $this->jobs->findPublished($department));
    }

    /**
     * @return list<string>
     */
    public function departments(): array
    {
        return $this->jobs->publishedDepartments();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function jobDetail(string $slug): ?array
    {
        $job = $this->jobs->findPublishedBySlug($slug);
        if (null === $job) {
            return null;
        }

        return $this->card($job) + [
            'manager' => (string) $job->getManager(),
            'division' => (string) $job->getDivision(),
            'mission' => JobOffer::lines($job->getMissions()),
            'description' => JobOffer::lines($job->getRoleDescription()),
            'education' => JobOffer::lines($job->getEducation()),
            'experience' => JobOffer::lines($job->getExperience()),
            'place' => $job->getCity(),
            'note' => 'Soyez parmi les premiers à postuler',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function card(JobOffer $job): array
    {
        return [
            'slug' => $job->getSlug(),
            'icon' => $job->getIcon() ?? 'monitor',
            'tone' => $job->getTone() ?? 'blue',
            'title' => $job->getTitle(),
            'text' => $job->getSummary(),
            'city' => $job->getCity(),
            'contract' => $job->getContract(),
            'time' => $job->getWorkTime(),
            'dept' => $job->getDepartment(),
        ];
    }
}
