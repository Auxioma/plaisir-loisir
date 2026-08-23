<?php

declare(strict_types=1);

namespace App\Event\Controller;

use App\Event\Service\EventDraftService;
use App\Event\StaticEventWizard;
use App\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Flow « Créer un événement » : UN layout wizard (stepper / formulaire /
 * aperçu live) décliné en 8 étapes via l'URL, + l'écran de succès.
 * L'onglet « Inviter via un lien » de l'étape 7 est un onglet client
 * (assets/events.js), pas une page.
 *
 * CÂBLAGE DU 21/08 : jusqu'ici les huit étapes s'enchaînaient par de simples
 * liens, sans le moindre formulaire — on remplissait, on cliquait « Suivant »,
 * et rien n'était conservé. L'écran de succès s'affichait sans qu'aucun
 * événement n'ait été créé.
 *
 * La saisie est désormais accumulée d'une étape à l'autre, et le dernier écran
 * crée réellement l'événement.
 */
final class EventWizardController extends AbstractController
{
    private const LAST_STEP = 8;

    public function __construct(
        private readonly EventDraftService $draft,
    ) {
    }

    #[Route(path: ['fr' => '/evenements/creer/succes', 'en' => '/en/events/create/success'], name: 'app_event_create_success')]
    public function success(): Response
    {
        return $this->render('event/succes.html.twig');
    }

    #[Route(path: ['fr' => '/evenements/creer/{etape}', 'en' => '/en/events/create/{etape}'], name: 'app_event_create', requirements: ['etape' => '[1-8]'], defaults: ['etape' => 1], methods: ['GET', 'POST'])]
    public function create(Request $request, int $etape): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleStep($request, $etape);
        }

        return $this->render('event/creer.html.twig', [
            'step' => $etape,
            'steps' => StaticEventWizard::steps(),
            'advice' => StaticEventWizard::advice($etape),
            'categories' => StaticEventWizard::categories(),
            'contacts' => StaticEventWizard::contacts(),
            // Ce qui a déjà été saisi, pour que les champs se retrouvent
            // remplis quand on revient en arrière.
            'draft' => $this->draft->current($request->getSession()),
            // À partir de l'étape 2 l'aperçu a la photo ; à partir de la 7
            // les vraies métadonnées sont propagées (spec étapes 7-8).
            'preview_photo' => $etape >= 2,
            'preview_filled' => $etape >= 7,
        ]);
    }

    /**
     * Enregistre l'étape et passe à la suivante.
     *
     * Redirection après envoi, jamais de rendu direct : sans cela, rafraîchir
     * la page renverrait le formulaire et l'on créerait deux événements.
     */
    private function handleStep(Request $request, int $etape): Response
    {
        $session = $request->getSession();

        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de recommencer cette étape.');

            return $this->redirectToRoute('app_event_create', ['etape' => $etape]);
        }

        $this->draft->merge($session, $request->request->all());

        if (self::LAST_STEP !== $etape) {
            // L'étape 1 porte un bouton « Publier l'événement » qui, dans la
            // maquette, mène à l'étape 2 : on conserve ce comportement.
            return $this->redirectToRoute('app_event_create', ['etape' => $etape + 1]);
        }

        $user = $this->getUser();
        [$event, $erreurs] = $this->draft->publish($session, $user instanceof User ? $user : null);

        if (null === $event) {
            foreach ($erreurs as $erreur) {
                $this->addFlash('error', $erreur);
            }

            return $this->redirectToRoute('app_event_create', ['etape' => $etape]);
        }

        $this->addFlash('success', sprintf('Votre événement « %s » a été publié.', $event->getTitle()));

        return $this->redirectToRoute('app_event_create_success');
    }
}
