<?php

declare(strict_types=1);

namespace App\PrivateActivity\Service;

use App\Catalog\Entity\Service;
use App\PrivateActivity\Entity\Invitation;
use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Repository\InvitationRepository;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Logique métier des activités privées : création, invitations et réponses.
 */
final class PrivateActivityService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InvitationRepository $invitations,
    ) {
    }

    public function create(
        User $organizer,
        string $title,
        ?string $description = null,
        ?\DateTimeImmutable $scheduledAt = null,
        ?string $location = null,
        ?Service $service = null,
    ): PrivateActivity {
        $activity = (new PrivateActivity())
            ->setOrganizer($organizer)
            ->setTitle($title)
            ->setDescription($description)
            ->setScheduledAt($scheduledAt)
            ->setLocation($location)
            ->setService($service);

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $activity;
    }

    /**
     * @throws \InvalidArgumentException si l'auteur n'est pas l'organisateur ou si
     *                                   la personne est déjà invitée
     */
    public function invite(PrivateActivity $activity, User $organizer, User $invitee): Invitation
    {
        if ($activity->getOrganizer() !== $organizer) {
            throw new \InvalidArgumentException('Seul l\'organisateur peut inviter.');
        }

        if (null !== $this->invitations->findOneByActivityAndInvitee($activity, $invitee)) {
            throw new \InvalidArgumentException('Cette personne est déjà invitée.');
        }

        $invitation = (new Invitation())->setInvitee($invitee);
        $activity->addInvitation($invitation);

        $this->entityManager->persist($invitation);
        $this->entityManager->flush();

        return $invitation;
    }

    /**
     * @throws \InvalidArgumentException si l'utilisateur n'est pas l'invité
     */
    public function respond(Invitation $invitation, User $invitee, bool $accept): void
    {
        if ($invitation->getInvitee() !== $invitee) {
            throw new \InvalidArgumentException('Seul l\'invité peut répondre à son invitation.');
        }

        if ($accept) {
            $invitation->accept();
        } else {
            $invitation->decline();
        }

        $this->entityManager->flush();
    }
}
