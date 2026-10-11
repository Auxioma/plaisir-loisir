<?php

declare(strict_types=1);

namespace App\Stats\Service;

use App\Catalog\Entity\Service;
use App\PrivateActivity\Entity\PrivateActivity;
use App\Provider\Entity\ProviderProfile;
use App\Stats\Entity\PageView;
use App\Stats\Entity\UniqueView;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Ulid;

/**
 * Compte une consultation publique (fiche pro ou fiche activité) pour les
 * statistiques de l'espace professionnel, et les vues des activités
 * gratuites entre membres (11/10). Les robots et l'auteur qui consulte sa
 * propre page ne sont pas comptés.
 *
 * UNE PERSONNE = UNE VUE (11/10, demande du client) : revenir sur la même
 * page ne recompte rien. Le membre connecté est reconnu par son compte, le
 * visiteur anonyme par son adresse IP et son navigateur (voir UniqueView).
 */
final class PageViewRecorder
{
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|preview|headless|curl|wget|python|monitor/i';
    private const SOCIAL_HOSTS = ['facebook.', 'instagram.', 'linkedin.', 'twitter.', 't.co', 'x.com', 'tiktok.', 'youtube.', 'pinterest.', 'whatsapp.'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
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

    /**
     * Activité gratuite entre membres : pas de professionnel ni de
     * statistiques détaillées, seulement le compteur affiché sur les cartes.
     */
    public function recordPrivateActivity(Request $request, PrivateActivity $activity, ?object $viewer): void
    {
        if ((null !== $viewer && $viewer === $activity->getOrganizer()) || $this->isBot($request)) {
            return;
        }

        if (null === $activity->getId() || !$this->firstVisit($request, UniqueView::KIND_PRIVATE_ACTIVITY, $activity->getId(), $viewer)) {
            return;
        }

        $this->entityManager->createQuery('UPDATE '.PrivateActivity::class.' a SET a.viewsCount = a.viewsCount + 1 WHERE a.id = :id')
            ->setParameter('id', $activity->getId(), 'ulid')
            ->execute();
        $this->entityManager->refresh($activity);
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

        if ($this->isBot($request)) {
            return;
        }

        $target = null !== $service ? $service->getId() : $provider->getId();
        if (null === $target || !$this->firstVisit($request, $kind, $target, $viewer)) {
            return;
        }

        $this->entityManager->persist(new PageView($provider, $service, $kind, $this->sourceOf($request)));
        $this->entityManager->flush();

        // Compteur dénormalisé du tri « Les plus vues » : incrément atomique
        // en base, pour ne pas perdre de vues entre deux visites simultanées.
        if (null !== $service) {
            $this->entityManager->createQuery('UPDATE '.Service::class.' s SET s.viewsCount = s.viewsCount + 1 WHERE s.id = :id')
                ->setParameter('id', $service->getId(), 'ulid')
                ->execute();
            $this->entityManager->refresh($service);
        }
    }

    /**
     * Note le passage de ce visiteur sur cette page ; faux s'il était déjà
     * venu. L'insertion « ON CONFLICT DO NOTHING » tranche aussi le cas de
     * deux onglets ouverts au même instant.
     */
    private function firstVisit(Request $request, string $kind, Ulid $target, ?object $viewer): bool
    {
        $identity = $viewer instanceof User && null !== $viewer->getId()
            ? 'user:'.$viewer->getId()->toBase32()
            : 'anon:'.$request->getClientIp().'|'.$request->headers->get('user-agent', '');

        $inserted = $this->entityManager->getConnection()->executeStatement(
            'INSERT INTO unique_view (id, kind, target_id, visitor, viewed_at) VALUES (?, ?, ?, ?, ?) ON CONFLICT (kind, target_id, visitor) DO NOTHING',
            [(new Ulid())->toRfc4122(), $kind, $target->toRfc4122(), hash_hmac('sha256', $identity, $this->secret), (new \DateTimeImmutable())->format('Y-m-d H:i:sP')],
        );

        return 1 === $inserted;
    }

    private function isBot(Request $request): bool
    {
        return 1 === preg_match(self::BOT_PATTERN, (string) $request->headers->get('user-agent', ''));
    }
}
