<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Enum\ReportReason;
use App\Admin\Enum\ReportSubjectType;
use App\Admin\Service\ReportService;
use App\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

/**
 * Point d'entrée UNIQUE pour tout bouton « Signaler » du site (§16.4 du
 * CDC), quel que soit le type de contenu — voir le commentaire de
 * ReportSubjectType pour lesquels ont déjà un vrai bouton.
 *
 * Formulaire minimal, sans maquette (comme /professionnels et
 * /activites-privees), intégré directement sur la page du contenu visé
 * plutôt qu'une page à part : voir `_partials/_report_form.html.twig`.
 */
#[IsGranted('ROLE_USER')]
final class ReportController extends AbstractController
{
    public function __construct(
        private readonly ReportService $service,
    ) {
    }

    #[Route(path: ['fr' => '/signalement', 'en' => '/en/report'], name: 'app_report_submit', methods: ['POST'])]
    public function submit(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

            return $this->redirectBack($request);
        }

        $type = ReportSubjectType::tryFrom((string) $request->request->get('subject_type', ''));
        $subjectIdRaw = (string) $request->request->get('subject_id', '');
        $subjectLabel = trim((string) $request->request->get('subject_label', ''));
        $reason = ReportReason::tryFrom((string) $request->request->get('reason', ''));
        $message = trim((string) $request->request->get('message', ''));

        if (null === $type || '' === $subjectLabel || null === $reason || !Ulid::isValid($subjectIdRaw)) {
            $this->addFlash('error', 'Signalement invalide.');

            return $this->redirectBack($request);
        }

        try {
            $this->service->submit(
                $this->currentUser(),
                $type,
                Ulid::fromString($subjectIdRaw),
                $subjectLabel,
                $reason,
                '' !== $message ? $message : null,
            );

            $this->addFlash('success', 'Merci, votre signalement a été transmis à la modération.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectBack($request);
    }

    /**
     * Retour sur la page du contenu signalé : c'est elle qui connaît la
     * bonne URL, transmise dans un champ caché plutôt que devinée ici.
     *
     * CHEMIN LOCAL UNIQUEMENT — jamais suivi tel quel. Rien n'empêche
     * quelqu'un de rejouer ce POST à la main avec un `redirect` pointant
     * ailleurs que sur ce site (ce champ n'est pas couvert par le jeton
     * CSRF, qui protège seulement contre un envoi déclenché à l'insu de la
     * victime) : sans ce contrôle, un signalement légitime pourrait terminer
     * en redirection ouverte vers un site de phishing.
     */
    private function redirectBack(Request $request): Response
    {
        $back = (string) $request->request->get('redirect', '');

        if ('' === $back || !str_starts_with($back, '/') || str_starts_with($back, '//')) {
            return $this->redirect($this->generateUrl('app_home'));
        }

        return $this->redirect($back);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
