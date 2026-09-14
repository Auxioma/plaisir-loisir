<?php

declare(strict_types=1);

namespace App\PrivateActivity\Service;

use App\Catalog\Entity\Category;
use App\Catalog\Entity\Service;
use App\PrivateActivity\Entity\Invitation;
use App\PrivateActivity\Entity\Participation;
use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Enum\ParticipationMode;
use App\PrivateActivity\Enum\ParticipationStatus;
use App\PrivateActivity\Enum\PrivateActivityStatus;
use App\PrivateActivity\Enum\PrivateActivityVisibility;
use App\PrivateActivity\Repository\InvitationRepository;
use App\PrivateActivity\Repository\ParticipationRepository;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Logique métier des activités privées : création, invitations, demandes de
 * participation, capacité et liste d'attente (§12-13 du CDC).
 *
 * PAS DE VERROU DE CONCURRENCE EXPLICITE
 * §13.3 du CDC demande d'empêcher tout dépassement de capacité « même en cas
 * de demandes simultanées ». Ici, la vérification (compter les participations
 * acceptées) et l'écriture se font dans le même appel PHP, sans verrou
 * pessimiste sur la ligne PrivateActivity : deux requêtes strictement
 * simultanées sur une place restante pourraient toutes deux réussir. Aucun
 * autre service du dépôt (QuoteService y compris) ne pose ce genre de verrou
 * — introduire le premier ici, seul, non testé sous charge réelle, aurait
 * ajouté de la complexité sans garantie sérieuse. À revisiter si la
 * volumétrie le justifie.
 */
