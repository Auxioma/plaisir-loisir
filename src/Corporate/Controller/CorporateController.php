<?php

declare(strict_types=1);

namespace App\Corporate\Controller;

use App\Corporate\StaticCorporate;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Pages institutionnelles (« corporate »), accessibles depuis le pied de page.
 *
 * Ces écrans portent un header propre — troisième variante après le header
 * principal et celui d'Événements (voir _partials/navbar_corporate.html.twig).
 */
final class CorporateController extends AbstractController
{
    #[Route('/a-propos', name: 'app_corporate_about')]
    public function about(): Response
    {
        return $this->render('corporate/apropos.html.twig', [
            'stats' => StaticCorporate::stats(),
            'values' => StaticCorporate::values(),
            'team' => StaticCorporate::team(),
        ]);
    }

    #[Route('/devenir-partenaire', name: 'app_corporate_partner')]
    public function partner(): Response
    {
        return $this->render('corporate/partenaire.html.twig', [
            'benefits' => StaticCorporate::partnerBenefits(),
            'steps' => StaticCorporate::partnerSteps(),
            'testimonials' => StaticCorporate::testimonials(),
            'arguments' => StaticCorporate::partnerArguments(),
        ]);
    }

    /**
     * Formulaire de candidature partenaire.
     *
     * Le traitement (validation, envoi, écran de succès) reste à câbler : la
     * maquette ne fournit que l'écran vierge.
     */
    #[Route('/devenir-partenaire/formulaire', name: 'app_corporate_partner_form', methods: ['GET', 'POST'])]
    public function partnerForm(): Response
    {
        return $this->render('corporate/partenaire_formulaire.html.twig');
    }

    #[Route('/carrieres', name: 'app_corporate_careers')]
    public function careers(): Response
    {
        return $this->render('corporate/carrieres.html.twig', [
            'jobs' => StaticCorporate::jobs(),
            'values' => StaticCorporate::careerValues(),
            'reasons' => StaticCorporate::careerReasons(),
            'testimonials' => StaticCorporate::testimonials(),
        ]);
    }

    /**
     * Listing des offres. La planche « Détails ofres » est ce même écran
     * recouvert de la fiche de l'offre : elle s'ouvre par ?offre=1, sans
     * JavaScript.
     */
    #[Route('/carrieres/offres', name: 'app_corporate_jobs')]
    public function jobs(Request $request): Response
    {
        return $this->render('corporate/offres.html.twig', [
            'jobs' => StaticCorporate::jobs(),
            'detail' => null !== $request->query->get('offre') ? StaticCorporate::jobDetail() : null,
        ]);
    }

    #[Route('/contactez-nous', name: 'app_corporate_contact', methods: ['GET', 'POST'])]
    public function contact(): Response
    {
        return $this->render('corporate/contact.html.twig', [
            'methods' => StaticCorporate::contactMethods(),
            'arguments' => StaticCorporate::contactArguments(),
        ]);
    }

    #[Route('/paiement-securise', name: 'app_corporate_payment')]
    public function payment(): Response
    {
        return $this->render('corporate/paiement.html.twig', [
            'cards' => StaticCorporate::paymentCards(),
        ]);
    }

    #[Route('/mentions-legales', name: 'app_corporate_legal')]
    public function legal(): Response
    {
        return $this->render('corporate/legal.html.twig', [
            'page_title' => 'Mentions légales',
            'intro' => "Les présentes mentions légales ont pour objectif d'informer les utilisateurs sur l'éditeur du site TrouveMoi Plaisirs & Loisirs et sur les conditions d'utilisation de la plateforme.",
            'sections' => StaticCorporate::legalSections(),
        ]);
    }

    #[Route('/conditions-generales', name: 'app_corporate_terms')]
    public function terms(): Response
    {
        return $this->render('corporate/legal.html.twig', [
            'page_title' => "Conditions Générales d'Utilisation",
            'intro' => "Bienvenue sur TrouveMoi Plaisirs & Loisirs. Les présentes Conditions Générales d'Utilisation (CGU) régissent votre utilisation de notre plateforme et de nos services.",
            'sections' => StaticCorporate::cguSections(),
        ]);
    }
}
