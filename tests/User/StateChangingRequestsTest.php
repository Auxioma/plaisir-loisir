<?php

declare(strict_types=1);

namespace App\Tests\User;

use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Deux gestes qui ne devaient pas s'obtenir par une simple adresse.
 *
 * POURQUOI CE TEST EXISTE
 * Le CTO a demandé le 25/08 de « passer les GET en POST ». Appliqué au pied de
 * la lettre, ce serait une régression : les onze formulaires de recherche du
 * site DOIVENT rester en GET, sans quoi les résultats ne sont plus ni
 * partageables ni indexables — l'inverse exact de ce qui vient d'être fait
 * pour le référencement.
 *
 * L'audit n'a retenu que les deux cas où la remarque tient vraiment :
 *
 *  1. le tunnel cadeau envoyait des données PERSONNELLES dans l'adresse de la
 *     page suivante (nom, e-mail, téléphone, destinataire, message). Une URL
 *     traverse l'historique du navigateur, les journaux d'accès du serveur et
 *     l'en-tête « Referer » envoyé aux tiers ;
 *  2. la déconnexion s'exécutait sur un GET, qu'un site tiers pouvait
 *     déclencher avec une balise <img>.
 *
 * Ce test fige les deux : il échouera si quelqu'un remet un `method="get"` sur
 * le formulaire cadeau, ou si le jeton de déconnexion disparaît du pare-feu.
 */
final class StateChangingRequestsTest extends WebTestCase
{
    public function testTheGiftFormDoesNotPutPersonalDataInTheUrl(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/cadeaux/offrir');

        self::assertSame(200, $client->getResponse()->getStatusCode());

        $form = $crawler->filter('#gift-form');
        self::assertGreaterThan(0, $form->count(), 'Le formulaire du tunnel cadeau est introuvable.');

        self::assertSame(
            'post',
            strtolower((string) $form->attr('method')),
            "Le tunnel cadeau est repassé en GET : le nom, l'e-mail, le téléphone et le "
            ."message privé se retrouveraient dans l'adresse de la page de paiement.",
        );

        self::assertGreaterThan(
            0,
            $form->filter('input[name="_token"]')->count(),
            'Le jeton anti-CSRF manque au formulaire cadeau.',
        );

        // Et la page de paiement s'ouvre bien quand le formulaire est envoyé.
        $client->submit($form->form([
            'fullName' => 'Martin Thomas',
            'email' => 'martin@example.com',
        ]));

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertStringNotContainsString(
            'martin@example.com',
            (string) $client->getRequest()->getUri(),
            "L'adresse de la page de paiement contient encore l'e-mail saisi.",
        );
    }

    /**
     * Sans jeton, la déconnexion ne doit pas avoir lieu.
     *
     * C'est exactement ce qu'un site tiers pourrait provoquer avec
     * `<img src="https://trouvemoi.eu/logout">` : une requête GET, sans rien
     * qui prouve que le visiteur l'ait demandée.
     */
    public function testAPlainGetDoesNotSignTheVisitorOut(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeUser());

        $client->request('GET', '/logout');

        // Le pare-feu refuse (403) ou ignore ; dans les deux cas la session
        // doit survivre. C'est ce dernier point qui compte, pas le code.
        $client->request('GET', '/compte/deconnexion');
        self::assertSame(
            200,
            $client->getResponse()->getStatusCode(),
            'Un simple GET sur /logout a suffi à déconnecter le visiteur.',
        );
    }

    public function testTheConfirmationScreenSignsTheVisitorOut(): void
    {
        $client = static::createClient();
        $client->loginUser($this->makeUser());

        $crawler = $client->request('GET', '/compte/deconnexion');
        self::assertSame(200, $client->getResponse()->getStatusCode());

        $form = $crawler->filter('form[action*="logout"]');
        self::assertGreaterThan(0, $form->count(), "L'écran de confirmation n'a plus de formulaire de déconnexion.");
        self::assertGreaterThan(
            0,
            $form->filter('input[name="_csrf_token"]')->count(),
            'Le jeton de déconnexion manque au formulaire.',
        );

        $client->submit($form->form());
        $client->followRedirect();

        // Une fois déconnecté, l'espace compte est de nouveau fermé.
        $client->request('GET', '/compte/deconnexion');
        self::assertTrue(
            $client->getResponse()->isRedirect(),
            "La déconnexion n'a pas eu lieu : l'espace compte reste accessible.",
        );
    }

    private function makeUser(): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = (new User())
            ->setEmail(sprintf('deconnexion-%s@example.com', uniqid()))
            ->setFirstName('Camille')
            ->setLastName('Test')
            ->setStatus(UserStatus::Active);
        $user->setPassword('peu-importe');

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
