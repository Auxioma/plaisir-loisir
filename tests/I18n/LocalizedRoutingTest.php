<?php

declare(strict_types=1);

namespace App\Tests\I18n;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * La langue vit dans l'adresse, pas en session.
 *
 * POURQUOI CE TEST EXISTE
 * La bascule français/anglais reposait sur la session : la MÊME adresse
 * servait les deux langues. Un moteur de recherche n'a pas de session : il
 * recevait donc toujours le français, et les pages anglaises n'existaient pour
 * personne. Chaque page a désormais une adresse par langue (/activites et
 * /en/activities), ce qui est la condition posée par Google pour indexer un
 * site multilingue.
 *
 * Ce test garde les trois choses qui peuvent se perdre en silence :
 * l'existence des adresses anglaises, les balises qui les relient entre elles,
 * et les règles d'accès — qui portent sur des CHEMINS et doivent donc couvrir
 * les deux langues, sous peine d'ouvrir au public un écran de compte.
 */
final class LocalizedRoutingTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function englishUrls(): iterable
    {
        $urls = [
            '/en',
            '/en/activities',
            '/en/activities/descente-en-canoe',
            '/en/destinations',
            '/en/destinations/popular',
            '/en/destinations/lille',
            '/en/authentication',
            '/en/signup',
            '/en/forgot-password',
            '/en/events',
            '/en/events/all',
            '/en/events/private',
            '/en/events/calendar',
            '/en/events/detail',
            '/en/events/detail/participants',
            '/en/events/groups',
            '/en/events/groups/detail/about',
            '/en/events/groups/detail/events',
            '/en/events/groups/detail/members',
            '/en/events/groups/detail/photos',
            '/en/events/groups/detail/discussions',
            '/en/events/groups/detail/photos/album',
            '/en/events/groups/detail/request-sent',
            '/en/events/create/1',
            '/en/events/create/success',
            '/en/events/groups/create/1',
            '/en/events/groups/create/success',
            '/en/gift-cards',
            '/en/gift-cards/workshops-and-crafts',
            '/en/gift-cards/buy',
            '/en/gift-cards/buy/payment',
            '/en/deals',
            '/en/deals/all',
            '/en/about-us',
            '/en/become-a-partner',
            '/en/become-a-partner/form',
            '/en/careers',
            '/en/careers/jobs',
            '/en/contact-us',
            '/en/secure-payment',
            '/en/legal-notice',
            '/en/terms-and-conditions',
        ];

        foreach ($urls as $url) {
            yield $url => [$url];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('englishUrls')]
    public function testEnglishUrlAnswers(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        self::assertSame(
            200,
            $client->getResponse()->getStatusCode(),
            sprintf('La version anglaise « %s » ne répond pas.', $url),
        );
    }

    /**
     * La page annonce la langue qu'elle affiche réellement. Sans cela, une
     * page anglaise se déclare française et son contenu est mal interprété.
     */
    public function testThePageDeclaresItsOwnLanguage(): void
    {
        $client = static::createClient();

        $client->request('GET', '/activites');
        self::assertStringContainsString('<html lang="fr">', $client->getResponse()->getContent() ?: '');

        $client->request('GET', '/en/activities');
        self::assertStringContainsString('<html lang="en">', $client->getResponse()->getContent() ?: '');
    }

    /**
     * Les deux versions se désignent mutuellement. C'est ce qui empêche un
     * moteur de recherche de les prendre pour du contenu dupliqué.
     */
    public function testEachPageDeclaresItsTranslation(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/en/activities');

        self::assertCount(
            1,
            $crawler->filter('link[rel="canonical"][href$="/en/activities"]'),
            'La page anglaise doit se désigner elle-même comme canonique.',
        );
        self::assertCount(
            1,
            $crawler->filter('link[rel="alternate"][hreflang="fr"][href$="/activites"]'),
            'La page anglaise doit désigner sa version française.',
        );
        self::assertCount(
            1,
            $crawler->filter('link[rel="alternate"][hreflang="en"][href$="/en/activities"]'),
        );
        self::assertCount(
            1,
            $crawler->filter('link[rel="alternate"][hreflang="x-default"][href$="/activites"]'),
            'x-default doit pointer vers le français, langue de référence.',
        );
    }

    /**
     * Changer de langue est un déplacement vers la même page dans l'autre
     * langue — pas un retour à l'accueil, et pas une perte des filtres.
     */
    public function testTheLanguageSelectorLeadsToTheSamePage(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/activites?q=canoe');

        $hrefs = $crawler->filter('a[href^="/en/activities"]')->extract(['href']);

        self::assertContains(
            '/en/activities?q=canoe',
            $hrefs,
            'Le sélecteur doit mener à la même page en anglais, filtres compris.',
        );
    }

    /**
     * Les règles d'accès du pare-feu visent des chemins : la version anglaise
     * d'un écran privé doit être protégée comme la française.
     */
    public function testEnglishAccountPagesStayPrivate(): void
    {
        $client = static::createClient();

        foreach (['/compte/favoris', '/en/account/favorites'] as $url) {
            $client->request('GET', $url);

            self::assertSame(
                302,
                $client->getResponse()->getStatusCode(),
                sprintf('« %s » doit exiger une connexion.', $url),
            );
        }
    }

    /**
     * /register était un chemin anglais servant une page française. L'ancienne
     * adresse doit survivre, sinon les liens déjà partagés tombent en 404.
     */
    public function testTheOldRegisterAddressStillLeadsSomewhere(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register?type=pro');

        $response = $client->getResponse();
        self::assertSame(301, $response->getStatusCode());
        self::assertStringEndsWith('/inscription?type=pro', (string) $response->headers->get('Location'));
    }

    /**
     * Rien sur le site français ne mène aux adresses anglaises, hormis le
     * sélecteur de langue : sans plan du site, Google ne les découvrirait pas.
     */
    public function testTheSitemapListsBothLanguages(): void
    {
        $client = static::createClient();
        $client->request('GET', '/sitemap.xml');

        self::assertSame(200, $client->getResponse()->getStatusCode());

        $xml = $client->getResponse()->getContent() ?: '';
        self::assertStringContainsString('<loc>http://localhost/activites</loc>', $xml);
        self::assertStringContainsString('<loc>http://localhost/en/activities</loc>', $xml);
        self::assertStringContainsString('hreflang="x-default"', $xml);
    }
}
