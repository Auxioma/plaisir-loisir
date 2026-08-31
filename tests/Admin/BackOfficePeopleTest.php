<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Corporate\Entity\ContactMessage;
use App\Corporate\Entity\PartnerApplication;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Les quatre écrans qui donnent à Loïc la main sur le site.
 *
 * POURQUOI ILS EXISTENT
 * Demande du CTO le 31/08. Deux d'entre eux comblent un trou : les
 * candidatures « Devenir partenaire » et les messages de contact étaient
 * enregistrés en base et relus NULLE PART. Le site recueillait des demandes,
 * promettait une réponse, et les jetait.
 *
 * CE QUE CES TESTS VÉRIFIENT VRAIMENT
 * Pas seulement que les écrans s'ouvrent. Que les garde-fous tiennent à
 * l'ADRESSE et non au bouton : masquer une action d'EasyAdmin cache le bouton,
 * l'URL reste tapable. C'est là que se joue la différence entre une règle et
 * une décoration.
 */
final class BackOfficePeopleTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function screens(): iterable
    {
        yield 'membres' => ['/admin/user', 'Membres'];
        yield 'prestataires' => ['/admin/provider-profile', 'Prestataires'];
        yield 'candidatures' => ['/admin/partner-application', 'Candidatures'];
        yield 'messages' => ['/admin/contact-message', 'Messages'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('screens')]
    public function testEveryScreenOpensAndIsReachableFromTheMenu(string $url, string $intitule): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        $client->request('GET', $url);

        self::assertSame(200, $client->getResponse()->getStatusCode(), sprintf('%s ne s\'ouvre pas.', $url));
        self::assertStringContainsString(
            $intitule,
            (string) $client->getResponse()->getContent(),
            sprintf('« %s » est absent du menu : un écran qu\'on ne trouve pas n\'existe pas.', $intitule),
        );
    }

    /**
     * LE TEST QUI COMPTE POUR LES CANDIDATURES : elles sont enfin lisibles.
     */
    public function testAPartnerApplicationSubmittedByAVisitorIsVisibleInTheBackOffice(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        $candidature = $this->makeApplication();

        $client->request('GET', '/admin/partner-application');

        self::assertStringContainsString(
            $candidature->getSiteName(),
            (string) $client->getResponse()->getContent(),
            'Une candidature reçue n\'apparaît pas : elle retombe dans le trou qu\'on vient de boucher.',
        );
    }

    public function testAContactMessageIsVisibleInTheBackOffice(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        $message = $this->makeMessage();

        $client->request('GET', '/admin/contact-message');

        self::assertStringContainsString($message->getSubject(), (string) $client->getResponse()->getContent());
    }

    public function testMarkingAnApplicationAsHandledIsRecorded(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        $candidature = $this->makeApplication();
        self::assertNull($candidature->getHandledAt());

        $client->request('GET', sprintf('/admin/partner-application/%s/mark-handled', $candidature->getId()));

        $relue = $this->reload(PartnerApplication::class, (string) $candidature->getId());
        self::assertInstanceOf(PartnerApplication::class, $relue);
        self::assertNotNull($relue->getHandledAt(), 'Marquer une candidature comme traitée ne laisse aucune trace.');
    }

    /**
     * Une candidature ne se réécrit pas — même en tapant l'adresse.
     */
    public function testAnApplicationCannotBeEditedByTypingTheUrl(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        $candidature = $this->makeApplication();
        $origine = $candidature->getSiteName();

        $client->request('GET', sprintf('/admin/partner-application/%s/edit', $candidature->getId()));

        self::assertStringNotContainsString(
            'name="PartnerApplication"',
            (string) $client->getResponse()->getContent(),
            'Le formulaire de modification d\'une candidature s\'est ouvert : on pourrait réécrire ce qu\'un tiers a envoyé.',
        );

        $relue = $this->reload(PartnerApplication::class, (string) $candidature->getId());
        self::assertInstanceOf(PartnerApplication::class, $relue);
        self::assertSame($origine, $relue->getSiteName());
    }

    /**
     * Un compte ne se supprime pas, même en tapant l'adresse : ses
     * réservations sont des pièces comptables.
     */
    public function testAnAccountCannotBeDeletedByTypingTheUrl(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        $membre = $this->makeUser();
        $identifiant = (string) $membre->getId();

        $client->request('POST', sprintf('/admin/user/%s/delete', $identifiant));

        self::assertNotNull(
            $this->reload(User::class, $identifiant),
            'Un compte a été supprimé depuis le back-office : son historique de réservations part avec lui.',
        );
    }

    /**
     * L'anonymisation efface les données personnelles et conserve la ligne.
     */
    public function testAnonymizingRemovesPersonalDataButKeepsTheRow(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeAdmin());

        $membre = $this->makeUser();
        $identifiant = (string) $membre->getId();
        $adresse = $membre->getEmail();

        $client->request('GET', sprintf('/admin/user/%s/anonymize', $identifiant));

        $relu = $this->reload(User::class, $identifiant);
        self::assertInstanceOf(User::class, $relu, 'La ligne a disparu : les réservations liées perdraient leur titulaire.');
        self::assertNotSame($adresse, $relu->getEmail(), 'L\'adresse e-mail est toujours là : rien n\'a été anonymisé.');
        self::assertStringNotContainsString('@example.com', $relu->getEmail());
        self::assertTrue($relu->isDeleted(), 'Le compte n\'est pas marqué comme supprimé : il resterait connectable.');
        self::assertSame(UserStatus::Suspended, $relu->getStatus());
    }

    /**
     * On ne s'anonymise pas soi-même : on perdrait son propre accès sans
     * moyen de revenir.
     */
    public function testAnAdminCannotAnonymizeTheirOwnAccount(): void
    {
        $client = static::createClient();
        $administrateur = $this->makeAdmin();
        $client->loginUser($administrateur);

        $identifiant = (string) $administrateur->getId();
        $client->request('GET', sprintf('/admin/user/%s/anonymize', $identifiant));

        $relu = $this->reload(User::class, $identifiant);
        self::assertInstanceOf(User::class, $relu);
        self::assertFalse($relu->isDeleted(), 'Un administrateur s\'est anonymisé lui-même et a perdu son accès.');
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $classe
     *
     * @return T|null
     */
    private function reload(string $classe, string $identifiant): ?object
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        return $entityManager->getRepository($classe)->find($identifiant);
    }

    private function makeApplication(): PartnerApplication
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $candidature = (new PartnerApplication())
            ->setSiteName('Site candidat '.uniqid())
            ->setSiteUrl('https://exemple.test')
            ->setSector('Sport')
            ->setTraffic('10 000 visites/mois')
            ->setEmail('candidat@example.com')
            ->setAddress('1 rue des Essais')
            ->setPostalCode('75001')
            ->setTermsAccepted(true);

        $entityManager->persist($candidature);
        $entityManager->flush();

        return $candidature;
    }

    private function makeMessage(): ContactMessage
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $message = (new ContactMessage())
            ->setName('Visiteur')
            ->setEmail('visiteur@example.com')
            ->setSubject('Question temoin '.uniqid())
            ->setMessage('Bonjour, une question.');

        $entityManager->persist($message);
        $entityManager->flush();

        return $message;
    }

    private function makeUser(): User
    {
        return $this->persistUser([]);
    }

    private function makeAdmin(): User
    {
        return $this->persistUser(['ROLE_ADMIN']);
    }

    /**
     * @param list<string> $roles
     */
    private function persistUser(array $roles): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail(sprintf('bo-%s@example.com', uniqid()))
            ->setFirstName('Loïc')
            ->setLastName('Test')
            ->setRoles($roles)
            ->setStatus(UserStatus::Active);
        $user->setPassword('peu-importe');

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
