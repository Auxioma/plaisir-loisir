<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Catalog\Entity\Category;
use App\Catalog\Entity\Media;
use App\Catalog\Entity\Service;
use App\Catalog\Enum\ServiceStatus;
use App\Catalog\Presenter\ActivityPresenter;
use App\Provider\Entity\ProviderProfile;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Une photo téléversée doit atterrir sur le disque et s'afficher sur le site.
 *
 * POURQUOI CE TEST EXISTE
 * C'est le maillon le plus fragile du back-office, et le seul qui écrive hors
 * de la base. Trois choses peuvent échouer sans message clair : le dossier de
 * destination n'existe pas ou n'est pas accessible en écriture, le chemin
 * enregistré ne correspond pas à l'adresse servie, ou l'image ne s'affiche que
 * dans l'administration et pas sur la page publique.
 *
 * Le chemin enregistré commence par « uploads/ » et non par « images/ » : les
 * fichiers livrés avec le site passent par l'AssetMapper, qui les renomme avec
 * une empreinte à la compilation. Un fichier ajouté après la mise en ligne ne
 * peut pas suivre ce chemin — d'où un dossier séparé, servi tel quel.
 */
final class PhotoUploadTest extends WebTestCase
{
    private const UPLOAD_DIR = __DIR__.'/../../public/uploads';

    public function testAnUploadedPhotoLandsOnDiskAndShowsOnTheSite(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        $service = $this->makePublishedService();
        $fichier = $this->makeUploadedPng();

        $crawler = $client->request('GET', '/admin/media/new');
        self::assertSame(200, $client->getResponse()->getStatusCode());

        $form = $crawler->filter('form[name="Media"]')->form();
        $form['Media[service]']->select((string) $service->getId());
        $form['Media[type]']->select(ActivityPresenter::MEDIA_COVER);
        $form['Media[path][file]']->upload($fichier->getPathname());
        $form['Media[position]'] = 0;

        $client->submit($form);

        self::assertLessThan(
            400,
            $client->getResponse()->getStatusCode(),
            'Le formulaire a refuse le televersement.',
        );

        // 1. Le chemin enregistré désigne bien le dossier des téléversements.
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $media = $entityManager->getRepository(Media::class)
            ->findOneBy(['service' => $service->getId(), 'type' => ActivityPresenter::MEDIA_COVER]);

        self::assertInstanceOf(Media::class, $media, "La photo n'a pas ete enregistree.");
        self::assertStringStartsWith(
            'uploads/',
            $media->getPath(),
            'Le chemin doit viser public/uploads, pas le dossier des images livrees.',
        );

        // 2. Le fichier existe réellement sur le disque.
        $surDisque = \dirname(self::UPLOAD_DIR).'/'.$media->getPath();
        self::assertFileExists($surDisque, 'Le fichier televerse est introuvable sur le disque.');

        try {
            // 3. Le visiteur voit la photo, à l'adresse attendue.
            $client->getCookieJar()->clear();
            $client->request('GET', '/activites');

            self::assertSame(200, $client->getResponse()->getStatusCode());
            self::assertStringContainsString(
                '/'.$media->getPath(),
                $client->getResponse()->getContent() ?: '',
                "La photo televersee n'apparait pas sur la page du catalogue.",
            );
        } finally {
            // Le test écrit hors de la base : il nettoie derrière lui.
            @unlink($surDisque);
            @unlink($fichier->getPathname());
        }
    }

    /**
     * Le dossier de destination doit exister et être accessible en écriture —
     * sinon le téléversement échoue au premier essai, sur le serveur.
     */
    public function testTheUploadDirectoryIsUsable(): void
    {
        self::assertDirectoryExists(self::UPLOAD_DIR, 'public/uploads est absent du depot.');
        self::assertDirectoryIsWritable(self::UPLOAD_DIR);
        self::assertFileExists(
            self::UPLOAD_DIR.'/.gitignore',
            'Sans ce fichier, le dossier ne survit pas au clone et les photos partiraient dans le depot.',
        );
    }

    private function makeUploadedPng(): UploadedFile
    {
        // Un PNG 1x1 valide, ecrit a la volee : le test ne depend d'aucun
        // fichier d'exemple qui pourrait disparaitre.
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        );
        $chemin = sys_get_temp_dir().'/photo-test-'.uniqid().'.png';
        file_put_contents($chemin, $png);

        return new UploadedFile($chemin, 'photo-de-loic.png', 'image/png', null, true);
    }

    private function makePublishedService(): Service
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $service = (new Service())
            ->setTitle('Activite pour televersement '.uniqid())
            ->setSlug('upload-'.uniqid())
            ->setDescription('Creee par le test de televersement.')
            // La colonne provider_id est non nulle : une activite appartient
            // toujours a un prestataire.
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
            ->setEmail(sprintf('admin-upload-%s@example.com', uniqid()))
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
