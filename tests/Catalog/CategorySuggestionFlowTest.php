<?php

declare(strict_types=1);

namespace App\Tests\Catalog;

use App\Catalog\Entity\CategorySuggestion;
use App\Catalog\Repository\CategoryRepository;
use App\Catalog\Service\CategorySuggestionService;
use App\Notification\Repository\NotificationRepository;
use App\PrivateActivity\Enum\PrivateActivityStatus;
use App\PrivateActivity\Repository\PrivateActivityRepository;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Catégorie manquante (05/10) : proposée depuis l'assistant, validée par
 * l'équipe, puis disponible pour reprendre le brouillon et publier.
 */
final class CategorySuggestionFlowTest extends WebTestCase
{
    private const WIZARD = '/compte/activites-privees/creer/';

    public function testAProposedCategoryCanBeApprovedAndUsedToPublishTheDraft(): void
    {
        $client = static::createClient();
        $user = $this->makeUser();
        $client->loginUser($user);
        $name = 'Catégorie test '.substr(uniqid(), -6);
        $title = 'Sortie à classer '.uniqid();

        $client->request('GET', '/compte/activites-privees/nouvelle?vierge=1');
        $this->post($client, 1, ['action' => 'suggest', 'title' => $title, 'description' => 'Une activité dont la catégorie manque encore.', 'suggest_context' => 'private', 'suggest_name' => $name], '/categories/proposer');
        self::assertResponseRedirects(self::WIZARD.'1#categorie');

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suggestion = $em->getRepository(CategorySuggestion::class)->findOneBy(['name' => $name]);
        self::assertNotNull($suggestion);
        self::assertTrue($suggestion->isPending());

        // La proposition en attente suffit pour avancer (12/10) ; le brouillon s'enregistre aussi.
        $this->post($client, 1, ['action' => 'next']);
        self::assertResponseRedirects(self::WIZARD.'2');
        $this->post($client, 1, ['action' => 'draft']);
        self::assertResponseRedirects(self::WIZARD.'1');
        $draft = static::getContainer()->get(PrivateActivityRepository::class)->findOneBy(['title' => $title]);
        self::assertNotNull($draft);
        self::assertSame(PrivateActivityStatus::Draft, $draft->getStatus());
        self::assertNull($draft->getCategory());

        // L'équipe valide : la catégorie existe et l'auteur est prévenu.
        // Entités rechargées : le noyau redémarre entre deux requêtes du client.
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suggestion = $em->getRepository(CategorySuggestion::class)->findOneBy(['name' => $name]);
        $user = $em->getRepository(User::class)->find($user->getId());
        $before = \count(static::getContainer()->get(NotificationRepository::class)->findByRecipient($user));
        $category = static::getContainer()->get(CategorySuggestionService::class)->approve($suggestion);
        self::assertNotNull(static::getContainer()->get(CategoryRepository::class)->findOneBy(['slug' => $category->getSlug()]));
        self::assertCount($before + 1, static::getContainer()->get(NotificationRepository::class)->findByRecipient($user));

        // Le membre reprend son brouillon : la catégorie est dans la liste.
        $client->request('GET', '/compte/activites-privees/'.$draft->getId().'/reprendre');
        self::assertResponseRedirects(self::WIZARD.'1');
        $crawler = $client->request('GET', self::WIZARD.'1');
        self::assertCount(1, $crawler->filter(sprintf('input[name="category"][value="%s"]', $category->getSlug())));

        $this->post($client, 1, ['action' => 'next', 'title' => $title, 'category' => $category->getSlug(), 'description' => 'Une activité dont la catégorie manque encore.']);
        self::assertResponseRedirects(self::WIZARD.'2');
        $this->post($client, 2, ['action' => 'next', 'date' => (new \DateTimeImmutable('+8 days'))->format('Y-m-d'), 'start_time' => '14:00', 'address' => 'Dassa-Zoumè, Bénin', 'show_exact' => '1']);
        self::assertResponseRedirects(self::WIZARD.'3', null, 'Une adresse hors de France, tapée à la main, doit être acceptée.');
        $this->post($client, 3, ['action' => 'next']);
        $this->post($client, 4, ['action' => 'next', 'mode' => 'automatic', 'visibility' => 'public']);
        $this->post($client, 5, ['action' => 'next', 'accept_terms' => '1']);
        self::assertResponseRedirects();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $published = static::getContainer()->get(PrivateActivityRepository::class)->find($draft->getId());
        self::assertSame(PrivateActivityStatus::Open, $published->getStatus(), 'Le brouillon est publié, pas recréé.');
        self::assertSame($category->getSlug(), $published->getCategory()?->getSlug());
        self::assertSame('Dassa-Zoumè', $published->getCity());
        self::assertCount(1, static::getContainer()->get(PrivateActivityRepository::class)->findBy(['title' => $title]));
    }

