<?php

declare(strict_types=1);

namespace App\Tests\Event;

use App\Event\Repository\EventRepository;
use App\Event\Repository\GroupAlbumRepository;
use App\Event\Repository\GroupRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Les fiches détail événement/groupe/album montrent bien l'objet réellement
 * consulté (17/09), pas un contenu figé identique quel que soit le clic —
 * défaut signalé le 15 septembre (docs/rapport-client-2026-09-15).
 */
final class EventGroupDetailTest extends WebTestCase
{
    public function testTwoDifferentEventsShowDifferentTitles(): void
    {
        $client = static::createClient();
        $events = static::getContainer()->get(EventRepository::class)->findForListing(limit: 2);
        self::assertCount(2, $events, 'La fixture doit fournir au moins deux événements.');

        $client->request('GET', '/evenements/detail/'.$events[0]->getSlug());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1.evn-detail__title', self::normalizeSpaces($events[0]->getTitle()));

        $client->request('GET', '/evenements/detail/'.$events[1]->getSlug());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1.evn-detail__title', self::normalizeSpaces($events[1]->getTitle()));
    }

    public function testUnknownEventSlugAnswers404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/evenements/detail/n-existe-pas');
        self::assertResponseStatusCodeSame(404);
    }

    public function testTwoDifferentGroupsShowDifferentNames(): void
    {
        $client = static::createClient();
        $groups = static::getContainer()->get(GroupRepository::class)->findForListing(limit: 2);
        self::assertCount(2, $groups, 'La fixture doit fournir au moins deux groupes.');

        $client->request('GET', '/evenements/groupes/detail/'.$groups[0]->getSlug());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1.evn-ghead__title', $groups[0]->getName());

        $client->request('GET', '/evenements/groupes/detail/'.$groups[1]->getSlug());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1.evn-ghead__title', $groups[1]->getName());
    }

    public function testGroupAlbumShowsItsOwnTitle(): void
    {
        $client = static::createClient();
        $groups = static::getContainer()->get(GroupRepository::class)->findForListing();
        $albums = static::getContainer()->get(GroupAlbumRepository::class)->findForGroup($groups[0]);
        self::assertNotEmpty($albums, 'La fixture doit fournir au moins un album pour ce groupe.');

        $client->request('GET', '/evenements/groupes/detail/'.$groups[0]->getSlug().'/photos/album/'.$albums[0]->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2.evn-gh2--tab', self::normalizeSpaces($albums[0]->getTitle()));
    }

    public function testAlbumFromAnotherGroupAnswers404(): void
    {
        $client = static::createClient();
        $groups = static::getContainer()->get(GroupRepository::class)->findForListing(limit: 2);
        $albums = static::getContainer()->get(GroupAlbumRepository::class)->findForGroup($groups[0]);
        self::assertNotEmpty($albums);

        // L'album appartient à groups[0], on l'ouvre sous l'URL de groups[1].
        $client->request('GET', '/evenements/groupes/detail/'.$groups[1]->getSlug().'/photos/album/'.$albums[0]->getId());
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * Certains titres de la fixture portent un double espace de la maquette
     * d'origine ; le HTML rendu, lui, normalise les espaces.
     */
    private static function normalizeSpaces(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
