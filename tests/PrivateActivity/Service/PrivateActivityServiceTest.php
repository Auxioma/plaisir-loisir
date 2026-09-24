<?php

declare(strict_types=1);

namespace App\Tests\PrivateActivity\Service;

use App\Catalog\Entity\Category;
use App\Notification\Entity\Notification;
use App\Notification\Enum\NotificationCategory;
use App\Notification\Repository\NotificationRepository;
use App\Notification\Service\NotificationService;
use App\PrivateActivity\Entity\Invitation;
use App\PrivateActivity\Entity\Participation;
use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Enum\InvitationStatus;
use App\PrivateActivity\Enum\ParticipationMode;
use App\PrivateActivity\Enum\ParticipationStatus;
use App\PrivateActivity\Enum\PrivateActivityStatus;
use App\PrivateActivity\Repository\InvitationRepository;
use App\PrivateActivity\Repository\ParticipationRepository;
use App\PrivateActivity\Service\PrivateActivityService;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class PrivateActivityServiceTest extends TestCase
{
    public function testCreatePersistsActivity(): void
    {
        $organizer = new User();
        $category = new Category();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(PrivateActivity::class));
        $em->expects(self::once())->method('flush');

        $activity = $this->service($em)->create($organizer, 'Pique-nique au parc', $category);

        self::assertSame($organizer, $activity->getOrganizer());
        self::assertSame('Pique-nique au parc', $activity->getTitle());
        self::assertSame($category, $activity->getCategory());
    }

    public function testInviteAddsInvitationWhenOrganizer(): void
    {
        $organizer = new User();
        $activity = (new PrivateActivity())->setOrganizer($organizer);

        $invitations = $this->createStub(InvitationRepository::class);
        $invitations->method('findOneByActivityAndInvitee')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(Invitation::class));
        $em->expects(self::once())->method('flush');

        $invitee = new User();
        $invitation = $this->service($em, $invitations)->invite($activity, $organizer, $invitee);

        self::assertSame($invitee, $invitation->getInvitee());
        self::assertCount(1, $activity->getInvitations());
    }

    public function testInviteRejectsNonOrganizer(): void
    {
        $activity = (new PrivateActivity())->setOrganizer(new User());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);

        $this->service($em)->invite($activity, new User(), new User());
    }

    public function testInviteRejectsDuplicate(): void
    {
        $organizer = new User();
        $activity = (new PrivateActivity())->setOrganizer($organizer);

        $invitations = $this->createStub(InvitationRepository::class);
        $invitations->method('findOneByActivityAndInvitee')->willReturn(new Invitation());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);

        $this->service($em, $invitations)->invite($activity, $organizer, new User());
    }

    public function testRespondAcceptsWhenInvitee(): void
    {
        $invitee = new User();
        $invitation = (new Invitation())->setInvitee($invitee);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $this->service($em)->respond($invitation, $invitee, true);

        self::assertSame(InvitationStatus::Accepted, $invitation->getStatus());
    }

    public function testRespondRejectsNonInvitee(): void
    {
        $invitation = (new Invitation())->setInvitee(new User());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $this->expectException(\InvalidArgumentException::class);

        $this->service($em)->respond($invitation, new User(), true);
    }

    /**
     * §13.1 du CDC : en mode automatique, une place est attribuée
     * immédiatement s'il en reste.
     */
    public function testAutomaticModeAcceptsImmediatelyWhenRoomIsLeft(): void
    {
        $activity = (new PrivateActivity())
            ->setOrganizer(new User())
            ->setTitle('Atelier poterie')
            ->setParticipationMode(ParticipationMode::Automatic)
            ->setMaxParticipants(2);

        $participations = $this->createStub(ParticipationRepository::class);
        $participations->method('findOneByActivityAndParticipant')->willReturn(null);

        // Stub, pas mock : l'acceptation immédiate déclenche aussi deux
        // notifications (organisateur + participant), chacune avec son
        // propre flush (voir le commentaire de service() ci-dessous) — ce
        // test porte sur le statut, pas sur le compte d'appels.
        $em = $this->createStub(EntityManagerInterface::class);

        $participation = $this->service($em, participations: $participations)
            ->requestParticipation($activity, $this->namedUser());

        self::assertSame(ParticipationStatus::Accepted, $participation->getStatus());
        self::assertSame(PrivateActivityStatus::Open, $activity->getStatus());
    }

    /**
     * §13.3, §13.4 : la capacité maximale ne doit jamais être dépassée ; une
     * demande de plus va en liste d'attente, pas en refus silencieux.
     *
     * La capacité déjà prise vient de la COLLECTION de l'activité (deux
     * participations Accepted déjà ajoutées), pas d'une requête stubbée :
     * c'est exactement ce que hasRoom() lit réellement (voir son
     * commentaire), et un stub aurait masqué une régression sur ce point
     * précis.
     */
    public function testAutomaticModeQueuesOnceCapacityIsReached(): void
    {
        $activity = (new PrivateActivity())
            ->setOrganizer(new User())
            ->setTitle('Atelier poterie')
            ->setParticipationMode(ParticipationMode::Automatic)
            ->setMaxParticipants(2);
        $activity->addParticipation((new Participation())->setParticipant(new User())->setStatus(ParticipationStatus::Accepted));
        $activity->addParticipation((new Participation())->setParticipant(new User())->setStatus(ParticipationStatus::Accepted));

        $participations = $this->createStub(ParticipationRepository::class);
        $participations->method('findOneByActivityAndParticipant')->willReturn(null);

        $em = $this->createStub(EntityManagerInterface::class);

        $participation = $this->service($em, participations: $participations)
            ->requestParticipation($activity, $this->namedUser());

        self::assertSame(ParticipationStatus::WaitingList, $participation->getStatus());
        self::assertSame(PrivateActivityStatus::Full, $activity->getStatus(), 'L\'activité doit passer FULL une fois la capacité atteinte.');
    }

    /**
     * §13.1 : en mode validation, la demande reste en attente même s'il
     * reste de la place — c'est l'organisateur qui décide.
     */
    public function testValidationModeAlwaysStartsPending(): void
    {
        $activity = (new PrivateActivity())
            ->setOrganizer(new User())
            ->setTitle('Sortie escalade')
            ->setParticipationMode(ParticipationMode::Validation)
            ->setMaxParticipants(10);

        $participations = $this->createStub(ParticipationRepository::class);
        $participations->method('findOneByActivityAndParticipant')->willReturn(null);

        $em = $this->createStub(EntityManagerInterface::class);

        $participation = $this->service($em, participations: $participations)
            ->requestParticipation($activity, $this->namedUser());

        self::assertSame(ParticipationStatus::Pending, $participation->getStatus());
    }

    public function testRequestParticipationRejectsTheOrganizerThemself(): void
    {
        $organizer = new User();
        $activity = (new PrivateActivity())->setOrganizer($organizer);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);

        $this->service($em)->requestParticipation($activity, $organizer);
    }

    public function testRequestParticipationRejectsACancelledActivity(): void
    {
        $activity = (new PrivateActivity())->setOrganizer(new User())->setStatus(PrivateActivityStatus::Cancelled);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);

        $this->service($em)->requestParticipation($activity, new User());
    }

    public function testDecideRejectsAcceptanceWhenCapacityIsAlreadyFull(): void
    {
        $organizer = new User();
        $activity = (new PrivateActivity())->setOrganizer($organizer)->setMaxParticipants(1);
        // Une place déjà prise...
        $activity->addParticipation((new Participation())->setParticipant(new User())->setStatus(ParticipationStatus::Accepted));
        // ...et une seconde demande, encore en attente, qu'on tente d'accepter.
        $participation = (new Participation())->setParticipant(new User())->setStatus(ParticipationStatus::Pending);
        $activity->addParticipation($participation);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $this->expectException(\InvalidArgumentException::class);

        $this->service($em)->decide($participation, $organizer, true);
    }

    /**
     * §13.4 : une annulation libère une place, aussitôt reprise par la
     * personne la plus ancienne de la liste d'attente.
     */
    public function testCancellingAnAcceptedParticipationPromotesTheOldestWaiting(): void
    {
        $organizer = new User();
        $activity = (new PrivateActivity())
            ->setOrganizer($organizer)
            ->setTitle('Sortie vélo')
            ->setMaxParticipants(1)
            ->setStatus(PrivateActivityStatus::Full);

        $leaving = (new Participation())->setParticipant($this->namedUser())->setStatus(ParticipationStatus::Accepted);
        $activity->addParticipation($leaving);

        $waiting = (new Participation())->setParticipant(new User())->setStatus(ParticipationStatus::WaitingList);
        $activity->addParticipation($waiting);

        $participations = $this->createStub(ParticipationRepository::class);
        $participations->method('findOldestWaiting')->willReturn([$waiting]);

        $em = $this->createStub(EntityManagerInterface::class);

        $this->service($em, participations: $participations)
            ->cancelParticipation($leaving, $leaving->getParticipant());

        self::assertSame(ParticipationStatus::Cancelled, $leaving->getStatus());
        self::assertSame(ParticipationStatus::Accepted, $waiting->getStatus(), 'La personne en liste d\'attente n\'a pas été promue.');
    }

    /**
     * NotificationService est une classe finale (§NotificationServiceTest) :
     * on l'instancie réellement, avec le même EntityManager que le service
     * testé, plutôt que de tenter de la doubler — même règle que
     * MessagingServiceTest.
     */
    /**
     * §15 du CDC (« Activités ») : nouvelle demande de participation à
     * notifier à l'organisateur.
     */
    public function testRequestParticipationNotifiesTheOrganizer(): void
    {
        $organizer = new User();
        $activity = (new PrivateActivity())
            ->setOrganizer($organizer)
            ->setTitle('Randonnée au Mont-Blanc')
            ->setParticipationMode(ParticipationMode::Validation);

        $participations = $this->createStub(ParticipationRepository::class);
        $participations->method('findOneByActivityAndParticipant')->willReturn(null);

        $notified = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity) use (&$notified): void {
            if ($entity instanceof Notification) {
                $notified[] = $entity;
            }
        });

        $this->service($em, participations: $participations)
            ->requestParticipation($activity, $this->namedUser());

        self::assertCount(1, $notified);
        self::assertSame($organizer, $notified[0]->getRecipient());
        self::assertSame(NotificationCategory::Activity, $notified[0]->getCategory());
    }

    public function testDecideNotifiesTheParticipantOfTheOutcome(): void
    {
        $organizer = new User();
        $participant = new User();
        $activity = (new PrivateActivity())->setOrganizer($organizer)->setTitle('Escalade')->setMaxParticipants(5);
        $participation = (new Participation())->setParticipant($participant)->setStatus(ParticipationStatus::Pending);
        $activity->addParticipation($participation);

        $notified = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity) use (&$notified): void {
            if ($entity instanceof Notification) {
                $notified[] = $entity;
            }
        });

        $this->service($em)->decide($participation, $organizer, false);

        self::assertCount(1, $notified);
        self::assertSame($participant, $notified[0]->getRecipient());
        self::assertSame('Participation refusée', $notified[0]->getTitle());
    }

    /**
     * §13.4 + §15 : la personne promue depuis la liste d'attente doit en
     * être notifiée, et pas seulement voir son statut changer en silence.
     */
    public function testCancellingNotifiesTheOrganizerAndThePromotedParticipant(): void
    {
        $organizer = new User();
        $activity = (new PrivateActivity())
            ->setOrganizer($organizer)
            ->setTitle('Sortie kayak')
            ->setMaxParticipants(1)
            ->setStatus(PrivateActivityStatus::Full);

        $leaving = (new Participation())->setParticipant($this->namedUser())->setStatus(ParticipationStatus::Accepted);
        $activity->addParticipation($leaving);

        $promoted = (new Participation())->setParticipant(new User())->setStatus(ParticipationStatus::WaitingList);
        $activity->addParticipation($promoted);

        $participations = $this->createStub(ParticipationRepository::class);
        $participations->method('findOldestWaiting')->willReturn([$promoted]);

        $notified = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity) use (&$notified): void {
            if ($entity instanceof Notification) {
                $notified[] = $entity;
            }
        });

        $this->service($em, participations: $participations)
            ->cancelParticipation($leaving, $leaving->getParticipant());

        self::assertCount(2, $notified);
        $recipients = array_map(static fn (Notification $n): ?User => $n->getRecipient(), $notified);
        self::assertContains($organizer, $recipients);
        self::assertContains($promoted->getParticipant(), $recipients);
    }

    public function testCancelNotifiesEveryActiveParticipant(): void
    {
        $organizer = new User();
        $activity = (new PrivateActivity())->setOrganizer($organizer)->setTitle('Pique-nique');

        $accepted = (new Participation())->setParticipant(new User())->setStatus(ParticipationStatus::Accepted);
        $refused = (new Participation())->setParticipant(new User())->setStatus(ParticipationStatus::Refused);
        $activity->addParticipation($accepted);
        $activity->addParticipation($refused);

        $notified = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity) use (&$notified): void {
            if ($entity instanceof Notification) {
                $notified[] = $entity;
            }
        });

        $this->service($em)->cancel($activity, $organizer);

        self::assertCount(1, $notified, 'Seuls les participants actifs (pas les refusés) doivent être notifiés.');
        self::assertSame($accepted->getParticipant(), $notified[0]->getRecipient());
    }

    private function service(
        EntityManagerInterface $em,
        ?InvitationRepository $invitations = null,
        ?ParticipationRepository $participations = null,
    ): PrivateActivityService {
        return new PrivateActivityService(
            $em,
            $invitations ?? $this->createStub(InvitationRepository::class),
            $participations ?? $this->createStub(ParticipationRepository::class),
            new NotificationService($em, $this->createStub(NotificationRepository::class)),
        );
    }

    /**
     * `User::$firstName`/`$lastName` sont des propriétés typées sans valeur
     * par défaut : un `new User()` nu plante dès que le code de production
     * lit ces champs (message de notification à l'organisateur/au
     * participant). Ce participant-là a un nom ; les autres `new User()` du
     * fichier ne servent que d'identité (destinataire, comparaison), jamais
     * de nom affiché.
     */
    private function namedUser(): User
    {
        return (new User())->setFirstName('Alix')->setLastName('Test');
    }
}
