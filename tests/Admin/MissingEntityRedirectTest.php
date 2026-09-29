<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

/**
 * Un élément disparu ramène à sa liste, pas à une page d'erreur.
 *
 * POURQUOI CE TEST EXISTE
 * Après le déploiement, ouvrir une activité supprimée entre-temps (lien gardé,
 * onglet resté ouvert) affichait une erreur. L'administrateur doit retrouver
 * la liste de la rubrique, avec un message qui explique pourquoi.
 * Voir `AdminErrorRedirectSubscriber`.
 */
final class MissingEntityRedirectTest extends WebTestCase
{
    public function testAMissingActivityLeadsBackToTheActivityList(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        foreach (['/edit', ''] as $suffix) {
            $client->request('GET', '/admin/service/'.new Ulid().$suffix);

            self::assertResponseRedirects('/admin/service', 303);
            $client->followRedirect();
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('body', 'n\'existe pas ou a été supprimé');
        }
    }

    public function testACustomActionOnAMissingElementLeadsBackToItsList(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        // Action maison (admin_contact_message_mark_handled) : rubrique et
        // action contiennent toutes deux des « _ ».
        $client->request('GET', '/admin/contact-message/'.new Ulid().'/mark-handled');

        self::assertResponseRedirects('/admin/contact-message', 303);
    }

    public function testAnUnknownAdminAddressLeadsToTheDashboard(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        $client->request('GET', '/admin/nimporte-quoi/nulle-part');

        self::assertResponseRedirects('/admin', 303);
    }

    private function makeAdmin(): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail(sprintf('admin-missing-%s@example.com', uniqid()))
            ->setFirstName('Loïc')
            ->setLastName('Test')
            ->setRoles(['ROLE_ADMIN'])
            ->setStatus(UserStatus::Active);
        $user->setPassword('peu-importe');

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
