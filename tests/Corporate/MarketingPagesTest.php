<?php

declare(strict_types=1);

namespace App\Tests\Corporate;

use App\Booking\Service\BookingService;
use App\Corporate\Entity\ContactMessage;
use App\Notification\Entity\NewsletterSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Pages refaites selon les maquettes du 04/10 : elles doivent fonctionner,
 * pas seulement s'afficher (offres réelles, newsletter enregistrée,
 * formulaire de contact contrôlé, filtre de langue d'Explorer).
 */
final class MarketingPagesTest extends WebTestCase
{
    public function testDealsAreTheRunningPromotionsWithTheirDiscountedPrice(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/offres');

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('.ofm-card')->count(), 'Aucune offre en cours affichée.');
        self::assertGreaterThan(0, $crawler->filter('.ofm-card .ofm-price s')->count(), 'Aucun prix barré : la remise n’est pas appliquée.');

        // Le filtre « -40 % et plus » ne garde que les grosses remises.
        $crawler = $client->request('GET', '/offres?reduction[]=40');
        foreach ($crawler->filter('.ofm-card .ofm-badge') as $badge) {
            self::assertMatchesRegularExpression('/^-(4\d|[5-9]\d)%$/', trim($badge->textContent));
        }
    }

    public function testTheBookedPriceIsTheDiscountedOne(): void
    {
        self::assertSame('36.00', BookingService::discount('45.00', 20));
        self::assertSame('16.99', BookingService::discount('19.99', 15));
        self::assertSame('25.00', BookingService::discount('25.00', 0));
    }

    public function testTheNewsletterFormStoresTheSubscriber(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/offres');
        $email = sprintf('abonne-%s@example.com', bin2hex(random_bytes(4)));

        $client->submit($crawler->filter('form[data-newsletter]')->form(['email' => $email]));
        self::assertResponseRedirects();

        $subscriber = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(NewsletterSubscriber::class)->findOneBy(['email' => $email]);
        self::assertNotNull($subscriber, 'L’inscription à la newsletter n’a pas été enregistrée.');
        self::assertSame('offres', $subscriber->getSource());
    }

    public function testTheContactFormRequiresItsFieldsAndRecordsTheMessage(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/contactez-nous');
        $form = $crawler->filter('form[data-pg-form]')->form();

        $client->submit($form, ['nom' => '', 'email' => 'pas-un-email', 'sujet' => '', 'message' => '']);
        self::assertResponseStatusCodeSame(422);

        $crawler = $client->request('GET', '/contactez-nous?sujet=Paiement');
        self::assertSame('Paiement', $crawler->filter('select[name="sujet"] option[selected]')->attr('value'));
        $client->submit($crawler->filter('form[data-pg-form]')->form(), [
            'nom' => 'Camille Test',
            'email' => 'camille@example.com',
            'message' => 'Bonjour, une question sur le paiement de ma réservation.',
        ]);
        self::assertResponseRedirects('/contactez-nous');

        $message = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(ContactMessage::class)->findOneBy(['email' => 'camille@example.com'], ['createdAt' => 'DESC']);
        self::assertNotNull($message);
        self::assertSame('Paiement', $message->getSubject());
    }

    public function testExplorerFiltersLeadToTheActivitySearch(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/explorer');

        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[data-pg-form]');
        self::assertSame('/activites', $form->attr('action'));
        self::assertGreaterThan(0, $crawler->filter('select[name="langue"] option[value="Anglais"]')->count());

        // Le filtre de langue est réellement appliqué par la recherche.
        $all = $client->request('GET', '/activites')->filter('a[href^="/activites/"]')->count();
        $english = $client->request('GET', '/activites?langue=Anglais')->filter('a[href^="/activites/"]')->count();
        $none = $client->request('GET', '/activites?langue=Klingon')->filter('a[href^="/activites/"]')->count();
        self::assertGreaterThan(0, $english);
        self::assertLessThan($all, $english);
        self::assertLessThan($english, $none + 1);
    }
}
