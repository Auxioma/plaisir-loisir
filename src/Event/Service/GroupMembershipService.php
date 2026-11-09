<?php

declare(strict_types=1);

namespace App\Event\Service;

use App\Event\Entity\Group;
use App\Event\Entity\GroupMembership;
use App\Event\Repository\GroupMembershipRepository;
use App\Notification\Enum\NotificationCategory;
use App\Notification\Service\NotificationService;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Demandes d'adhésion, acceptation, refus et départ d'un groupe (09/11).
 *
 * Group::$membersCount reste un compteur recopié (les cartes du listing
 * l'affichent sans requête par vignette) : il est recalculé ici à chaque
 * changement — membres acceptés, plus le créateur s'il y en a un.
 */
final class GroupMembershipService
{
    public function __construct(
        private readonly GroupMembershipRepository $memberships,
        private readonly EntityManagerInterface $em,
        private readonly NotificationService $notifications,
    ) {
    }

    /**
     * État de la personne vis-à-vis du groupe : owner, member, pending ou null.
     */
    public function statusFor(Group $group, ?User $user): ?string
    {
        if (!$user instanceof User) {
            return null;
        }
        if ($group->getOwner() === $user) {
            return 'owner';
        }

        return $this->memberships->findOneFor($group, $user)?->getStatus();
    }

    /**
     * Crée la demande. Sans créateur pour la valider, l'adhésion est immédiate.
     */
    public function request(Group $group, User $user): GroupMembership
    {
        $owner = $group->getOwner();
        $membership = new GroupMembership($group, $user, null === $owner ? GroupMembership::MEMBER : GroupMembership::PENDING);
        $this->em->persist($membership);
        $this->em->flush();
        $this->refreshCount($group);

        if (null !== $owner) {
            $this->notifications->notify(
                $owner,
                NotificationCategory::Activity,
                'Nouvelle demande d’adhésion',
                sprintf('%s souhaite rejoindre « %s ».', $this->displayName($user), $group->getName()),
            );
        }

        return $membership;
    }

    /** Annule une demande ou quitte le groupe. */
    public function leave(GroupMembership $membership): void
    {
        $group = $membership->getGroup();
        $this->em->remove($membership);
        $this->em->flush();
        $this->refreshCount($group);
    }

    public function accept(GroupMembership $membership): void
    {
        $membership->accept();
        $this->em->flush();
        $this->refreshCount($membership->getGroup());

        $this->notifications->notify(
            $membership->getUser(),
            NotificationCategory::Activity,
            'Demande acceptée',
            sprintf('Bienvenue dans le groupe « %s » !', $membership->getGroup()->getName()),
        );
    }

    public function refuse(GroupMembership $membership): void
    {
        $user = $membership->getUser();
        $name = $membership->getGroup()->getName();
        $this->leave($membership);

        $this->notifications->notify(
            $user,
            NotificationCategory::Activity,
            'Demande non retenue',
            sprintf('Votre demande pour rejoindre « %s » n’a pas été acceptée.', $name),
        );
    }

    public function refreshCount(Group $group): void
    {
        $group->setMembersCount($this->memberships->countMembers($group) + (null !== $group->getOwner() ? 1 : 0));
        $this->em->flush();
    }

    private function displayName(User $user): string
    {
        $name = trim($user->getFirstName().' '.$user->getLastName());

        return '' !== $name ? $name : 'Un membre';
    }
}
