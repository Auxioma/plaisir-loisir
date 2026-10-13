<?php

declare(strict_types=1);

namespace App\Notification\Command;

use App\Notification\Entity\Notification;
use App\Notification\Enum\NotificationCategory;
use App\Notification\Service\NotificationService;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Notifications d'anniversaire (13/10, retour client n° 33) : chaque matin,
 * les membres dont c'est l'anniversaire reçoivent un message, une seule fois
 * par an. À lancer par le planificateur du serveur :
 *   0 8 * * * php bin/console app:notifications:anniversaires.
 */
#[AsCommand(name: 'app:notifications:anniversaires', description: 'Souhaite leur anniversaire aux membres nés ce jour.')]
final class BirthdayNotificationsCommand
{
    public const TITLE = 'Joyeux anniversaire !';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationService $notifications,
    ) {
    }

    public function __invoke(OutputInterface $output): int
    {
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris'));
        $sent = 0;

        /** @var list<User> $users */
        $users = $this->entityManager->createQueryBuilder()
            ->select('u')->from(User::class, 'u')
            ->andWhere('u.birthDate IS NOT NULL')
            ->andWhere('u.status = :active')->setParameter('active', UserStatus::Active)
            ->getQuery()->getResult();

        foreach ($users as $user) {
            if ($user->isDeleted() || $user->getBirthDate()?->format('m-d') !== $today->format('m-d')) {
                continue;
            }
            $already = (int) $this->entityManager->createQueryBuilder()
                ->select('COUNT(n.id)')->from(Notification::class, 'n')
                ->andWhere('n.recipient = :u')->setParameter('u', $user->getId(), 'ulid')
                ->andWhere('n.title = :t')->setParameter('t', self::TITLE)
                ->andWhere('n.createdAt >= :since')->setParameter('since', $today->modify('first day of January'))
                ->getQuery()->getSingleScalarResult();
            if ($already > 0) {
                continue;
            }
            $this->notifications->notify($user, NotificationCategory::System, self::TITLE, sprintf('Toute l’équipe TrouveMoi vous souhaite un très bel anniversaire, %s ! Et si vous fêtiez ça avec une sortie entre membres ?', $user->getFirstName()));
            ++$sent;
        }

        $output->writeln(sprintf('%d message(s) d’anniversaire envoyé(s).', $sent));

        return Command::SUCCESS;
    }
}
