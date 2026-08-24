<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Catalog\Entity\Category;
use App\Catalog\Entity\Media;
use App\Catalog\Entity\Service;
use App\Catalog\Entity\ServiceDetail;
use App\Catalog\Enum\ServiceStatus;
use App\Catalog\Presenter\ActivityPresenter;
use App\Catalog\Repository\ServiceRepository;
use App\Provider\Entity\ProviderProfile;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Modifier et supprimer, les deux gestes quotidiens.
 *
 * POURQUOI CE TEST EXISTE
 * Tout le reste ne couvrait que la CRÉATION. Or une activité du catalogue est
 * rattachée à une fiche détaillée et à des photos : la supprimer touche à
 * plusieurs tables. Si la base refuse l'effacement en cascade, Loïc reçoit une
 * erreur serveur sur un geste banal, et il n'a aucun moyen de comprendre
 * pourquoi.
 *
 * La modification, elle, peut échouer autrement : un champ qu'on n'affiche pas
 * dans le formulaire mais que la base exige se retrouve vidé à l'enregistrement.
 */
final class EditAndDeleteTest extends WebTestCase
{
    public function testEditingAnActivityKeepsItConsistent(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());
        $service = $this->makeActivityWithDetailAndPhoto();
        $id = (string) $service->getId();

        $crawler = $client->request('GET', '/admin/service/'.$id.'/edit');
        self::assertSame(200, $client->getResponse()->getStatusCode(), "L'ecran de modification ne s'ouvre pas.");

        $form = $crawler->filter('form[name="Service"]')->form();
        $form['Service[title]'] = 'Titre corrige '.uniqid();
        $client->submit($form);

        self::assertLessThan(400, $client->getResponse()->getStatusCode(), 'La modification a ete refusee.');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $rechargé = static::getContainer()->get(ServiceRepository::class)->find($id);

        self::assertInstanceOf(Service::class, $rechargé);
        self::assertStringStartsWith('Titre corrige', $rechargé->getTitle());
        // Les liens que le formulaire ne montre pas ne doivent pas disparaitre.
        self::assertNotNull($rechargé->getDetail(), 'La fiche detaillee a ete perdue a la modification.');
        self::assertCount(1, $rechargé->getMedia(), 'La photo a ete perdue a la modification.');
        self::assertNotNull($rechargé->getProvider());
        // L'index de recherche suit le nouveau titre.
        self::assertStringContainsString('corrige', (string) $rechargé->getSearchText());

        // Et la page publique s'ouvre toujours.
        $client->getCookieJar()->clear();
        $client->request('GET', '/activites/'.$rechargé->getSlug());
        self::assertSame(200, $client->getResponse()->getStatusCode());
    }

    /**
     * Le geste le plus risqué : l'activité tient à une fiche détaillée et à des
     * photos, dans d'autres tables.
     */
    public function testDeletingAnActivityDoesNotFail(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());
        $service = $this->makeActivityWithDetailAndPhoto();
        $id = (string) $service->getId();

        // EasyAdmin 5 n'affiche pas de formulaire de suppression : le lien
        // porte l'adresse dans `formaction` et une fenetre de confirmation
        // envoie un formulaire cache. On poste donc directement, comme le
        // navigateur le ferait apres confirmation.
        $crawler = $client->request('GET', '/admin/service/'.$id);
        self::assertSame(200, $client->getResponse()->getStatusCode(), "La fiche ne s'ouvre pas.");

        $lien = $crawler->filter('[data-action-name="delete"]')->first();
        self::assertGreaterThan(0, $lien->count(), 'Aucune action de suppression sur la fiche.');

        // Le jeton anti-CSRF vit dans le formulaire cache. Sans lui,
        // EasyAdmin repond une REDIRECTION et ne supprime rien : le test
        // aurait ete vert sans que rien ne se passe.
        $jeton = $crawler->filter('#action-confirmation-form input[name="token"]')->attr('value');
        self::assertNotNull($jeton, 'Le jeton de confirmation est introuvable.');

        $client->request('POST', (string) $lien->attr('formaction'), ['token' => $jeton]);

        self::assertLessThan(
            400,
            $client->getResponse()->getStatusCode(),
            'La suppression rend une erreur : Loic ne pourrait pas retirer une activite.',
        );

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        self::assertNull(
            static::getContainer()->get(ServiceRepository::class)->find($id),
            "L'activite est toujours la apres suppression.",
        );

        // Le catalogue continue de repondre apres coup.
        $client->getCookieJar()->clear();
        $client->request('GET', '/activites');
        self::assertSame(200, $client->getResponse()->getStatusCode());
    }

    private function makeActivityWithDetailAndPhoto(): Service
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $service = (new Service())
            ->setTitle('Activite complete '.uniqid())
            ->setSlug('complete-'.uniqid())
            ->setDescription('Avec fiche detaillee et photo.')
            ->setProvider($entityManager->getRepository(ProviderProfile::class)->findOneBy([]))
            ->setCategory($entityManager->getRepository(Category::class)->findOneBy([]))
            ->setStatus(ServiceStatus::Published);
        $entityManager->persist($service);

        $detail = (new ServiceDetail())->setService($service)->setOrganizer('Aventure Nature');
        $entityManager->persist($detail);

        $media = (new Media())
            ->setService($service)
            ->setType(ActivityPresenter::MEDIA_COVER)
            ->setPath('images/activities/canoe-riviere.jpg')
            ->setPosition(0);
        $entityManager->persist($media);

        $entityManager->flush();
        $entityManager->refresh($service);

        return $service;
    }

    private function makeAdmin(): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail(sprintf('admin-edit-%s@example.com', uniqid()))
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
