<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\User\Entity\User;
use App\User\Enum\UserStatus;
use App\User\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * La commande qui ouvre — et rouvre — l'accès au back-office.
 *
 * POURQUOI CE TEST EXISTE
 * C'est le seul chemin vers le rôle administrateur, et le seul moyen de
 * redonner un mot de passe tant que les e-mails ne partent pas. S'il casse,
 * plus personne n'entre dans le back-office, et rien sur le site ne le
 * signalerait.
 */
final class GrantAdminCommandTest extends KernelTestCase
{
    public function testItCreatesAnAdministrator(): void
    {
        $email = sprintf('cmd-neuf-%s@example.com', uniqid());

        $tester = $this->lancer([
            'email' => $email,
            '--mot-de-passe' => 'Motdepasse!2026',
            '--prenom' => 'Loïc',
        ]);

        self::assertSame(0, $tester->getStatusCode());

        $user = $this->users()->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);
        self::assertContains('ROLE_ADMIN', $user->getRoles());
        // Un compte créé en ligne de commande n'a pas d'e-mail à confirmer.
        self::assertSame(UserStatus::Active, $user->getStatus());
        // Le message de remplacement ne doit PAS apparaître : rien n'a été
        // remplacé, le compte vient d'être créé.
        self::assertStringNotContainsString('a été remplacé', $tester->getDisplay());
    }

    public function testItRefusesToCreateAnAccountWithoutAPassword(): void
    {
        $tester = $this->lancer(['email' => sprintf('cmd-vide-%s@example.com', uniqid())]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('mot-de-passe', $tester->getDisplay());
    }

    /**
     * Le filet de secours : redonner un accès à quelqu'un qui a perdu son mot
     * de passe, alors que la page « mot de passe oublié » dépend d'un e-mail
     * qui ne part pas sans worker.
     */
    public function testItReplacesThePasswordOfAnExistingAccount(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $email = sprintf('cmd-existant-%s@example.com', uniqid());
        $user = (new User())
            ->setEmail($email)
            ->setFirstName('Loïc')
            ->setLastName('Test')
            ->setStatus(UserStatus::Active);
        $user->setPassword($hasher->hashPassword($user, 'AncienMotDePasse!1'));
        $entityManager->persist($user);
        $entityManager->flush();

        $tester = $this->lancer(['email' => $email, '--mot-de-passe' => 'NouveauMotDePasse!2']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('remplac', $tester->getDisplay());

        $entityManager->clear();
        $rechargé = $this->users()->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $rechargé);
        self::assertTrue($hasher->isPasswordValid($rechargé, 'NouveauMotDePasse!2'));
        self::assertFalse($hasher->isPasswordValid($rechargé, 'AncienMotDePasse!1'));
        self::assertContains('ROLE_ADMIN', $rechargé->getRoles());
    }

    /**
     * Rejouée sans mot de passe, la commande ne doit toucher à rien : c'est ce
     * qui la rend sûre à relancer après un déploiement.
     */
    public function testItLeavesThePasswordAloneWhenNoneIsGiven(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $email = sprintf('cmd-idem-%s@example.com', uniqid());
        $user = (new User())
            ->setEmail($email)
            ->setFirstName('Loïc')
            ->setLastName('Test')
            ->setRoles(['ROLE_ADMIN'])
            ->setStatus(UserStatus::Active);
        $user->setPassword($hasher->hashPassword($user, 'MotDePasseIntact!3'));
        $entityManager->persist($user);
        $entityManager->flush();

        $tester = $this->lancer(['email' => $email]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('avait déjà accès', $tester->getDisplay());

        $entityManager->clear();
        $rechargé = $this->users()->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $rechargé);
        self::assertTrue($hasher->isPasswordValid($rechargé, 'MotDePasseIntact!3'));
    }

    /**
     * @param array<string, string> $arguments
     */
    private function lancer(array $arguments): CommandTester
    {
        self::bootKernel();
        $application = new Application(self::$kernel);

        $tester = new CommandTester($application->find('app:admin:grant'));
        $tester->execute($arguments);

        return $tester;
    }

    private function users(): UserRepository
    {
        return static::getContainer()->get(UserRepository::class);
    }
}
