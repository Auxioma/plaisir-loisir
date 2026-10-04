<?php

declare(strict_types=1);

namespace App\Notification\Controller;

use App\Notification\Entity\NewsletterSubscriber;
use App\Notification\Repository\NewsletterSubscriberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Inscription à la lettre d'information (04/10). Répond en JSON au fetch du
 * formulaire, et par redirection + message flash sans JavaScript.
 */
final class NewsletterController extends AbstractController
{
    #[Route(path: ['fr' => '/newsletter', 'en' => '/en/newsletter'], name: 'app_newsletter_subscribe', methods: ['POST'])]
    public function subscribe(Request $request, NewsletterSubscriberRepository $subscribers, EntityManagerInterface $entityManager, ValidatorInterface $validator, TranslatorInterface $translator): Response
    {
        $email = mb_strtolower(trim((string) $request->request->get('email', '')));
        $back = $request->headers->get('referer') ?: $this->generateUrl('app_offers');

        $error = null;
        if (!$this->isCsrfTokenValid('newsletter', (string) $request->request->get('_token'))) {
            $error = 'Votre session a expiré, merci de réessayer.';
        } elseif ('' === $email || \count($validator->validate($email, [new Assert\Email(), new Assert\Length(max: 180)])) > 0) {
            $error = 'Adresse e-mail invalide.';
        }

        if (null === $error) {
            $subscriber = $subscribers->findOneByEmail($email);
            if (null === $subscriber) {
                $subscriber = (new NewsletterSubscriber())->setEmail($email);
                $entityManager->persist($subscriber);
            }
            $subscriber->setUnsubscribedAt(null)
                ->setLocale($request->getLocale())
                ->setSource(mb_substr((string) $request->request->get('source', ''), 0, 40) ?: null);
            $entityManager->flush();
        }

        $message = $error ?? 'Merci, vous êtes inscrit ! Vous recevrez nos meilleures offres par e-mail.';
        if ($request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json')) {
            return new JsonResponse(['ok' => null === $error, 'message' => $translator->trans($message)], null === $error ? 200 : 422);
        }
        $this->addFlash(null === $error ? 'success' : 'error', $message);

        return $this->redirect($back);
    }
}
