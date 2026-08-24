<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Le chemin réel pour entrer dans le back-office.
 *
 * POURQUOI CE TEST EXISTE
 * Aucun lien du site ne mène à /admin : on y accède en tapant l'adresse. Tout
 * repose donc sur une chaîne que personne n'avait vérifiée de bout en bout —
 * demander /admin sans être connecté, être renvoyé vers la connexion, s'y
 * identifier, et REVENIR sur /admin.
 *
 * Ce dernier point n'a rien d'automatique : le pare-feu déclare
 * `default_target_path: app_home`. Si Symfony ne mémorisait pas l'adresse
 * demandée, Loïc atterrirait sur la page d'accueil du site après s'être
 * connecté, sans comprendre pourquoi — et devrait retaper /admin.
 */
final class AdminEntryPathTest extends WebTestCase
{
    private const MOT_DE_PASSE = 'MotDePasseDeTest!2026';

    public function testTheFullWayIn(): void
    {
        $client = static::createClient();
        $email = $this->makeAdmin();

        // 1. Il tape l'adresse sans être connecté : on l'envoie se connecter.
        $client->request('GET', '/admin');
        self::assertResponseRedirects();
        $crawler = $client->followRedirect();
        self::assertSame(200, $client->getResponse()->getStatusCode());

        // 2. Il s'identifie avec le vrai formulaire.
        $form = $crawler->filter('form')->reduce(
            static fn ($node): bool => null !== $node->filter('input[name="_email"]')->getNode(0),
        )->form();
        $form['_email'] = $email;
        $form['_password'] = self::MOT_DE_PASSE;
        $client->submit($form);

        // 3. Il doit revenir SUR le back-office, pas sur la page d'accueil.
        self::assertResponseRedirects();
        $destination = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString(
            '/admin',
            $destination,
            sprintf(
                'Apres connexion, on renvoie vers « %s » au lieu du back-office : '
                .'l adresse demandee n a pas ete memorisee.',
                $destination,
            ),
        );

        $client->followRedirect();
        while ($client->getResponse()->isRedirect()) {
            $client->followRedirect();
        }

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertStringContainsString(
            'Activités du catalogue',
            $client->getResponse()->getContent() ?: '',
            "Le parcours n'aboutit pas sur les activites.",
        );
    }

    private function makeAdmin(): string
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = (new User())
            ->setEmail(sprintf('entree-admin-%s@example.com', uniqid()))
            ->setFirstName('Loïc')
            ->setLastName('Test')
            ->setRoles(['ROLE_ADMIN'])
            ->setStatus(UserStatus::Active);
        $user->setPassword($hasher->hashPassword($user, self::MOT_DE_PASSE));

        $entityManager->persist($user);
        $entityManager->flush();

        return $user->getEmail();
    }
}
