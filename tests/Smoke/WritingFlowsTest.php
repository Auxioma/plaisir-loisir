<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use App\Corporate\Repository\ContactMessageRepository;
use App\Event\Repository\EventRepository;
use App\User\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Les parcours qui ÉCRIVENT vraiment.
 *
 * POURQUOI CE TEST EXISTE
 * Trois formulaires du site acceptaient une soumission sans rien enregistrer :
 * l'inscription, « Contactez-nous » et les deux assistants de création. On
 * remplissait, on validait, et il ne se passait rien — sans le moindre message
 * d'erreur. C'est le pire défaut possible, et il ne se voit pas à l'écran.
 *
 * Chaque test vérifie donc l'EFFET en base, pas le code de réponse.
 */
final class WritingFlowsTest extends WebTestCase
{
    public function testRegistrationCreatesAnAccount(): void
    {
        $client = static::createClient();
        $email = sprintf('test-%s@example.com', uniqid());

        $this->submit($client, '/register', ['registration_form' => $this->registrationFields($email)]);

        $users = static::getContainer()->get(UserRepository::class);
        $user = $users->findOneBy(['email' => $email]);

        self::assertNotNull($user, "L'inscription n'a créé aucun compte.");
        // Le champ unique « Nom & prénom » : premier mot = nom.
        self::assertSame('Dupont', $user->getLastName());
        self::assertSame('Jeanne', $user->getFirstName());
        self::assertNotContains('ROLE_PROVIDER', $user->getRoles());
    }

    public function testRegistrationAsProviderGrantsTheRole(): void
    {
        $client = static::createClient();
        $email = sprintf('pro-%s@example.com', uniqid());

        $this->submit($client, '/register', [
            'registration_form' => ['accountType' => 'pro'] + $this->registrationFields($email, 'Riviere Paul'),
        ]);

        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);

        self::assertNotNull($user);
        self::assertContains('ROLE_PROVIDER', $user->getRoles());
    }

    /**
     * Un e-mail déjà pris renvoyait une page d'erreur HTTP 409 au lieu du
     * formulaire.
     */
    public function testRegistrationWithATakenEmailShowsTheFormAgain(): void
    {
        $client = static::createClient();
        $email = sprintf('double-%s@example.com', uniqid());

        $champs = ['registration_form' => $this->registrationFields($email, 'Martin Luc')];

        $this->submit($client, '/register', $champs);
        $this->submit($client, '/register', $champs);

        self::assertLessThan(500, $client->getResponse()->getStatusCode());
    }

    public function testContactFormRecordsTheMessage(): void
    {
        $client = static::createClient();
        $sujet = sprintf('Question %s', uniqid());

        $this->submit($client, '/contactez-nous', [
            'nom' => 'Claire Fontaine',
            'email' => 'claire@example.com',
            'sujet' => $sujet,
            'message' => 'Bonjour, une question sur une activité.',
        ]);

        $messages = static::getContainer()->get(ContactMessageRepository::class);

        self::assertNotNull(
            $messages->findOneBy(['subject' => $sujet]),
            'Le message de contact n\'a pas été enregistré.',
        );
    }

    /**
     * L'assistant de création : huit étapes, un événement à l'arrivée.
     */
    public function testEventWizardCreatesAnEvent(): void
    {
        $client = static::createClient();
        $titre = sprintf('Tournoi %s', uniqid());

        $this->submit($client, '/evenements/creer/1', ['titre' => $titre]);
        $this->submit($client, '/evenements/creer/2', [
            'date_debut' => '12 / 09 / 2026',
            'heure_debut' => '14:00',
            'date_fin' => '12 / 09 / 2026',
            'heure_fin' => '19:00',
        ]);
        $this->submit($client, '/evenements/creer/3', ['lieu' => 'Autrans, 38880']);

        foreach ([4, 5, 6, 7] as $etape) {
            $this->submit($client, '/evenements/creer/'.$etape, []);
        }

        $this->submit($client, '/evenements/creer/8', ['visibilite' => 'public']);

        $events = static::getContainer()->get(EventRepository::class);
        $event = $events->findOneBy(['title' => $titre]);

        self::assertNotNull($event, "L'assistant n'a créé aucun événement.");
        self::assertSame('Autrans, 38880', $event->getLocation());
        // La date est enregistrée en UTC ; à l'heure d'été, 14h00 à Paris.
        self::assertSame('2026-09-12', $event->getStartsAt()->format('Y-m-d'));
    }

    /**
     * Le cœur des favoris répond 401 à un visiteur, et non une redirection :
     * une requête en arrière-plan ne sait pas suivre une redirection.
     */
    public function testFavouritesRefuseVisitorsWithAClearAnswer(): void
    {
        $client = static::createClient();

        $client->request('POST', '/favoris/basculer', [
            'type' => 'activite',
            'slug' => 'descente-en-canoe',
            '_token' => 'csrf-token',
        ], server: ['HTTP_ORIGIN' => 'http://localhost']);

        self::assertSame(401, $client->getResponse()->getStatusCode());
    }

    /**
     * Les champs du formulaire d'inscription, dans la forme que Symfony attend.
     *
     * Le jeton vit DANS le tableau du formulaire, et non au premier niveau :
     * c'est ainsi qu'un formulaire Symfony le nomme.
     *
     * @return array<string, string>
     */
    private function registrationFields(string $email, string $nom = 'Dupont Jeanne'): array
    {
        return [
            'fullName' => $nom,
            'email' => $email,
            'password' => 'MotDePasseSolide2026',
            'agreeTerms' => '1',
            'accountType' => 'client',
            '_token' => 'csrf-token',
        ];
    }

    /**
     * Envoie un formulaire en simulant une requête de même origine.
     *
     * La protection CSRF du projet est « sans état » : elle accepte la requête
     * dès lors que l'en-tête Origin correspond au site. Sans cet en-tête, tous
     * les envois seraient rejetés et les tests ne prouveraient rien.
     *
     * @param array<string, mixed> $champs
     */
    private function submit(KernelBrowser $client, string $url, array $champs): void
    {
        $client->request('POST', $url, $champs + ['_token' => 'csrf-token'], server: [
            'HTTP_ORIGIN' => 'http://localhost',
        ]);
    }
}
