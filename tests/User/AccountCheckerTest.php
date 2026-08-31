<?php

declare(strict_types=1);

namespace App\Tests\User;

use App\User\Entity\User;
use App\User\Enum\UserStatus;
use App\User\Service\AccountAnonymizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Le statut d'un compte est-il réellement opposable à la connexion ?
 *
 * LE DÉFAUT QUE CES TESTS PROTÈGENT
 * L'entité User portait un statut (actif / en attente / suspendu) et une date
 * de suppression douce depuis l'origine. Ni l'un ni l'autre n'était vérifié à
 * la connexion : le fournisseur d'utilisateurs de Symfony cherche l'adresse
 * e-mail et rend la ligne trouvée, quelle que soit sa colonne `status`.
 *
 * Le défaut restait invisible parce qu'aucun écran ne permettait de suspendre
 * un compte. En construire un sans corriger cela aurait donné à Loïc un bouton
 * qui ne fait rien — pire qu'un bouton absent, parce qu'on s'y fie.
 *
 * ON PASSE DONC PAR LE VRAI FORMULAIRE, pas par loginUser() : cette méthode
 * fabrique un jeton directement et court-circuiterait précisément le contrôle
 * qu'on veut éprouver.
 */
final class AccountCheckerTest extends WebTestCase
{
    private const MOT_DE_PASSE = 'MotDePasseEssai2026!';

    public function testAnActiveAccountCanLogIn(): void
    {
        $client = static::createClient();
        $membre = $this->makeUser(UserStatus::Active);

        $this->submitLogin($client, $membre->getEmail(), self::MOT_DE_PASSE);

        self::assertNotNull(
            static::getContainer()->get('security.token_storage')->getToken(),
            'Un compte actif ne parvient plus à se connecter : le contrôle est trop strict.',
        );
    }

    public function testASuspendedAccountCannotLogIn(): void
    {
        $client = static::createClient();
        $membre = $this->makeUser(UserStatus::Suspended);

        $this->submitLogin($client, $membre->getEmail(), self::MOT_DE_PASSE);

        self::assertNull(
            static::getContainer()->get('security.token_storage')->getToken(),
            'Un compte suspendu s\'est connecté : le bouton « Suspendre » du back-office ne suspend rien.',
        );
    }

    public function testAnAnonymizedAccountCannotLogIn(): void
    {
        $client = static::createClient();
        $membre = $this->makeUser(UserStatus::Active);
        $adresse = $membre->getEmail();

        $this->anonymizer()->anonymize($membre);

        // L'ancienne adresse ne doit plus exister du tout.
        $this->submitLogin($client, $adresse, self::MOT_DE_PASSE);

        self::assertNull(
            static::getContainer()->get('security.token_storage')->getToken(),
            'Un compte anonymisé se reconnecte avec son ancienne adresse.',
        );
    }

    /**
     * « En attente » est le statut par défaut de l'entité, mais l'inscription
     * active immédiatement : le refuser bloquerait les comptes créés
     * autrement — fixtures, back-office, commande d'administration — sans rien
     * protéger. Ce test fige ce choix pour qu'il ne change pas par accident.
     */
    public function testAPendingAccountIsNotBlocked(): void
    {
        $client = static::createClient();
        $membre = $this->makeUser(UserStatus::Pending);

        $this->submitLogin($client, $membre->getEmail(), self::MOT_DE_PASSE);

        self::assertNotNull(
            static::getContainer()->get('security.token_storage')->getToken(),
            'Un compte « en attente » ne peut plus se connecter : les comptes créés hors inscription sont bloqués.',
        );
    }

    private function submitLogin(object $client, string $email, string $motDePasse): void
    {
        $crawler = $client->request('GET', '/login');
        $formulaire = $crawler->filter('form')->form();
        $formulaire['_email'] = $email;
        $formulaire['_password'] = $motDePasse;

        $client->submit($formulaire);
    }

    private function anonymizer(): AccountAnonymizer
    {
        $service = static::getContainer()->get(AccountAnonymizer::class);
        self::assertInstanceOf(AccountAnonymizer::class, $service);

        return $service;
    }

    private function makeUser(UserStatus $statut): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = (new User())
            ->setEmail(sprintf('etat-%s@example.com', uniqid()))
            ->setFirstName('Camille')
            ->setLastName('Test')
            ->setStatus($statut);
        $user->setPassword($hasher->hashPassword($user, self::MOT_DE_PASSE));

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
