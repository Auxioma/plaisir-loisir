<?php

declare(strict_types=1);

namespace App\Tests\Home;

use App\Event\Entity\Event;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PublicEventsTest extends WebTestCase
{
    public function testVisitorsDiscoverOnlyPublishedPublicEvents(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(6));
        $events = [];
        foreach (['public', 'members', 'private', 'draft'] as $kind) {
            $event = (new Event())
                ->setTitle('Découverte '.$kind.' '.$suffix)
                ->setSlug('decouverte-'.$kind.'-'.$suffix)
                ->setStartsAt(new \DateTimeImmutable('+1 hour'))
                ->setVisibility('draft' === $kind ? 'public' : $kind)
                ->setPrivate('private' === $kind)
                ->setStatus('draft' === $kind ? 'draft' : 'published');
            $em->persist($event);
            $events[] = $event;
        }
        $em->flush();

        try {
            $client->request('GET', '/evenements?q='.$suffix);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('body', 'Découverte public '.$suffix);
            foreach (['members', 'private', 'draft'] as $hidden) {
                self::assertSelectorTextNotContains('body', 'Découverte '.$hidden.' '.$suffix);
            }
            $client->request('GET', '/');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('#evenements h2', 'Événements près de chez vous');
            self::assertSelectorExists('#evenements a[href="/evenements"]');
            self::assertSelectorExists('.tm-menu a[href="/evenements"]');
        } finally {
            $em = static::getContainer()->get(EntityManagerInterface::class);
            foreach ($events as $event) {
                $managed = $em->find(Event::class, $event->getId());
                if (null !== $managed) {
                    $em->remove($managed);
                }
            }
            $em->flush();
        }
    }
}
