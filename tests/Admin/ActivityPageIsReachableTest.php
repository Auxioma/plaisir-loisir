<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Catalog\Entity\Category;
use App\Catalog\Entity\Service;
use App\Catalog\Enum\ServiceStatus;
use App\Provider\Entity\ProviderProfile;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Une activité publiée doit avoir une page qui s'ouvre.
 *
 * POURQUOI CE TEST EXISTE
 * Le 24/08, les QUATRE activités du catalogue en production menaient à une
 * erreur 404 : `ActivityController::show()` refusait d'afficher une activité
 * sans fiche détaillée. Un visiteur voyait une carte, cliquait, et tombait sur
 * une page d'erreur. Aucune activité du site n'était consultable.
 *
 * Une activité publiée doit avoir une page. Ce test l'exige dans les deux cas :
 * sans fiche éditoriale, la page s'ouvre avec ce que l'activité sait
 * d'elle-même ; avec une fiche, elle affiche ce qui a été saisi.
 */
final class ActivityPageIsReachableTest extends WebTestCase
{
    public function testWithoutADetailSheetThePageStillOpens(): void
    {
        $client = static::createClient();
        $service = $this->makeActivity();

        $client->request('GET', '/activites/'.$service->getSlug());

        self::assertSame(
            200,
            $client->getResponse()->getStatusCode(),
            'Une activite publiee sans fiche editoriale doit avoir une page, pas une erreur.',
        );

        $html = $client->getResponse()->getContent() ?: '';
        // Elle affiche ce qu'elle sait d'elle-meme.
        self::assertStringContainsString($service->getTitle(), $html);
        // Et pas les encadres restes vides.
        self::assertStringNotContainsString("Inclus dans l'offre", $html, 'Un encadre vide est affiche.');
        self::assertStringNotContainsString('Informations pratiques', $html, 'Un encadre vide est affiche.');
    }

    public function testAnActivityGetsItsPageOnceTheDetailSheetIsFilledIn(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        $service = $this->makeActivity();

        // Loïc ouvre « Fiches détaillées » et en crée une pour son activité.
        $crawler = $client->request('GET', '/admin/service-detail/new');
        self::assertSame(200, $client->getResponse()->getStatusCode());

        $form = $crawler->filter('form[name="ServiceDetail"]')->form();
        $form['ServiceDetail[service]']->select((string) $service->getId());
        $form['ServiceDetail[organizer]'] = 'Aventure Nature';
        $form['ServiceDetail[presentationSubtitle]'] = 'Trois heures sur le lac';
        $form['ServiceDetail[presentationText]'] = 'Une sortie encadree, materiel fourni.';
        $client->submit($form);

        self::assertLessThan(
            400,
            $client->getResponse()->getStatusCode(),
            'Le formulaire de fiche detaillee a refuse la saisie.',
        );

        // Un visiteur anonyme ouvre la page : elle doit repondre.
        $client->getCookieJar()->clear();
        $client->request('GET', '/activites/'.$service->getSlug());

        self::assertSame(
            200,
            $client->getResponse()->getStatusCode(),
            "La page de l'activite ne s'ouvre toujours pas apres saisie de sa fiche detaillee.",
        );
        self::assertStringContainsString('Aventure Nature', $client->getResponse()->getContent() ?: '');

        // Et la version anglaise aussi.
        $client->request('GET', '/en/activities/'.$service->getSlug());
        self::assertSame(200, $client->getResponse()->getStatusCode());
    }

    /**
     * L'écran des activités doit signaler celles dont la page est cassée.
     */
    public function testTheListWarnsAboutAMissingDetailSheet(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());
        $this->makeActivity();

        $client->request('GET', '/admin/service');

        self::assertStringContainsString(
            'MANQUANTE',
            $client->getResponse()->getContent() ?: '',
            'Rien ne signale a Loic qu une activite publiee a une page cassee.',
        );
    }

    private function makeActivity(): Service
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $service = (new Service())
            ->setTitle('Kayak sur le lac '.uniqid())
            ->setSlug('kayak-'.uniqid())
            ->setDescription('Sortie encadree de trois heures.')
            ->setProvider($entityManager->getRepository(ProviderProfile::class)->findOneBy([]))
            ->setCategory($entityManager->getRepository(Category::class)->findOneBy([]))
            ->setStatus(ServiceStatus::Published);

        $entityManager->persist($service);
        $entityManager->flush();

        self::assertNull($service->getDetail(), 'Le point de depart du test suppose une activite sans fiche.');

        return $service;
    }

    private function makeAdmin(): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail(sprintf('admin-fiche-%s@example.com', uniqid()))
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
