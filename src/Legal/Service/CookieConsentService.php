<?php

declare(strict_types=1);

namespace App\Legal\Service;

use App\Legal\Entity\CookieConsent;
use App\Legal\Enum\CookieCategory;
use App\Legal\Enum\LegalDocumentType;
use App\Legal\Repository\CookieConsentRepository;
use App\Legal\Repository\LegalDocumentRepository;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bandeau de cookies : mémorise le choix du visiteur et le retrouve.
 *
 * Le jeton de visiteur est déposé dans un cookie technique de treize mois. Ce
 * cookie-là ne demande pas de consentement : il ne sert qu'à se souvenir de la
 * réponse, y compris d'un refus, ce que la CNIL range parmi les traceurs
 * strictement nécessaires.
 */
final class CookieConsentService
{
    /** Nom du cookie qui porte le jeton de visiteur. */
    public const TOKEN_COOKIE = 'tm_consent';

    /** Durée de conservation, alignée sur la recommandation CNIL. */
    private const LIFETIME_MONTHS = 13;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CookieConsentRepository $consents,
        private readonly LegalDocumentRepository $documents,
    ) {
    }

    /**
     * Faut-il afficher le bandeau ?
     *
     * Oui tant qu'aucune décision valide n'a été prise, et de nouveau lorsque
     * la précédente a dépassé treize mois.
     */
    public function shouldPrompt(Request $request): bool
    {
        $consent = $this->currentConsent($request);

        return null === $consent || $consent->isExpired();
    }

    public function currentConsent(Request $request): ?CookieConsent
    {
        $token = $request->cookies->get(self::TOKEN_COOKIE);

        if (!\is_string($token) || '' === $token) {
            return null;
        }

        return $this->consents->findLatestForVisitor($token);
    }

    /**
     * Enregistre une décision et renvoie le cookie à déposer sur la réponse.
     *
     * @param list<CookieCategory> $accepted
     *
     * @return array{0: CookieConsent, 1: Cookie}
     */
    public function decide(Request $request, array $accepted, ?User $user = null): array
    {
        $token = $request->cookies->get(self::TOKEN_COOKIE);

        if (!\is_string($token) || 32 !== \strlen($token)) {
            // bin2hex(random_bytes(16)) : 32 caractères hexadécimaux, sans
            // aucune donnée dérivée du visiteur.
            $token = bin2hex(random_bytes(16));
        }

        $policy = $this->documents->findCurrent(LegalDocumentType::CookiePolicy, $request->getLocale());

        $consent = new CookieConsent();
        $consent->setVisitorToken($token)
            ->setUser($user)
            ->setAcceptedCategories($accepted)
            ->setPolicyVersion($policy?->getVersion())
            ->setIpAddress($request->getClientIp());

        $this->entityManager->persist($consent);
        $this->entityManager->flush();

        $cookie = Cookie::create(self::TOKEN_COOKIE)
            ->withValue($token)
            ->withExpires(new \DateTimeImmutable(sprintf('+%d months', self::LIFETIME_MONTHS)))
            ->withPath('/')
            ->withSecure($request->isSecure())
            // Inaccessible au JavaScript : ce jeton n'a aucune raison d'être lu
            // côté navigateur, et l'exposer en ferait une cible inutile.
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX);

        return [$consent, $cookie];
    }

    /**
     * Refus de tout ce qui est facultatif.
     *
     * @return array{0: CookieConsent, 1: Cookie}
     */
    public function refuseAll(Request $request, ?User $user = null): array
    {
        return $this->decide($request, [], $user);
    }

    /**
     * Acceptation de toutes les catégories.
     *
     * @return array{0: CookieConsent, 1: Cookie}
     */
    public function acceptAll(Request $request, ?User $user = null): array
    {
        return $this->decide($request, CookieCategory::optional(), $user);
    }

    /**
     * Le visiteur autorise-t-il cette catégorie ?
     *
     * Défaut : non. En l'absence de décision, rien de facultatif ne se dépose.
     */
    public function allows(Request $request, CookieCategory $category): bool
    {
        if (!$category->isOptional()) {
            return true;
        }

        $consent = $this->currentConsent($request);

        return null !== $consent && !$consent->isExpired() && $consent->accepts($category);
    }

    /**
     * Rattache au compte les décisions prises avant la connexion.
     */
    public function linkToUser(Request $request, User $user): void
    {
        $consent = $this->currentConsent($request);

        if (null !== $consent && null === $consent->getUser()) {
            $consent->setUser($user);
            $this->entityManager->flush();
        }
    }

    public function responseWithCookie(Response $response, Cookie $cookie): Response
    {
        $response->headers->setCookie($cookie);

        return $response;
    }
}
