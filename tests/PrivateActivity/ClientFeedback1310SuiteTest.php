<?php

declare(strict_types=1);

namespace App\Tests\PrivateActivity;

use App\Catalog\Entity\Category;
use App\Catalog\Entity\CategorySuggestion;
use App\Catalog\Enum\ActivityLevel;
use App\Notification\Command\BirthdayNotificationsCommand;
use App\Notification\Entity\Notification;
use App\PrivateActivity\Entity\Photo;
use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Enum\PrivateActivityVisibility;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Retours client du 13/10, points 21 à 37 : accueil sans champ « code
 * postal », Destinations, catégorie proposée sans autre choix, album à dépôt
 * multiple, offres, cartes d'événements, notifications.
 */
final class ClientFeedback1310SuiteTest extends WebTestCase
{
    public function testTheHomePageNoLongerAsksForAPostalCode(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.ld-near');
        self::assertSelectorNotExists('input[name="ville"]');
    }

    public function testDestinationFiltersReallyFilterAndEveryResultIsReachable(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/destinations', ['type' => 'villes']);
        self::assertResponseIsSuccessful();
        // « île » ne répond plus à « ville » : Bali n'est pas une ville.
        self::assertStringNotContainsString('Bali', $crawler->filter('.ds-grid')->text());
        self::assertSelectorExists('.ds-active');

        $all = $client->request('GET', '/destinations', ['page' => 9])->filter('.ds-card')->count();
        $cheap = $client->request('GET', '/destinations', ['budget' => 25, 'page' => 9])->filter('.ds-card')->count();
        self::assertLessThan($all, $cheap);
        self::assertSelectorNotExists('.ex-more');
    }

    public function testAMemberTypesANewCategoryAndGoesStraightToStepTwo(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeUser());
        $name = 'Catégorie test '.uniqid();

        $page = '/compte/activites-privees/creer/1';
        $client->request('POST', $page, $this->token($client, $page) + [
            'action' => 'next',
            'title' => 'Sortie avec une nouvelle catégorie',
            'level' => ActivityLevel::Beginner->value,
            'description' => str_repeat('Une sortie conviviale entre membres. ', 3),
            'suggest_name' => $name,
        ]);

        self::assertResponseRedirects('/compte/activites-privees/creer/2');
        $suggestion = $this->em()->getRepository(CategorySuggestion::class)->findOneBy(['name' => $name]);
        self::assertNotNull($suggestion);
        self::assertTrue($suggestion->isPending());
    }

    public function testAProfessionalTypesANewCategoryAndGoesStraightToStepTwo(): void
    {
        $client = static::createClient();
        $pro = $this->em()->getRepository(User::class)->findOneBy(['email' => 'annonceur@trouvemoi.test']);
        self::assertNotNull($pro, 'Fixtures requises (annonceur@trouvemoi.test).');
        $client->loginUser($pro);
        $client->request('GET', '/pro/activites/nouvelle?vierge=1');

        $page = '/pro/activites/assistant/1';
        $client->request('POST', $page, $this->token($client, $page) + [
            'action' => 'next',
            'title' => 'Activité pro catégorie proposée',
            'activity_type' => 'supervised',
            'level' => 'beginner',
            'languages' => ['Français'],
            'subtitle' => 'Une accroche de test assez longue.',
            'suggest_name' => 'Catégorie pro '.uniqid(),
        ]);

        self::assertResponseRedirects('/pro/activites/assistant/2');
    }

    public function testTheAlbumAcceptsSeveralPhotosAtOnce(): void
    {
        $client = static::createClient();
        $organizer = $this->makeUser();
        $activity = $this->makeActivity($organizer);
        $client->loginUser($organizer);

        $url = '/compte/albums/'.$activity->getId();
        $crawler = $client->request('GET', $url);
        self::assertSelectorExists('form[data-album-drop] input[name="photos[]"][multiple]');
        $token = (string) $crawler->filter('form[data-album-drop] input[name="_token"]')->attr('value');

        $client->request('POST', $url, ['_token' => $token], ['photos' => [$this->image(), $this->image()]]);
        self::assertResponseRedirects($url);
        $client->followRedirect();
        self::assertSelectorTextContains('body', '2 photos ajoutées');
        self::assertCount(2, $this->em()->getRepository(Photo::class)->findBy(['author' => $organizer]));
    }

    public function testEventCardsShowOnlyRealData(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/evenements');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('.evl-card .evl-btn'));
        self::assertCount(0, $crawler->filter('.evl-avatars'));
    }

    public function testAPaidActivityPresentsItsOrganizerLikeAFreeOne(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/activites/atelier-cuisine-provencale');

        self::assertResponseIsSuccessful();
        $link = $crawler->filter('.act-org a[href^="/professionnels/"]')->first();
        self::assertCount(1, $link);
        self::assertSelectorTextContains('.act-org', 'Professionnel vérifié');

        $client->request('GET', (string) $link->attr('href'));
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.pdp .mb-head .mb-head__avatar');
        self::assertSelectorExists('.pdp-trust');
    }

    public function testMembersAreWishedAHappyBirthdayOnce(): void
    {
        $user = $this->makeUser()->setBirthDate(new \DateTimeImmutable('-30 years'));
        $this->em()->flush();

        $tester = new CommandTester((new Application(static::bootKernel()))->find('app:notifications:anniversaires'));
        $tester->execute([]);
        $tester->execute([]);

        $notifications = $this->em()->getRepository(Notification::class)->findBy(['recipient' => $user, 'title' => BirthdayNotificationsCommand::TITLE]);
        self::assertCount(1, $notifications);
    }

    private function image(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'al').'.jpg';
        imagejpeg(imagecreatetruecolor(60, 40), $path);

        return new UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true);
    }

    /** @return array{_token: string} */
    private function token(KernelBrowser $client, string $page): array
    {
        $crawler = $client->request('GET', $page);

        return ['_token' => (string) $crawler->filter('input[name="_token"]')->first()->attr('value')];
    }

    private function makeActivity(User $organizer): PrivateActivity
    {
        $category = $this->em()->getRepository(Category::class)->findOneBy([]);
        self::assertNotNull($category);
        $activity = (new PrivateActivity())
            ->setOrganizer($organizer)
            ->setTitle('Sortie album '.uniqid())
            ->setCategory($category)
            ->setDescription('Une sortie pour tester l’album photo.')
            ->setScheduledAt(new \DateTimeImmutable('+3 days 10:00'))
            ->setCity('Rouen')
            ->setVisibility(PrivateActivityVisibility::Public)
            ->setLevel(ActivityLevel::Beginner)
            ->setMaxParticipants(10);
        $this->em()->persist($activity);
        $this->em()->flush();

        return $activity;
    }

    private function makeUser(): User
    {
        $user = (new User())
            ->setEmail(sprintf('retours-1310b-%s@example.com', uniqid()))
            ->setFirstName('Camille')
            ->setLastName('Test')
            ->setStatus(UserStatus::Active);
        $user->setPassword('peu-importe');
        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
