<?php

declare(strict_types=1);

namespace App\Tests\User;

use App\User\Entity\SocialIdentity;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SocialAuthFlowTest extends WebTestCase
{
    private array $savedEnv = [];
    private MockHttpClient $http;
    private array $profile;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['GOOGLE_OAUTH_CLIENT_ID', 'GOOGLE_OAUTH_CLIENT_SECRET', 'FACEBOOK_OAUTH_CLIENT_ID', 'FACEBOOK_OAUTH_CLIENT_SECRET'] as $key) {
            $this->savedEnv[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null];
            $_ENV[$key] = $_SERVER[$key] = 'fixture-oauth-credential';
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach ($this->savedEnv as $key => [$env, $server]) {
            if (null === $env) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $env;
            }
            if (null === $server) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $server;
            }
        }
    }

    public function testGoogleCreatesAClientAndOpensARealSession(): void
    {
        $email = uniqid('social-').'@example.test';
        $client = $this->browser($email);
        $state = $this->start($client);
        $client->request('GET', '/connexion/google/retour?state='.$state.'&code=valid-code');
        self::assertResponseRedirects('/compte/tableau-de-bord');
        $user = $this->user($email);
        self::assertNotNull($user);
        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertSame(UserStatus::Active, $user->getStatus());
        $client->request('GET', '/compte/tableau-de-bord');
        self::assertResponseIsSuccessful();
    }

    public function testExistingPasswordAndClientArePreservedAndBookingResumes(): void
    {
        $email = uniqid('social-existing-').'@example.test';
        $client = $this->browser($email);
        $user = $this->makeUser($email);
        $password = $user->getPassword();
        $state = $this->start($client);
        $session = $client->getRequest()->getSession();
        $session->set('_security.main.target_path', '/reservations/reprendre');
        $session->save();
        $client->request('GET', '/connexion/google/retour?state='.$state.'&code=valid');
        self::assertResponseRedirects('/reservations/reprendre');
        self::assertSame($password, $this->user($email)->getPassword());
        self::assertSame(1, $this->em()->getRepository(User::class)->count(['email' => $email]));
        self::assertSame(1, $this->em()->getRepository(SocialIdentity::class)->count(['user' => $user]));
        $client->restart();
        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->selectButton('Se connecter')->form([
            '_email' => $email,
            '_password' => 'PasswordFixture2026!',
        ]));
        self::assertResponseRedirects('/compte/tableau-de-bord');
    }

    public function testGoogleCanVerifyAPendingClient(): void
    {
        $email = uniqid('social-pending-').'@example.test';
        $client = $this->browser($email);
        $this->makeUser($email, UserStatus::Pending);
        $state = $this->start($client);
        $client->request('GET', '/connexion/google/retour?state='.$state.'&code=valid');
        self::assertResponseRedirects('/compte/tableau-de-bord');
        self::assertSame(UserStatus::Active, $this->user($email)->getStatus());
    }

    #[DataProvider('forbiddenAccounts')]
    public function testSocialLoginCannotLinkOrOpenAForbiddenAccount(array $roles, UserStatus $status, bool $deleted): void
    {
        $email = uniqid('social-forbidden-').'@example.test';
        $client = $this->browser($email);
        $user = $this->makeUser($email, $status, $roles);
        if ($deleted) {
            $user->softDelete();
            $this->em()->flush();
        }
        $state = $this->start($client);
        $client->request('GET', '/connexion/google/retour?state='.$state.'&code=valid');
        self::assertResponseRedirects('/login');
        self::assertNull(static::getContainer()->get('security.token_storage')->getToken());
        self::assertSame(0, $this->em()->getRepository(SocialIdentity::class)->count(['user' => $user]));
        self::assertSame($status, $user->getStatus());
    }

    public static function forbiddenAccounts(): iterable
    {
        yield 'provider' => [['ROLE_PROVIDER'], UserStatus::Active, false];
        yield 'admin' => [['ROLE_ADMIN'], UserStatus::Active, false];
        yield 'suspended' => [[], UserStatus::Suspended, false];
        yield 'deleted' => [[], UserStatus::Active, true];
    }

    public function testInvalidAndReplayedStateNeverCallsTheProvider(): void
    {
        $client = $this->browser(uniqid().'@example.test');
        $state = $this->start($client);
        $client->request('GET', '/connexion/google/retour?state=incorrect&code=valid');
        self::assertResponseRedirects('/login');
        $client->request('GET', '/connexion/google/retour?state='.$state.'&code=valid');
        self::assertResponseRedirects('/login');
        self::assertNull(static::getContainer()->get('security.token_storage')->getToken());
        self::assertSame(0, $this->http->getRequestsCount());
    }

    #[DataProvider('facebookClients')]
    public function testFacebookRequiresEmailProofBeforeCreatingOrLinkingAClient(bool $existing): void
    {
        $email = uniqid('social-facebook-').'@example.test';
        $client = $this->browser($email, 'facebook');
        $existingUser = $existing ? $this->makeUser($email) : null;
        $password = $existingUser?->getPassword();
        $state = $this->start($client, 'facebook');
        $client->request('GET', '/connexion/facebook/retour?state='.$state.'&code=valid');
        self::assertResponseRedirects('/connexion/confirmation-email');
        self::assertSame($existing ? 1 : 0, $this->em()->getRepository(User::class)->count(['email' => $email]));
        if ($existingUser) {
            self::assertSame(0, $this->em()->getRepository(SocialIdentity::class)->count(['user' => $existingUser]));
        }
        self::assertNull(static::getContainer()->get('security.token_storage')->getToken());
        self::assertEmailCount(1);
        $message = self::getMailerMessage();
        preg_match('/code ([A-F0-9]{8})/', $message->getTextBody(), $matches);
        self::assertNotEmpty($matches);
        $crawler = $client->request('GET', '/connexion/confirmation-email');
        $client->submit($crawler->selectButton('Vérifier')->form(['code' => $matches[1]]));
        self::assertResponseRedirects('/compte/tableau-de-bord');
        self::assertSame(UserStatus::Active, $this->user($email)->getStatus());
        if ($existingUser) {
            self::assertSame($password, $this->user($email)->getPassword());
        }
        // Une identité déjà liée se reconnecte par son identifiant stable,
        // même si Facebook ne renvoie plus d'e-mail.
        $client->restart();
        $this->profile['email'] = null;
        $state = $this->start($client, 'facebook');
        $client->request('GET', '/connexion/facebook/retour?state='.$state.'&code=valid');
        self::assertResponseRedirects('/compte/tableau-de-bord');
        self::assertSame(1, $this->em()->getRepository(User::class)->count(['email' => $email]));
    }

    public static function facebookClients(): iterable
    {
        yield 'new client' => [false];
        yield 'existing password client' => [true];
    }

    public function testProviderFailureReturnsToLoginWithoutCreatingAClient(): void
    {
        $email = uniqid('social-error-').'@example.test';
        $client = $this->browser($email, failure: true);
        $state = $this->start($client);
        $client->request('GET', '/connexion/google/retour?state='.$state.'&code=valid');
        self::assertResponseRedirects('/login');
        self::assertNull($this->user($email));
    }

    public function testExpiredOrWrongProviderStateCannotOpenASession(): void
    {
        $client = $this->browser(uniqid().'@example.test');
        $state = $this->start($client);
        $client->request('GET', '/connexion/facebook/retour?state='.$state.'&code=valid');
        self::assertResponseRedirects('/login');
        $state = $this->start($client);
        $session = $client->getRequest()->getSession();
        $session->set('oauth_started', time() - 601);
        $session->save();
        $client->request('GET', '/connexion/google/retour?state='.$state.'&code=valid');
        self::assertResponseRedirects('/login');
        self::assertSame(0, $this->http->getRequestsCount());
    }

    public function testMissingEmailReturnsToClassicLogin(): void
    {
        $client = $this->browser(uniqid().'@example.test', 'facebook');
        $this->profile['email'] = null;
        $state = $this->start($client, 'facebook');
        $client->request('GET', '/connexion/facebook/retour?state='.$state.'&code=valid');
        self::assertResponseRedirects('/login');
        self::assertNull(static::getContainer()->get('security.token_storage')->getToken());
        self::assertEmailCount(0);
    }

    private function browser(string $email, string $provider = 'google', bool $failure = false): KernelBrowser
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->profile = 'google' === $provider
            ? ['sub' => 'id-'.$email, 'email' => $email, 'email_verified' => true, 'given_name' => 'Alice', 'family_name' => 'Test']
            : ['id' => 'id-'.$email, 'email' => $email, 'first_name' => 'Alice', 'last_name' => 'Test'];
        $this->http = new MockHttpClient($failure
            ? [new MockResponse('not json', ['http_code' => 502])]
            : function (string $method, string $url): MockResponse {
                return new MockResponse(str_contains($url, '/token') || str_contains($url, '/oauth/access_token')
                    ? '{"access_token":"fixture-token"}'
                    : json_encode($this->profile, JSON_THROW_ON_ERROR));
            });
        static::getContainer()->set('http_client', $this->http);

        return $client;
    }

    private function start(KernelBrowser $client, string $provider = 'google'): string
    {
        $client->request('GET', '/connexion/'.$provider);
        self::assertResponseRedirects();
        $destination = $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('google' === $provider ? 'accounts.google.com' : 'www.facebook.com', $destination);
        parse_str(parse_url($destination, PHP_URL_QUERY), $query);

        return $query['state'];
    }

    private function makeUser(string $email, UserStatus $status = UserStatus::Active, array $roles = []): User
    {
        $user = (new User())->setEmail($email)->setFirstName('Alice')->setLastName('Test')->setStatus($status)->setRoles($roles);
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'PasswordFixture2026!'));
        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    private function user(string $email): ?User
    {
        return $this->em()->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
