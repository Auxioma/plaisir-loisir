<?php

declare(strict_types=1);

namespace App\Provider\Controller\Space;

use App\Catalog\Entity\Service;
use App\Catalog\Enum\ServiceStatus;
use App\Catalog\Repository\CategoryRepository;
use App\Catalog\Repository\ServiceRepository;
use App\Catalog\Service\ActivityDraftService;
use App\Catalog\Service\ActivityPublishingService;
use App\Catalog\StaticActivityWizard;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

/**
 * Assistant « Créer une activité » de l'espace pro (05/10) : 6 étapes
 * validées côté serveur, aperçu de la carte du catalogue, brouillon, envoi
 * en validation. Remplace le formulaire d'une page, qui ne couvrait qu'une
 * partie de ce que la fiche publique affiche.
 *
 * Boutons (champ `action`) : « next » valide et avance, « prev » recule en
 * gardant la saisie, « draft » enregistre en brouillon, « submit » (dernière
 * étape) enregistre et envoie en validation — ou enregistre les
 * modifications d'une activité déjà en ligne.
 */
final class ProviderActivityWizardController extends AbstractProviderSpaceController
{
    public function __construct(
        private readonly ActivityDraftService $drafts,
        private readonly ActivityPublishingService $publishing,
        private readonly CategoryRepository $categories,
        private readonly ServiceRepository $services,
    ) {
    }

    #[Route(path: ['fr' => '/pro/activites/nouvelle', 'en' => '/en/pro/activities/new'], name: 'app_pro_activities_new', methods: ['GET'])]
    public function new(Request $request): Response
    {
        // Un brouillon non enregistré en cours est repris, sauf demande explicite.
        $current = $this->drafts->current($request->getSession());
        if ($request->query->getBoolean('vierge') || null !== $current['id']) {
            $this->drafts->start($request->getSession());
        }

        return $this->redirectToRoute('app_pro_activity_wizard', ['etape' => 1]);
    }

    #[Route(path: ['fr' => '/pro/activites/{id}/modifier', 'en' => '/en/pro/activities/{id}/edit'], name: 'app_pro_activities_edit', methods: ['GET'])]
    public function edit(string $id, Request $request): Response
    {
        $service = $this->own($id);
        if (ServiceStatus::Suspended === $service->getStatus()) {
            $this->addFlash('error', 'Cette activité est suspendue par l’équipe TrouveMoi : contactez le support pour la débloquer.');

            return $this->redirectToRoute('app_pro_activities');
        }
        $this->drafts->load($request->getSession(), $service);

        return $this->redirectToRoute('app_pro_activity_wizard', ['etape' => 1]);
    }

    #[Route(path: ['fr' => '/pro/activites/assistant/{etape}', 'en' => '/en/pro/activities/wizard/{etape}'], name: 'app_pro_activity_wizard', requirements: ['etape' => '[1-6]'], methods: ['GET', 'POST'])]
    public function step(int $etape, Request $request): Response
    {
        $session = $request->getSession();
        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('activity_wizard', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Votre session a expiré, merci de recommencer cette étape.');

                return $this->redirectToRoute('app_pro_activity_wizard', ['etape' => $etape]);
            }

            $action = (string) $request->request->get('action', 'next');
            $errors = $this->drafts->submitStep($session, $etape, $request);

            if ('prev' === $action) {
                return $this->redirectToRoute('app_pro_activity_wizard', ['etape' => max(1, $etape - 1)]);
            }

            if ('draft' === $action) {
                $draft = $this->drafts->current($session);
                if (mb_strlen((string) ($draft['title'] ?? '')) < 5 || null === $this->categories->findOneBy(['slug' => (string) ($draft['category'] ?? '')])) {
                    $this->addFlash('error', 'Donnez au moins un titre et une catégorie (étape 1) pour enregistrer un brouillon.');
                } else {
                    $service = $this->drafts->persist($draft, $this->currentProvider());
                    $this->drafts->load($session, $service);
                    $this->addFlash('success', ServiceStatus::Published === $service->getStatus()
                        ? sprintf('Modifications de « %s » enregistrées.', $service->getTitle())
                        : sprintf('Brouillon « %s » enregistré : retrouvez-le dans « Mes activités ».', $service->getTitle()));

                    return $this->redirectToRoute('app_pro_activity_wizard', ['etape' => $etape]);
                }
            } elseif ([] === $errors) {
                if (ActivityDraftService::STEPS !== $etape) {
                    return $this->redirectToRoute('app_pro_activity_wizard', ['etape' => $etape + 1]);
                }

                [$invalid, $stepErrors] = $this->drafts->firstInvalidStep($this->drafts->current($session));
                if (null !== $invalid) {
                    $this->addFlash('error', sprintf('Complétez l’étape %d avant l’envoi : %s', $invalid, reset($stepErrors)));

                    return $this->redirectToRoute('app_pro_activity_wizard', ['etape' => $invalid]);
                }

                $service = $this->drafts->persist($this->drafts->current($session), $this->currentProvider());
                $this->drafts->clear($session);

                return $this->afterSave($service);
            }
        } elseif ($etape > $this->drafts->maxReachable($session)) {
            return $this->redirectToRoute('app_pro_activity_wizard', ['etape' => $this->drafts->maxReachable($session)]);
        }

        $draft = $this->drafts->current($session);
        $service = null !== $draft['id'] ? $this->services->find((string) $draft['id']) : null;

        return $this->renderSpace('provider/space/activity_wizard.html.twig', 'Mes activités', [
            'step' => $etape,
            'steps' => StaticActivityWizard::steps(),
            'advice' => StaticActivityWizard::advice($etape),
            'draft' => $draft,
            'done' => (array) $draft['done'],
            'errors' => $errors,
            'service' => $service,
            'categories' => $this->categories->findRoots(),
            'category' => $this->categories->findOneBy(['slug' => (string) ($draft['category'] ?? '')]),
            'types' => StaticActivityWizard::activityTypes(),
            'levels' => StaticActivityWizard::levels(),
            'languages' => StaticActivityWizard::languages(),
            'periods' => StaticActivityWizard::openingPeriods(),
            'durations' => StaticActivityWizard::durations(),
            'units' => StaticActivityWizard::pricingUnits(),
            'booking_types' => StaticActivityWizard::bookingTypes(),
            'max_gallery' => ActivityDraftService::MAX_GALLERY,
            'max_packages' => ActivityDraftService::MAX_PACKAGES,
            'verified' => 'verified' === $this->currentProvider()->getStatus()->value,
        ], new Response(null, [] === $errors ? 200 : 422));
    }

    private function afterSave(Service $service): Response
    {
        if (\in_array($service->getStatus(), [ServiceStatus::Draft, ServiceStatus::Archived], true)) {
            try {
                $this->publishing->submit($service);
                $this->addFlash('success', sprintf('« %s » est envoyée en validation : notre équipe la publie sous 24 h.', $service->getTitle()));
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', sprintf('« %s » est enregistrée en brouillon. %s', $service->getTitle(), $e->getMessage()));
            }
        } else {
            $this->addFlash('success', sprintf('Modifications de « %s » enregistrées.', $service->getTitle()));
        }

        return $this->redirectToRoute('app_pro_activities');
    }

    private function own(string $id): Service
    {
        $service = Ulid::isValid($id) ? $this->services->find(Ulid::fromString($id)) : null;
        if (null === $service || $service->isDeleted() || $service->getProvider() !== $this->currentProvider()) {
            throw $this->createNotFoundException('Activité introuvable.');
        }

        return $service;
    }
}
