<?php

declare(strict_types=1);

namespace App\PrivateActivity\Controller;

use App\Event\Service\EventDraftService;
use App\PrivateActivity\Service\PrivateActivityDraftService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Créer une activité gratuite » — retours client du 07/10.
 *
 * Un particulier crée TOUJOURS gratuitement ; seuls les professionnels
 * déposent des activités payantes. Le bouton mène ici :
 *  - visiteur : choix Particulier (connexion client) / Professionnel
 *    (connexion prestataire) — et non plus la page « Devenir partenaire » ;
 *  - professionnel : son assistant de dépôt (espace pro) ;
 *  - membre : 1. activité gratuite ou événement gratuit, 2. visibilité
 *    (public, privé, membres), puis l'assistant correspondant, la
 *    visibilité déjà choisie.
 */
final class CreateFreeController extends AbstractController
{
    private const TYPES = ['activite', 'evenement'];
    /** Visibilité choisie => valeur de l'activité gratuite / de l'événement. */
    private const VISIBILITIES = [
        'public' => ['activity' => 'public', 'event' => 'public'],
        'prive' => ['activity' => 'private', 'event' => 'private'],
        'membres' => ['activity' => 'members_only', 'event' => 'members'],
    ];

    #[Route(path: ['fr' => '/creer-une-activite-gratuite', 'en' => '/en/create-a-free-activity'], name: 'app_create_free', methods: ['GET'])]
    public function __invoke(Request $request, PrivateActivityDraftService $activityDrafts, EventDraftService $eventDrafts): Response
    {
        if ($this->isGranted('ROLE_PROVIDER')) {
            return $this->redirectToRoute('app_pro_activities_new');
        }

        // « Particulier » : la connexion, puis retour ici (cible mémorisée par le pare-feu).
        if ($request->query->getBoolean('particulier')) {
            $this->denyAccessUnlessGranted('ROLE_USER');

            return $this->redirectToRoute('app_create_free');
        }

        if (!$this->isGranted('ROLE_USER')) {
            return $this->render('private_activity/create_free.html.twig', ['step' => 'who']);
        }

        $type = (string) $request->query->get('type', '');
        if (!\in_array($type, self::TYPES, true)) {
            return $this->render('private_activity/create_free.html.twig', ['step' => 'type', 'type' => '']);
        }

        $visibility = (string) $request->query->get('visibilite', '');
        if (!\array_key_exists($visibility, self::VISIBILITIES)) {
            return $this->render('private_activity/create_free.html.twig', ['step' => 'visibility', 'type' => $type]);
        }

        $session = $request->getSession();
        if ('evenement' === $type) {
            $eventDrafts->clear($session);
            $eventDrafts->patch($session, ['visibility' => self::VISIBILITIES[$visibility]['event']]);

            return $this->redirectToRoute('app_event_create', ['etape' => 1]);
        }

        $activityDrafts->clear($session);
        $activityDrafts->patch($session, ['visibility' => self::VISIBILITIES[$visibility]['activity']]);

        return $this->redirectToRoute('app_account_private_activity_wizard', ['etape' => 1]);
    }
}
