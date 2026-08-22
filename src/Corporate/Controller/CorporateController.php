<?php

declare(strict_types=1);

namespace App\Corporate\Controller;

use App\Corporate\Entity\ContactMessage;
use App\Corporate\Entity\PartnerApplication;
use App\Corporate\Service\CorporateInboxService;
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
    /**
     * Formulaire « Devenir partenaire ».
     *
     * Avant le 20/08, la route acceptait POST et le contrôleur se contentait de
     * réafficher la page : la candidature partait dans le vide, sans erreur ni
     * confirmation. Le prospect croyait avoir postulé.
     */
    #[Route('/devenir-partenaire/formulaire', name: 'app_corporate_partner_form', methods: ['GET', 'POST'])]
    public function partnerForm(Request $request, CorporateInboxService $inbox): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Votre session a expiré, merci de renvoyer le formulaire.');

                return $this->redirectToRoute('app_corporate_partner_form');
            }

            $application = new PartnerApplication();
            $application
                ->setSiteName((string) $request->request->get('nom_site'))
                ->setSiteUrl((string) $request->request->get('url_site'))
                ->setSector((string) $request->request->get('secteur'))
                ->setTraffic((string) $request->request->get('trafic'))
                ->setCompanyName($request->request->getString('entreprise'))
                ->setContactName($request->request->getString('responsable'))
                ->setPhone($request->request->getString('telephone'))
                ->setCity($request->request->getString('ville'))
                ->setAddress((string) $request->request->get('adresse'))
                ->setPostalCode((string) $request->request->get('code_postal'))
                ->setEmail((string) $request->request->get('email'))
                ->setDescription($request->request->getString('description'))
                ->setTermsAccepted($request->request->getBoolean('cgu'))
                ->setIpAddress($request->getClientIp());

            $errors = $inbox->submitPartnerApplication($application);

            if ([] === $errors) {
                $this->addFlash('success', 'Votre candidature a bien été enregistrée. Notre équipe vous répondra rapidement.');

                return $this->redirectToRoute('app_corporate_partner_form');
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

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

    /**
     * Formulaire « Contactez-nous ».
     *
     * Même défaut que le formulaire partenaire jusqu'au 20/08 : la route
     * acceptait POST, le contrôleur ne lisait même pas la requête. Le message
     * était perdu et l'expéditeur n'en savait rien.
     */
    #[Route('/contactez-nous', name: 'app_corporate_contact', methods: ['GET', 'POST'])]
    public function contact(Request $request, CorporateInboxService $inbox): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Votre session a expiré, merci de renvoyer le formulaire.');

                return $this->redirectToRoute('app_corporate_contact');
            }

            $message = new ContactMessage();
            $message
                ->setName((string) $request->request->get('nom'))
                ->setEmail((string) $request->request->get('email'))
                ->setSubject((string) $request->request->get('sujet'))
                ->setMessage((string) $request->request->get('message'))
                ->setIpAddress($request->getClientIp());

            $errors = $inbox->submitContact($message);

            if ([] === $errors) {
                $this->addFlash('success', 'Votre message a bien été envoyé. Nous vous répondrons au plus vite.');

                return $this->redirectToRoute('app_corporate_contact');
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

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
