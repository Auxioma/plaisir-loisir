<?php

declare(strict_types=1);

namespace App\Corporate\Service;

use App\Corporate\CorporateDefaults;
use App\Corporate\Entity\JobOffer;
use App\Corporate\Entity\PageBlock;
use App\Corporate\Enum\PageBlockSection;
use App\Corporate\Repository\JobOfferRepository;
use App\Corporate\Repository\PageBlockRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Charge le contenu de départ des pages institutionnelles (09/11).
 *
 * RÈGLE : une section n'est remplie que si elle est VIDE. Relancer l'import
 * ne duplique rien et n'écrase jamais ce qui a été saisi ou supprimé depuis
 * le back-office — d'où l'appel sans risque à chaque déploiement.
 */
final class CorporateContentImporter
{
    public function __construct(
        private readonly PageBlockRepository $blocks,
        private readonly JobOfferRepository $jobs,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return array<string, int> nombre de lignes créées, par section
     */
    public function import(bool $dryRun = false): array
    {
        $created = [];

        foreach (CorporateDefaults::blocks() as $key => $rows) {
            $section = PageBlockSection::from($key);
            if ($this->blocks->countSection($section) > 0) {
                $created[$section->label()] = 0;
                continue;
            }

            foreach ($rows as $i => $row) {
                $block = (new PageBlock())
                    ->setSection($section)
                    ->setPosition(($i + 1) * 10)
                    ->setTitle($row['title'])
                    ->setText($row['text'] ?? null)
                    ->setAuthor($row['author'] ?? null)
                    ->setRole($row['role'] ?? null)
                    ->setImage($row['image'] ?? null)
                    ->setIcon($row['icon'] ?? null)
                    ->setTone($row['tone'] ?? null)
                    ->setFeatured($row['featured'] ?? false);
                if (!$dryRun) {
                    $this->em->persist($block);
                }
            }
            $created[$section->label()] = \count($rows);
        }

        $created['Offres d’emploi'] = 0;
        if (0 === $this->jobs->count([])) {
            foreach (CorporateDefaults::jobs() as $i => $row) {
                $detail = CorporateDefaults::jobDetail($row['slug']);
                $job = (new JobOffer())
                    ->setSlug($row['slug'])
                    ->setTitle($row['title'])
                    ->setSummary($row['text'])
                    ->setCity($row['city'])
                    ->setContract($row['contract'])
                    ->setWorkTime($row['time'])
                    ->setDepartment($row['dept'])
                    ->setIcon($row['icon'])
                    ->setTone($row['tone'])
                    ->setPosition(($i + 1) * 10)
                    ->setManager($detail['manager'] ?? null)
                    ->setDivision($detail['division'] ?? null)
                    ->setMissions(implode("\n", $detail['mission'] ?? []))
                    ->setRoleDescription(implode("\n", $detail['description'] ?? []))
                    ->setEducation(implode("\n", $detail['education'] ?? []))
                    ->setExperience(implode("\n", $detail['experience'] ?? []));
                if (!$dryRun) {
                    $this->em->persist($job);
                }
                ++$created['Offres d’emploi'];
            }
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        return $created;
    }
}
