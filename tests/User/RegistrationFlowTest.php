<?php

declare(strict_types=1);

namespace App\Tests\User;

use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * L'écran d'inscription (/inscription), de bout en bout.
 *
 * CE QUI EST TESTÉ ICI PRÉCISÉMENT
 * RegistrationServiceTest (tests/User/Service/) prouve déjà que le SERVICE
 * refuse un e-mail déjà pris (ConflictHttpException). Ce test-ci vérifie le
 * BRANCHEMENT complet — formulaire, contrôleur, écran — jamais couvert avant
 * le 14/09 : un signalement client (« pas de message d'erreur explicite en
 * cas d'e-mail déjà utilisé ») a motivé cette vérification.
 */
final class RegistrationFlowTest extends WebTestCase
{
    public function testANewEmailCreatesAPendingAccountAndRedirectsToEmailVerification(): void
    {
        $client = static::createClient();
        $email = sprintf('nouveau-%s@example.com', uniqid());

        $crawler = $client->request('GET', '/inscription');
        $form = $crawler->selectButton('Créer un compte')->form([
            'registration_form[fullName]' => 'Durand Alice',
            'registration_form[email]' => $email,
            'registration_form[password]' => 'un-mot-de-passe-solide',
            'registration_form[agreeTerms]' => '1',
        ]);
        $client->submit($form);

        // Depuis le Lot I (15/09) : le compte reste en attente jusqu'à la
        // vérification de l'adresse, on enchaîne donc sur cet écran plutôt
        // que sur une connexion qui échouerait (AccountChecker).
        self::assertResponseRedirects('/verification-email');

        $user = static::getContainer()->get(\App\User\Repository\UserRepository::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user, 'Le compte n\'a pas été créé alors que l\'e-mail était disponible.');
        self::assertSame(UserStatus::Pending, $user->getStatus());
        self::assertNotNull($user->getEmailVerificationCodeHash(), 'Un code de vérification doit avoir été envoyé.');
    }

    /**
     * LE CAS SIGNALÉ : s'inscrire avec un e-mail déjà utilisé.
     *
     * Attendu : aucun second compte, un message d'erreur explicite affiché
     * sur l'écran d'inscription lui-même (pas de redirection, pas de page
     * d'erreur HTTP 409 brute).
     */
    public function testAnAlreadyUsedEmailShowsAnExplicitErrorAndCreatesNoSecondAccount(): void
    {
        $client = static::createClient();
        $email = sprintf('deja-pris-%s@example.com', uniqid());
        $this->makeExistingUser($email);

        $crawler = $client->request('GET', '/inscription');
        $form = $crawler->selectButton('Créer un compte')->form([
            'registration_form[fullName]' => 'Durand Bob',
            'registration_form[email]' => $email,
            'registration_form[password]' => 'un-mot-de-passe-solide',
            'registration_form[agreeTerms]' => '1',
        ]);
        $client->submit($form);

        self::assertResponseIsSuccessful('L\'écran doit se ré-afficher (200), pas rediriger ni renvoyer une erreur HTTP brute.');
        self::assertSelectorTextContains(
            '.toast-body',
            'Un compte existe déjà avec cet e-mail',
            'Aucun message explicite ne signale que l\'e-mail est déjà pris.',
        );

        $count = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(User::class)
            ->count(['email' => $email]);
        self::assertSame(1, $count, 'Une tentative avec un e-mail déjà pris a créé un second compte.');
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
