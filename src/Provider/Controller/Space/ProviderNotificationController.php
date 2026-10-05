<?php

declare(strict_types=1);

namespace App\Provider\Controller\Space;

use App\Notification\Service\NotificationFeed;
use App\Notification\Service\NotificationService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Notifications dans l'espace professionnel (05/10). Avant, la cloche de
 * l'en-tête envoyait le professionnel sur /compte/notifications : la
 * coquille de l'espace particulier, avec un menu pro recopié.
 */
final class ProviderNotificationController extends AbstractProviderSpaceController
{
    #[Route(path: ['fr' => '/pro/notifications', 'en' => '/en/pro/notifications'], name: 'app_pro_notifications', methods: ['GET'])]
    public function index(Request $request, NotificationFeed $feed): Response
    {
        return $this->renderSpace('provider/space/notifications.html.twig', '', $feed->page($this->currentUser(), $request));
    }

    #[Route(path: ['fr' => '/pro/notifications/tout-marquer-lu', 'en' => '/en/pro/notifications/mark-all-read'], name: 'app_pro_notifications_mark_all_read', methods: ['POST'])]
    public function markAllRead(Request $request, NotificationService $notifications): Response
    {
        if ($this->csrfOk($request)) {
            $notifications->markAllAsRead($this->currentUser());
        }

        return $this->redirectToRoute('app_pro_notifications');
    }
}
