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
 * `ActivityController::show()` renvoie une erreur 404 quand l'activité n'a pas
 * de fiche détaillée. C'est volontaire — une page à moitié vide serait pire —
 * mais le résultat est brutal : l'activité reste visible dans le catalogue et
 * le clic ne mène nulle part.
 *
 * Constaté en production sur « kayak-lac-rose ». Et la première version du
 * back-office n'exposait PAS la fiche détaillée : toute activité que Loïc
 * aurait créée aurait eu ce défaut.
 *
 * Le test tient les deux bouts : sans fiche la page renvoie 404 (le garde-fou
 * fonctionne), avec une fiche saisie dans le back-office elle s'ouvre.
 */
final class ActivityPageIsReachableTest extends WebTestCase
{
    public function testWithoutADetailSheetThePageAnswers404(): void
    {
        $client = static::createClient();
        $service = $this->makeActivity();

        $client->request('GET', '/activites/'.$service->getSlug());

        self::assertSame(
            404,
            $client->getResponse()->getStatusCode(),
            'Le garde-fou a saute : une activite sans fiche detaillee doit repondre 404, pas une page a moitie vide.',
        );
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
