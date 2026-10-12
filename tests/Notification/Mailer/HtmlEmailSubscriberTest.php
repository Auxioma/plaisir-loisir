<?php

declare(strict_types=1);

namespace App\Tests\Notification\Mailer;

use App\Notification\Mailer\HtmlEmailSubscriber;
use PHPUnit\Framework\TestCase;

final class HtmlEmailSubscriberTest extends TestCase
{
    public function testTheTextIsSplitIntoTypedBlocks(): void
    {
        $blocks = HtmlEmailSubscriber::blocks("Bonjour Camille,\n\nVous avez demandé à réinitialiser le mot de passe de votre\ncompte TrouveMoi.\n\nVotre code de vérification est : AB12CD34\n\nVoir l'activité et répondre : https://trouvemoi.eu/activites-privees/1\n\nL'équipe TrouveMoi Plaisirs & Loisirs");

        self::assertSame(['paragraph', 'paragraph', 'code', 'button', 'signature'], array_column($blocks, 'type'));
        self::assertSame('AB12CD34', $blocks[2]['code']);
        self::assertSame('https://trouvemoi.eu/activites-privees/1', $blocks[3]['url']);
        self::assertSame('Voir l\'activité et répondre', $blocks[3]['label']);
        // Ligne coupée au milieu d'une phrase : recollée.
        self::assertStringContainsString('de votre compte TrouveMoi', $blocks[1]['html']);
    }

    public function testParagraphsAreEscapedAndLinksClickable(): void
    {
        $blocks = HtmlEmailSubscriber::blocks('Message <b>gras</b> et lien https://trouvemoi.eu/aide dans la phrase.');

        self::assertStringContainsString('&lt;b&gt;', $blocks[0]['html']);
        self::assertStringContainsString('<a href="https://trouvemoi.eu/aide"', $blocks[0]['html']);
    }
}
