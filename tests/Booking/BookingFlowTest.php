<?php

declare(strict_types=1);

namespace App\Tests\Booking;

use App\Availability\Entity\Availability;
use App\Booking\Entity\Booking;
use App\Booking\Enum\BookingStatus;
use App\Catalog\Entity\Service;
use App\Catalog\Enum\ServiceStatus;
use App\Payment\Entity\Payment;
use App\Payment\Enum\PaymentStatus;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tunnel de réservation depuis la fiche d'une activité (04/10) : créneau,
 * voyageurs, récapitulatif, demande envoyée, places décomptées ; un visiteur
 * est renvoyé vers la connexion.
 *
 * Encaissement direct (17/10) : rien n'est payé sur TrouveMoi. Le client voit
 * les moyens de paiement du professionnel, qui confirme la réservation puis
 * déclare le règlement reçu.
 */
final class BookingFlowTest extends WebTestCase
{
    public function testAClientRequestsASlotAndPaysTheProviderDirectly(): void
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
        self::assertSelectorTextContains('.bk-pay', 'Vous réglez directement l’organisateur');
        self::assertSelectorTextContains('.bk-pay', 'Virement bancaire');
        self::assertSelectorNotExists('form[action$="/payer"]');

        $client->submit($summary->filter('form[action$="/envoyer"]')->form());
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Demande envoyée');
        // Coordonnées du professionnel montrées au client après l'envoi.
        self::assertSelectorTextContains('.bk-direct', 'FR76 3000 6000 0112 3456 7890 189');

        $em->clear();
        $booking = $em->getRepository(Booking::class)->findOneBy(['client' => $user, 'service' => $service], ['createdAt' => 'DESC']);
        self::assertNotNull($booking);
        self::assertSame(BookingStatus::Pending, $booking->getStatus(), 'Rien n’est payé sur TrouveMoi : le professionnel confirme.');
        self::assertSame(3, $booking->getParticipants());
        self::assertSame($before + 3, $em->getRepository(Availability::class)->find($slot->getId())->getBooked());
        $payment = $em->getRepository(Payment::class)->findOneBy(['booking' => $booking]);
        self::assertNotNull($payment);
        self::assertSame(PaymentStatus::Pending, $payment->getStatus());

        // Le professionnel confirme, puis déclare le règlement reçu.
        $provider = $service->getProvider()?->getUser();
        self::assertNotNull($provider);
        $client->loginUser($provider);
        $crawler = $client->request('GET', '/pro/reservations?q='.$booking->getReference());
        $client->submit($crawler->filter(sprintf('form[action$="/%s/confirm"]', $booking->getId()))->form());
        $crawler = $client->request('GET', '/pro/reservations?q='.$booking->getReference());
        $client->submit($crawler->filter(sprintf('form[action$="/%s/reglement"]', $booking->getId()))->form(['method' => 'transfer']));
        self::assertResponseRedirects();

        $em->clear();
        $booking = $em->getRepository(Booking::class)->find($booking->getId());
        self::assertSame(BookingStatus::Confirmed, $booking->getStatus());
        $payment = $em->getRepository(Payment::class)->findOneBy(['booking' => $booking]);
        self::assertSame(PaymentStatus::Paid, $payment->getStatus());
        self::assertSame('transfer', $payment->getMethod());

        $client->loginUser($user);
        $client->request('GET', '/reservations/'.$booking->getId().'/confirmation');
        self::assertSelectorTextContains('h1', 'confirmée');
        self::assertSelectorTextContains('.bk-direct', 'Règlement reçu par l’organisateur');
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
