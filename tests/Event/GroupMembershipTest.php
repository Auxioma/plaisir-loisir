<?php

declare(strict_types=1);

namespace App\Tests\Event;

use App\Event\Entity\Group;
use App\Event\Entity\GroupMembership;
use App\Event\Repository\GroupMembershipRepository;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Adhésion aux groupes (09/11) : le bouton « Demander à rejoindre le
 * groupe » enregistre une vraie demande, le créateur la traite, et l'onglet
 * Membres comme le compteur reflètent la base.
 */
final class GroupMembershipTest extends WebTestCase
{
    public function testVisitorIsSentToLogin(): void
    {
        $client = static::createClient();
        $group = $this->makeGroup(null);

        $client->request('POST', '/evenements/groupes/detail/'.$group->getSlug().'/rejoindre');

        self::assertResponseRedirects('/login');
    }

    public function testGroupWithoutCreatorIsJoinedAtOnceAndCanBeLeft(): void
    {
        $client = static::createClient();
        $group = $this->makeGroup(null);
        $member = $this->makeUser('Camille', 'Membre');
        $client->loginUser($member);

        $this->submitJoin($client, $group);
        self::assertResponseRedirects('/evenements/groupes/detail/'.$group->getSlug().'/membres');

        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('#evn-members-all', 'Camille Membre');
        self::assertSame(1, $this->reload($group)->getMembersCount());

        // Le bouton devient « Quitter le groupe ».
        $client->submit($crawler->filter('.evn-gtabs__actions form')->form());
        self::assertSame(0, $this->reload($group)->getMembersCount());
        self::assertNull($this->memberships()->findOneFor($this->reload($group), $member));
    }

    public function testCreatorAcceptsARequest(): void
    {
        $client = static::createClient();
        $owner = $this->makeUser('Alex', 'Créateur');
        $group = $this->makeGroup($owner);
        $member = $this->makeUser('Sacha', 'Candidat');

        $client->loginUser($member);
        $this->submitJoin($client, $group);
        self::assertResponseRedirects('/evenements/groupes/detail/demande-envoyee?groupe='.$group->getSlug());
        $client->followRedirect();
        self::assertSelectorTextContains('.evn-sent__text', $group->getName());

        $membership = $this->memberships()->findOneFor($this->reload($group), $member);
        self::assertInstanceOf(GroupMembership::class, $membership);
        self::assertTrue($membership->isPending());
        self::assertSame(1, $this->reload($group)->getMembersCount(), 'Une demande en attente ne compte pas.');

        // Un autre membre ne peut pas trancher.
        $client->request('POST', sprintf('/evenements/groupes/detail/%s/demandes/%s/accepter', $group->getSlug(), $membership->getId()));
        self::assertResponseStatusCodeSame(403);

        $client->loginUser($owner);
        $crawler = $client->request('GET', '/evenements/groupes/detail/'.$group->getSlug().'/membres');
        self::assertSelectorTextContains('.evn-mlist--requests', 'Sacha Candidat');
        $client->submit($crawler->filter('.evn-mlist--requests form')->first()->form());

        $client->followRedirect();
        self::assertSelectorNotExists('.evn-mlist--requests');
        self::assertSelectorTextContains('#evn-members-all', 'Sacha Candidat');
        self::assertSame(2, $this->reload($group)->getMembersCount());
    }

    private function submitJoin(KernelBrowser $client, Group $group): void
    {
        $crawler = $client->request('GET', '/evenements/groupes/detail/'.$group->getSlug());
        self::assertResponseIsSuccessful();
        $client->submit($crawler->filter('#evn-join form')->form());
    }

    private function makeGroup(?User $owner): Group
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $id = uniqid();
        $group = (new Group())
            ->setName('Groupe test '.$id)
            ->setSlug('groupe-test-'.$id)
            ->setOwner($owner)
            ->setMembersCount(null !== $owner ? 1 : 0);
        $em->persist($group);
        $em->flush();

        return $group;
    }

    private function makeUser(string $first, string $last): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())
            ->setEmail(sprintf('groupe-%s@example.com', uniqid()))
            ->setFirstName($first)
            ->setLastName($last)
            ->setRoles([])
            ->setStatus(UserStatus::Active);
        $user->setPassword('peu-importe');
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function reload(Group $group): Group
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        return $em->find(Group::class, $group->getId()) ?? throw new \LogicException('Groupe disparu.');
    }

    private function memberships(): GroupMembershipRepository
    {
        return static::getContainer()->get(GroupMembershipRepository::class);
    }
}
