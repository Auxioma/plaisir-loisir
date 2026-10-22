<?php

declare(strict_types=1);

namespace App\Tests\Catalog;

use App\Catalog\Entity\GiftCard;
use App\Catalog\Repository\GiftCardRepository;
use App\Catalog\Service\GiftCardService;
use App\Notification\Message\SendNotificationEmail;
use App\Payment\Stripe\CheckoutGateway;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class GiftCardFlowTest extends WebTestCase
{
    public function testStripeReturnConfirmsAndEmailsOnlyOnce(): void
    {
        $client = static::createClient();
        $card = $this->card();
        $gateway = $this->createMock(CheckoutGateway::class);
        $gateway->expects(self::once())->method('isCheckoutPaid')->with($card->getCheckoutReference(), 5000, 'EUR', 'gift-'.$card->getId())->willReturn(true);
        $messages = [];
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::exactly(2))->method('dispatch')->willReturnCallback(static function ($message) use (&$messages): Envelope {
            $messages[] = $message;

            return new Envelope($message);
        });
        $service = $this->service($gateway, $bus);
        static::getContainer()->set(GiftCardService::class, $service);
        $client->disableReboot();
        $client->request('GET', '/cadeaux/bon/'.$card->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.gc-done__ok', 'Votre bon cadeau est prêt');
        self::assertSame(GiftCard::STATUS_PAID, $card->getStatus());
        self::assertNotNull($card->getExpiresAt());
        self::assertNotNull($card->getRecipientEmailSentAt());
        self::assertSame('recipient@example.com', $messages[1]->recipientEmail);
        self::assertStringContainsString('/cadeaux/bon/'.$card->getId(), $messages[1]->body);
        $service->confirmBySessionReference((string) $card->getCheckoutReference());
        $client->request('GET', '/cadeaux/bon/'.$card->getId());
        self::assertResponseIsSuccessful();
    }

    public function testUnpaidStripeSessionNeverActivatesOrEmailsTheCard(): void
    {
        static::createClient();
        $card = $this->card();
        $gateway = $this->createStub(CheckoutGateway::class);
        $gateway->method('isCheckoutPaid')->willReturn(false);
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');
        $this->service($gateway, $bus)->synchronize($card);
        self::assertFalse($card->isPaid());
        self::assertNull($card->getExpiresAt());
    }

    public function testCancelledCardCannotBeReactivated(): void
    {
        static::createClient();
        $card = $this->card()->setStatus(GiftCard::STATUS_CANCELLED);
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        $gateway = $this->createMock(CheckoutGateway::class);
        $gateway->expects(self::never())->method('isCheckoutPaid');
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');
        $this->service($gateway, $bus)->synchronize($card);
        self::assertSame(GiftCard::STATUS_CANCELLED, $card->getStatus());
    }

    public function testPaidCardDownloadsAStyledPdfAndPendingCardDoesNot(): void
    {
        $client = static::createClient();
        $card = $this->card();
        $url = '/cadeaux/bon/'.$card->getId().'/pdf';
        $client->request('GET', $url);
        self::assertResponseStatusCodeSame(404);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $card = $em->find(GiftCard::class, $card->getId());
        $card->markPaid(new \DateTimeImmutable());
        $em->flush();
        $client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/pdf');
        self::assertStringStartsWith('%PDF-', $client->getResponse()->getContent());
        self::assertStringContainsString('attachment;', $client->getResponse()->headers->get('Content-Disposition'));
    }

    public function testFailedRecipientEmailCanBeRetriedWithoutResendingBuyerEmail(): void
    {
        static::createClient();
        $card = $this->card();
        $id = $card->getId();
        $gateway = $this->createStub(CheckoutGateway::class);
        $gateway->method('isCheckoutPaid')->willReturn(true);
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::exactly(2))->method('dispatch')->willReturnCallback(static function (SendNotificationEmail $message): Envelope {
            if ('recipient@example.com' === $message->recipientEmail) {
                throw new \RuntimeException('SMTP temporairement indisponible');
            }

            return new Envelope($message);
        });
        try {
            $this->service($gateway, $bus)->synchronize($card);
            self::fail('La panne doit rester visible au contrôleur.');
        } catch (\RuntimeException $e) {
            self::assertSame('SMTP temporairement indisponible', $e->getMessage());
        }
        static::ensureKernelShutdown();
        static::bootKernel();
        $card = static::getContainer()->get(EntityManagerInterface::class)->find(GiftCard::class, $id);
        self::assertTrue($card->isPaid());
        self::assertNotNull($card->getBuyerEmailSentAt());
        self::assertNull($card->getRecipientEmailSentAt());
        $retry = $this->createMock(MessageBusInterface::class);
        $retry->expects(self::once())->method('dispatch')->with(self::callback(static fn (SendNotificationEmail $message): bool => 'recipient@example.com' === $message->recipientEmail))->willReturnCallback(static fn ($message): Envelope => new Envelope($message));
        $this->service($gateway, $retry)->synchronize($card);
        self::assertNotNull($card->getRecipientEmailSentAt());
    }

    public function testProductionNeverSimulatesAPaidGiftWithoutStripe(): void
    {
        static::createClient();
        $card = $this->card();
        $gateway = $this->createMock(CheckoutGateway::class);
        $gateway->expects(self::never())->method('createCheckoutSession');
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');
        $this->expectException(\RuntimeException::class);
        try {
            $this->service($gateway, $bus, '', 'prod')->checkout($card);
        } finally {
            self::assertFalse($card->isPaid());
        }
    }

    private function service(CheckoutGateway $gateway, MessageBusInterface $bus, string $secret = 'sk_test_fake', string $environment = 'test'): GiftCardService
    {
        return new GiftCardService(
            static::getContainer()->get(EntityManagerInterface::class),
            static::getContainer()->get(GiftCardRepository::class),
            $bus,
            static::getContainer()->get(UrlGeneratorInterface::class),
            static fn (): CheckoutGateway => $gateway,
            $secret,
            $environment,
        );
    }

    private function card(): GiftCard
    {
        $card = (new GiftCard())->setCode('TEST-'.bin2hex(random_bytes(5)))->setLabel('Une activité à offrir')->setAmount('50.00')
            ->setBuyerName('Alice')->setBuyerEmail('buyer@example.com')->setRecipientName('Bob')->setRecipientEmail('recipient@example.com')
            ->setMessage('Joyeux anniversaire !')->setCheckoutReference('cs_test_'.bin2hex(random_bytes(8)));
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($card);
        $em->flush();

        return $card;
    }
}