    /**
     * Demande du client (12/10) : le membre publie sans attendre la validation ;
     * l'activité reste invisible puis passe en ligne quand la catégorie est validée.
     */
    public function testAnActivityPublishedWithAProposedCategoryGoesOnlineWhenItIsApproved(): void
    {
        [$client, $title, $name] = $this->publishWithProposedCategory();

        $activity = static::getContainer()->get(PrivateActivityRepository::class)->findOneBy(['title' => $title]);
        self::assertNotNull($activity);
        self::assertSame(PrivateActivityStatus::PendingCategory, $activity->getStatus());
        self::assertNull($activity->getCategory());
        self::assertSame($name, $activity->getCategorySuggestion()?->getName());

        // Invisible des autres membres tant que la catégorie n'est pas validée.
        $client->loginUser($this->makeUser());
        $client->request('GET', '/activites-privees/'.$activity->getId());
        self::assertResponseStatusCodeSame(403);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suggestion = $em->getRepository(CategorySuggestion::class)->findOneBy(['name' => $name]);
        $category = static::getContainer()->get(CategorySuggestionService::class)->approve($suggestion);

        $em->clear();
        $activity = static::getContainer()->get(PrivateActivityRepository::class)->findOneBy(['title' => $title]);
        self::assertSame(PrivateActivityStatus::Open, $activity->getStatus());
        self::assertSame($category->getSlug(), $activity->getCategory()?->getSlug());
        self::assertNull($activity->getCategorySuggestion());
    }

    public function testARejectedCategorySendsTheActivityBackToDraft(): void
    {
        [, $title, $name] = $this->publishWithProposedCategory();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $suggestion = $em->getRepository(CategorySuggestion::class)->findOneBy(['name' => $name]);
        static::getContainer()->get(CategorySuggestionService::class)->reject($suggestion, 'Trop proche de « Sport »');

        $em->clear();
        $activity = static::getContainer()->get(PrivateActivityRepository::class)->findOneBy(['title' => $title]);
        self::assertSame(PrivateActivityStatus::Draft, $activity->getStatus());
        self::assertNull($activity->getCategorySuggestion());
    }

    /** @return array{0: KernelBrowser, 1: string, 2: string} */
    private function publishWithProposedCategory(): array
    {
        $client = static::createClient();
        $client->loginUser($this->makeUser());
        $name = 'Catégorie attente '.substr(uniqid(), -6);
        $title = 'Sortie en attente '.uniqid();
        $description = 'Une activité publiée avant la validation de sa catégorie.';

        $client->request('GET', '/compte/activites-privees/nouvelle?vierge=1');
        $this->post($client, 1, ['action' => 'suggest', 'title' => $title, 'description' => $description, 'suggest_context' => 'private', 'suggest_name' => $name], '/categories/proposer');
        $this->post($client, 1, ['action' => 'next', 'title' => $title, 'level' => 'all_levels', 'description' => $description]);
        self::assertResponseRedirects(self::WIZARD.'2');
        $this->post($client, 2, ['action' => 'next', 'date' => (new \DateTimeImmutable('+8 days'))->format('Y-m-d'), 'start_time' => '14:00', 'address' => 'Forêt de Montgeon, 76620 Le Havre', 'show_exact' => '1']);
        $this->post($client, 3, ['action' => 'next']);
        $this->post($client, 4, ['action' => 'next', 'mode' => 'automatic', 'visibility' => 'public']);
        $this->post($client, 5, ['action' => 'next', 'accept_terms' => '1']);
        self::assertResponseRedirects();

        return [$client, $title, $name];
    }

    public function testProposingAnExistingCategorySelectsIt(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeUser());
        $existing = static::getContainer()->get(CategoryRepository::class)->findOneBy(['slug' => 'bien-etre']);
        self::assertNotNull($existing);

        $client->request('GET', '/compte/activites-privees/nouvelle?vierge=1');
        $this->post($client, 1, ['action' => 'suggest', 'title' => 'Yoga du dimanche', 'suggest_context' => 'private', 'suggest_name' => 'bien-être'], '/categories/proposer');
        $crawler = $client->request('GET', self::WIZARD.'1');
        self::assertCount(1, $crawler->filter('input[name="category"][value="bien-etre"][checked]'));
    }

    /** @param array<string, mixed> $data */
    private function post(KernelBrowser $client, int $step, array $data, ?string $target = null): void
    {
        $crawler = $client->request('GET', self::WIZARD.$step);
        $token = (string) $crawler->filter('form[data-ew-form] input[name="_token"]')->attr('value');
        $client->request('POST', $target ?? self::WIZARD.$step, ['_token' => $token] + $data);
    }

    private function makeUser(): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail(sprintf('categorie-%s@example.com', uniqid()))->setFirstName('Cat')->setLastName('Test')->setStatus(UserStatus::Active);
        $user->setPassword('x');
        $em->persist($user);
        $em->flush();

        return $user;
    }
}
