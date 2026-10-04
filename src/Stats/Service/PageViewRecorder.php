<?php

declare(strict_types=1);

namespace App\Stats\Service;

use App\Catalog\Entity\Service;
use App\Provider\Entity\ProviderProfile;
use App\Stats\Entity\PageView;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Compte une consultation publique (fiche pro ou fiche activité) pour les
 * statistiques de l'espace professionnel. Les robots et le professionnel
 * qui consulte sa propre page ne sont pas comptés.
 */
final class PageViewRecorder
{
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|preview|headless|curl|wget|python|monitor/i';
    private const SOCIAL_HOSTS = ['facebook.', 'instagram.', 'linkedin.', 'twitter.', 't.co', 'x.com', 'tiktok.', 'youtube.', 'pinterest.', 'whatsapp.'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function recordProfile(Request $request, ProviderProfile $provider, ?object $viewer): void
    {
        $this->record($request, $provider, null, PageView::KIND_PROFILE, $viewer);
    }

    public function recordActivity(Request $request, Service $service, ?object $viewer): void
    {
        $provider = $service->getProvider();
        if (null !== $provider) {
            $this->record($request, $provider, $service, PageView::KIND_ACTIVITY, $viewer);
        }
    }

    public function sourceOf(Request $request): string
    {
        $referer = (string) $request->headers->get('referer', '');
        if ('' === $referer) {
            return 'direct';
        }

        $host = (string) parse_url($referer, \PHP_URL_HOST);
        if ('' === $host || $host === $request->getHost()) {
            return 'search';
        }

        foreach (self::SOCIAL_HOSTS as $social) {
            if (str_contains($host, $social)) {
                return 'social';
            }
        }

        return str_contains($host, 'google.') || str_contains($host, 'bing.') || str_contains($host, 'qwant.') ? 'other' : 'partner';
    }

    private function record(Request $request, ProviderProfile $provider, ?Service $service, string $kind, ?object $viewer): void
    {
        if (null !== $viewer && $viewer === $provider->getUser()) {
            return;
        }

        if (1 === preg_match(self::BOT_PATTERN, (string) $request->headers->get('user-agent', ''))) {
            return;
        }

        $this->entityManager->persist(new PageView($provider, $service, $kind, $this->sourceOf($request)));
        $this->entityManager->flush();
    }
}
