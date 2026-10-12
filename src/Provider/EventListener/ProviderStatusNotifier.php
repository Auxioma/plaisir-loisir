<?php

declare(strict_types=1);

namespace App\Provider\EventListener;

use App\Notification\Enum\NotificationCategory;
use App\Notification\Service\NotificationService;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Enum\ProviderStatus;
use App\User\Repository\UserRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * Prévient le professionnel à chaque étape de la vérification de son compte
 * (12/10, retour client : « je n'ai eu aucune information que mon compte pro
 * devait être validé »). Notification dans l'espace pro ET e-mail
 * (NotificationEmailListener), quelle que soit l'origine du changement :
 * envoi du dossier, ou statut modifié par l'équipe dans le back-office.
 *
 * Les notifications partent après l'enregistrement (postFlush) : en créer
 * pendant le flush en cours serait refusé par Doctrine.
 */
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: ProviderProfile::class)]
#[AsDoctrineListener(event: Events::postFlush)]
final class ProviderStatusNotifier
{
    /** @var list<array{0: ProviderProfile, 1: ProviderStatus}> */
    private array $changes = [];

    public function __construct(
        private readonly NotificationService $notifications,
        private readonly UserRepository $users,
    ) {
    }

    public function preUpdate(ProviderProfile $profile, PreUpdateEventArgs $event): void
    {
        if ($event->hasChangedField('status') && $event->getOldValue('status') !== $event->getNewValue('status')) {
            $this->changes[] = [$profile, $profile->getStatus()];
        }
    }

    public function postFlush(PostFlushEventArgs $event): void
    {
        if ([] === $this->changes) {
            return;
        }
        $changes = $this->changes;
        $this->changes = [];

        foreach ($changes as [$profile, $status]) {
            $user = $profile->getUser();
            if (null === $user) {
                continue;
            }
            [$title, $message] = match ($status) {
                ProviderStatus::PendingVerification => ['Dossier professionnel reçu', 'Votre dossier a bien été envoyé. Notre équipe vérifie vos justificatifs sous 48 h ouvrées. En attendant, vous pouvez préparer vos activités payantes dans votre espace : elles seront publiées dès la validation de votre compte. Vous serez prévenu par e-mail.'],
                ProviderStatus::Verified => ['Compte professionnel validé', 'Bonne nouvelle : votre compte professionnel est validé. Vos activités peuvent maintenant être publiées et réservées par nos clients.'],
                ProviderStatus::Suspended => ['Compte professionnel non validé', 'Votre compte professionnel n’est pas (ou plus) validé : vos activités ne sont pas visibles. Consultez vos documents dans votre espace ou contactez notre équipe pour en connaître la raison.'],
                ProviderStatus::Draft => ['Dossier à compléter', 'Votre dossier professionnel est à compléter : déposez vos justificatifs puis cliquez sur « Envoyer mon dossier » dans votre espace pour qu’il soit vérifié.'],
            };
            $this->notifications->notify($user, NotificationCategory::System, $title, $message);

            if (ProviderStatus::PendingVerification === $status) {
                foreach ($this->users->findAdmins() as $admin) {
                    $this->notifications->notify($admin, NotificationCategory::System, 'Dossier professionnel à vérifier', \sprintf('« %s » a envoyé son dossier : il attend votre vérification dans le back-office (Professionnels).', $profile->getDisplayName()));
                }
            }
        }
    }
}
