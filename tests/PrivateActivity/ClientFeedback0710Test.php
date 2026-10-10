<?php

declare(strict_types=1);

namespace App\Tests\PrivateActivity;

use App\Catalog\Entity\Category;
use App\Catalog\Enum\ActivityLevel;
use App\Event\Entity\Event;
use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Enum\PrivateActivityVisibility;
use App\PrivateActivity\Service\PrivateActivityInviteLink;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Retours client du 07/10 : choix « Créer une activité gratuite », lien
 * d'invitation des activités privées, page « Participants & demandes »,
 * niveau et compte à rebours sur les annonces.
 */
final class ClientFeedback0710Test extends WebTestCase
{
    public function testVisitorChoosesBetweenIndividualAndProfessional(): void
    {
        $client = static::createClient();
        $client->request('GET', '/creer-une-activite-gratuite');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Particulier');
        self::assertSelectorTextContains('body', 'Professionnel');
        // Plus de renvoi vers « Devenir partenaire » depuis le bouton de l'en-tête.
        self::assertSelectorNotExists('.tm-header a.tm-btn--cta[href="/devenir-partenaire"]');
    }

    public function testMemberPicksTypeThenVisibilityThenLandsInTheWizard(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeUser());

        $client->request('GET', '/creer-une-activite-gratuite');
        self::assertSelectorExists('input[name="type"][value="evenement"]');

        $client->request('GET', '/creer-une-activite-gratuite', ['type' => 'activite']);
        self::assertSelectorExists('input[name="visibilite"][value="membres"]');

        $client->request('GET', '/creer-une-activite-gratuite', ['type' => 'activite', 'visibilite' => 'prive']);
        self::assertResponseRedirects('/compte/activites-privees/creer/1');

