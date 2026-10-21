<?php

declare(strict_types=1);

namespace App\Tests\Event;

use App\Event\Entity\Event;
use App\Event\Service\EventDocumentRenderer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

final class EventDocumentTest extends KernelTestCase
{
    public function testInvitationAndFullEventProduceDifferentPdfs(): void
    {
        self::bootKernel();
        $event = (new Event())->setTitle('Une invitation de test')->setSlug('invitation-test')
            ->setStartsAt(new \DateTimeImmutable('+3 days'))->setDescription('Description complète et détaillée.');
        $renderer = static::getContainer()->get(EventDocumentRenderer::class);
        foreach ([true, false] as $invitation) {
            $response = $renderer->response($event, 'https://example.com/event', $invitation, [
                'event' => ['organizer' => 'Camille', 'hours' => '14:00'], 'going' => [],
                'participants_visible' => false, 'online_visible' => false, 'waiting' => 0,
            ]);
            self::assertSame('application/pdf', $response->headers->get('Content-Type'));
            self::assertStringStartsWith('%PDF-', (string) $response->getContent());
            self::assertStringContainsString($invitation ? 'invitation-' : 'evenement-', (string) $response->headers->get('Content-Disposition'));
        }
    }

    public function testFullDocumentKeepsLongDescriptionAndHidesOnlineLinkAndParticipants(): void
    {
        self::bootKernel();
        $event = (new Event())->setTitle('Détails')->setSlug('details')
            ->setStartsAt(new \DateTimeImmutable('+3 days'))->setLocationType('online')
            ->setOnlineUrl('https://secret.example.com')->setDescription(str_repeat('Tout le détail. ', 80).'FIN DU TEXTE');
        $html = static::getContainer()->get(Environment::class)->render('event/document_pdf.html.twig', [
            'entity' => $event, 'event' => ['organizer' => 'Camille', 'hours' => '14:00'],
            'invitation' => false, 'going' => [], 'participants_visible' => false,
            'online_visible' => false, 'waiting' => 0, 'photos' => [], 'qr' => '', 'link' => 'https://example.com',
        ]);
        self::assertStringContainsString('FIN DU TEXTE', $html);
        self::assertStringNotContainsString('secret.example.com', $html);
        self::assertStringContainsString('ne pas afficher', $html);
    }
}
