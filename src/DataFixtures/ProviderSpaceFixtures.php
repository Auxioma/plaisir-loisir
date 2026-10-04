<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Booking\Entity\Booking;
use App\Booking\Entity\BookingItem;
use App\Booking\Enum\BookingStatus;
use App\Catalog\Entity\Promotion;
use App\Catalog\Entity\Service;
use App\Catalog\Enum\PromotionKind;
use App\Messaging\Entity\Conversation;
use App\Messaging\Entity\Message;
use App\Payment\Entity\Payment;
use App\Payment\Enum\PaymentStatus;
use App\Provider\Entity\ClientNote;
use App\Provider\Entity\ProviderProfile;
use App\Review\Entity\Review;
use App\Stats\Entity\PageView;
use App\Support\Entity\SupportTicket;
use App\Support\Entity\SupportTicketMessage;
use App\Support\Enum\TicketCategory;
use App\Support\Enum\TicketStatus;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Données de démonstration de l'espace professionnel (02/10) pour le compte
 * annonceur@trouvemoi.test : clients, réservations sur 90 jours (passées et
 * à venir), paiements, avis, conversations, consultations, offres, tickets.
 *
 * Tirage pseudo-aléatoire à graine fixe : les chiffres sont stables d'un
 * chargement à l'autre (tests et démos reproductibles). Dates relatives à
 * « aujourd'hui » pour que les tableaux de bord aient toujours de quoi
 * afficher.
 */
final class ProviderSpaceFixtures extends Fixture implements DependentFixtureInterface
{
    private const CLIENTS = [
        ['Julie', 'Martin', '06 12 34 56 78', 'Lyon'],
        ['Marc', 'Dubois', '06 23 45 67 89', 'Paris'],
        ['Sophie', 'Leroy', '06 34 56 78 90', 'Grenoble'],
        ['David', 'Petit', '06 45 67 89 01', 'Marseille'],
        ['Claire', 'Bernard', '06 56 78 90 12', 'Bordeaux'],
        ['Thomas', 'Moreau', '06 67 89 01 23', 'Annecy'],
        ['Emma', 'Robert', '06 78 90 12 34', 'Nantes'],
        ['Lucas', 'Fontaine', '06 89 01 23 45', 'Lille'],
    ];

    private const COMMENTS = [
        5 => ['Une expérience incroyable ! Le guide était passionné et attentionné.', 'Magique ! Un moment hors du temps, je recommande à 100 %.', 'Excellente organisation, beaucoup d’apprentissages. Merci !'],
        4 => ['Très bon moment, encadrement sympathique et pédagogue.', 'Super activité. Seul petit bémol : un peu d’attente au départ.'],
        3 => ['Correct, mais le groupe était un peu grand à mon goût.'],
        2 => ['Déçu par le point de rendez-vous difficile à trouver.'],
    ];

