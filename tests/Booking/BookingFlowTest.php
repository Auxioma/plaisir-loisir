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
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tunnel de réservation depuis la fiche d'une activité (04/10, refait le
 * 20/10) : créneau, voyageurs, récapitulatif, choix du moyen de paiement,
 * validation, paiement confirmé ; places décomptées ; un visiteur est renvoyé
 * vers la connexion.
 *
 * Le client paie le professionnel DIRECTEMENT : par carte sur son compte
 * Stripe (Stripe Connect) ou par PayPal — Stripe et PayPal sont simulés en
 * test —, la réservation est alors confirmée ; ou sur place, et le
 * professionnel confirme puis déclare le règlement. Facture et QR des deux
 * côtés.
 */
final class BookingFlowTest extends WebTestCase
{
    public function testAClientPaysTheProviderByCardOnHisStripeAccount(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        [$service, $slot] = $this->serviceWithSlot($em);
        $user = $this->client($em, 'julie.martin@client.trouvemoi.test');
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
        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Finaliser ma réservation');
        self::assertSelectorExists('.bk-pay form[method="post"][action$="/payer"]');
        self::assertSelectorNotExists('.bk-progress');
        $booking = $this->lastBooking($em, $user, $service);
        self::assertSame($before + 3, $em->getRepository(Availability::class)->find($slot->getId())->getBooked());

        // La page affichée après « Réserver » contient directement les moyens acceptés.
        self::assertSelectorExists('input[name="moyen"][value="stripe"]');
        self::assertSelectorExists('input[name="moyen"][value="paypal"]');
        self::assertSelectorExists('input[name="moyen"][value="onsite"]');

        // Un seul formulaire après « Réserver », puis départ direct vers Stripe.
        $crawler = $client->getCrawler();
        self::assertSelectorTextContains('.bk-methods', 'Stripe');
        $client->submit($crawler->filter('form[action$="/payer"]')->form());
        self::assertResponseRedirects();
        self::assertStringContainsString('/paiement/retour?session_id=mock_cs_', (string) $client->getResponse()->headers->get('Location'));
        $client->followRedirect();
        self::assertResponseRedirects('/reservations/'.$booking->getId().'/confirmation');
        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Paiement effectué avec succès');
        self::assertSelectorTextContains('.rg-grid', 'Visa **** 4242');
        self::assertSelectorTextContains('.rg-qr', '#'.$booking->getReference());
        self::assertSelectorExists('.rg-invoice');

        $em->clear();
        $booking = $em->getRepository(Booking::class)->find($booking->getId());
        self::assertSame(BookingStatus::Confirmed, $booking->getStatus(), 'Payée en ligne : la réservation est confirmée.');
        $payment = $em->getRepository(Payment::class)->findOneBy(['booking' => $booking]);
        self::assertSame(PaymentStatus::Paid, $payment->getStatus());
        self::assertSame(Payment::CHANNEL_STRIPE, $payment->getChannel());
        self::assertSame('card', $payment->getMethod());
        self::assertStringStartsWith('mock_acct_', (string) $payment->getExternalAccount(), 'Session créée sur le compte Stripe du professionnel.');
        self::assertMatchesRegularExpression('/^FACT-\d{4}-\d{5}$/', (string) $payment->getInvoiceNumber());

        // Revenir sur l'adresse de retour ne refait rien.
        $client->request('GET', '/reservations/'.$booking->getId().'/paiement/retour?session_id='.$payment->getExternalId());
        self::assertResponseRedirects('/reservations/'.$booking->getId().'/confirmation');

        // Facture PDF et QR côté client.
        $client->request('GET', '/reservations/'.$booking->getId().'/facture?telecharger=1');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertStringContainsString('attachment', (string) $client->getResponse()->headers->get('Content-Disposition'));
        self::assertStringStartsWith('%PDF', (string) $client->getResponse()->getContent());
        // Billet PDF : infos de l'activité, facture et QR à présenter.
        $client->request('GET', '/reservations/'.$booking->getId().'/qr');
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertStringStartsWith('%PDF', (string) $client->getResponse()->getContent());

        // Un autre client n'y a pas accès.
        $client->loginUser($this->client($em, 'marc.dubois@client.trouvemoi.test'));
        $client->request('GET', '/reservations/'.$booking->getId().'/facture');
        self::assertResponseStatusCodeSame(404);

        // Côté professionnel : réservation payée, facture, QR, contrôle de l'entrée.
        $client->loginUser($service->getProvider()?->getUser());
        $client->request('GET', '/pro/reservations/'.$booking->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Paiement reçu sur votre compte prestataire');
        self::assertSelectorTextContains('.rg-received', number_format((float) $booking->getTotalPrice(), 2, ',', ' '));
        self::assertSelectorTextNotContains('.rg', 'Frais de service');
        self::assertSelectorTextContains('.rg-timeline', 'Paiement validé');
        $client->request('GET', '/pro/reservations/'.$booking->getId().'/facture');
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertStringContainsString('inline', (string) $client->getResponse()->headers->get('Content-Disposition'));

        $crawler = $client->request('GET', '/pro/reservations/controle?code='.$booking->getReference());
        self::assertResponseRedirects('/pro/reservations/'.$booking->getId().'/controle');
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('.rg-banner', 'Réservation valable');
        $client->submit($crawler->filter('form[action$="/entree"]')->form());
        $client->followRedirect();
        self::assertSelectorTextContains('.rg-banner', 'Entrée déjà validée');
        $em->clear();
        self::assertNotNull($em->getRepository(Booking::class)->find($booking->getId())->getCheckedInAt());

        $client->request('GET', '/pro/reservations/controle?code=RZZZZZZ');
        self::assertSelectorTextContains('.rg-banner', 'QR non reconnu');
    }

    public function testAClientPaysTheProviderWithPaypal(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        [$service, $slot] = $this->serviceWithSlot($em);
        $user = $this->client($em, 'julie.martin@client.trouvemoi.test');
        $client->loginUser($user);
        $booking = $this->book($client, $em, $user, $service, $slot);

        $crawler = $client->request('GET', '/reservations/'.$booking->getId().'/paiement?moyen=paypal');
        $client->submit($crawler->filter('form[action$="/payer"]')->form());
        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertMatchesRegularExpression('#/paiement/retour\?token=MOCK\d+X#', $location);

        // Une commande PayPal qui n'est pas celle de cette réservation est refusée.
        $client->request('GET', '/reservations/'.$booking->getId().'/paiement/retour?token=MOCK100XABCDEF');
        self::assertResponseRedirects('/reservations/'.$booking->getId().'/paiement');
        $em->clear();
        self::assertSame(PaymentStatus::Pending, $em->getRepository(Payment::class)->findOneBy(['booking' => $booking->getId()])->getStatus());

        $client->request('GET', $location);
        self::assertResponseRedirects('/reservations/'.$booking->getId().'/confirmation');
        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Paiement effectué avec succès');

        $em->clear();
        $payment = $em->getRepository(Payment::class)->findOneBy(['booking' => $booking->getId()]);
        self::assertSame(PaymentStatus::Paid, $payment->getStatus());
        self::assertSame('paypal', $payment->getMethod());
        self::assertSame(BookingStatus::Confirmed, $payment->getBooking()?->getStatus());
    }

    public function testAClientPaysOnSiteAndTheProviderDeclaresThePayment(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        [$service, $slot] = $this->serviceWithSlot($em);
        $user = $this->client($em, 'julie.martin@client.trouvemoi.test');
        $client->loginUser($user);
        $booking = $this->book($client, $em, $user, $service, $slot);

        $crawler = $client->request('GET', '/reservations/'.$booking->getId().'/paiement?moyen=onsite');
        self::assertSelectorTextContains('.bk-methods', 'sur place');
        $client->submit($crawler->filter('form[action$="/payer"]')->form());
        self::assertResponseRedirects('/reservations/'.$booking->getId().'/confirmation');
        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Demande envoyée');

        $em->clear();
        $payment = $em->getRepository(Payment::class)->findOneBy(['booking' => $booking->getId()]);
        self::assertSame(PaymentStatus::Pending, $payment->getStatus());
        self::assertSame(Payment::CHANNEL_ONSITE, $payment->getChannel());
        self::assertSame(BookingStatus::Pending, $payment->getBooking()?->getStatus(), 'Sur place : le professionnel confirme.');

        // Le tunnel est terminé : plus de paiement possible.
        $client->request('GET', '/reservations/'.$booking->getId().'/paiement');
        self::assertResponseRedirects('/reservations/'.$booking->getId().'/confirmation');

        // Le professionnel confirme puis déclare le règlement en espèces.
        $client->loginUser($service->getProvider()?->getUser());
        $crawler = $client->request('GET', '/pro/reservations/'.$booking->getId());
        self::assertSelectorTextContains('h1', 'Réservation à confirmer');
        $client->submit($crawler->filter(sprintf('form[action$="/%s/confirm"]', $booking->getId()))->form());
        $crawler = $client->request('GET', '/pro/reservations/'.$booking->getId());
        $client->submit($crawler->filter(sprintf('form[action$="/%s/reglement"]', $booking->getId()))->form(['method' => 'cash']));
        $client->request('GET', '/pro/reservations/'.$booking->getId());
        self::assertSelectorTextContains('h1', 'Paiement reçu');

        $em->clear();
        $payment = $em->getRepository(Payment::class)->findOneBy(['booking' => $booking->getId()]);
        self::assertSame(PaymentStatus::Paid, $payment->getStatus());
        self::assertSame('cash', $payment->getMethod());
        self::assertNotNull($payment->getInvoiceNumber());
        self::assertSame(BookingStatus::Confirmed, $payment->getBooking()?->getStatus());

        $client->loginUser($user);
        $client->request('GET', '/reservations/'.$booking->getId().'/confirmation');
        self::assertSelectorTextContains('h1', 'Règlement reçu par le prestataire');
        $client->request('GET', '/reservations/'.$booking->getId().'/facture');
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
    }

    public function testAClientCanAbandonAnUnfinishedOnlinePayment(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        [$service, $slot] = $this->serviceWithSlot($em);
        $user = $this->client($em, 'julie.martin@client.trouvemoi.test');
        $client->loginUser($user);
        $booking = $this->book($client, $em, $user, $service, $slot);

        // Départ vers PayPal sans retour : rien n'est débité.
        $crawler = $client->request('GET', '/reservations/'.$booking->getId().'/paiement?moyen=paypal');
        $client->submit($crawler->filter('form[action$="/payer"]')->form());
        $client->request('GET', '/reservations/'.$booking->getId().'/paiement/annule?moyen=paypal');
        self::assertResponseRedirects('/reservations/'.$booking->getId().'/paiement?moyen=paypal');

        $crawler = $client->request('GET', '/reservations/'.$booking->getId());
        self::assertSelectorTextContains('h1', 'Finaliser ma réservation');
        $client->submit($crawler->filter('form[action$="/annuler"]')->form());

        $em->clear();
        self::assertSame(BookingStatus::Cancelled, $em->getRepository(Booking::class)->find($booking->getId())->getStatus());
        self::assertNull($em->getRepository(Payment::class)->findOneBy(['booking' => $booking->getId()]), 'Le paiement non finalisé est effacé.');
        self::assertSame($slot->getBooked(), $em->getRepository(Availability::class)->find($slot->getId())->getBooked(), 'Places libérées.');
    }

    public function testAMethodTheProviderDoesNotAcceptIsRefused(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        [$service, $slot] = $this->serviceWithSlot($em);
        $user = $this->client($em, 'julie.martin@client.trouvemoi.test');
        $client->loginUser($user);
        $booking = $this->book($client, $em, $user, $service, $slot);

        $client->request('GET', '/reservations/'.$booking->getId().'/paiement/validation?moyen=bitcoin');
        self::assertResponseRedirects('/reservations/'.$booking->getId().'/paiement');

        $crawler = $client->request('GET', '/reservations/'.$booking->getId().'/paiement?moyen=stripe');
        $client->request('POST', '/reservations/'.$booking->getId().'/payer', [
            '_token' => (string) $crawler->filter('form[action$="/payer"] input[name="_token"]')->attr('value'),
            'moyen' => 'transfer',
        ]);
        self::assertResponseRedirects('/reservations/'.$booking->getId().'/paiement');
        $em->clear();
        self::assertNull($em->getRepository(Payment::class)->findOneBy(['booking' => $booking->getId()]));
    }

    public function testCheckoutOnlyDisplaysMethodsStillAcceptedByTheProvider(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        [$service, $slot] = $this->serviceWithSlot($em);
        $user = $this->client($em, 'julie.martin@client.trouvemoi.test');
        $client->loginUser($user);
        $booking = $this->book($client, $em, $user, $service, $slot);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $providerId = $service->getProvider()->getId();
        $provider = $em->find(\App\Provider\Entity\ProviderProfile::class, $providerId);
        $paypal = $provider->acceptsPaypal();
        $cash = $provider->acceptsCash();
        $provider->setAcceptsPaypal(false)->setAcceptsCash(false);
        $em->flush();
        try {
            $client->request('GET', '/reservations/'.$booking->getId().'?moyen=paypal');
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('input[name="moyen"][value="stripe"]:checked');
            self::assertSelectorNotExists('input[name="moyen"][value="paypal"]');
            self::assertSelectorNotExists('input[name="moyen"][value="onsite"]');
        } finally {
            $em = static::getContainer()->get(EntityManagerInterface::class);
            $provider = $em->find(\App\Provider\Entity\ProviderProfile::class, $providerId);
            $provider->setAcceptsPaypal($paypal)->setAcceptsCash($cash);
            $em->flush();
        }
    }

    public function testOldReviewLinksReturnToTheSingleCheckoutWithTheSelectedMethod(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        [$service, $slot] = $this->serviceWithSlot($em);
        $user = $this->client($em, 'julie.martin@client.trouvemoi.test');
        $client->loginUser($user);
        $booking = $this->book($client, $em, $user, $service, $slot);
        $client->request('GET', '/reservations/'.$booking->getId().'/paiement/validation?moyen=paypal');
        self::assertResponseRedirects('/reservations/'.$booking->getId().'/paiement?moyen=paypal');
        $client->followRedirect();
        self::assertSelectorExists('form[action$="/payer"] input[value="paypal"]:checked');
        self::assertSelectorNotExists('form[action*="/validation"]');
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

    #[\PHPUnit\Framework\Attributes\DataProvider('authenticationPaths')]
    public function testVisitorResumesAfterRealLoginAndCannotReplayTheDraft(bool $register): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        [$service, $slot] = $this->serviceWithSlot($em);
        $initialCount = $em->getRepository(Booking::class)->count(['service' => $service->getId()]);
        $user = $this->client($em, 'marc.dubois@client.trouvemoi.test');
        $hasher = static::getContainer()->get(\Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, 'ResumePassword123'));
        $em->flush();
        $client->request('POST', '/activites/'.$service->getSlug().'/reserver', [
            'date' => $slot->getStartsAt()->format('Y-m-d'), 'time' => $slot->getStartsAt()->format('H:i'), 'adults' => 3,
        ]);
        if ($register) {
            $email = sprintf('booking-signup-%s@example.com', uniqid());
            $crawler = $client->request('GET', '/inscription');
            $client->submit($crawler->selectButton('Créer un compte')->form([
                'registration_form[fullName]' => 'Alice Réservation',
                'registration_form[email]' => $email,
                'registration_form[password]' => 'ResumePassword123',
                'registration_form[agreeTerms]' => '1',
            ]));
            self::assertResponseRedirects('/verification-email');
            $message = self::getMailerMessage();
            self::assertNotNull($message);
            self::assertSame(1, preg_match('/code de vérification est : ([A-Z0-9]{8})/', $message->getTextBody(), $matches));
            $crawler = $client->followRedirect();
            $client->request('POST', '/verification-email', [
                'code' => $matches[1],
                '_token' => $crawler->filter('input[name="_token"]')->first()->attr('value'),
            ]);
            self::assertResponseRedirects('/login');
        } else {
            $email = $user->getEmail();
        }
        $crawler = $client->followRedirect();
        $form = $crawler->filterXPath('//form[.//input[@name="_email"]]')->form();
        $form['_email'] = $email;
        $form['_password'] = 'ResumePassword123';
        $client->submit($form);
        self::assertResponseRedirects('/reservations/reprendre');
        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();
        $client->submit($crawler->filter('form[data-controller="booking-resume"]')->form());
        self::assertResponseRedirects();
        self::assertMatchesRegularExpression('#/reservations/[0-9A-Z]+$#', $client->getResponse()->headers->get('Location'));
        $bookingId = basename($client->getResponse()->headers->get('Location'));
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $booking = $em->getRepository(Booking::class)->find($bookingId);
        self::assertNotNull($booking);
        self::assertSame(3, $booking->getParticipants());
        $client->request('POST', '/reservations/reprendre');
        self::assertResponseRedirects('/activites');
        self::assertSame($initialCount + 1, $em->getRepository(Booking::class)->count(['service' => $service->getId()]));

        $paymentUrl = '/reservations/'.$booking->getId().'/paiement';
        $client->getCookieJar()->clear();
        $client->request('GET', $paymentUrl);
        self::assertResponseRedirects('http://localhost/login');
        $crawler = $client->followRedirect();
        $form = $crawler->filterXPath('//form[.//input[@name="_email"]]')->form();
        $form['_email'] = $email;
        $form['_password'] = 'ResumePassword123';
        $client->submit($form);
        self::assertResponseRedirects('http://localhost'.$paymentUrl);
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public static function authenticationPaths(): iterable
    {
        yield 'existing account' => [false];
        yield 'registration and email verification' => [true];
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

    private function client(EntityManagerInterface $em, string $email): User
    {
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user);

        return $user;
    }

    private function lastBooking(EntityManagerInterface $em, User $user, Service $service): Booking
    {
        $booking = $em->getRepository(Booking::class)->findOneBy(['client' => $user, 'service' => $service], ['createdAt' => 'DESC']);
        self::assertNotNull($booking);

        return $booking;
    }

    /** Réserve 2 places sur le créneau et renvoie la réservation (en attente de paiement). */
    private function book(KernelBrowser $client, EntityManagerInterface $em, User $user, Service $service, Availability $slot): Booking
    {
        $crawler = $client->request('GET', '/activites/'.$service->getSlug());
        $client->request('POST', '/activites/'.$service->getSlug().'/reserver', [
            '_token' => (string) $crawler->filter('form#reserver input[name="_token"]')->attr('value'),
            'date' => $slot->getStartsAt()->format('Y-m-d'),
            'time' => $slot->getStartsAt()->format('H:i'),
            'adults' => 2,
        ]);
        // L'identifiant vient de la redirection : « la dernière réservation »
        // du client peut être celle d'un autre test (fixtures aléatoires).
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertMatchesRegularExpression('#^/reservations/([0-9A-HJKMNP-TV-Z]{26})$#', $location, 'Réservation refusée : '.implode(' ', $client->getRequest()->getSession()->getFlashBag()->peek('error')));
        $booking = $em->getRepository(Booking::class)->find(substr($location, \strlen('/reservations/')));
        self::assertNotNull($booking);
        self::assertSame($user->getId()?->toString(), $booking->getClient()?->getId()?->toString());

        return $booking;
    }

    /** @return array{0: Service, 1: Availability} */
    private function serviceWithSlot(EntityManagerInterface $em): array
    {
        $service = $em->getRepository(Service::class)->findOneBy(['slug' => 'descente-en-canoe', 'status' => ServiceStatus::Published]);
        self::assertNotNull($service);
        // Jour unique par appel : plusieurs tests créent un créneau sur la même activité.
        static $n = 0;
        $start = (new \DateTimeImmutable(sprintf('+%d days', 6 + ++$n)))->setTime(10, random_int(0, 59));
        $slot = (new Availability())->setService($service)
            ->setStartsAt($start)->setEndsAt($start->modify('+2 hours'))->setCapacity(20);
        $em->persist($slot);
        $em->flush();

        return [$service, $slot];
    }
}
