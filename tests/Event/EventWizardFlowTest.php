<?php

declare(strict_types=1);

namespace App\Tests\Event;

use App\Event\Entity\EventInvitation;
use App\Event\Entity\EventRegistration;
use App\Event\Repository\EventRepository;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Assistant « Créer un événement » (maquettes creation_evenements, 04/10) :
 * chaque étape refuse les champs requis manquants, on ne saute pas
 * d'étape, et la publication crée l'événement visible dans la liste.
 */
final class EventWizardFlowTest extends WebTestCase
{
    public function testTheGuideIsPublicAndTheStepsNeedAnAccount(): void
    {
        $client = static::createClient();
        $client->request('GET', '/evenements/creer');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Guide');

        $client->request('GET', '/evenements/creer/1');
        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testRequiredFieldsBlockEachStepAndAStepCannotBeSkipped(): void
    {
        $client = $this->logged();

        $client->request('GET', '/evenements/creer/4');
        self::assertResponseRedirects('/evenements/creer/1');

        $this->post($client, 1, ['title' => 'Ab', 'summary' => '', 'visibility' => 'public']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.ew-error', '3 caractères');

        $this->post($client, 1, ['title' => 'Soirée test', 'type' => 'repas', 'summary' => 'Un petit résumé valide', 'visibility' => 'public']);
        self::assertResponseRedirects('/evenements/creer/2');

        $this->post($client, 2, ['date_mode' => 'precise', 'start_date' => '2001-01-01', 'start_time' => '18:00', 'end_time' => '17:00', 'timezone' => 'Europe/Paris', 'reminder' => '24h']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.ew-main', 'aujourd’hui ou plus tard');
    }

    public function testAFullWizardPublishesTheEvent(): void
    {
        $client = $this->logged();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $invitee = $em->getRepository(User::class)->findOneBy(['email' => 'marc.dubois@client.trouvemoi.test']);
        $title = 'Pique-nique '.uniqid();
        $day = (new \DateTimeImmutable('+10 days'))->format('Y-m-d');

        $this->post($client, 1, ['title' => $title, 'type' => 'sortie', 'summary' => 'Pique-nique convivial au bord du lac.', 'description' => '', 'visibility' => 'public']);
        self::assertResponseRedirects('/evenements/creer/2');
        $this->post($client, 2, ['date_mode' => 'precise', 'start_date' => $day, 'start_time' => '18:00', 'end_date' => '', 'end_time' => '22:30', 'timezone' => 'Europe/Paris', 'reminder' => '24h', 'in_calendar' => '1']);
        self::assertResponseRedirects('/evenements/creer/3');
        $this->post($client, 3, ['location_type' => 'precise', 'address' => '']);
        self::assertResponseStatusCodeSame(422);
        $this->post($client, 3, ['location_type' => 'precise', 'address' => 'Parc de la Tête d’Or', 'city' => 'Lyon', 'postal_code' => '69006', 'lat' => '45.7772', 'lng' => '4.8550']);
        self::assertResponseRedirects('/evenements/creer/4');
        $this->post($client, 4, []);
        self::assertResponseStatusCodeSame(422);
        $this->post($client, 4, ['category' => 'plein-air']);
        self::assertResponseRedirects('/evenements/creer/5');

        // Étape 5 : couverture obligatoire.
        $this->post($client, 5, ['description' => 'Une longue description du pique-nique avec tout le programme.']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.ew-main', 'image de couverture');
        $image = sys_get_temp_dir().'/ew-cover-'.uniqid().'.png';
        imagepng(imagecreatetruecolor(40, 30), $image);
        $this->post($client, 5, ['description' => 'Une longue description du pique-nique avec tout le programme.'], ['cover' => new UploadedFile($image, 'cover.png', 'image/png', null, true)]);
        self::assertResponseRedirects('/evenements/creer/6');

        // La valeur est lue dans le menu affiché, comme un vrai navigateur :
        // le 04/10, « 20 participants » partait avec la valeur 3 (clés
        // renumérotées par le filtre Twig merge) et l'étape refusait la saisie.
        $crawler = $client->request('GET', '/evenements/creer/6');
        $capacity = $crawler->filter('select[name="capacity"] option')->reduce(static fn ($o): bool => '20 participants' === trim($o->text()))->attr('value');
        self::assertSame('20', $capacity);
        $this->post($client, 6, ['visibility' => 'public', 'capacity' => (string) $capacity, 'reminder' => '24h', 'registration' => '1', 'show_participants' => '1']);
        self::assertResponseRedirects('/evenements/creer/7');
        $this->post($client, 7, ['invites' => [(string) $invitee->getId()], 'invite_emails' => 'ami@exemple.fr']);
        self::assertResponseRedirects('/evenements/creer/8');

        // Sans acceptation des conditions : refus.
        $this->post($client, 8, ['publish_mode' => 'now']);
        self::assertResponseStatusCodeSame(422);
        $this->post($client, 8, ['publish_mode' => 'now', 'accept_terms' => '1']);
        self::assertResponseRedirects();

        $event = static::getContainer()->get(EventRepository::class)->findOneBy(['title' => $title]);
        self::assertNotNull($event);
        self::assertSame('published', $event->getStatus());
        self::assertSame($day.' 18:00', $event->getStartsAt()->format('Y-m-d H:i'));
        self::assertSame('Lyon, 69006', $event->getLocation());
        self::assertSame('plein-air', $event->getCategory()?->getSlug());
        self::assertSame(20, $event->getCapacity());
        self::assertNotNull($event->getImagePath());
        self::assertSame(1, $em->getRepository(EventRegistration::class)->count(['event' => $event]));
        self::assertSame(2, $em->getRepository(EventInvitation::class)->count(['event' => $event]));

        // Visible dans la liste et sur sa fiche.
        $client->request('GET', '/evenements?q='.rawurlencode($title));
        self::assertSelectorTextContains('body', $title);
        $client->request('GET', '/evenements/detail/'.$event->getSlug());
        self::assertResponseIsSuccessful();
    }

    private function logged(): KernelBrowser
    {
        $client = static::createClient();
        $user = static::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->findOneBy(['email' => 'julie.martin@client.trouvemoi.test']);
        $client->loginUser($user);
        // Brouillon propre à chaque test.
        $client->request('GET', '/evenements/creer');

        return $client;
    }

    /**
     * @param array<string, mixed>        $data
     * @param array<string, UploadedFile> $files
     */
    private function post(KernelBrowser $client, int $step, array $data, array $files = []): void
    {
        $crawler = $client->request('GET', '/evenements/creer/'.$step);
        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');
        $client->request('POST', '/evenements/creer/'.$step, $data + ['_token' => $token, 'action' => 'next'], $files);
    }
}
