<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Payment\Entity\SubscriptionPlan;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Création d'une offre d'abonnement dans le back-office (17/10 : l'ajout
 * échouait sur un prix à la française, une adresse ou un ordre laissés vides).
 */
final class SubscriptionPlanCrudTest extends WebTestCase
{
    public function testAPlanIsCreatedWithAFrenchPriceAndOnlyItsName(): void
    {
        $client = $this->adminClient();
        $name = 'Offre Pro '.uniqid();

        $this->submit($client, ['name' => $name, 'priceAmount' => '29,90']);
        self::assertResponseRedirects();

        $plan = static::getContainer()->get(EntityManagerInterface::class)->getRepository(SubscriptionPlan::class)->findOneBy(['name' => $name]);
        self::assertNotNull($plan);
        self::assertSame('29.90', $plan->getPriceAmount());
        self::assertStringStartsWith('offre-pro-', $plan->getSlug());
        self::assertSame(0, $plan->getPosition());
        self::assertTrue($plan->isActive());
    }

    public function testAZeroPriceIsRefusedWithAMessage(): void
    {
        $client = $this->adminClient();

        $this->submit($client, ['name' => 'Offre gratuite', 'priceAmount' => '0']);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Le prix doit être supérieur à 0.', (string) $client->getResponse()->getContent());
    }

    /** @param array<string, string> $values */
    private function submit(KernelBrowser $client, array $values): void
    {
        $crawler = $client->request('GET', '/admin/subscription-plan/new');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="SubscriptionPlan"]')->form();
        foreach (['slug' => '', 'position' => '', 'stripePriceId' => ''] + $values as $field => $value) {
            $form['SubscriptionPlan['.$field.']'] = $value;
        }
        $client->submit($form);
    }

    private function adminClient(): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $admin = (new User())
            ->setEmail(sprintf('admin-offres-%s@example.com', uniqid()))
            ->setFirstName('Admin')
            ->setLastName('Offres')
            ->setRoles(['ROLE_ADMIN'])
            ->setStatus(UserStatus::Active);
        $admin->setPassword('peu-importe');
        $em->persist($admin);
        $em->flush();
        $client->loginUser($admin);

        return $client;
    }
}
