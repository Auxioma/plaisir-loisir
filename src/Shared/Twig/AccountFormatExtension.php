<?php

declare(strict_types=1);

namespace App\Shared\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Intl\Countries;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Mise en forme des dates et montants de l'espace compte (maquettes
 * profil_particulier, 30/09) : « 25 mai 2024 », « MAI », « 89,00 € ».
 *
 * La langue suit celle de la page (dans l'URL), pas celle du serveur.
 */
final class AccountFormatExtension extends AbstractExtension
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('pf_date', $this->date(...)),
            new TwigFilter('pf_month', $this->month(...)),
            new TwigFilter('pf_money', $this->money(...)),
            new TwigFilter('pf_country', $this->country(...)),
        ];
    }

    /**
     * @param string $pattern motif ICU : 'd MMMM y' (25 mai 2024), 'd MMM y', 'HH:mm'…
     */
    public function date(?\DateTimeInterface $date, string $pattern = 'd MMMM y'): string
    {
        if (null === $date) {
            return '—';
        }

        return (string) \IntlDateFormatter::formatObject($date, $pattern, $this->locale());
    }

    /** Mois abrégé en capitales, sans point : « MAI », « JUIN ». */
    public function month(?\DateTimeInterface $date): string
    {
        return null === $date ? '' : mb_strtoupper(rtrim($this->date($date, 'MMM'), '.'));
    }

    public function money(float|int|string|null $amount, string $currency = 'EUR'): string
    {
        if (null === $amount || '' === $amount) {
            return 'Gratuit';
        }

        $formatter = new \NumberFormatter($this->locale(), \NumberFormatter::CURRENCY);

        return (string) $formatter->formatCurrency((float) $amount, $currency);
    }

    /** Nom du pays dans la langue de la page : « FR » → « France ». */
    public function country(?string $code): string
    {
        if (null === $code || !Countries::exists($code)) {
            return (string) $code;
        }

        return Countries::getName($code, $this->locale());
    }

    private function locale(): string
    {
        return $this->requestStack->getCurrentRequest()?->getLocale() ?? 'fr';
    }
}