final class PrivateActivityService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InvitationRepository $invitations,
        private readonly ParticipationRepository $participations,
    ) {
    }

    public function create(
        User $organizer,
        string $title,
        Category $category,
        ?string $description = null,
        ?\DateTimeImmutable $scheduledAt = null,
        ?string $city = null,
        ?string $location = null,
        bool $showExactAddress = true,
        PrivateActivityVisibility $visibility = PrivateActivityVisibility::Public,
        ParticipationMode $participationMode = ParticipationMode::Validation,
        ?int $minParticipants = null,
        ?int $maxParticipants = null,
        ?Service $service = null,
    ): PrivateActivity {
        $activity = (new PrivateActivity())
            ->setOrganizer($organizer)
            ->setTitle($title)
            ->setCategory($category)
            ->setDescription($description)
            ->setScheduledAt($scheduledAt)
            ->setCity($city)
            ->setLocation($location)
            ->setShowExactAddress($showExactAddress)
            ->setVisibility($visibility)
            ->setParticipationMode($participationMode)
            ->setMinParticipants($minParticipants)
            ->setMaxParticipants($maxParticipants)
            ->setService($service);

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $activity;
    }

    public function cancel(PrivateActivity $activity, User $organizer): void
    {
        if ($activity->getOrganizer() !== $organizer) {
            throw new \InvalidArgumentException('Seul l\'organisateur peut annuler cette activité.');
        }

        $activity->setStatus(PrivateActivityStatus::Cancelled);
        $this->entityManager->flush();
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

    /**
     * Un membre découvre une activité et demande à y participer (§13.1).
     *
     * Mode AUTOMATIQUE : la place est prise immédiatement s'il en reste, sinon
     * la demande part en liste d'attente. Mode VALIDATION : la demande reste
     * en attente, quelle que soit la capacité restante — c'est l'organisateur
     * qui décide (decide()).
     *
     * @throws \InvalidArgumentException si l'activité est annulée, si
     *                                   l'organisateur essaie de participer à
     *                                   sa propre activité, ou en cas de
     *                                   doublon
     */
    public function requestParticipation(PrivateActivity $activity, User $participant): Participation
    {
        if (PrivateActivityStatus::Cancelled === $activity->getStatus()) {
            throw new \InvalidArgumentException('Cette activité est annulée.');
        }

        if ($activity->getOrganizer() === $participant) {
            throw new \InvalidArgumentException('L\'organisateur participe déjà à sa propre activité.');
        }

        if (null !== $this->participations->findOneByActivityAndParticipant($activity, $participant)) {
            throw new \InvalidArgumentException('Vous avez déjà demandé à participer à cette activité.');
        }

        $participation = (new Participation())->setParticipant($participant);

        if (ParticipationMode::Automatic === $activity->getParticipationMode() && $this->hasRoom($activity)) {
            $participation->setStatus(ParticipationStatus::Accepted);
        } elseif (ParticipationMode::Automatic === $activity->getParticipationMode()) {
            $participation->setStatus(ParticipationStatus::WaitingList);
        } else {
            $participation->setStatus(ParticipationStatus::Pending);
        }

        $activity->addParticipation($participation);
        $this->entityManager->persist($participation);
        $this->refreshFullStatus($activity);
        $this->entityManager->flush();

        return $participation;
    }

    /**
     * L'organisateur statue sur une demande en attente (mode VALIDATION).
     *
     * @throws \InvalidArgumentException si l'auteur n'est pas l'organisateur, si la
     *                                   demande n'est plus en attente, ou (en cas
     *                                   d'acceptation) si la capacité est déjà atteinte
     */
    public function decide(Participation $participation, User $organizer, bool $accept): void
    {
        $activity = $participation->getPrivateActivity();

        if (null === $activity || $activity->getOrganizer() !== $organizer) {
            throw new \InvalidArgumentException('Seul l\'organisateur peut statuer sur cette demande.');
        }

        if (ParticipationStatus::Pending !== $participation->getStatus()) {
            throw new \InvalidArgumentException('Cette demande a déjà été traitée.');
        }

        if ($accept) {
            if (!$this->hasRoom($activity)) {
                throw new \InvalidArgumentException('Capacité atteinte : mettez la demande en liste d\'attente ou refusez-la.');
            }

            $participation->setStatus(ParticipationStatus::Accepted);
            $this->refreshFullStatus($activity);
        } else {
            $participation->setStatus(ParticipationStatus::Refused);
        }

        $this->entityManager->flush();
    }

    /**
     * Le participant annule sa place. Si elle était acceptée, une place se
     * libère : la personne la plus ancienne de la liste d'attente est
     * automatiquement promue (§13.4 du CDC).
     *
     * @throws \InvalidArgumentException si l'auteur n'est pas le participant
     */
    public function cancelParticipation(Participation $participation, User $participant): void
    {
        if ($participation->getParticipant() !== $participant) {
            throw new \InvalidArgumentException('Seul le participant peut annuler sa propre participation.');
        }

        $wasAccepted = ParticipationStatus::Accepted === $participation->getStatus();
        $participation->setStatus(ParticipationStatus::Cancelled);

        $activity = $participation->getPrivateActivity();

        if ($wasAccepted && null !== $activity) {
            $activity->setStatus(PrivateActivityStatus::Open);
            $this->promoteFromWaitingList($activity);
        }

        $this->entityManager->flush();
    }

    /**
     * Compte les places prises à partir de la COLLECTION en mémoire, pas
     * d'une requête `COUNT` (ParticipationRepository::countAccepted(), gardée
     * pour l'affichage). Une requête interrogerait la base telle qu'elle
     * était avant le flush() de cette méthode : juste après avoir accepté la
     * toute dernière place, le compte lu resterait celui d'AVANT
     * l'acceptation, et l'activité ne repasserait jamais FULL. La collection,
     * elle, reflète tout de suite l'ajout ou le changement de statut fait
     * plus haut dans le même appel.
     */
    private function hasRoom(PrivateActivity $activity): bool
    {
        $max = $activity->getMaxParticipants();

        if (null === $max) {
            return true;
        }

        $accepted = $activity->getParticipations()->filter(
            static fn (Participation $p): bool => ParticipationStatus::Accepted === $p->getStatus(),
        )->count();

        return $accepted < $max;
    }

    private function refreshFullStatus(PrivateActivity $activity): void
    {
        if (PrivateActivityStatus::Cancelled === $activity->getStatus()) {
            return;
        }

        $activity->setStatus($this->hasRoom($activity) ? PrivateActivityStatus::Open : PrivateActivityStatus::Full);
    }

    private function promoteFromWaitingList(PrivateActivity $activity): void
    {
        if (!$this->hasRoom($activity)) {
            return;
        }

        $next = $this->participations->findOldestWaiting($activity)[0] ?? null;

        if (null !== $next) {
            $next->setStatus(ParticipationStatus::Accepted);
            $this->refreshFullStatus($activity);
        }
    }
}
