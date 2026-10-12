<?php

declare(strict_types=1);

namespace App\Tests\Legal;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Bandeau cookies (12/10) : affiché tant qu'aucun choix n'est fait, masqué
 * ensuite ; « Tout refuser » n'autorise rien de facultatif.
 */
final class CookieConsentFlowTest extends WebTestCase
{
    public function testTheBannerIsShownUntilAChoiceIsMade(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/faq');
        $banner = $crawler->filter('[data-cookie-banner]');
        self::assertCount(1, $banner);
        self::assertNull($banner->attr('hidden'), 'Sans choix, le bandeau doit être visible.');

        $token = (string) $crawler->filter('[data-cookie-form] input[name="_token"]')->attr('value');
        $client->request('POST', '/cookies/consentement', ['_token' => $token, 'choice' => 'none'], [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        self::assertSame(['necessary'], json_decode((string) $client->getResponse()->getContent(), true)['accepted']);
        self::assertResponseHasCookie('tm_consent');

        $crawler = $client->request('GET', '/faq');
        self::assertNotNull($crawler->filter('[data-cookie-banner]')->attr('hidden'), 'Une fois le choix fait, le bandeau est masqué.');
    }

    public function testCustomChoiceKeepsOnlyOptionalCategories(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');
        $token = (string) $crawler->filter('[data-cookie-form] input[name="_token"]')->attr('value');
        $client->request('POST', '/cookies/consentement', ['_token' => $token, 'choice' => 'custom', 'categories' => ['statistics', 'bogus']], [], ['HTTP_ACCEPT' => 'application/json']);

        self::assertSame(['necessary', 'statistics'], json_decode((string) $client->getResponse()->getContent(), true)['accepted']);
    }

    public function testAnInvalidTokenIsRejected(): void
    {
        $client = static::createClient();
        $client->request('POST', '/cookies/consentement', ['_token' => 'x', 'choice' => 'all']);
        self::assertResponseStatusCodeSame(403);
    }
}
