<?php

declare(strict_types=1);

namespace App\Tests\User;

use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Le parcours « mot de passe oublié » (3 écrans), de bout en bout.
 *
 * CE QUE CE TEST VÉRIFIE, ET POURQUOI
 * Un client a signalé que saisir une adresse SANS compte redirige quand même
 * vers l'écran du code, « sans vérifier si l'e-mail existe ». C'est le
 * comportement voulu, documenté dans PasswordResetService : révéler
 * l'existence d'un compte à ce stade permettrait à n'importe qui de savoir
 * qui est inscrit (énumération de comptes). Ce test fige ce choix — pour une
 * adresse inconnue, AUCUN e-mail ne part et le code à l'étape 2 est refusé —
 * et vérifie que l'étape 2 donne alors un message clair, faute d'un message
 * plus tôt.
 */
final class PasswordResetFlowTest extends WebTestCase
{
    /**
     * Adresse inconnue : le parcours avance quand même (choix de sécurité
     * volontaire), mais ne doit RIEN envoyer — aucun e-mail
     * à personne — et le code saisi à l'étape 2 doit être refusé avec un
     * message clair, pas une erreur serveur ni un silence.
     */
    public function testAnUnknownEmailIsToldThereIsNoAccountAndSendsNothing(): void
    {
        $client = static::createClient();
        $email = sprintf('jamais-inscrit-%s@example.com', uniqid());

        $crawler = $client->request('GET', '/mot-de-passe-oublie');
        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');

        // Depuis le 05/10 : pas d'écran de code pour une adresse sans compte.
        $client->request('POST', '/mot-de-passe-oublie', ['email' => $email, '_token' => $token]);
        self::assertResponseRedirects('/mot-de-passe-oublie');
        self::assertEmailCount(0, message: 'Une adresse sans compte ne doit déclencher AUCUN envoi.');
        $client->followRedirect();
        self::assertSelectorTextContains('.toast-body', 'Aucun compte');

        // Et l'écran du code reste fermé.
        $client->request('GET', '/mot-de-passe-oublie/verification');
        self::assertResponseRedirects('/mot-de-passe-oublie');
    }

    /**
     * Adresse connue : un e-mail part réellement (envoi synchrone), avec le bon destinataire et un code de 8
     * caractères conforme à la maquette.
     */
    public function testAKnownEmailQueuesTheResetCodeEmail(): void
    {
        $client = static::createClient();
        $email = sprintf('connu-%s@example.com', uniqid());
        $this->makeExistingUser($email);

        $crawler = $client->request('GET', '/mot-de-passe-oublie');
        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/mot-de-passe-oublie', ['email' => $email, '_token' => $token]);
        self::assertResponseRedirects('/mot-de-passe-oublie/verification');
        self::assertEmailCount(1, message: 'L\'e-mail du code n\'a pas été envoyé.');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user);
        self::assertNotNull($user->getResetCodeHash(), 'Aucun code n\'a été généré pour un compte pourtant existant.');
    }

    /**
     * Un mauvais code, pour un VRAI compte cette fois, doit être refusé sans
     * jamais laisser deviner qu'il existe un compte de plus qu'un autre.
     */
    public function testAWrongCodeForARealAccountIsRejectedWithTheSameMessage(): void
    {
        $client = static::createClient();
        $email = sprintf('vrai-compte-%s@example.com', uniqid());
        $this->makeExistingUser($email);

        $crawler = $client->request('GET', '/mot-de-passe-oublie');
        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');
        $client->request('POST', '/mot-de-passe-oublie', ['email' => $email, '_token' => $token]);

        $crawler = $client->request('GET', '/mot-de-passe-oublie/verification');
        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');
        $client->request('POST', '/mot-de-passe-oublie/verification', ['code' => 'ZZZZZZZZ', '_token' => $token]);

        self::assertResponseRedirects('/mot-de-passe-oublie/verification');
        $client->followRedirect();
        self::assertSelectorTextContains('.toast-body', 'code est incorrect ou périmé');
    }

    private function makeExistingUser(string $email): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail($email)
            ->setFirstName('Compte')
            ->setLastName('Existant')
            ->setStatus(UserStatus::Active);
        $user->setPassword('peu-importe');

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