        // La visibilité choisie est déjà posée à l'étape 4 de l'assistant.
        $session = $client->getRequest()->getSession();
        self::assertSame('private', $session->get('private_activity_draft')['visibility'] ?? null);
    }

    public function testThePrivateInviteLinkOpensThePrivateActivity(): void
    {
        $client = static::createClient();
        $activity = $this->makeActivity($this->makeUser(), PrivateActivityVisibility::Private);
        $url = '/activites-privees/'.$activity->getId();

        $client->loginUser($this->makeUser());
        $client->request('GET', $url);
        self::assertResponseStatusCodeSame(403);

        $key = static::getContainer()->get(PrivateActivityInviteLink::class)->key($activity);
        $client->request('GET', $url, ['cle' => 'mauvaise-cle']);
        self::assertResponseStatusCodeSame(403);

        $client->request('GET', $url, ['cle' => $key]);
        self::assertResponseIsSuccessful();
        // Accès mémorisé pour la session : plus besoin de la clé.
        $client->request('GET', $url);
        self::assertResponseIsSuccessful();
    }

    public function testTheListingShowsLevelFreeParticipantsAndCountdown(): void
    {
        $client = static::createClient();
        $activity = $this->makeActivity($this->makeUser(), PrivateActivityVisibility::Public);

        $crawler = $client->request('GET', '/activites-privees');
        self::assertResponseIsSuccessful();
        $card = $crawler->filter('.fa-card')->reduce(static fn ($node): bool => str_contains($node->text(), $activity->getTitle()));
        self::assertCount(1, $card);
        self::assertStringContainsString('Gratuit', $card->text());
        self::assertStringContainsString('Débutant', $card->text());
        self::assertCount(1, $card->filter('[data-starts-at]'));
        self::assertStringContainsString('places prises', $card->text());

        $client->request('GET', '/activites-privees/'.$activity->getId());
        self::assertSelectorTextContains('.fa-countdown--full', 'L’activité commence dans');
    }

    public function testOnlyTheOrganizerManagesParticipants(): void
    {
        $client = static::createClient();
        $organizer = $this->makeUser();
        $activity = $this->makeActivity($organizer, PrivateActivityVisibility::Public);
        $url = '/compte/activites-privees/'.$activity->getId().'/participants';

        $client->loginUser($this->makeUser());
        $client->request('GET', $url);
        self::assertResponseStatusCodeSame(403);

        $client->loginUser($organizer);
        $client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $activity->getTitle());
        self::assertSelectorExists('form[action$="/inviter"] textarea[name="emails"]');
    }

    public function testTheOrganizerInvitesByEmail(): void
    {
        $client = static::createClient();
        $client->enableProfiler();
        $organizer = $this->makeUser();
        $activity = $this->makeActivity($organizer, PrivateActivityVisibility::Private);
        $invitee = $this->makeUser();

        $client->loginUser($organizer);
        $token = $this->tokenFrom($client, '/compte/activites-privees/'.$activity->getId().'/participants');
        $client->request('POST', '/compte/activites-privees/'.$activity->getId().'/inviter', [
            '_token' => $token,
            'emails' => $invitee->getEmail().', inconnu@example.com',
            'retour' => 'participants',
        ]);

        self::assertResponseRedirects('/compte/activites-privees/'.$activity->getId().'/participants');
        // Un seul e-mail par personne : la notification du membre, l'invitation de l'inconnu.
        self::assertEmailCount(2);

        // Le membre invité voit désormais l'activité privée.
        $client->loginUser($invitee);
        $client->request('GET', '/activites-privees/'.$activity->getId());
        self::assertResponseIsSuccessful();
    }

    public function testTheEventInvitationCardHasAQrCodeAndOpensThePrivateEvent(): void
    {
        $client = static::createClient();
        $organizer = $this->makeUser();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $event = (new Event())
            ->setTitle('Anniversaire de Léa')
            ->setSlug(sprintf('anniversaire-lea-%s', uniqid()))
            ->setOrganizer($organizer)
            ->setVisibility('private')
            ->setPrivate(true)
            ->setStartsAt(new \DateTimeImmutable('+1 month 14:30'));
        $entityManager->persist($event);
        $entityManager->flush();
        $url = '/evenements/detail/'.$event->getSlug().'/invitation';

        // Réservé à l'organisateur.
        $client->loginUser($this->makeUser());
        $client->request('GET', $url);
        self::assertResponseStatusCodeSame(403);

        $client->loginUser($organizer);
        $crawler = $client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.fa-invite__card', 'Anniversaire de Léa');
        self::assertStringStartsWith('data:image/svg+xml;base64,', (string) $crawler->filter('.fa-invite__qr img')->attr('src'));

        // Le lien du carton ouvre l'événement privé à un invité sans invitation nominative.
        $link = (string) $crawler->filter('[data-copy]')->first()->attr('data-copy');
        self::assertStringContainsString('cle=', $link);
        $client->restart();
        $client->loginUser($this->makeUser());
        $client->request('GET', '/evenements/detail/'.$event->getSlug());
        self::assertResponseStatusCodeSame(403);
        $client->request('GET', (string) parse_url($link, \PHP_URL_PATH).'?'.parse_url($link, \PHP_URL_QUERY));
        self::assertResponseIsSuccessful();
    }

    private function tokenFrom(KernelBrowser $client, string $url): string
    {
        $crawler = $client->request('GET', $url);

        return (string) $crawler->filter('form[action$="/inviter"] input[name="_token"]')->attr('value');
    }

    private function makeActivity(User $organizer, PrivateActivityVisibility $visibility): PrivateActivity
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $category = $entityManager->getRepository(Category::class)->findOneBy([]);
        self::assertNotNull($category, 'Les fixtures du catalogue doivent être chargées.');

        $activity = (new PrivateActivity())
            ->setOrganizer($organizer)
            ->setTitle('Sortie test '.uniqid())
            ->setCategory($category)
            ->setDescription('Une sortie de test pour vérifier les retours du client.')
            ->setScheduledAt(new \DateTimeImmutable('+3 days 10:00'))
            ->setCity('Rouen')
            ->setVisibility($visibility)
            ->setLevel(ActivityLevel::Beginner)
            ->setMaxParticipants(10);
        $entityManager->persist($activity);
        $entityManager->flush();

        return $activity;
    }

    private function makeUser(): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())
            ->setEmail(sprintf('retours-0710-%s@example.com', uniqid()))
            ->setFirstName('Camille')
            ->setLastName('Test')
            ->setStatus(UserStatus::Active);
        $user->setPassword('peu-importe');
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
