<?php

declare(strict_types=1);

namespace App\Tests\Home;

use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Page d'accueil — maquette landing_page (05/10) : une seule page pour les
 * visiteurs et les membres, données réelles, liens vivants, recherche à
 * trois modes.
 */
final class LandingPageTest extends WebTestCase
{
    public function testVisitorsAndMembersSeeTheSameOffer(): void
    {
        $client = static::createClient();
        $guest = $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Explorez vos envies');
        self::assertGreaterThan(0, $guest->filter('.fa-card .fa-badge--free')->count(), 'Les activités gratuites doivent être visibles sans connexion.');
        self::assertGreaterThan(0, $guest->filter('a.ld-card__title[href^="/activites/"]')->count(), 'Les activités des prestataires doivent être visibles.');
        self::assertGreaterThan(0, $guest->filter('.ld-dest')->count());
        self::assertCount(0, $guest->filter('.tm-account__toggle'));

        $client->loginUser($this->makeUser());
        $member = $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Explorez vos envies');
        self::assertSame($guest->filter('.ld-card')->count(), $member->filter('.ld-card')->count());
        self::assertCount(1, $member->filter('.tm-account__toggle'));
    }

    public function testEveryLinkOfThePageLeadsSomewhere(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeUser());
        $crawler = $client->request('GET', '/');

        $hrefs = array_unique(array_filter(
            $crawler->filter('.ld a[href], .tm-header a[href]')->each(static fn ($a): string => (string) $a->attr('href')),
            static fn (string $h): bool => str_starts_with($h, '/'),
        ));
        self::assertGreaterThan(15, \count($hrefs));
        foreach ($hrefs as $href) {
            $client->request('GET', $href);
            self::assertLessThan(400, $client->getResponse()->getStatusCode(), sprintf('Le lien %s de l’accueil échoue.', $href));
        }
    }

    public function testTheSearchModesLeadToTheRightResults(): void
    {
        $client = static::createClient();

        $client->request('GET', '/activites?type=gratuites&lieu=Rouen&q=&date=');
        self::assertResponseRedirects('/activites-privees?lieu=Rouen');

        $client->request('GET', '/activites?type=toutes&lieu=Rouen');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.ex-free-banner');

        $client->request('GET', '/activites-privees?lieu=Rouen');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.fa-grid', 'Sortie vélo');
    }

    private function makeUser(): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())
            ->setEmail(sprintf('accueil-%s@example.com', uniqid()))
            ->setFirstName('Alix')
            ->setLastName('Test')
            ->setStatus(UserStatus::Active);
        $user->setPassword('peu-importe');
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
