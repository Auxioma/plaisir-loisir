<?php

declare(strict_types=1);

namespace App\PrivateActivity\Service;

use App\Notification\Enum\NotificationCategory;
use App\Notification\Service\NotificationService;
use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Repository\InvitationRepository;
use App\User\Entity\User;
use App\User\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Invitations par e-mail à une activité gratuite (retours client du 07/10 :
 * « pour l'invitation privée il manque le choix de pouvoir inviter par mail
 * et partage »).
 *
 * Une adresse d'un membre crée une Invitation (accès à l'activité privée) et
 * une notification avec le lien ; une adresse sans compte reçoit un e-mail
 * avec le lien d'invitation (lien signé pour une activité privée).
 */
final class PrivateActivityInvitations
{
    public const MAX_PER_SEND = 30;

    public function __construct(
        private readonly PrivateActivityService $activities,
        private readonly InvitationRepository $invitations,
        private readonly UserRepository $users,
        private readonly NotificationService $notifications,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Découpe une saisie libre (virgules, points-virgules, espaces, retours).
     *
     * @return array{0: list<string>, 1: list<string>} [adresses valides, adresses invalides]
     */
    public static function parse(string $raw): array
    {
        $valid = [];
        $invalid = [];
        foreach (array_unique(array_filter(array_map('trim', preg_split('/[,;\s]+/', mb_strtolower($raw)) ?: []))) as $email) {
            if (false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
                $invalid[] = $email;
            } else {
                $valid[] = $email;
            }
        }

        return [$valid, $invalid];
    }

    /**
     * @param list<string> $emails adresses déjà validées (parse())
     *
     * @return int nombre d'invitations envoyées
     */
    public function invite(PrivateActivity $activity, User $organizer, array $emails, string $link, string $note = ''): int
    {
        if ($activity->getOrganizer() !== $organizer) {
            throw new \InvalidArgumentException("Seul l'organisateur peut inviter.");
        }
        if (\count($emails) > self::MAX_PER_SEND) {
            throw new \InvalidArgumentException(\sprintf('%d adresses au maximum par envoi.', self::MAX_PER_SEND));
        }

        $name = trim($organizer->getFirstName().' '.$organizer->getLastName());
        $when = $activity->getScheduledAt()?->format('d/m/Y à H\hi');
        $sent = 0;

        foreach ($emails as $email) {
            if ($email === mb_strtolower($organizer->getEmail())) {
                continue;
            }

            // Un membre reçoit UNE notification (envoyée aussi par e-mail selon
            // ses préférences), lien compris ; pas de second e-mail en double.
            $member = $this->users->findOneBy(['email' => $email]);
            if (null !== $member) {
                if (null === $this->invitations->findOneByActivityAndInvitee($activity, $member)) {
                    $this->activities->invite($activity, $organizer, $member);
                }
                $this->notifications->notify($member, NotificationCategory::Activity, 'Invitation à une activité', \sprintf(
                    '%s vous invite à « %s »%s.%s Voir l’activité : %s',
                    $name,
                    $activity->getTitle(),
                    $when ? ' le '.$when : '',
                    '' !== trim($note) ? ' « '.trim($note).' »' : '',
                    $link,
                ));
                ++$sent;
                continue;
            }

            $body = \sprintf("Bonjour,\n\n%s vous invite à une activité gratuite sur TrouveMoi Plaisirs & Loisirs :\n\n« %s »\n%s%s\n%s\nVoir l'activité et répondre : %s\n\nÀ bientôt sur TrouveMoi !",
                $name,
                $activity->getTitle(),
                $when ? 'Le '.$when."\n" : '',
                $activity->getCity() ? 'À '.$activity->getCity()."\n" : '',
                '' !== trim($note) ? "\nSon message : ".trim($note)."\n" : '',
                $link,
            );

            try {
                $this->mailer->send((new Email())
                    ->to($email)
                    ->replyTo($organizer->getEmail())
                    ->subject(\sprintf('%s vous invite : %s', $name, $activity->getTitle()))
                    ->text($body));
                ++$sent;
            } catch (\Throwable $e) {
                $this->logger->error('Invitation à une activité gratuite non envoyée.', ['email' => $email, 'exception' => $e]);
            }
        }

        return $sent;
    }
}
