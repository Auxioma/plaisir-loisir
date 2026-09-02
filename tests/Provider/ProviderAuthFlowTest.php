<?php

declare(strict_types=1);

namespace App\Tests\Provider;

use App\Provider\Entity\ProviderDocument;
use App\Provider\Enum\ProviderStatus;
use App\Provider\Repository\ProviderDocumentRepository;
use App\Provider\Repository\ProviderProfileRepository;
use App\Provider\Service\ProviderDocumentStorage;
use App\User\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Le parcours d'authentification PROFESSIONNEL, de bout en bout.
 *
 * CE QUE CE TEST PROTÈGE
 * L'inscription professionnelle n'existait pas : la tuile « Je suis un pro
 * prestataire » menait au formulaire CLIENT, où le professionnel ne déclarait
 * ni son activité, ni son siège social, ni la moindre pièce justificative. Le
 * risque, en construisant les sept écrans, était d'obtenir des pages qui
 * s'affichent sans que rien ne s'enregistre — le défaut le plus coûteux, parce
 * qu'il ne se voit pas.
 *
 * Chaque test vérifie donc l'EFFET (en base, sur le disque), pas seulement le
 * code de réponse.
 */
final class ProviderAuthFlowTest extends WebTestCase
{
    /**
     * L'étape 1 crée le compte, le dossier et l'identité légale.
     */
    public function testTheFirstStepOpensTheFile(): void
    {
        $client = static::createClient();
        $email = \sprintf('pro-%s@example.com', uniqid());

        $this->submitFirstStep($client, $email);

        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);

        self::assertNotNull($user, "L'étape 1 de l'inscription professionnelle n'a créé aucun compte.");
        self::assertSame('Riviere', $user->getLastName());
        self::assertSame('Paul', $user->getFirstName());
        self::assertContains('ROLE_PROVIDER', $user->getRoles(), 'Le compte créé par le parcours professionnel ne porte pas ROLE_PROVIDER.');

        $profile = static::getContainer()->get(ProviderProfileRepository::class)->findOneByUser($user);

