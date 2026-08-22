<?php

declare(strict_types=1);

namespace App\Legal\Service;

use App\Legal\Entity\LegalAcceptance;
use App\Legal\Entity\LegalDocument;
use App\Legal\Enum\ConsentSource;
use App\Legal\Enum\LegalDocumentType;
use App\Legal\Repository\LegalAcceptanceRepository;
use App\Legal\Repository\LegalDocumentRepository;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Recueil et vérification du consentement aux textes juridiques.
 *
 * NON `final`, à dessein : ce service est injecté dans RegistrationService,
 * dont les tests le remplacent par une doublure — PHPUnit ne sait pas doubler
 * une classe finale. C'est la seule raison ; rien n'invite à en hériter.
 */
class ConsentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LegalDocumentRepository $documents,
        private readonly LegalAcceptanceRepository $acceptances,
    ) {
    }

    /**
     * Enregistre l'acceptation des documents exigés à l'inscription.
     *
     * Tolérant par construction : si aucun texte n'est encore publié en base,
     * on n'enregistre rien et l'inscription se poursuit. Bloquer la création
     * d'un compte parce que les CGU n'ont pas encore été saisies serait
     * disproportionné — et c'est exactement l'état du projet aujourd'hui.
     *
     * @return list<LegalAcceptance>
     */
    public function recordRegistrationConsent(User $user, ?Request $request = null, string $locale = 'fr'): array
    {
        $recorded = [];

        foreach (LegalDocumentType::requiredAtRegistration() as $type) {
            $document = $this->documents->findCurrent($type, $locale);

            if (null === $document) {
                continue;
            }

            $recorded[] = $this->record($document, $user, ConsentSource::Registration, $request, false);
        }

        if ([] !== $recorded) {
            $this->entityManager->flush();
        }

        return $recorded;
    }

    /**
     * Enregistre une acceptation isolée.
     */
    public function record(
        LegalDocument $document,
        User $user,
        ConsentSource $source = ConsentSource::Registration,
        ?Request $request = null,
        bool $flush = true,
    ): LegalAcceptance {
        $acceptance = new LegalAcceptance();
        $acceptance->setUser($user)
            ->setDocument($document)
            ->setSource($source)
            ->setIpAddress($request?->getClientIp())
            ->setUserAgent($request?->headers->get('User-Agent'));

        $this->entityManager->persist($acceptance);

        if ($flush) {
            $this->entityManager->flush();
        }

        return $acceptance;
    }

    /**
     * Documents en vigueur que l'utilisateur n'a pas encore acceptés et dont la
     * ré-acceptation est exigée.
     *
     * Sert à afficher, à la connexion suivante, la demande d'accord sur une
     * nouvelle version des CGU.
     *
     * @return list<LegalDocument>
     */
    public function pendingReacceptance(User $user, string $locale = 'fr'): array
    {
        $pending = [];

        foreach (LegalDocumentType::requiredAtRegistration() as $type) {
            $document = $this->documents->findCurrent($type, $locale);

            if (null === $document || !$document->requiresReacceptance()) {
                continue;
            }

            if (!$this->acceptances->hasAccepted($user, $document)) {
                $pending[] = $document;
            }
        }

        return $pending;
    }
}
