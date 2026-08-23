<?php

declare(strict_types=1);

namespace App\I18n\Twig;

use App\I18n\Routing\LocaleUrlGenerator;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Fonctions Twig liees a la langue de l'URL.
 *
 * - locale_switch_path('en') : la page courante, en anglais
 * - locale_alternates()      : les adresses absolues par langue (hreflang)
 * - group_tab('membres')     : le segment d'URL de l'onglet dans la langue
 *                              courante ('members' en anglais)
 */
final class LocaleExtension extends AbstractExtension
{
    public function __construct(
        private readonly LocaleUrlGenerator $urls,
        private readonly RequestStack $requests,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('locale_switch_path', $this->switchPath(...)),
            new TwigFunction('locale_alternates', $this->alternates(...)),
            new TwigFunction('group_tab', $this->groupTab(...)),
        ];
    }

    public function switchPath(string $locale): string
    {
        $request = $this->requests->getCurrentRequest();

        return null === $request ? '/' : $this->urls->switchTo($request, $locale);
    }

    /**
     * @return array<string, string>
     */
    public function alternates(): array
    {
        $request = $this->requests->getCurrentRequest();

        return null === $request ? [] : $this->urls->alternates($request);
    }

    public function groupTab(string $key): string
    {
        $request = $this->requests->getCurrentRequest();

        return LocaleUrlGenerator::tabSegment($key, $request?->getLocale() ?? 'fr');
    }
}
