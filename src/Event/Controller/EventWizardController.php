<?php

declare(strict_types=1);

namespace App\Event\Controller;

use App\Event\StaticEventWizard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Flow « Créer un événement » : UN layout wizard (stepper / formulaire /
 * aperçu live) décliné en 8 étapes via l'URL, + l'écran de succès.
 * L'onglet « Inviter via un lien » de l'étape 7 est un onglet client
 * (assets/events.js), pas une page.
 */
final class EventWizardController extends AbstractController
{
    #[Route('/evenements/creer/succes', name: 'app_event_create_success')]
    public function success(): Response
    {
        return $this->render('event/succes.html.twig');
    }

    #[Route('/evenements/creer/{etape}', name: 'app_event_create', requirements: ['etape' => '[1-8]'], defaults: ['etape' => 1])]
    public function create(int $etape): Response
    {
        return $this->render('event/creer.html.twig', [
            'step' => $etape,
            'steps' => StaticEventWizard::steps(),
            'advice' => StaticEventWizard::advice($etape),
            'categories' => StaticEventWizard::categories(),
            'contacts' => StaticEventWizard::contacts(),
            // À partir de l'étape 2 l'aperçu a la photo ; à partir de la 7
            // les vraies métadonnées sont propagées (spec étapes 7-8).
            'preview_photo' => $etape >= 2,
            'preview_filled' => $etape >= 7,
        ]);
    }
}
