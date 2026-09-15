<?php

declare(strict_types=1);

namespace App\Shared\Service;

use App\Messaging\Repository\MessageRepository;
use App\User\Entity\User;
use App\User\StaticAccount;

/**
 * Le bloc « profil » attendu par account/_sidebar.html.twig (nom, e-mail,
 * avatar, ancienneté, compteur de non-lus).
 *
 * EXTRAIT LE 14/09, APRÈS TROIS COPIES
 * ServiceRequestController, PrivateActivityController puis
 * ProviderRequestController/SubscriptionController/ProviderDashboardController
 * avaient chacun leur propre méthode privée identique (voir
 * AccountController::accountUser(), la première version, non touchée ici :
 * `/compte/*` continue de fonctionner sans dépendre de ce partage). Trois
 * copies passées, c'était le signal que la duplication documentée à
 * l'origine (« devancer la vraie refonte de l'espace compte ») avait dépassé
 * son utilité — d'où ce partage dans Shared, le domaine prévu pour ce genre
 * d'utilitaire transverse.
 *
 * L'IDENTITÉ EST RÉELLE, LE RESTE PAS ENCORE
 * Avatar réel depuis le Lot I (User::$avatarPath), avec repli sur celui de la
 * maquette tant que rien n'a été déposé. Les messages non lus sont réels
 * depuis le Lot G ; les notifications non lues restent la démo, faute
 * d'écran de préférences pour les compter.
 */
final class AccountIdentityPresenter
{
    public function __construct(
        private readonly MessageRepository $messages,
    ) {
    }

    /**
     * @return array{name: string, firstName: string, email: string, avatar: string, memberSince: string, unreadMessages: int, unreadNotifications: int}
     */
    public function identityFor(User $user): array
    {
        $firstName = $user->getFirstName();
        $fullName = trim($firstName.' '.$user->getLastName());
        $demo = StaticAccount::user();

        return [
            'name' => '' !== $fullName ? $fullName : $user->getEmail(),
            'firstName' => '' !== $firstName ? $firstName : $user->getLastName(),
            'email' => $user->getEmail(),
            'avatar' => $user->getAvatarPath() ?? $demo['avatar'],
            'memberSince' => $this->formatMemberSince($user->getCreatedAt()),
            'unreadMessages' => $this->messages->countUnreadForUser($user),
            'unreadNotifications' => $demo['unreadNotifications'],
        ];
    }

    /**
     * « Membre depuis Mai 2026 » — mois en toutes lettres, dans la langue
     * active du site.
     */
    private function formatMemberSince(?\DateTimeImmutable $createdAt): string
    {
        if (null === $createdAt) {
            return '';
        }

        $formatted = (string) \IntlDateFormatter::formatObject($createdAt, 'LLLL y', \Locale::getDefault());

        return mb_strtoupper(mb_substr($formatted, 0, 1)).mb_substr($formatted, 1);
    }
}
