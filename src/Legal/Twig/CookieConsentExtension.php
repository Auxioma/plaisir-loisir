<?php

declare(strict_types=1);

namespace App\Legal\Twig;

use App\Legal\Enum\CookieCategory;
use App\Legal\Service\CookieConsentService;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Bandeau cookies (12/10) : {{ cookie_banner_needed() }} et
 * {{ cookie_allows('statistics') }} dans les gabarits.
 *
 * Sans jeton tm_consent, aucune requête : le bandeau est simplement affiché.
 */
final class CookieConsentExtension extends AbstractExtension
{
    public function __construct(
        private readonly CookieConsentService $consents,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cookie_banner_needed', $this->bannerNeeded(...)),
            new TwigFunction('cookie_allows', $this->allows(...)),
        ];
    }

    public function bannerNeeded(): bool
    {
        $request = $this->requestStack->getMainRequest();

        return null !== $request && $this->consents->shouldPrompt($request);
    }

    public function allows(string $category): bool
    {
        $request = $this->requestStack->getMainRequest();
        $cat = CookieCategory::tryFrom($category);

        return null !== $request && null !== $cat && $this->consents->allows($request, $cat);
    }
}
