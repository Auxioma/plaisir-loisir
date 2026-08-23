<?php

declare(strict_types=1);

namespace App\I18n\Controller;

use App\I18n\Routing\LocaleUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Plan du site, en deux langues.
 *
 * Un moteur de recherche ne devine pas /en/activities : rien sur le site
 * francais n'y mene, sinon le selecteur de langue. Le plan du site declare
 * donc chaque page dans ses deux versions, et chaque entree renvoie vers son
 * equivalent dans l'autre langue (balises xhtml:link), ce que Google demande
 * pour comprendre qu'il s'agit d'une seule et meme page.
 */
final class SitemapController extends AbstractController
{
    /**
     * Pages publiques a proposer a l'indexation.
     *
     * Sont volontairement absents : les ecrans de compte et de paiement (prives),
     * les etapes des assistants de creation (sans contenu propre), les pages
     * d'erreur et les adresses techniques.
     *
     * Les fiches d'activite (/activites/{slug}) et de ville
     * (/destinations/{ville}) viendront s'y ajouter quand le catalogue reel
     * remplacera les donnees de demonstration : les lister aujourd'hui
     * reviendrait a soumettre a Google des pages de test.
     *
     * @var list<string>
     */
    private const ROUTES = [
        'app_home',
        'app_activities',
        'app_destinations',
        'app_destinations_popular',
        'app_gifts',
        'app_gifts_category',
        'app_gifts_offer',
        'app_offers',
        'app_offers_all',
        'app_events',
        'app_events_all',
        'app_events_calendar',
        'app_events_private',
        'app_events_detail',
        'app_groups',
        'app_corporate_about',
        'app_corporate_partner',
        'app_corporate_careers',
        'app_corporate_jobs',
        'app_corporate_contact',
        'app_corporate_payment',
        'app_corporate_legal',
        'app_corporate_terms',
        'app_register',
    ];

    #[Route('/sitemap.xml', name: 'app_sitemap', defaults: ['_format' => 'xml'])]
    public function index(): Response
    {
        $entries = [];
        foreach (self::ROUTES as $route) {
            $alternates = [];
            foreach (LocaleUrlGenerator::LOCALES as $locale) {
                $alternates[$locale] = $this->generateUrl(
                    $route,
                    ['_locale' => $locale],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );
            }
            foreach ($alternates as $locale => $url) {
                $entries[] = ['loc' => $url, 'locale' => $locale, 'alternates' => $alternates];
            }
        }

        $response = $this->render('sitemap/index.xml.twig', ['entries' => $entries]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response;
    }
}
