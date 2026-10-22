<?php

declare(strict_types=1);

namespace App\Tests\Payment;

use App\Availability\Entity\Availability;
use App\Booking\Entity\Booking;
use App\Booking\Enum\BookingStatus;
use App\Catalog\Entity\Service;
use App\Catalog\Enum\ServiceStatus;
use App\Payment\Entity\Payment;
use App\Payment\Enum\PaymentStatus;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Webhook Stripe Connect (20/10) : un client paie par carte sur le compte
 * Stripe du professionnel puis ferme son navigateur avant le retour ; Stripe
 * prévient TrouveMoi (événement du compte connecté) et la réservation est
 * confirmée quand même. Un appel non signé, ou signé d'un autre secret, est
 * refusé.
 */
final class StripeConnectWebhookTest extends WebTestCase
{
    public function testAPaidSessionOnTheProviderAccountConfirmsTheBooking(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        [$booking, $payment] = $this->startedCardPayment($client, $em);

        $payload = json_encode([
            'id' => 'evt_test_1',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'account' => $payment->getExternalAccount(),
            'data' => ['object' => ['id' => $payment->getExternalId(), 'object' => 'checkout.session', 'payment_status' => 'paid']],
        ], \JSON_THROW_ON_ERROR);

        // Signature d'un autre secret : refusé, rien ne bouge.
        $client->request('POST', '/webhook/stripe', server: ['HTTP_STRIPE_SIGNATURE' => $this->sign($payload, 'whsec_autre')], content: $payload);
        self::assertResponseStatusCodeSame(400);

        $client->request('POST', '/webhook/stripe', server: ['HTTP_STRIPE_SIGNATURE' => $this->sign($payload, 'whsec_test_connect')], content: $payload);
        self::assertResponseIsSuccessful();

        $em->clear();
        $payment = $em->getRepository(Payment::class)->findOneBy(['booking' => $booking->getId()]);
        self::assertSame(PaymentStatus::Paid, $payment->getStatus());
        self::assertSame(BookingStatus::Confirmed, $payment->getBooking()?->getStatus());

        // Le client revient ensuite : rien n'est payé deux fois.
        $client->request('POST', '/webhook/stripe', server: ['HTTP_STRIPE_SIGNATURE' => $this->sign($payload, 'whsec_test_connect')], content: $payload);
        self::assertResponseIsSuccessful();
    }

    public function testAccountUpdatesFollowStripe(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $provider = $this->service($em)->getProvider();
        self::assertNotNull($provider);
        $account = (string) $provider->getStripeAccountId();

        $event = static fn (bool $enabled): string => json_encode(['id' => 'evt_acct', 'object' => 'event', 'type' => 'account.updated', 'account' => $account, 'data' => ['object' => ['id' => $account, 'object' => 'account', 'charges_enabled' => $enabled]]], \JSON_THROW_ON_ERROR);

        $client->request('POST', '/webhook/stripe', server: ['HTTP_STRIPE_SIGNATURE' => $this->sign($event(false), 'whsec_test_connect')], content: $event(false));
        self::assertResponseIsSuccessful();
        $em->clear();
        self::assertFalse($this->service($em)->getProvider()?->isStripeChargesEnabled());

        $client->request('POST', '/webhook/stripe', server: ['HTTP_STRIPE_SIGNATURE' => $this->sign($event(true), 'whsec_test_connect')], content: $event(true));
        $em->clear();
        self::assertTrue($this->service($em)->getProvider()?->isStripeChargesEnabled());
    }

    /** @return array{0: Booking, 1: Payment} */
    private function startedCardPayment(KernelBrowser $client, EntityManagerInterface $em): array
    {
        $service = $this->service($em);
        $start = (new \DateTimeImmutable('+9 days'))->setTime(random_int(6, 20), random_int(0, 59));
        $slot = (new Availability())->setService($service)->setStartsAt($start)->setEndsAt($start->modify('+2 hours'))->setCapacity(20);
        $em->persist($slot);
        $em->flush();

        $user = $em->getRepository(User::class)->findOneBy(['email' => 'marc.dubois@client.trouvemoi.test']);
        self::assertNotNull($user);
        $client->loginUser($user);
        $crawler = $client->request('GET', '/activites/'.$service->getSlug());
        $client->request('POST', '/activites/'.$service->getSlug().'/reserver', [
            '_token' => (string) $crawler->filter('form#reserver input[name="_token"]')->attr('value'),
            'date' => $start->format('Y-m-d'), 'time' => $start->format('H:i'), 'adults' => 1,
        ]);
        $booking = $em->getRepository(Booking::class)->findOneBy(['client' => $user, 'service' => $service], ['createdAt' => 'DESC']);
        self::assertNotNull($booking);

        // Départ vers Stripe, sans revenir sur le site.
        $crawler = $client->request('GET', '/reservations/'.$booking->getId().'/paiement?moyen=stripe');
        $client->submit($crawler->filter('form[action$="/payer"]')->form());
        self::assertResponseRedirects();

        $em->clear();
        $payment = $em->getRepository(Payment::class)->findOneBy(['booking' => $booking->getId()]);
        self::assertNotNull($payment);
        self::assertSame(PaymentStatus::Pending, $payment->getStatus());

        return [$booking, $payment];
    }

    private function service(EntityManagerInterface $em): Service
    {
        $service = $em->getRepository(Service::class)->findOneBy(['slug' => 'descente-en-canoe', 'status' => ServiceStatus::Published]);
        self::assertNotNull($service);

        return $service;
    }

    private function sign(string $payload, string $secret): string
    {
        $time = time();

        return sprintf('t=%d,v1=%s', $time, hash_hmac('sha256', $time.'.'.$payload, $secret));
    }
}
