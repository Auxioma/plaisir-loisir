<?php

declare(strict_types=1);

namespace App\Provider\Controller\Space;

use App\Event\Repository\EventRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Événements organisés par le professionnel (brouillons compris), dans son
 * espace (05/10) — auparavant seulement visibles dans l'espace particulier.
 */
final class ProviderEventController extends AbstractProviderSpaceController
{
    #[Route(path: ['fr' => '/pro/evenements', 'en' => '/en/pro/events'], name: 'app_pro_events', methods: ['GET'])]
    public function index(EventRepository $events): Response
    {
        return $this->renderSpace('provider/space/events.html.twig', '', [
            'events' => $events->findByOrganizer($this->currentUser()),
        ]);
    }
}
