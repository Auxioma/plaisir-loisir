<?php

declare(strict_types=1);

namespace App\Tests\Payment;

use App\Payment\Entity\Subscription;
use App\Payment\Entity\SubscriptionPlan;
use App\Payment\Enum\BillingPeriod;
use App\Payment\Enum\SubscriptionStatus;
use App\Payment\Repository\SubscriptionRepository;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Enum\ProviderStatus;
use App\Provider\Repository\ProviderProfileRepository;
use App\Provider\Service\ProviderSlugService;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Le parcours abonnement, de bout en bout (§17 du CDC) : souscrire, voir son
 * abonnement, le résilier — avec le SubscriptionGateway MOCK (celui
 * réellement en service, voir config/services.yaml, faute de clés Stripe de
 * test dans cet environnement).
 */
final class SubscriptionFlowTest extends WebTestCase
{
    public function testAGuestCannotReachTheSubscriptionScreen(): void
    {
        $client = static::createClient();
        $client->request('GET', '/pro/abonnement');

        self::assertResponseRedirects('/login');
    }

    public function testAClientAccountCannotReachTheSubscriptionScreen(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeClientUser());

        $client->request('GET', '/pro/abonnement');

        // Zone interdite à ce type de compte : retour sur son propre espace
        // avec un message (AccessDeniedHandler), plus d'erreur 403 brute.
        self::assertResponseRedirects('/compte/tableau-de-bord');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Vous n\'avez pas accès à cette page.');
    }

    public function testSubscribingActivatesImmediatelyWithTheMockGateway(): void
    {
        $client = static::createClient();
        $plan = $this->makePlan('Offre Test', '29.00');
        $providerUser = $this->makeProviderUser();

        $client->loginUser($providerUser);
        $crawler = $client->request('GET', '/pro/abonnement');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Offre Test');

        $token = (string) $crawler->filter(sprintf('form[action*="%s"] input[name="_token"]', $plan->getSlug()))->attr('value');
        $client->request('POST', '/pro/abonnement/'.$plan->getSlug().'/souscrire', ['_token' => $token]);

        self::assertResponseRedirects('/pro/abonnement/succes');
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        $subscription = $this->reloadSubscriptionFor($providerUser);
        self::assertSame(SubscriptionStatus::Active, $subscription->getStatus());
        self::assertNotNull($subscription->getStripeCustomerId());
        // Ulid : deux objets distincts après le rebond du noyau entre deux
        // requêtes (voir le commentaire en tête de classe) — comparer leur
        // valeur, pas leur identité d'objet.
        self::assertSame((string) $plan->getId(), (string) $subscription->getPlan()?->getId());

        // Retour sur l'écran : il doit maintenant montrer l'abonnement en
        // cours, pas la liste des offres.
        $client->request('GET', '/pro/abonnement');
        self::assertSelectorTextContains('body', 'Actif');
    }

    public function testCannotSubscribeTwice(): void
    {
        $client = static::createClient();
        $plan = $this->makePlan('Offre Unique', '19.00');
        $providerUser = $this->makeProviderUser();

        $client->loginUser($providerUser);
        $crawler = $client->request('GET', '/pro/abonnement');
        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');
        $client->request('POST', '/pro/abonnement/'.$plan->getSlug().'/souscrire', ['_token' => $token]);
        self::assertResponseRedirects();

        // Un second essai (par exemple double-clic) ne doit pas créer une
        // seconde ligne.
        $crawler = $client->request('GET', '/pro/abonnement');
        // L'écran affiche maintenant l'abonnement en cours, plus le
        // formulaire de souscription : la route existe toujours si on la
        // rappelle directement (cas d'un onglet resté ouvert).
        $client->request('POST', '/pro/abonnement/'.$plan->getSlug().'/souscrire', ['_token' => $token]);
        self::assertResponseRedirects('/pro/abonnement');

        // Compte propre à CE prestataire, pas un COUNT global : la table
        // s'accumule d'un test à l'autre dans cette suite (pas de rollback
        // par test), comme ailleurs dans le dépôt (voir PasswordResetFlowTest).
        $provider = static::getContainer()->get(ProviderProfileRepository::class)->findOneByUser($providerUser);
        self::assertNotNull($provider);
        $count = \count(static::getContainer()->get(SubscriptionRepository::class)->findHistoryFor($provider));
        self::assertSame(1, $count, 'Une seconde souscription a été créée alors qu\'un abonnement était déjà en cours.');
    }

    public function testCancellingKeepsTheSubscriptionActiveUntilPeriodEnd(): void
    {
        $client = static::createClient();
        $plan = $this->makePlan('Offre à résilier', '15.00');
        $providerUser = $this->makeProviderUser();

        $client->loginUser($providerUser);
        $crawler = $client->request('GET', '/pro/abonnement');
        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');
        $client->request('POST', '/pro/abonnement/'.$plan->getSlug().'/souscrire', ['_token' => $token]);

        $crawler = $client->request('GET', '/pro/abonnement');
        $cancelToken = (string) $crawler->filter('form[action*="resilier"] input[name="_token"]')->attr('value');
        $client->request('POST', '/pro/abonnement/resilier', ['_token' => $cancelToken]);
        self::assertResponseRedirects('/pro/abonnement');

        $subscription = $this->reloadSubscriptionFor($providerUser);
        self::assertTrue($subscription->isCancelAtPeriodEnd());
        self::assertSame(SubscriptionStatus::Active, $subscription->getStatus(), 'Doit rester actif jusqu\'à la fin de la période payée.');
    }

    private function reloadSubscriptionFor(User $providerUser): Subscription
    {
        $provider = static::getContainer()->get(ProviderProfileRepository::class)->findOneByUser($providerUser);
        self::assertNotNull($provider);

        $subscription = static::getContainer()->get(SubscriptionRepository::class)->findCurrentFor($provider);
        self::assertNotNull($subscription);

        return $subscription;
    }

    private function makePlan(string $name, string $price): SubscriptionPlan
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $plan = (new SubscriptionPlan())
            ->setName($name)
            ->setSlug('plan-'.uniqid())
            ->setBillingPeriod(BillingPeriod::Monthly)
            ->setPriceAmount($price)
            ->setActive(true);

        $entityManager->persist($plan);
        $entityManager->flush();

        return $plan;
    }

    private function makeClientUser(): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail(sprintf('client-abo-%s@example.com', uniqid()))
            ->setFirstName('Client')
            ->setLastName('Test')
            ->setStatus(UserStatus::Active);
        $user->setPassword('peu-importe');

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function makeProviderUser(): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail(sprintf('pro-abo-%s@example.com', uniqid()))
            ->setFirstName('Pro')
            ->setLastName('Test')
            ->setStatus(UserStatus::Active);
        $user->setPassword('peu-importe');
        $user->setRoles(['ROLE_PROVIDER']);
        $entityManager->persist($user);

        $profile = (new ProviderProfile())
            ->setUser($user)
            ->setDisplayName('Pro Abonnement '.uniqid())
            ->setStatus(ProviderStatus::Verified);

        static::getContainer()->get(ProviderSlugService::class)->assign($profile);

        $entityManager->persist($profile);
        $entityManager->flush();

        return $user;
    }
}
