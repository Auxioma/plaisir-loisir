<?php

declare(strict_types=1);

namespace App\Corporate\Controller;

use App\Corporate\Entity\ContactMessage;
use App\Corporate\Entity\PartnerApplication;
use App\Corporate\Service\CorporateInboxService;
use App\Corporate\StaticCorporate;
use App\Legal\Enum\LegalDocumentType;
use App\Legal\Service\LegalContentRenderer;
use App\Legal\Service\LegalDocumentService;
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
    #[Route(path: ['fr' => '/a-propos', 'en' => '/en/about-us'], name: 'app_corporate_about')]
    public function about(): Response
    {
        return $this->render('corporate/apropos.html.twig', [
            'stats' => StaticCorporate::stats(),
            'values' => StaticCorporate::values(),
            'team' => StaticCorporate::team(),
        ]);
    }

    #[Route(path: ['fr' => '/devenir-partenaire', 'en' => '/en/become-a-partner'], name: 'app_corporate_partner')]
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
    #[Route(path: ['fr' => '/devenir-partenaire/formulaire', 'en' => '/en/become-a-partner/form'], name: 'app_corporate_partner_form', methods: ['GET', 'POST'])]
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

    #[Route(path: ['fr' => '/carrieres', 'en' => '/en/careers'], name: 'app_corporate_careers')]
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
    #[Route(path: ['fr' => '/carrieres/offres', 'en' => '/en/careers/jobs'], name: 'app_corporate_jobs')]
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
    #[Route(path: ['fr' => '/contactez-nous', 'en' => '/en/contact-us'], name: 'app_corporate_contact', methods: ['GET', 'POST'])]
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

    #[Route(path: ['fr' => '/paiement-securise', 'en' => '/en/secure-payment'], name: 'app_corporate_payment')]
    public function payment(): Response
    {
        return $this->render('corporate/paiement.html.twig', [
            'cards' => StaticCorporate::paymentCards(),
        ]);
    }

    /**
     * LES PAGES JURIDIQUES LISENT DÉSORMAIS LA BASE (31/08).
     *
     * Elles affichaient jusqu'ici des tableaux écrits en PHP dans
     * StaticCorporate. Le CTO a demandé que ces textes soient gérés depuis la
     * base : les conditions générales évoluent dans le temps, et une évolution
     * ne peut pas exiger un déploiement.
     *
     * Le modèle existait déjà entièrement (table legal_document, versionnée).
     * Il manquait seulement le branchement, l'écran d'administration et deux
     * routes : les conditions générales de VENTE, distinctes des conditions
     * d'utilisation, et la politique de confidentialité, dont le lien du pied
     * de page pointait dans le vide depuis l'origine.
     */
    #[Route(path: ['fr' => '/mentions-legales', 'en' => '/en/legal-notice'], name: 'app_corporate_legal')]
    public function legal(Request $request, LegalDocumentService $documents, LegalContentRenderer $renderer): Response
    {
        return $this->renderLegalDocument(
            LegalDocumentType::LegalNotice,
            $request,
            $documents,
            $renderer,
            "Les présentes mentions légales ont pour objectif d'informer les utilisateurs sur l'éditeur du site TrouveMoi Plaisirs & Loisirs et sur les conditions d'utilisation de la plateforme.",
        );
    }

    #[Route(path: ['fr' => '/conditions-generales', 'en' => '/en/terms-and-conditions'], name: 'app_corporate_terms')]
    public function terms(Request $request, LegalDocumentService $documents, LegalContentRenderer $renderer): Response
    {
        return $this->renderLegalDocument(
            LegalDocumentType::TermsOfService,
            $request,
            $documents,
            $renderer,
            "Bienvenue sur TrouveMoi Plaisirs & Loisirs. Les présentes Conditions Générales d'Utilisation (CGU) régissent votre utilisation de notre plateforme et de nos services.",
        );
    }

    #[Route(path: ['fr' => '/conditions-generales-de-vente', 'en' => '/en/terms-of-sale'], name: 'app_corporate_sales_terms')]
    public function salesTerms(Request $request, LegalDocumentService $documents, LegalContentRenderer $renderer): Response
    {
        return $this->renderLegalDocument(
            LegalDocumentType::TermsOfSale,
            $request,
            $documents,
            $renderer,
            'Les présentes Conditions Générales de Vente encadrent la réservation et le paiement des prestations proposées sur la plateforme.',
        );
    }

    #[Route(path: ['fr' => '/politique-de-confidentialite', 'en' => '/en/privacy-policy'], name: 'app_corporate_privacy')]
    public function privacy(Request $request, LegalDocumentService $documents, LegalContentRenderer $renderer): Response
    {
        return $this->renderLegalDocument(
            LegalDocumentType::PrivacyPolicy,
            $request,
            $documents,
            $renderer,
            'Cette politique explique quelles données personnelles nous collectons, pourquoi, combien de temps nous les conservons et comment exercer vos droits.',
        );
    }

    #[Route(path: ['fr' => '/politique-de-cookies', 'en' => '/en/cookie-policy'], name: 'app_corporate_cookies')]
    public function cookies(Request $request, LegalDocumentService $documents, LegalContentRenderer $renderer): Response
    {
        return $this->renderLegalDocument(
            LegalDocumentType::CookiePolicy,
            $request,
            $documents,
            $renderer,
            'Cette page détaille les traceurs déposés par le site, leur finalité et la manière de revenir sur votre choix.',
        );
    }

    /**
     * Rend une page juridique à partir de la version en vigueur.
     *
     * CE QUI SE PASSE QUAND AUCUNE VERSION N'EST PUBLIÉE
     * La page ne renvoie PAS une erreur 404, et c'est un choix. Ces cinq
     * adresses sont citées dans le pied de page de toutes les pages, dans les
     * cases à cocher de l'inscription et dans les mentions envoyées aux
     * partenaires : une 404 y remettrait exactement le lien mort qu'on vient
     * de supprimer, et donnerait au visiteur l'impression d'un site cassé.
     *
     * La page affiche donc son titre, son fil d'Ariane et une phrase indiquant
     * que le texte est en cours de rédaction, avec le lien de contact. C'est
     * l'état réel : la rédaction d'un texte juridique relève du client, pas du
     * code. Le jour où il fournit le texte, il suffit de le publier depuis le
     * back-office pour que la page se remplisse, sans déploiement.
     */
    private function renderLegalDocument(
        LegalDocumentType $type,
        Request $request,
        LegalDocumentService $documents,
        LegalContentRenderer $renderer,
        string $introDefaut,
    ): Response {
        $locale = $request->getLocale();
        $document = $documents->current($type, $locale);

        // Repli sur le français : mieux vaut un texte juridique lisible dans la
        // mauvaise langue qu'une page vide dans la bonne.
        if (null === $document && 'fr' !== $locale) {
            $document = $documents->current($type, 'fr');
        }

        return $this->render('corporate/legal.html.twig', [
            'page_title' => $type->label(),
            'intro' => $introDefaut,
            'sections' => null !== $document ? $renderer->sections($document->getContent()) : [],
            'document' => $document,
        ]);
    }
}
