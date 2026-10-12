<?php

declare(strict_types=1);

namespace App\Shared\Analytics;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Événements Google Analytics décidés côté serveur avant une redirection
 * (12/10) : inscription, formulaire envoyé… Mis en attente en session, ils
 * sont rendus dans la page suivante ({{ ga_queued_events() }} dans base) puis
 * envoyés par assets/analytics.js si le visiteur a accepté la mesure.
 *
 * Pas dans les « flash » : le gabarit des messages les afficherait en toast.
 */
final class AnalyticsEventQueue extends AbstractExtension
{
    private const KEY = '_ga_events';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    /** @param array<string, mixed> $params */
    public function push(string $name, array $params = []): void
    {
        $session = $this->requestStack->getCurrentRequest()?->hasSession() ? $this->requestStack->getSession() : null;
        if (null === $session) {
            return;
        }
        $events = (array) $session->get(self::KEY, []);
        $events[] = ['name' => $name, 'params' => $params];
        $session->set(self::KEY, \array_slice($events, -10));
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('ga_queued_events', $this->pull(...))];
    }

    /** @return list<array{name: string, params: array<string, mixed>}> */
    public function pull(): array
    {
        $request = $this->requestStack->getMainRequest();
        if (null === $request || !$request->hasPreviousSession()) {
            return [];
        }
        $session = $request->getSession();
        $events = (array) $session->get(self::KEY, []);
        if ([] !== $events) {
            $session->remove(self::KEY);
        }

        return array_values($events);
    }
}
