<?php

declare(strict_types=1);

namespace App\Tests\Booking;

use App\Availability\Entity\Availability;
use App\Booking\Entity\Booking;
use App\Booking\Enum\BookingStatus;
use App\Catalog\Entity\Service;
use App\Catalog\Enum\ServiceStatus;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tunnel de réservation depuis la fiche d'une activité (04/10) : créneau,
 * voyageurs, récapitulatif, paiement (processeur simulé), confirmation,
 * places décomptées ; un visiteur est renvoyé vers la connexion.
 */
final class BookingFlowTest extends WebTestCase
{
    public function testAClientBooksASlotAndPays(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        [$service, $slot] = $this->serviceWithSlot($em);
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'julie.martin@client.trouvemoi.test']);
        self::assertNotNull($user);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/activites/'.$service->getSlug());
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form#reserver');
        self::assertCount(1, $form, 'Le panneau de réservation doit être un vrai formulaire.');
        self::assertStringContainsString($slot->getStartsAt()->format('Y-m-d'), (string) $form->attr('data-slots'));

        $before = $slot->getBooked();
        $client->request('POST', '/activites/'.$service->getSlug().'/reserver', [
            '_token' => (string) $form->filter('input[name="_token"]')->attr('value'),
            'date' => $slot->getStartsAt()->format('Y-m-d'),
            'time' => $slot->getStartsAt()->format('H:i'),
            'adults' => 2,
            'children' => 1,
        ]);
        self::assertResponseRedirects();
        $summary = $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Récapitulatif');

        $client->submit($summary->filter('form[action$="/payer"]')->form());
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'confirmée');

        $em->clear();
        $booking = $em->getRepository(Booking::class)->findOneBy(['client' => $user, 'service' => $service], ['createdAt' => 'DESC']);
        self::assertNotNull($booking);
        self::assertSame(BookingStatus::Confirmed, $booking->getStatus());
        self::assertSame(3, $booking->getParticipants());
        self::assertSame($before + 3, $em->getRepository(Availability::class)->find($slot->getId())->getBooked());
    }

    public function testAVisitorIsSentToLoginWithHisSelectionKept(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        [$service, $slot] = $this->serviceWithSlot($em);

        $client->request('POST', '/activites/'.$service->getSlug().'/reserver', [
            'date' => $slot->getStartsAt()->format('Y-m-d'), 'time' => $slot->getStartsAt()->format('H:i'), 'adults' => 3, 'children' => 0,
        ]);
        self::assertResponseRedirects('/login');

        $crawler = $client->request('GET', '/activites/'.$service->getSlug());
        self::assertSame('3', $crawler->filter('input[name="adults"]')->attr('value'));
    }

    public function testAnUnknownTimeIsRefused(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        [$service] = $this->serviceWithSlot($em);
        $client->loginUser($em->getRepository(User::class)->findOneBy(['email' => 'marc.dubois@client.trouvemoi.test']));

        $crawler = $client->request('GET', '/activites/'.$service->getSlug());
        $client->request('POST', '/activites/'.$service->getSlug().'/reserver', [
            '_token' => (string) $crawler->filter('form#reserver input[name="_token"]')->attr('value'),
            'date' => (new \DateTimeImmutable('+3 days'))->format('Y-m-d'), 'time' => '03:17', 'adults' => 1,
        ]);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('.toast-body', 'créneau');
    }

    /** @return array{0: Service, 1: Availability} */
    private function serviceWithSlot(EntityManagerInterface $em): array
    {
        $service = $em->getRepository(Service::class)->findOneBy(['slug' => 'descente-en-canoe', 'status' => ServiceStatus::Published]);
        self::assertNotNull($service);
        // Heure unique par appel : plusieurs tests créent un créneau sur la même activité.
        $start = (new \DateTimeImmutable('+6 days'))->setTime(random_int(6, 20), random_int(0, 59));
        $slot = (new Availability())->setService($service)
            ->setStartsAt($start)->setEndsAt($start->modify('+2 hours'))->setCapacity(20);
        $em->persist($slot);
        $em->flush();

        return [$service, $slot];
    }
}