        self::assertNotNull($profile, 'Aucun dossier prestataire n\'a été ouvert.');
        self::assertSame(ProviderStatus::Draft, $profile->getStatus(), 'Le dossier part en vérification dès l\'étape 1, alors que les pièces manquent encore.');
        self::assertNotNull($profile->getMainCategory(), "Le « Choix de l'activité » n'a pas été enregistré.");
    }

    /**
     * L'étape 1 mène à l'étape 2 : sans cela, le parcours s'arrête au premier
     * écran et personne ne dépose jamais de pièce.
     */
    public function testTheFirstStepLeadsToTheDocuments(): void
    {
        $client = static::createClient();

        $this->submitFirstStep($client, \sprintf('suite-%s@example.com', uniqid()));

        self::assertResponseRedirects('/pro/inscription/documents');
    }

    /**
     * L'étape 2 range les pièces et soumet le dossier à vérification.
     */
    public function testTheSecondStepFilesTheDocumentsAndSubmits(): void
    {
        $client = static::createClient();
        $email = \sprintf('docs-%s@example.com', uniqid());

        $this->submitFirstStep($client, $email);

        $client->request(
            'POST',
            '/pro/inscription/documents',
            ['_token' => 'csrf-token'],
            ['operating_licence' => $this->fakeDocument('licence.pdf')],
            ['HTTP_ORIGIN' => 'http://localhost'],
        );

        self::assertResponseRedirects('/pro/inscription/confirmation');

        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user);

        $profile = static::getContainer()->get(ProviderProfileRepository::class)->findOneByUser($user);
        self::assertNotNull($profile);

        $documents = static::getContainer()->get(ProviderDocumentRepository::class)->findForProfile($profile);

        self::assertCount(1, $documents, 'La pièce déposée n\'a pas été enregistrée.');
        self::assertSame('licence.pdf', $documents[0]->getOriginalName());

        // Le dossier complet part en vérification : c'est ce que promet
        // l'écran de fin (« notre service client vous contactera »).
        static::getContainer()->get(EntityManagerInterface::class)->refresh($profile);
        self::assertSame(ProviderStatus::PendingVerification, $profile->getStatus());

        $this->forget($documents[0]);
    }

    /**
     * LE FICHIER NE DOIT PAS ÊTRE SERVI PAR LE SERVEUR WEB.
     *
     * Une licence d'exploitation ou un certificat d'assurance déposés dans
     * public/ seraient téléchargeables par quiconque devine leur adresse. Ce
     * test échoue si quelqu'un déplace un jour le dossier de dépôt.
     */
    public function testDocumentsAreStoredOutsideTheWebRoot(): void
    {
        $client = static::createClient();

        $this->submitFirstStep($client, \sprintf('hors-%s@example.com', uniqid()));

        $client->request(
            'POST',
            '/pro/inscription/documents',
            ['_token' => 'csrf-token'],
            ['operating_licence' => $this->fakeDocument('kbis.pdf')],
            ['HTTP_ORIGIN' => 'http://localhost'],
        );

        $storage = static::getContainer()->get(ProviderDocumentStorage::class);
        $racineWeb = \dirname(__DIR__, 2).'/public';

        self::assertStringStartsNotWith(
            realpath($racineWeb) ?: $racineWeb,
            realpath($storage->directory()) ?: $storage->directory(),
            'Les pièces justificatives sont rangées sous public/ : elles seraient téléchargeables par n\'importe qui.',
        );
    }

    /**
     * Un fichier qui n'est ni PDF ni image est refusé, et le dossier ne part
     * pas en vérification pour autant.
     */
    public function testAnExecutableIsRefused(): void
    {
        $client = static::createClient();
        $email = \sprintf('refus-%s@example.com', uniqid());

        $this->submitFirstStep($client, $email);

        $client->request(
            'POST',
            '/pro/inscription/documents',
            ['_token' => 'csrf-token'],
            ['operating_licence' => $this->fakeDocument('charge.exe', "MZ\x90\x00\x03")],
            ['HTTP_ORIGIN' => 'http://localhost'],
        );

        self::assertResponseRedirects('/pro/inscription/documents');

        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        $profile = static::getContainer()->get(ProviderProfileRepository::class)->findOneByUser($user);

        self::assertSame(ProviderStatus::Draft, $profile->getStatus(), 'Un dossier sans pièce valable a été soumis à vérification.');
    }

    /**
     * Les étapes 2 et 3 refusent de s'afficher hors parcours : sinon il
     * suffirait d'ouvrir directement l'écran de félicitations.
     */
    public function testTheLaterStepsRefuseToOpenOnTheirOwn(): void
    {
        $client = static::createClient();

        $client->request('GET', '/pro/inscription/documents');
        self::assertResponseRedirects('/pro/inscription');

        $client->request('GET', '/pro/inscription/confirmation');
        self::assertResponseRedirects('/pro/inscription');
    }

    /**
     * Le compte créé par ce parcours peut réellement se connecter — c'est
     * toute la raison du champ mot de passe ajouté à la maquette.
     */
    public function testTheAccountCanLogIn(): void
    {
        $client = static::createClient();
        $email = \sprintf('connexion-%s@example.com', uniqid());

        $this->submitFirstStep($client, $email);

        $client->request('POST', '/login', [
            '_email' => $email,
            '_password' => 'motdepasse123',
            '_csrf_token' => 'csrf-token',
        ], server: ['HTTP_ORIGIN' => 'http://localhost']);

        self::assertResponseRedirects();
        self::assertStringNotContainsString(
            '/pro/connexion',
            (string) $client->getResponse()->headers->get('Location'),
            'La connexion a échoué : le compte créé à l\'étape 1 ne peut pas se connecter.',
        );
    }

    /**
     * Les quatre écrans à bande latérale s'affichent, dans les deux langues.
     */
    public function testEveryScreenAnswers(): void
    {
        $client = static::createClient();

        foreach (['/pro/connexion', '/pro/mot-de-passe-oublie', '/pro/inscription', '/en/pro/login', '/en/pro/signup'] as $adresse) {
            $client->request('GET', $adresse);
            self::assertResponseIsSuccessful(\sprintf('L\'écran « %s » ne répond pas.', $adresse));
        }
    }

    /**
     * La réinitialisation enchaîne bien ses trois écrans.
     */
    public function testThePasswordResetWalksThroughItsThreeScreens(): void
    {
        $client = static::createClient();

        // Sans adresse en session, la vue OTP renvoie au premier écran.
        $client->request('GET', '/pro/mot-de-passe-oublie/verification');
        self::assertResponseRedirects('/pro/mot-de-passe-oublie');

        $client->request('POST', '/pro/mot-de-passe-oublie', [
            '_token' => 'csrf-token',
            'email' => 'inconnu@example.com',
        ], server: ['HTTP_ORIGIN' => 'http://localhost']);

        // Muet sur l'existence du compte : on avance quand même.
        self::assertResponseRedirects('/pro/mot-de-passe-oublie/verification');

        $crawler = $client->request('GET', '/pro/mot-de-passe-oublie/verification');
        self::assertResponseIsSuccessful();

        // Autant de cases que de caractères dans le code réellement envoyé.
        self::assertCount(
            \App\User\Service\PasswordResetService::CODE_LENGTH,
            $crawler->filter('.pa-otp__cell'),
            'Le nombre de cases ne correspond pas à la longueur du code envoyé : le code reçu ne rentrerait pas.',
        );

        // Sans code validé, l'écran du nouveau mot de passe reste fermé.
        $client->request('GET', '/pro/mot-de-passe-oublie/nouveau');
        self::assertResponseRedirects('/pro/mot-de-passe-oublie');
    }

    /**
     * LES TROIS PASSERELLES DEPUIS LE CÔTÉ CLIENT.
     *
     * Elles pointaient toutes vers « /authentification?type=pro », qui ramenait
     * au formulaire CLIENT avec un type caché : le professionnel tournait en
     * rond et repartait avec un compte sans activité, sans siège social et sans
     * pièce justificative. Le parcours professionnel existait déjà que ces
     * liens n'y menaient toujours pas — c'est exactement le genre de câblage
     * qu'on oublie, et qui ne se voit pas à l'écran.
     *
     * Depuis un écran d'INSCRIPTION on part sur l'inscription professionnelle ;
     * depuis un écran de CONNEXION, sur la connexion professionnelle.
     *
     * @param non-empty-string $depuis
     * @param non-empty-string $vers
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('clientScreensWithAProfessionalLink')]
    public function testEveryClientScreenPointsAtTheProfessionalPath(string $depuis, string $vers): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', $depuis);

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(
            0,
            $crawler->filter(sprintf('a[href="%s"]', $vers))->count(),
            sprintf('L\'écran « %s » ne mène pas au parcours professionnel (%s attendu).', $depuis, $vers),
        );

        // Et l'ancienne impasse a bien disparu : « ?type=pro » ramenait au
        // formulaire client.
        self::assertSame(
            0,
            $crawler->filter('a[href*="type=pro"]')->count(),
            sprintf('L\'écran « %s » porte encore un lien « ?type=pro » vers le formulaire client.', $depuis),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function clientScreensWithAProfessionalLink(): iterable
    {
        yield 'écran de choix' => ['/authentification', '/pro/inscription'];
        yield 'inscription client' => ['/inscription', '/pro/inscription'];
        yield 'connexion client' => ['/login', '/pro/connexion'];
    }

    /**
     * Les écrans professionnels n'ont ni navbar ni pied de page : le logo est
     * la seule sortie vers le site, et il doit être cliquable. Sans lui, un
     * visiteur égaré est dans une impasse.
     */
    public function testTheLogoIsTheWayOutOfTheProfessionalScreens(): void
    {
        $client = static::createClient();

        foreach (['/pro/connexion', '/pro/inscription'] as $adresse) {
            $crawler = $client->request('GET', $adresse);

            self::assertGreaterThan(
                0,
                $crawler->filter('a[href="/"] img')->count(),
                sprintf('Le logo de « %s » ne ramène pas à l\'accueil : l\'écran est une impasse.', $adresse),
            );
        }
    }

    /*
     * ------------------------------------------------------------------------
     *  Utilitaires
     * ------------------------------------------------------------------------
     */

    private function submitFirstStep(KernelBrowser $client, string $email): void
    {
        $categorie = static::getContainer()->get(\App\Catalog\Repository\CategoryRepository::class)->findOneBy(['parent' => null]);
        self::assertNotNull($categorie, 'Aucune catégorie racine : le jeu de données de test est incomplet.');

        $client->request('POST', '/pro/inscription', [
            '_token' => 'csrf-token',
            'provider_registration_form' => [
                'lastName' => 'Riviere',
                'firstName' => 'Paul',
                'email' => $email,
                'phone' => '0102030405',
                'password' => 'motdepasse123',
                'mainCategory' => (string) $categorie->getId(),
                'registeredOffice' => '12 rue des Écoles, 75005 Paris',
                '_token' => 'csrf-token',
            ],
        ], server: ['HTTP_ORIGIN' => 'http://localhost']);
    }

    /**
     * Un vrai fichier temporaire : UploadedFile refuse de déplacer un chemin
     * qui n'existe pas, et `move()` est justement ce qu'on veut éprouver.
     */
    private function fakeDocument(string $nom, string $contenu = "%PDF-1.4\n%%EOF\n"): UploadedFile
    {
        $chemin = tempnam(sys_get_temp_dir(), 'doc');
        file_put_contents($chemin, $contenu);

        // `test: true` : sans ce drapeau, UploadedFile exige que le fichier
        // vienne d'un vrai téléversement HTTP et rejette tout le reste.
        return new UploadedFile($chemin, $nom, null, null, true);
    }

    /**
     * Efface le fichier rangé sur le disque : la suite ne doit pas laisser
     * derrière elle des pièces justificatives dans var/uploads/.
     */
    private function forget(ProviderDocument $document): void
    {
        $chemin = static::getContainer()->get(ProviderDocumentStorage::class)->pathOf($document);

        if (is_file($chemin)) {
            unlink($chemin);
        }
    }
}
