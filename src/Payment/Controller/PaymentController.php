<?php

declare(strict_types=1);

namespace App\Payment\Controller;

use App\Payment\Repository\PaymentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Pages de retour après un passage par la page de paiement hébergée de Stripe.
 *
 * Ce sont pour l'instant des réponses minimales : le vrai rendu (Twig) viendra
 * avec le front-end. Elles existent surtout pour fournir à Stripe une URL de retour
 * de succès et une URL d'abandon.
 *
 * Rappel important : la confirmation d'un paiement ne se fait JAMAIS ici (une URL
 * de retour n'est pas fiable — le client peut la court-circuiter), mais dans le
 * webhook signé par Stripe.
 */
final class PaymentController extends AbstractController
{
    public function __construct(
        private readonly PaymentRepository $payments,
    ) {
    }

    /**
     * Retour de Stripe : on renvoie vers la confirmation de la réservation,
     * qui affiche « en cours de confirmation » tant que le webhook n'a pas
     * réglé le paiement.
     */
    #[Route(path: ['fr' => '/paiement/succes', 'en' => '/en/payment/success'], name: 'payment_success', methods: ['GET'])]
    public function success(Request $request): Response
    {
        $payment = $this->payments->findOneByReference((string) $request->query->get('session_id', ''));
        $booking = $payment?->getBooking();

        if (null !== $booking && $booking->getClient() === $this->getUser()) {
            return $this->redirectToRoute('app_booking_confirmation', ['id' => (string) $booking->getId()]);
        }

        $this->addFlash('info', 'Merci, votre paiement est en cours de confirmation.');

        return $this->redirectToRoute('app_account_history');
    }

    #[Route(path: ['fr' => '/paiement/annule', 'en' => '/en/payment/canceled'], name: 'payment_cancel', methods: ['GET'])]
    public function cancel(): Response
    {
        $this->addFlash('info', 'Paiement annulé : aucune somme n’a été prélevée. Votre réservation reste en attente dans « Mes réservations ».');

        return $this->redirectToRoute($this->getUser() ? 'app_account_history' : 'app_activities');
    }
}
