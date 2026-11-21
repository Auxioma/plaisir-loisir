<?php

declare(strict_types=1);

namespace App\Tests\Favorite;

use App\Catalog\Enum\ActivityLevel;
use App\Favorite\Service\FavoriteService;
use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Enum\PrivateActivityVisibility;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Page « Mes favoris » (/mes-favoris), section activités gratuites.
 *
 * Retour client du 21/11 : chaque activité mise en favori y montrait la même
 * image par défaut (chemin écrit en dur dans le gabarit) au lieu de sa photo.
 */
final class FavoritesPageImageTest extends WebTestCase
{
    public function testAFavoriteFreeActivityShowsItsOwnCover(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail(sprintf('favoris-image-%s@example.com', uniqid()))
            ->setFirstName('Agnès')
            ->setLastName('Test')
            ->setStatus(UserStatus::Active);
        $user->setPassword('peu-importe');
        $entityManager->persist($user);

        $cover = 'uploads/private-activities/couverture-'.uniqid().'.jpg';
        $activity = (new PrivateActivity())
            ->setOrganizer($user)
            ->setTitle('Sortie favorite '.uniqid())
            ->setDescription('Une sortie de test pour l’image des favoris.')
            ->setScheduledAt(new \DateTimeImmutable('+10 days 10:00'))
            ->setCity('Rouen')
            ->setVisibility(PrivateActivityVisibility::Public)
            ->setLevel(ActivityLevel::Beginner)
            ->setMaxParticipants(10)
            ->setCoverImage($cover);
        $entityManager->persist($activity);
        $entityManager->flush();

        static::getContainer()->get(FavoriteService::class)->toggle($user, 'privateActivity', $activity);

        $client->loginUser($user);
        $crawler = $client->request('GET', '/mes-favoris?onglet=activites');

        self::assertResponseIsSuccessful();
        $image = $crawler->filter('.fv-acts .fv-act img');
        self::assertCount(1, $image);
        self::assertStringContainsString($cover, (string) $image->attr('src'));
    }
}
