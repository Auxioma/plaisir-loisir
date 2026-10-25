<?php

declare(strict_types=1);

namespace App\Admin\Service;

use App\Admin\Enum\AuditAction;
use App\Event\Entity\Event;
use App\Event\Entity\EventInvitation;
use App\Event\Entity\EventRegistration;
use App\Event\Repository\EventRegistrationRepository;
use App\Favorite\Entity\Favorite;
use App\Notification\Enum\NotificationCategory;
use App\Notification\Service\NotificationService;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/** Maintient les inscriptions et la visibilité quand l'administration intervient. */
final class EventAdminService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EventRegistrationRepository $registrations,
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {
    }

    public function requiredCapacity(Event $event): int
    {
        $count = null !== $event->getId() ? $this->registrations->countGoing($event) : 0;
        $organizer = $event->getOrganizer();
        $registration = null !== $event->getId() && null !== $organizer ? $this->registrations->findOneFor($event, $organizer) : null;

        return $count + (null !== $organizer && (!$registration || EventRegistration::GOING !== $registration->getStatus()) ? 1 : 0);
    }

    public function save(Event $event): void
    {
        $before = $this->em->getUnitOfWork()->getOriginalEntityData($event);
        $event->setPrivate(!\in_array($event->getVisibility(), ['public', 'members'], true));
        if ('published' === $event->getStatus()) {
            if (($before['status'] ?? null) !== 'published' || null === $event->getPublishAt()) {
                $event->setPublishAt(new \DateTimeImmutable());
            }
        } elseif ('scheduled' !== $event->getStatus()) {
            $event->setPublishAt(null);
        }
        $locationChanged = [] !== $before && ($before['location'] ?? null) === $event->getLocation()
            && (($before['city'] ?? null) !== $event->getCity() || ($before['postalCode'] ?? null) !== $event->getPostalCode()
                || ($before['address'] ?? null) !== $event->getAddress() || ($before['locationType'] ?? null) !== $event->getLocationType());
        if (!$event->getLocation() || $locationChanged) {
            $event->setLocation(match ($event->getLocationType()) {
                'online' => 'En ligne',
                'tbd' => 'Lieu à définir',
                default => trim(($event->getCity() ?? '').' '.($event->getPostalCode() ?? '')) ?: $event->getAddress(),
            });
        }
        $this->em->persist($event);
        if (null !== ($organizer = $event->getOrganizer())) {
            $registration = null !== $event->getId() ? $this->registrations->findOneFor($event, $organizer) : null;
            if (null === $registration) {
                $this->em->persist(new EventRegistration($event, $organizer));
            } else {
                $registration->setStatus(EventRegistration::GOING);
            }
        }
        $this->em->flush();
        if ($event->isPrivate()) {
            foreach ($this->registrations->findBy(['event' => $event]) as $registration) {
                $this->inviteRegisteredClient($registration);
            }
        }
        $this->recount($event);

        if ([] !== $before && (($before['status'] ?? null) !== $event->getStatus()
            || ($before['startsAt'] ?? null) != $event->getStartsAt()
            || ($before['location'] ?? null) !== $event->getLocation())) {
            $users = [];
            if (null !== $organizer) {
                $users[(string) $organizer->getId()] = $organizer;
            }
            foreach ($this->registrations->findGoing($event) as $registration) {
                $users[(string) $registration->getUser()->getId()] = $registration->getUser();
            }
            foreach ($users as $user) {
                $this->notifications->notify($user, NotificationCategory::Activity, 'suspended' === $event->getStatus() ? 'Événement suspendu' : 'Événement mis à jour par l’administration', 'suspended' === $event->getStatus() ? sprintf('« %s » a été retiré du site par l’administration. Contactez le support pour plus d’informations.', $event->getTitle()) : sprintf('Les informations ou la publication de « %s » ont été modifiées par l’équipe TrouveMoi.', $event->getTitle()));
            }
        }
    }

    public function recount(Event $event): void
    {
        $event->setParticipantsCount($this->registrations->countGoing($event));
        $this->em->flush();
    }

    public function createRegistration(EventRegistration $registration): void
    {
        $this->em->persist($registration);
        $this->inviteRegisteredClient($registration);
        $this->updateRegistration($registration);
    }

    private function inviteRegisteredClient(EventRegistration $registration): void
    {
        if ($registration->getEvent()->isPrivate()
            && null === $this->em->getRepository(EventInvitation::class)->findOneBy(['event' => $registration->getEvent(), 'user' => $registration->getUser()])) {
            $this->em->persist(new EventInvitation($registration->getEvent(), $registration->getUser()));
        }
    }

    public function delete(Event $event, ?User $actor): void
    {
        $id = $event->getId();
        $title = $event->getTitle();
        $users = [];
        if (null !== $event->getOrganizer()) {
            $users[(string) $event->getOrganizer()->getId()] = $event->getOrganizer();
        }
        foreach ($this->registrations->findBy(['event' => $event]) as $registration) {
            $users[(string) $registration->getUser()->getId()] = $registration->getUser();
            $this->em->remove($registration);
        }
        // Retirer aussi les objets suivis par Doctrine avant le parent : la
        // cascade SQL seule les laisserait référencer un événement supprimé
        // lors du flush suivant (audit et notifications).
        foreach ([EventInvitation::class, Favorite::class] as $class) {
            foreach ($this->em->getRepository($class)->findBy(['event' => $event]) as $child) {
                $this->em->remove($child);
            }
        }
        $this->em->remove($event);
        $this->em->flush();
        $this->audit->log($actor, AuditAction::ContentDeleted, 'event', $id, $title);
        foreach ($users as $user) {
            $this->notifications->notify($user, NotificationCategory::Activity, 'Événement supprimé', sprintf('« %s » a été supprimé par l’administration.', $title));
        }
    }

    public function updateRegistration(EventRegistration $registration): void
    {
        $this->em->flush();
        $this->recount($registration->getEvent());
        $this->notifications->notify($registration->getUser(), NotificationCategory::Activity, 'Inscription mise à jour', sprintf('Votre inscription à « %s » a été modifiée : %s.', $registration->getEvent()->getTitle(), EventRegistration::GOING === $registration->getStatus() ? 'participation confirmée' : 'liste d’attente'));
    }

    public function deleteRegistration(EventRegistration $registration): void
    {
        $event = $registration->getEvent();
        $wasGoing = EventRegistration::GOING === $registration->getStatus();
        $this->em->remove($registration);
        $this->em->flush();
        $count = $this->registrations->countGoing($event);
        if ($wasGoing && (null === $event->getCapacity() || $count < $event->getCapacity())) {
            $next = $this->registrations->findOneBy(['event' => $event, 'status' => EventRegistration::WAITLIST], ['createdAt' => 'ASC']);
            if (null !== $next) {
                $next->setStatus(EventRegistration::GOING);
                $this->notifications->notify($next->getUser(), NotificationCategory::Activity, 'Une place s’est libérée', sprintf('Vous participez désormais à « %s ».', $event->getTitle()));
            }
        }
        $this->recount($event);
        $this->notifications->notify($registration->getUser(), NotificationCategory::Activity, 'Inscription retirée', sprintf('Votre inscription à « %s » a été retirée par l’administration.', $event->getTitle()));
    }
}
