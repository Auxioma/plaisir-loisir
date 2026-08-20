<?php

declare(strict_types=1);

namespace App\Legal\Service;

use App\Legal\Entity\LegalDocument;
use App\Legal\Enum\LegalDocumentType;
use App\Legal\Repository\LegalDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Publication et consultation des textes juridiques.
 */
final class LegalDocumentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LegalDocumentRepository $documents,
    ) {
    }

    public function current(LegalDocumentType $type, string $locale = 'fr'): ?LegalDocument
    {
        return $this->documents->findCurrent($type, $locale);
    }

    /**
     * Publie une nouvelle version.
     *
     * On refuse d'écraser une version existante : si le numéro est déjà pris,
     * c'est une erreur d'appel, pas une mise à jour. Le seul moyen de corriger
     * un texte publié est d'en publier un suivant.
     */
    public function publish(
        LegalDocumentType $type,
        string $version,
        string $title,
        string $content,
        string $locale = 'fr',
        ?string $changeSummary = null,
        bool $requiresReacceptance = false,
        ?\DateTimeImmutable $effectiveAt = null,
    ): LegalDocument {
        if (null !== $this->documents->findOneByVersion($type, $version, $locale)) {
            throw new \InvalidArgumentException(sprintf('La version « %s » du document « %s » (%s) existe déjà. Un texte publié ne se modifie pas : publiez une nouvelle version.', $version, $type->value, $locale));
        }

        $document = new LegalDocument();
        $document->setType($type)
            ->setLocale($locale)
            ->setVersion($version)
            ->setTitle($title)
            ->setContent($content)
            ->setChangeSummary($changeSummary)
            ->setRequiresReacceptance($requiresReacceptance)
            ->publish($effectiveAt);

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        return $document;
    }
}
