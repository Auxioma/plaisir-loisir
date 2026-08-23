<?php

declare(strict_types=1);

namespace App\Payment\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    #[Route(path: ['fr' => '/paiement/succes', 'en' => '/en/payment/success'], name: 'payment_success', methods: ['GET'])]
    public function success(): Response
    {
        return new Response('Merci, votre paiement est en cours de confirmation.');
    }

    #[Route(path: ['fr' => '/paiement/annule', 'en' => '/en/payment/canceled'], name: 'payment_cancel', methods: ['GET'])]
    public function cancel(): Response
    {
        return new Response('Paiement annulé.');
    }
}
