<?php

declare(strict_types=1);

namespace App\Event\Controller;

use App\Event\Service\GroupDraftService;
use App\Event\StaticGroupWizard;
use App\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Flow « Créer un groupe » : quatre étapes déclinées par l'URL, plus l'écran
 * de succès. Même principe que l'assistant d'événement — et même défaut
 * jusqu'au 21/08 : les étapes s'enchaînaient par de simples liens, sans
 * formulaire, et l'écran de succès s'affichait sans qu'aucun groupe n'ait été
 * créé.
 */
final class GroupWizardController extends AbstractController
{
    private const LAST_STEP = 4;

    public function __construct(
        private readonly GroupDraftService $draft,
    ) {
    }

    #[Route(path: ['fr' => '/evenements/groupes/creer/succes', 'en' => '/en/events/groups/create/success'], name: 'app_group_create_success')]
    public function success(): Response
    {
        return $this->render('event/group/succes.html.twig');
    }

    #[Route(path: ['fr' => '/evenements/groupes/creer/{etape}', 'en' => '/en/events/groups/create/{etape}'], name: 'app_group_create', requirements: ['etape' => '[1-4]'], defaults: ['etape' => 1], methods: ['GET', 'POST'])]
    public function create(Request $request, int $etape): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleStep($request, $etape);
        }

        return $this->render('event/group/creer.html.twig', [
            'step' => $etape,
            'advice' => StaticGroupWizard::advice($etape),
            'tags' => StaticGroupWizard::tags(),
            'cities' => StaticGroupWizard::cities(),
            'draft' => $this->draft->current($request->getSession()),
        ]);
    }

    /**
     * Redirection après envoi : rafraîchir la page ne doit pas créer un second
     * groupe.
     */
    private function handleStep(Request $request, int $etape): Response
    {
        $session = $request->getSession();

        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de recommencer cette étape.');

            return $this->redirectToRoute('app_group_create', ['etape' => $etape]);
        }

        $this->draft->merge($session, $request->request->all());

        if (self::LAST_STEP !== $etape) {
            return $this->redirectToRoute('app_group_create', ['etape' => $etape + 1]);
        }

        $user = $this->getUser();
        [$group, $erreurs] = $this->draft->publish($session, $user instanceof User ? $user : null);

        if (null === $group) {
            foreach ($erreurs as $erreur) {
                $this->addFlash('error', $erreur);
            }

            return $this->redirectToRoute('app_group_create', ['etape' => $etape]);
        }

        $this->addFlash('success', sprintf('Votre groupe « %s » a été créé.', $group->getName()));

        return $this->redirectToRoute('app_group_create_success');
    }
}