    /** @var list<array{0: string, 1: object, 2: \DateTimeImmutable}> */
    private array $backdates = [];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function getDependencies(): array
    {
        return [CatalogFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        mt_srand(20261002);

        $owner = $manager->getRepository(User::class)->findOneBy(['email' => 'annonceur@trouvemoi.test']);
        $provider = null !== $owner ? $manager->getRepository(ProviderProfile::class)->findOneBy(['user' => $owner]) : null;
        if (!$provider instanceof ProviderProfile) {
            return;
        }

        $provider->setCity('Lyon')
            ->setAddress('15 rue des Monts d’Or, 69000 Lyon, France')
            ->setInterventionZone('Alpes, Auvergne-Rhône-Alpes')
            ->setAvailabilityLabel('Lun - Dim : 7h - 20h')
            ->setBio('Passionnée de plein air depuis toujours, j’accompagne petits et grands à la découverte de nos plus beaux paysages, en toute sécurité.')
            ->setWebsiteUrl('https://www.camille-aventures.fr')
            ->setPayoutIban('FR7630006000011234567890189');
        $provider->getUser()?->setPhone('06 12 34 56 78');

        /** @var list<Service> $services */
        $services = $manager->getRepository(Service::class)->findBy(['provider' => $provider], ['position' => 'ASC']);
        if ([] === $services) {
            return;
        }

        $clients = [];
        foreach (self::CLIENTS as $i => [$first, $last, $phone]) {
            $user = (new User())->setEmail(strtolower($first.'.'.$last).'@client.trouvemoi.test')->setFirstName($first)->setLastName($last)->setPhone($phone)->setStatus(UserStatus::Active);
            $user->setPassword($this->passwordHasher->hashPassword($user, 'Password123'));
            $manager->persist($user);
            $this->backdate($user, new \DateTimeImmutable(sprintf('-%d days', 120 + 20 * $i)));
            $clients[] = $user;
        }

        $today = new \DateTimeImmutable('today');
        $bookings = [];
        for ($n = 0; $n < 46; ++$n) {
            $service = $services[mt_rand(0, min(5, \count($services) - 1))];
            $client = $clients[mt_rand(0, \count($clients) - 1)];
            $offset = mt_rand(-75, 25);
            $startsAt = $today->modify(sprintf('%+d days', $offset))->setTime([9, 10, 14, 17, 18][mt_rand(0, 4)], [0, 30][mt_rand(0, 1)]);
            $createdAt = $startsAt->modify(sprintf('-%d days', mt_rand(2, 12)))->setTime(mt_rand(8, 21), mt_rand(0, 59));
            if ($createdAt > new \DateTimeImmutable()) {
                $createdAt = new \DateTimeImmutable('-2 hours');
            }
            $participants = mt_rand(1, 3);
            $price = (float) ($service->getPackages()->first() ?: null)?->getPrice();

            $status = match (true) {
                0 === $n % 13 => BookingStatus::Cancelled,
                $startsAt < $today => BookingStatus::Completed,
                0 === $n % 4 => BookingStatus::Pending,
                default => BookingStatus::Confirmed,
            };

            $booking = (new Booking())->setClient($client)->setService($service)->setStartsAt($startsAt)->setParticipants($participants)->setStatus($status)
                ->setCurrency('EUR')->setTotalPrice(number_format($price * $participants, 2, '.', ''));
            $booking->addItem((new BookingItem())->setLabel('Tarif unique')->setUnitPrice(number_format($price, 2, '.', ''))->setQuantity($participants)->setCurrency('EUR'));
            $manager->persist($booking);
            $this->backdate($booking, $createdAt);
            $bookings[] = $booking;

            if (BookingStatus::Cancelled !== $status) {
                $payment = (new Payment())->setBooking($booking)->setAmount($booking->getTotalPrice())->setCurrency('EUR')
                    ->setStatus(BookingStatus::Pending === $status ? PaymentStatus::Pending : PaymentStatus::Paid)
                    ->setMethod(['card', 'card', 'card', 'paypal', 'transfer'][mt_rand(0, 4)])
                    ->setReference(sprintf('demo_%d', $n));
                $manager->persist($payment);
                $this->backdate($payment, $createdAt->modify('+5 minutes'));
            }

            if (BookingStatus::Completed === $status && 0 !== $n % 3) {
                $rating = [5, 5, 5, 4, 4, 5, 3, 4, 5, 2][mt_rand(0, 9)];
                $comments = self::COMMENTS[$rating];
                $review = (new Review())->setAuthor($client)->setProvider($provider)->setBooking($booking)->setService($service)->setRating($rating)
                    ->setComment($comments[mt_rand(0, \count($comments) - 1)]);
                if (0 === $n % 2) {
                    $review->reply('Merci beaucoup pour votre retour, au plaisir de vous revoir !');
                }
                $manager->persist($review);
                $this->backdate($review, $startsAt->modify('+1 day'));
            }
        }

        // Conversations.
        $threads = [
            [0, ['Bonjour, est-ce que l’activité est toujours disponible ce week-end ?', 'Bonjour Julie, oui l’activité est bien disponible samedi à 09:00.', 'Parfait ! Y a-t-il une limite d’âge pour participer ?', 'L’activité est accessible à partir de 8 ans, accompagné d’un adulte.', 'Merci beaucoup pour votre réponse rapide 😊']],
            [1, ['Merci pour ces informations, je vais réserver.']],
            [2, ['À quelle heure démarre la randonnée ?', 'Le départ est à 9h depuis le parking du col.']],
            [3, ['Super, merci beaucoup !']],
            [4, ['Peut-on venir avec des enfants ?']],
            [5, ['Je dois modifier ma réservation.', 'Bien sûr, quelle date vous conviendrait ?']],
        ];
        foreach ($threads as $t => [$clientIndex, $lines]) {
            $conversation = (new Conversation())->setClient($clients[$clientIndex])->setProvider($provider)->setService($services[$t % \count($services)]);
            $manager->persist($conversation);
            $this->backdate($conversation, new \DateTimeImmutable(sprintf('-%d days', $t * 2 + 1)));
            foreach ($lines as $l => $body) {
                $fromClient = 0 === $l % 2;
                $message = (new Message())->setAuthor($fromClient ? $clients[$clientIndex] : $provider->getUser())->setBody($body);
                $conversation->addMessage($message);
                if (!$fromClient || $t > 1) {
                    $message->markAsRead();
                }
                $manager->persist($message);
                $this->backdate($message, new \DateTimeImmutable(sprintf('-%d days -%d minutes', $t * 2, 60 - $l * 2)));
            }
        }

        $note = (new ClientNote($provider, $clients[0]))->setBody('Cliente très sympathique, organisée et ponctuelle.');
        $manager->persist($note);

        // Consultations des 75 derniers jours.
        $sources = ['search', 'search', 'search', 'search', 'direct', 'direct', 'social', 'partner', 'other'];
        for ($d = 75; $d >= 0; --$d) {
            $day = $today->modify(sprintf('-%d days', $d));
            for ($v = 0, $max = mt_rand(6, 16); $v < $max; ++$v) {
                $at = $day->setTime(mt_rand(7, 22), mt_rand(0, 59));
                $manager->persist(new PageView($provider, null, PageView::KIND_PROFILE, $sources[mt_rand(0, 8)], $at));
                $manager->persist(new PageView($provider, $services[mt_rand(0, min(5, \count($services) - 1))], PageView::KIND_ACTIVITY, $sources[mt_rand(0, 8)], $at->modify('+2 minutes')));
            }
        }

        // Offres.
        foreach ([
            ['Réduction 20%', 'Sur toutes les randonnées guidées', PromotionKind::Reduction, 20, 0, -10, 20, 856, 112],
            ['2 activités achetées', 'La 3e offerte', PromotionKind::TwoForOne, null, 1, -20, 10, 642, 89],
            ['Réduction 15%', 'Réservation anticipée', PromotionKind::Reduction, 15, 2, -5, 25, 423, 56],
            ['Réduction 10%', 'Pour les nouveaux clients', PromotionKind::Reduction, 10, 3, 5, 35, 0, 0],
            ['Réduction 25%', 'Offre de printemps', PromotionKind::Reduction, 25, 4, -60, -30, 529, 78],
            ['Offre week-end', 'Accès coupe-file', PromotionKind::Special, null, 5, -40, -35, 312, 45],
        ] as [$title, $subtitle, $kind, $discount, $serviceIndex, $start, $end, $views, $clicks]) {
            $promo = (new Promotion())->setProvider($provider)->setService($services[$serviceIndex % \count($services)])->setTitle($title)->setSubtitle($subtitle)
                ->setKind($kind)->setDiscountPercent($discount)->setStartsAt($today->modify(sprintf('%+d days', $start)))->setEndsAt($today->modify(sprintf('%+d days', $end))->setTime(23, 59, 59))
                ->setViewsCount($views)->setClicksCount($clicks);
            $manager->persist($promo);
        }

        // Tickets support.
        foreach ([
            [4587, 'Problème de paiement', TicketCategory::Payments, TicketStatus::Open, false],
            [4501, 'Modifier une réservation', TicketCategory::Bookings, TicketStatus::InProgress, true],
            [4432, 'Question sur les commissions', TicketCategory::Billing, TicketStatus::Resolved, true],
        ] as [$number, $subject, $category, $status, $answered]) {
            $ticket = (new SupportTicket())->setNumber($number)->setAuthor($provider->getUser())->setSubject($subject)->setCategory($category)->setStatus($status);
            $ticket->addMessage((new SupportTicketMessage())->setAuthor($provider->getUser())->setBody('Bonjour, pouvez-vous m’aider à ce sujet ? Merci.'));
            if ($answered) {
                $ticket->addMessage((new SupportTicketMessage())->setFromStaff(true)->setBody('Bonjour, merci pour votre message : nous regardons cela et revenons vers vous rapidement.'));
            }
            $manager->persist($ticket);
        }

        $manager->flush();

        // Les horodatages sont posés à la persistance (TimestampableTrait) :
        // on antidate ensuite pour étaler l'historique.
        $connection = $manager instanceof EntityManagerInterface ? $manager->getConnection() : null;
        if (null !== $connection) {
            foreach ($this->backdates as [$table, $entity, $date]) {
                $connection->executeStatement(sprintf('UPDATE %s SET created_at = :d, updated_at = :d WHERE id = :id', $table), ['d' => $date->format('Y-m-d H:i:sP'), 'id' => $entity->getId()?->toRfc4122()]);
            }
        }
    }

    private function backdate(object $entity, \DateTimeImmutable $date): void
    {
        $table = match (true) {
            $entity instanceof User => '"user"',
            $entity instanceof Booking => 'booking',
            $entity instanceof Payment => 'payment',
            $entity instanceof Review => 'review',
            $entity instanceof Conversation => 'conversation',
            $entity instanceof Message => 'message',
            default => null,
        };
        if (null !== $table) {
            $this->backdates[] = [$table, $entity, $date];
        }
    }
}
