<?php

declare(strict_types=1);

namespace App\Tests\Home;

use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Trois liens morts de l'accueil connecté (Lot K, 16/09) : « Créer une
 * activité privée », « En savoir plus » et « Découvrez nos abonnements »
 * ne menaient nulle part (href="#"). Même angle que HomeLinksTest, côté
 * home/connected.html.twig plutôt que home/index.html.twig.
 */
final class HomeConnectedLinksTest extends WebTestCase
{
    public function testThePreviouslyDeadLinksNowOpenARealPage(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeUser());

        $crawler = $client->request('GET', '/');
        self::assertResponseIsSuccessful();

        $liens = [
            'Créer une activité privée',
            'En savoir plus',
            'Découvrez nos abonnements',
        ];

        foreach ($liens as $texte) {
            $lien = $crawler->filterXPath(sprintf('//a[contains(., "%s")]', $texte));
            self::assertGreaterThan(0, $lien->count(), sprintf('Le lien "%s" est introuvable sur l\'accueil connecté.', $texte));

            $href = (string) $lien->first()->attr('href');
            self::assertNotSame('#', $href, sprintf('Le lien "%s" est toujours mort (href="#").', $texte));

            $client->request('GET', $href);
            self::assertLessThan(400, $client->getResponse()->getStatusCode(), sprintf('Le lien "%s" mène à %s, qui échoue.', $texte, $href));
        }
    }

    private function makeUser(): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail(sprintf('accueil-connecte-%s@example.com', uniqid()))
            ->setFirstName('Alix')
            ->setLastName('Test')
            ->setStatus(UserStatus::Active);
        $user->setPassword('peu-importe');

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
