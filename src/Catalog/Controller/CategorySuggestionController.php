<?php

declare(strict_types=1);

namespace App\Catalog\Controller;

use App\Catalog\Service\ActivityDraftService;
use App\Catalog\Service\CategorySuggestionService;
use App\PrivateActivity\Service\PrivateActivityDraftService;
use App\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Ma catégorie n'existe pas » — envoyé depuis l'étape 1 des assistants de
 * création (activité entre membres ou activité pro, 05/10). Le formulaire de
 * l'étape est posté ici en entier (bouton `formaction`) : la saisie déjà
 * faite est gardée, puis on revient sur l'étape.
 */
final class CategorySuggestionController extends AbstractController
{
    #[Route(path: ['fr' => '/categories/proposer', 'en' => '/en/categories/suggest'], name: 'app_category_suggest', methods: ['POST'])]
    public function suggest(Request $request, CategorySuggestionService $suggestions, PrivateActivityDraftService $privateDrafts, ActivityDraftService $proDrafts): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $pro = 'pro' === $request->request->get('suggest_context') && $this->isGranted('ROLE_PROVIDER');
        $back = $pro ? $this->generateUrl('app_pro_activity_wizard', ['etape' => 1]) : $this->generateUrl('app_account_private_activity_wizard', ['etape' => 1]);
        if (!$this->isCsrfTokenValid($pro ? 'activity_wizard' : 'private_activity_wizard', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

            return $this->redirect($back);
        }

        // La saisie de l'étape 1 n'est pas perdue.
        $pro ? $proDrafts->submitStep($request->getSession(), 1, $request) : $privateDrafts->submitStep($request->getSession(), 1, $request);
        $patch = static fn (array $v) => $pro ? $proDrafts->patch($request->getSession(), $v) : $privateDrafts->patch($request->getSession(), $v);

        try {
            $result = $suggestions->suggest((string) $request->request->get('suggest_name', ''), (string) $request->request->get('suggest_description', ''), $user, $pro ? 'pro' : 'private');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirect($back.'#categorie');
        }

        if ('existing' === $result['status']) {
            $patch(['category' => $result['category']->getSlug(), 'category_suggestion' => null]);
            $this->addFlash('success', sprintf('La catégorie « %s » existe déjà : elle est sélectionnée.', $result['category']->getName()));
        } else {
            $patch(['category_suggestion' => $result['suggestion']->getName()]);
            $this->addFlash('success', sprintf('Catégorie « %s » proposée : notre équipe la valide sous 48 h et vous prévient. Enregistrez votre activité en brouillon pour la reprendre ensuite.', $result['suggestion']->getName()));
        }

        return $this->redirect($back.'#categorie');
    }
}
