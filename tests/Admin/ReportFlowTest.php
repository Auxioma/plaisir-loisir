<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Admin\Entity\Report;
use App\Admin\Enum\ReportStatus;
use App\Catalog\Entity\Category;
use App\Messaging\Service\MessagingService;
use App\PrivateActivity\Service\PrivateActivityService;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Enum\ProviderStatus;
use App\Provider\Service\ProviderSlugService;
use App\Quote\Service\QuoteService;
use App\Review\Service\ReviewService;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Le formulaire « Signaler », de bout en bout (§16.4 du CDC) — sur une
 * activité privée, l'une des deux surfaces qui portent réellement le bouton
 * aujourd'hui (voir le commentaire de ReportSubjectType). Le traitement côté
 * modération (ReportService::resolve/dismiss, écriture dans AuditLog) est
 * déjà couvert par ReportServiceTest ; ce test-ci protège le BRANCHEMENT
 * public — route, CSRF, doublon — qui n'existait pas avant le 14/09.
 */
final class ReportFlowTest extends WebTestCase
{
    public function testReportingAnActivityCreatesAPendingReport(): void
    {
        $client = static::createClient();
        $activity = $this->makeActivity();
        $reporter = $this->makeUser();

        $client->loginUser($reporter);
        $crawler = $client->request('GET', '/activites-privees/'.$activity->getId());
        self::assertResponseIsSuccessful();

        $token = (string) $crawler->filter('form[action*="signalement"] input[name="_token"]')->attr('value');
        $client->request('POST', '/signalement', [
            '_token' => $token,
            'subject_type' => 'activity',
            'subject_id' => (string) $activity->getId(),
            'subject_label' => $activity->getTitle(),
            'reason' => 'spam',
            'message' => 'Ressemble à une publicité déguisée.',
        ]);

        self::assertResponseRedirects();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $report = $entityManager->getRepository(Report::class)->findOneBy(['subjectLabel' => $activity->getTitle()]);

        self::assertNotNull($report, 'Le signalement n\'a pas été enregistré.');
        self::assertSame(ReportStatus::Pending, $report->getStatus());
        self::assertSame('spam', $report->getReason()->value);
    }

    public function testReportingTheSameActivityTwiceIsRefused(): void
    {
        $client = static::createClient();
        $activity = $this->makeActivity();
        $reporter = $this->makeUser();

        $client->loginUser($reporter);
        $crawler = $client->request('GET', '/activites-privees/'.$activity->getId());
        $token = (string) $crawler->filter('form[action*="signalement"] input[name="_token"]')->attr('value');

        $payload = [
            '_token' => $token,
            'subject_type' => 'activity',
            'subject_id' => (string) $activity->getId(),
            'subject_label' => $activity->getTitle(),
            'reason' => 'spam',
        ];

        $client->request('POST', '/signalement', $payload);
        $client->request('POST', '/signalement', $payload);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $count = $entityManager->getRepository(Report::class)->count(['subjectLabel' => $activity->getTitle()]);

        self::assertSame(1, $count, 'Le même signalement a été enregistré deux fois.');
    }

    /**
     * §16.4 du CDC : le formulaire « Signaler » couvre aussi les avis
     * (Lot K, 16/09 — jusqu'ici seuls Profil et Activité l'avaient).
     */
    public function testReportingAReviewCreatesAPendingReport(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $category = $entityManager->getRepository(Category::class)->findOneBy([]);
        self::assertNotNull($category);

        $providerUser = $this->makeUser();
        $provider = (new ProviderProfile())
            ->setUser($providerUser)
            ->setDisplayName('Pro '.uniqid())
            ->setMainCategory($category)
            ->setStatus(ProviderStatus::Verified);
        static::getContainer()->get(ProviderSlugService::class)->assign($provider);
        $entityManager->persist($provider);
        $entityManager->flush();

        $author = $this->makeUser();
        $quoteService = static::getContainer()->get(QuoteService::class);
        $request = $quoteService->createRequest($author, $category, 'Demande à noter', 'Description.');
        $quote = $quoteService->submitQuote($request, $provider, '100.00');
        $quoteService->accept($quote);

        $review = static::getContainer()->get(ReviewService::class)->addReview($quote, $author, 5, 'Très bien.');

        $reporter = $this->makeUser();
        $client->loginUser($reporter);
        $crawler = $client->request('GET', '/professionnels/'.$provider->getSlug());
        self::assertResponseIsSuccessful();

        $token = (string) $crawler->filter('form[action*="signalement"] input[name="_token"]')->first()->attr('value');
        $client->request('POST', '/signalement', [
            '_token' => $token,
            'subject_type' => 'review',
            'subject_id' => (string) $review->getId(),
            'subject_label' => 'Avis de '.$author->getFirstName(),
            'reason' => 'offensive_content',
        ]);

        self::assertResponseRedirects();

        $report = $entityManager->getRepository(Report::class)->findOneBy(['subjectLabel' => 'Avis de '.$author->getFirstName()]);
        self::assertNotNull($report, 'Le signalement de l\'avis n\'a pas été enregistré.');
        self::assertSame(ReportStatus::Pending, $report->getStatus());
    }

    /**
     * §16.4 du CDC : le formulaire « Signaler » couvre aussi les messages
     * (Lot K, 16/09).
     */
    public function testReportingAMessageCreatesAPendingReport(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $category = $entityManager->getRepository(Category::class)->findOneBy([]);
        self::assertNotNull($category);

        $providerUser = $this->makeUser();
        $provider = (new ProviderProfile())
            ->setUser($providerUser)
            ->setDisplayName('Pro '.uniqid())
            ->setMainCategory($category)
            ->setStatus(ProviderStatus::Verified);
        static::getContainer()->get(ProviderSlugService::class)->assign($provider);
        $entityManager->persist($provider);
        $entityManager->flush();

        $messagingService = static::getContainer()->get(MessagingService::class);
        $reporter = $this->makeUser();
        $conversation = $messagingService->openConversation($reporter, $provider);
        $messagingService->sendMessage($conversation, $providerUser, 'Contenu litigieux.');

        $client->loginUser($reporter);
        $crawler = $client->request('GET', '/compte/messages/'.$conversation->getId());
        self::assertResponseIsSuccessful();

        $token = (string) $crawler->filter('form[action*="signalement"] input[name="_token"]')->first()->attr('value');
        $client->request('POST', '/signalement', [
            '_token' => $token,
            'subject_type' => 'message',
            'subject_id' => (string) $conversation->getMessages()->first()->getId(),
            'subject_label' => 'Message signalé',
            'reason' => 'offensive_content',
        ]);

        self::assertResponseRedirects();

        $report = $entityManager->getRepository(Report::class)->findOneBy(['subjectLabel' => 'Message signalé']);
        self::assertNotNull($report, 'Le signalement du message n\'a pas été enregistré.');
    }

    private function makeActivity(): \App\PrivateActivity\Entity\PrivateActivity
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $category = $entityManager->getRepository(Category::class)->findOneBy([]);
        self::assertNotNull($category);

        return static::getContainer()->get(PrivateActivityService::class)->create(
            $this->makeUser(),
            'Activité signalée '.uniqid(),
            $category,
        );
    }

    private function makeUser(): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail(sprintf('signalement-%s@example.com', uniqid()))
            ->setFirstName('Test')
            ->setLastName('Signalant')
            ->setStatus(UserStatus::Active);
        $user->setPassword('peu-importe');

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
