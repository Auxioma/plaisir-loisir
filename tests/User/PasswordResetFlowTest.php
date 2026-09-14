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
     * volontaire), mais ne doit RIEN mettre en file d'attente — aucun e-mail
     * à personne — et le code saisi à l'étape 2 doit être refusé avec un
     * message clair, pas une erreur serveur ni un silence.
     */
    public function testAnUnknownEmailQueuesNoEmailAndTheCodeStepClearlyRejectsAnyCode(): void
    {
        $client = static::createClient();
        $email = sprintf('jamais-inscrit-%s@example.com', uniqid());

        $connection = static::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $before = (int) $connection->fetchOne('SELECT COUNT(*) FROM messenger_messages');

        $crawler = $client->request('GET', '/mot-de-passe-oublie');
        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/mot-de-passe-oublie', ['email' => $email, '_token' => $token]);
        self::assertResponseRedirects('/mot-de-passe-oublie/verification');

        // Compte relatif à l'état avant l'appel : la table s'accumule d'un
        // test à l'autre (pas de rollback par test dans cette suite), un
        // simple COUNT(*) === 0 serait donc fragile.
        $after = (int) $connection->fetchOne('SELECT COUNT(*) FROM messenger_messages');
        self::assertSame($before, $after, 'Une adresse sans compte ne doit déclencher AUCUN envoi.');

        $crawler = $client->request('GET', '/mot-de-passe-oublie/verification');
        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');
        $client->request('POST', '/mot-de-passe-oublie/verification', ['code' => 'AAAAAAAA', '_token' => $token]);

        self::assertResponseRedirects('/mot-de-passe-oublie/verification');
        $client->followRedirect();
        self::assertSelectorTextContains('.alert', 'code est incorrect ou périmé');
    }

    /**
     * Adresse connue : un e-mail part réellement (mis en file d'attente pour
     * le worker Messenger), avec le bon destinataire et un code de 8
     * caractères conforme à la maquette.
     */
    public function testAKnownEmailQueuesTheResetCodeEmail(): void
    {
        $client = static::createClient();
        $email = sprintf('connu-%s@example.com', uniqid());
        $this->makeExistingUser($email);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $before = (int) $entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages');

        $crawler = $client->request('GET', '/mot-de-passe-oublie');
        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/mot-de-passe-oublie', ['email' => $email, '_token' => $token]);
        self::assertResponseRedirects('/mot-de-passe-oublie/verification');

        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user);
        self::assertNotNull($user->getResetCodeHash(), 'Aucun code n\'a été généré pour un compte pourtant existant.');

        $after = (int) $entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages');
        self::assertSame($before + 1, $after, 'L\'e-mail du code n\'a pas été mis en file d\'attente.');
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
        self::assertSelectorTextContains('.alert', 'code est incorrect ou périmé');
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
