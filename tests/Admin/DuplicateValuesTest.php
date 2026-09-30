<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Catalog\Entity\Category;
use App\Catalog\Entity\Destination;
use App\Catalog\Entity\Service;
use App\Catalog\Enum\ServiceStatus;
use App\Provider\Entity\ProviderProfile;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Une valeur déjà prise ne doit jamais produire une erreur serveur.
 *
 * POURQUOI CE TEST EXISTE
 * Le 29/09, créer une activité portant le titre d'une autre rendait une
 * erreur 500 : l'adresse (slug) recopiée du titre était déjà prise, et seule
 * la base s'en apercevait. Deux cas à tenir :
 * - l'adresse : deux activités peuvent avoir le même titre, on suffixe
 *   (-2, -3…) sans rien demander (UniqueSlugSubscriber) ;
 * - toute autre valeur unique (e-mail…) : message sous le champ, formulaire
 *   conservé (auto_mapping du validateur, config/packages/validator.yaml).
 */
final class DuplicateValuesTest extends WebTestCase
{
    public function testTwoActivitiesWithTheSameTitleGetDistinctAddresses(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        $first = $this->makeActivity();
        $second = $this->makeActivity();

        // Même titre, même adresse que la première : ce que produit SlugField.
        $crawler = $client->request('GET', '/admin/service/'.$second->getId().'/edit');
        $form = $crawler->filter('form[name="Service"]')->form();
        $form['Service[title]'] = $first->getTitle();
        $form['Service[slug]'] = $first->getSlug();
        $client->submit($form);

        self::assertLessThan(400, $client->getResponse()->getStatusCode(), 'Un titre en double rend une erreur.');

        $reloaded = $this->reload(Service::class, (string) $second->getId());
        self::assertSame($first->getSlug().'-2', $reloaded->getSlug());
        self::assertSame($first->getTitle(), $reloaded->getTitle());
    }

    public function testADestinationWithAnExistingNameIsCreated(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        $name = 'Ville en double '.uniqid();
        $slug = 'ville-en-double-'.uniqid();

        foreach ([1, 2] as $_) {
            $this->submitDestination($client, $name, $slug);
            self::assertLessThan(400, $client->getResponse()->getStatusCode(), 'Une destination en double rend une erreur.');
        }

        $repository = static::getContainer()->get(EntityManagerInterface::class)->getRepository(Destination::class);
        self::assertNotNull($repository->findOneBy(['slug' => $slug]));
        self::assertNotNull($repository->findOneBy(['slug' => $slug.'-2']));
    }

    public function testAnEmailAlreadyUsedIsReportedOnTheField(): void
    {
        $client = static::createClient();
        $admin = $this->makeAdmin();
        $client->loginUser($admin);
        $other = $this->makeAdmin();

        $crawler = $client->request('GET', '/admin/user/'.$other->getId().'/edit');
        $form = $crawler->filter('form[name="User"]')->form();
        $form['User[email]'] = $admin->getEmail();
        $client->submit($form);

        // 422 : formulaire réaffiché avec l'erreur, pas une page d'erreur.
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('form[name="User"] .invalid-feedback, form[name="User"] .form-error-message');
    }

    /**
     * Même famille : un lien obligatoire en base (prestataire, catégorie)
     * mais « facultatif » côté PHP passait la validation, puis la base
     * refusait. Voir RequiredByDatabaseConfigurator.
     */
    public function testAnActivityWithoutProviderIsRefusedOnTheForm(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        $crawler = $client->request('GET', '/admin/service/new');
        $form = $crawler->filter('form[name="Service"]')->form();
        $form['Service[title]'] = 'Sans prestataire '.uniqid();
        $form['Service[description]'] = 'Aucun prestataire choisi.';
        $client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name="Service"]', 'Ce champ est obligatoire.');
    }

    private function submitDestination(KernelBrowser $client, string $name, string $slug): void
    {
        $crawler = $client->request('GET', '/admin/destination/new');
        $form = $crawler->filter('form[name="Destination"]')->form();
        $form['Destination[name]'] = $name;
        $form['Destination[slug]'] = $slug;
        $form['Destination[country]']->select('FR');
        $client->submit($form);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function reload(string $class, string $id): object
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $entity = $entityManager->find($class, $id);
        self::assertInstanceOf($class, $entity);

        return $entity;
    }

    private function makeActivity(): Service
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $service = (new Service())
            ->setTitle('Balade en double '.uniqid())
            ->setSlug('balade-en-double-'.uniqid())
            ->setDescription('Pour tester les doublons.')
            ->setProvider($entityManager->getRepository(ProviderProfile::class)->findOneBy([]))
            ->setCategory($entityManager->getRepository(Category::class)->findOneBy([]))
            ->setStatus(ServiceStatus::Published);
        $entityManager->persist($service);
        $entityManager->flush();

        return $service;
    }

    private function makeAdmin(): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail(sprintf('admin-doublon-%s@example.com', uniqid()))
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
