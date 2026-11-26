<?php

declare(strict_types=1);

namespace App\Admin\Service;

use App\Admin\Enum\AuditAction;
use App\Favorite\Entity\Favorite;
use App\Notification\Enum\NotificationCategory;
use App\Notification\Service\NotificationService;
use App\PrivateActivity\Entity\MemberReview;
use App\PrivateActivity\Entity\Participation;
use App\PrivateActivity\Entity\Photo;
use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Enum\ParticipationStatus;
use App\PrivateActivity\Enum\PrivateActivityStatus;
use App\PrivateActivity\Repository\AlbumRepository;
use App\PrivateActivity\Service\AlbumService;
use App\PrivateActivity\Service\PrivateActivityService;
use App\Shared\Service\PublicImageStorage;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gestion des activités gratuites entre membres depuis le back-office (26/11).
 *
 * Pendant d'EventAdminService pour les PrivateActivity. Les règles métier
 * (capacité, liste d'attente, notifications) restent dans
 * PrivateActivityService : ce service ne fait que traduire une saisie
 * EasyAdmin en appels à ces règles, et historiser ce qui doit l'être.
 */
final class PrivateActivityAdminService
{
    /** Modifications qui changent la sortie pour ceux qui y vont. */
    private const PRACTICAL_FIELDS = ['scheduledAt', 'endsAt', 'city', 'location', 'meetingPoint'];

    /** Modifications de contenu signalées à l'organisateur. */
    private const CONTENT_FIELDS = ['title', 'description', 'category', 'visibility', 'participationMode', 'level', 'minParticipants', 'maxParticipants', 'postalCode', 'toBring', 'coverImage', 'showExactAddress'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PrivateActivityService $activities,
        private readonly AlbumRepository $albums,
        private readonly AlbumService $albumService,
        private readonly PublicImageStorage $images,
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * Enregistre une création ou une modification faite dans le formulaire.
     *
     * Le formulaire a déjà posé le nouveau statut sur l'entité. On le remet
     * de côté le temps d'enregistrer le reste, puis on applique le changement
     * par la règle métier correspondante, qui prévient les bonnes personnes.
     */
    public function save(PrivateActivity $activity): void
    {
        $before = $this->em->getUnitOfWork()->getOriginalEntityData($activity);
        $isNew = [] === $before;
        $target = $activity->getStatus();
        $previous = $before['status'] ?? null;

        if ($previous instanceof PrivateActivityStatus && $previous !== $target) {
            $activity->setStatus($previous);
        }
        $this->em->persist($activity);
        $this->em->flush();

        if (!$isNew && $previous !== $target) {
            match (true) {
                PrivateActivityStatus::Suspended === $target => $this->activities->suspend($activity),
                PrivateActivityStatus::Cancelled === $target => $this->activities->cancelByAdministration($activity),
                PrivateActivityStatus::Suspended === $previous && \in_array($target, [PrivateActivityStatus::Open, PrivateActivityStatus::Full], true) => $this->activities->reinstate($activity),
                default => $activity->setStatus($target),
            };
        }

        // « Ouverte » ou « complète » ne se choisissent pas : elles découlent
        // des places restantes. Une capacité relevée profite aussi à la liste
        // d'attente.
        if (\in_array($activity->getStatus(), [PrivateActivityStatus::Open, PrivateActivityStatus::Full], true)) {
            $activity->setStatus(PrivateActivityStatus::Open);
            $this->activities->syncCapacity($activity);
        } else {
            $this->em->flush();
        }

        if ($isNew || $previous !== $target) {
            return;
        }

        $changed = static fn (array $fields): bool => [] !== array_filter(
            $fields,
            static fn (string $field): bool => self::differs($before[$field] ?? null, self::read($activity, $field)),
        );
        if ($changed([...self::CONTENT_FIELDS, ...self::PRACTICAL_FIELDS]) && null !== $organizer = $activity->getOrganizer()) {
            $this->notifications->notify($organizer, NotificationCategory::Activity, 'Activité modifiée par l’administration', \sprintf('Les informations de « %s » ont été modifiées par l’équipe TrouveMoi.', $activity->getTitle()));
        }
        if ($changed(self::PRACTICAL_FIELDS)) {
            foreach ($activity->getParticipations() as $participation) {
                if ($participation->isAccepted() && null !== $participant = $participation->getParticipant()) {
                    $this->notifications->notify($participant, NotificationCategory::Activity, 'Activité mise à jour', \sprintf('La date ou le lieu de « %s » a changé : consultez la fiche de l’activité.', $activity->getTitle()));
                }
            }
        }
    }

    /**
     * Suppression définitive : participations, invitations, album (photos et
     * fichiers), avis et favoris partent avec l'activité. Historisée.
     */
    public function delete(PrivateActivity $activity, ?User $actor): void
    {
        $id = $activity->getId();
        $title = $activity->getTitle();
        $cover = $activity->getCoverImage();

        $users = [];
        if (null !== $organizer = $activity->getOrganizer()) {
            $users[(string) $organizer->getId()] = $organizer;
        }
        foreach ($activity->getParticipations() as $participation) {
            if (\in_array($participation->getStatus(), [ParticipationStatus::Accepted, ParticipationStatus::Pending, ParticipationStatus::WaitingList], true)
                && null !== $participant = $participation->getParticipant()) {
                $users[(string) $participant->getId()] = $participant;
            }
        }

        // Même précaution qu'EventAdminService::delete : retirer d'abord les
        // objets qui pointent vers l'activité, sinon Doctrine garderait des
        // références vers une ligne supprimée par la cascade SQL.
        if (null !== $album = $this->albums->findOneByActivity($activity)) {
            foreach ($album->getPhotos()->toArray() as $photo) {
                $this->albumService->removePhoto($photo);
            }
            $this->em->remove($album);
        }
        foreach ([MemberReview::class, Favorite::class] as $class) {
            foreach ($this->em->getRepository($class)->findBy(['privateActivity' => $activity]) as $child) {
                $this->em->remove($child);
            }
        }
        $this->em->remove($activity);
        $this->em->flush();
        $this->images->delete($cover);

        $this->audit->log($actor, AuditAction::ContentDeleted, 'private_activity', $id, $title);
        foreach ($users as $user) {
            $this->notifications->notify($user, NotificationCategory::Activity, 'Activité supprimée', \sprintf('« %s » a été supprimée par l’équipe TrouveMoi.', $title));
        }
    }

    /**
     * Statut d'une participation changé dans le formulaire : on rétablit
     * l'ancien, puis la règle métier applique le nouveau (liste d'attente,
     * statut complet, notifications).
     *
     * @throws \InvalidArgumentException si l'activité est complète
     */
    public function updateParticipation(Participation $participation): void
    {
        $previous = $this->em->getUnitOfWork()->getOriginalEntityData($participation)['status'] ?? null;
        $target = $participation->getStatus();
        if ($previous instanceof ParticipationStatus) {
            $participation->setStatus($previous);
        }
        $this->activities->moderateParticipation($participation, $target);
    }

    /**
     * Retire la ligne : le membre pourra de nouveau demander à participer.
     * Une place confirmée libérée profite à la liste d'attente.
     */
    public function deleteParticipation(Participation $participation): void
    {
        $this->activities->moderateParticipation($participation, ParticipationStatus::Cancelled);
        $participation->getPrivateActivity()?->getParticipations()->removeElement($participation);
        $this->em->remove($participation);
        $this->em->flush();
    }

    public function deletePhoto(Photo $photo, ?User $actor): void
    {
        $author = $photo->getAuthor();
        $title = $photo->getAlbum()?->getPrivateActivity()?->getTitle() ?? '';
        $id = $photo->getId();
        $this->albumService->removePhoto($photo);
        $this->audit->log($actor, AuditAction::ContentDeleted, 'album_photo', $id, $title);
        if (null !== $author) {
            $this->notifications->notify($author, NotificationCategory::Activity, 'Photo retirée', \sprintf('Une de vos photos de l’album « %s » a été retirée par l’équipe TrouveMoi.', $title));
        }
    }

    public function deleteReview(MemberReview $review, ?User $actor): void
    {
        $author = $review->getAuthor();
        $title = $review->getPrivateActivity()?->getTitle() ?? '';
        $id = $review->getId();
        $this->em->remove($review);
        $this->em->flush();
        $this->audit->log($actor, AuditAction::ContentDeleted, 'member_review', $id, $title);
        if (null !== $author) {
            $this->notifications->notify($author, NotificationCategory::Activity, 'Avis retiré', \sprintf('Votre avis sur « %s » a été retiré par l’équipe TrouveMoi.', $title));
        }
    }

    /** Les dates se comparent par valeur, tout le reste (entités, énumérations) par identité. */
    private static function differs(mixed $old, mixed $new): bool
    {
        if ($old instanceof \DateTimeInterface && $new instanceof \DateTimeInterface) {
            return $old->getTimestamp() !== $new->getTimestamp();
        }

        return $old !== $new;
    }

    /** Valeur comparable à celle que garde l'UnitOfWork (getOriginalEntityData). */
    private static function read(PrivateActivity $activity, string $field): mixed
    {
        return match ($field) {
            'title' => $activity->getTitle(),
            'description' => $activity->getDescription(),
            'category' => $activity->getCategory(),
            'visibility' => $activity->getVisibility(),
            'participationMode' => $activity->getParticipationMode(),
            'level' => $activity->getLevel(),
            'minParticipants' => $activity->getMinParticipants(),
            'maxParticipants' => $activity->getMaxParticipants(),
            'postalCode' => $activity->getPostalCode(),
            'toBring' => $activity->getToBring(),
            'coverImage' => $activity->getCoverImage(),
            'showExactAddress' => $activity->showsExactAddress(),
            'scheduledAt' => $activity->getScheduledAt(),
            'endsAt' => $activity->getEndsAt(),
            'city' => $activity->getCity(),
            'location' => $activity->getLocation(),
            'meetingPoint' => $activity->getMeetingPoint(),
            default => throw new \LogicException($field),
        };
    }
}
