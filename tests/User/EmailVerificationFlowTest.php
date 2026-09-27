<?php

declare(strict_types=1);

namespace App\Tests\User;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Le parcours de vérification d'e-mail à l'inscription, de bout en bout
 * (Lot I, 15/09). Même style que PasswordResetFlowTest : on vérifie qu'un
 * e-mail part réellement (envoi synchrone, compté par le mailer) et qu'un mauvais code
 * est refusé, sans décoder le vrai code — déjà couvert au niveau service
 * (EmailVerificationServiceTest::testConfirmActivatesTheAccountWithTheRightCode).
 *
 * On passe systématiquement par le VRAI formulaire d'inscription (pas par
 * une session fabriquée à la main) : c'est lui qui arme la session que
 * l'écran de vérification attend, comme PasswordResetFlowTest le fait pour
 * son étape 1.
 */
final class EmailVerificationFlowTest extends WebTestCase
{
    public function testRegistrationQueuesAVerificationEmailAndBlocksLoginUntilConfirmed(): void
    {
        $client = static::createClient();
        $email = sprintf('verif-%s@example.com', uniqid());

        $this->register($client, $email);

        self::assertEmailCount(1, message: 'Le code de vérification n\'a pas été envoyé.');

        // Tant que le compte n'est pas vérifié, il ne peut pas se connecter
        // (AccountChecker).
        $client->request('GET', '/login');
        $form = $client->getCrawler()->filter('form')->form();
        $form['_email'] = $email;
        $form['_password'] = 'un-mot-de-passe-solide';
        $client->submit($form);

        self::assertNull(static::getContainer()->get('security.token_storage')->getToken());
    }

    public function testAWrongCodeIsRejectedWithAnExplicitMessage(): void
    {
        $client = static::createClient();
        $this->register($client, sprintf('verif-mauvais-%s@example.com', uniqid()));

        $crawler = $client->request('GET', '/verification-email');
        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/verification-email', ['code' => 'WRONGCOD', '_token' => $token]);

        self::assertResponseRedirects('/verification-email');
        $client->followRedirect();
        self::assertSelectorTextContains('.toast-body', 'code est incorrect ou périmé');
    }

    public function testResendQueuesANewEmail(): void
    {
        $client = static::createClient();
        $this->register($client, sprintf('verif-renvoi-%s@example.com', uniqid()));

        $crawler = $client->request('GET', '/verification-email');
        $token = (string) $crawler->filter('form[action*="renvoyer"] input[name="_token"]')->attr('value');
        $client->request('POST', '/verification-email/renvoyer', ['_token' => $token]);

        self::assertResponseRedirects('/verification-email');
        self::assertEmailCount(1, message: 'Le renvoi n\'a pas envoyé de nouvel e-mail.');
    }

    private function register(object $client, string $email): void
    {
        $crawler = $client->request('GET', '/inscription');
        $form = $crawler->selectButton('Créer un compte')->form([
            'registration_form[fullName]' => 'Durand Camille',
            'registration_form[email]' => $email,
            'registration_form[password]' => 'un-mot-de-passe-solide',
            'registration_form[agreeTerms]' => '1',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/verification-email');
    }
}
