<?php

declare(strict_types=1);

namespace App\User\Controller;

use App\User\Entity\User;
use App\User\StaticAccount;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Espace compte « Paramètre du profil » (spec profil) : layout à sidebar
 * persistante + favoris (3 onglets), liste de favoris, notifications,
 * parrainage et confirmation de déconnexion.
 *
 * Depuis le câblage du 17/08, l'IDENTITÉ affichée (nom, e-mail, ancienneté)
 * est celle du compte en session. Le CONTENU (favoris, notifications,
 * parrainage) reste celui des maquettes : il sera branché avec les entités
 * Favorite et Notification. Les états vide/rempli se pilotent par « ?vide=1 ».
 */
#[IsGranted('ROLE_USER')]
final class AccountController extends AbstractController
{
    #[Route('/compte/favoris', name: 'app_account_favorites')]
    public function favorites(Request $request): Response
    {
        $tab = $request->query->get('onglet', 'activites');
        if (!\in_array($tab, ['activites', 'destinations', 'prestataires'], true)) {
            $tab = 'activites';
        }

        // États par défaut fidèles aux captures : Activités et Prestataires
        // remplis, Destinations vide ; ?vide=1 force l'état vide de démo.
        $empty = $request->query->has('vide')
            ? $request->query->getBoolean('vide')
            : 'destinations' === $tab;

        return $this->render('account/favoris.html.twig', [
            'user' => $this->accountUser(),
            'menu' => StaticAccount::menu(),
            'active' => 'Mes favoris',
            'tab' => $tab,
            'empty' => $empty,
            'favorites' => 'prestataires' === $tab ? StaticAccount::providers() : StaticAccount::favorites(),
        ]);
    }

    #[Route('/compte/favoris/listes/{slug}', name: 'app_account_favorites_list', defaults: ['slug' => 'alsace-2026'])]
    public function favoritesList(string $slug): Response
    {
        return $this->render('account/liste.html.twig', [
            'user' => $this->accountUser(),
            'menu' => StaticAccount::menu(),
            'active' => 'Mes favoris',
            'list_name' => 'Alsace - 2026',
            'favorites' => StaticAccount::alsaceList(),
        ]);
    }

    #[Route('/compte/notifications', name: 'app_account_notifications')]
    public function notifications(Request $request): Response
    {
        return $this->render('account/notifications.html.twig', [
            'user' => $this->accountUser(),
            'menu' => StaticAccount::menu(),
            'active' => 'Notifications',
            'empty' => $request->query->getBoolean('vide'),
            'groups' => StaticAccount::notifications(),
        ]);
    }

    #[Route('/compte/parrainage', name: 'app_account_referral')]
    public function referral(): Response
    {
        return $this->render('account/parrainage.html.twig', [
            'user' => $this->accountUser(),
            'menu' => StaticAccount::menu(),
            'active' => 'Parrainage',
        ]);
    }

    #[Route('/compte/deconnexion', name: 'app_account_logout_confirm')]
    public function logoutConfirm(): Response
    {
        return $this->render('account/deconnexion.html.twig', [
            'user' => $this->accountUser(),
            'menu' => StaticAccount::menu(),
            'active' => 'Déconnexion',
        ]);
    }

    /**
     * Le bloc « profil » de la sidebar, alimenté par le compte en session.
     *
     * On garde la forme de tableau attendue par les templates plutôt que de
     * leur passer l'entité : cela évite de toucher aux six écrans déjà calés
     * au pixel, et laisse cohabiter l'identité réelle et les compteurs encore
     * statiques le temps que les entités correspondantes soient branchées.
     *
     * @return array{name: string, firstName: string, email: string, avatar: string, memberSince: string, unread: int}
     */
    private function accountUser(): array
    {
        $user = $this->getUser();

        // Sécurité de type : la classe entière exige ROLE_USER, donc getUser()
        // ne peut pas être nul ici ; ce garde-fou rassure surtout PHPStan.
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $firstName = $user->getFirstName();
        $fullName = trim($firstName.' '.$user->getLastName());

        $demo = StaticAccount::user();

        return [
            'name' => '' !== $fullName ? $fullName : $user->getEmail(),
            'firstName' => '' !== $firstName ? $firstName : $user->getLastName(),
            'email' => $user->getEmail(),
            // L'entité User ne porte aucune photo de profil : tant que le
            // téléversement d'avatar n'existe pas, on garde celle de la
            // maquette plutôt que d'afficher un cadre vide.
            'avatar' => $demo['avatar'],
            'memberSince' => $this->formatMemberSince($user->getCreatedAt()),
            // Compteur de notifications non lues : encore celui de la démo,
            // il sera branché sur NotificationRepository avec l'écran.
            'unread' => $demo['unread'],
        ];
    }

    /**
     * « Membre depuis Mai 2026 » — mois en toutes lettres, dans la langue
     * active du site (l'écran existe en français et en anglais).
     */
    private function formatMemberSince(?\DateTimeImmutable $createdAt): string
    {
        if (null === $createdAt) {
            return '';
        }

        // « LLLL » = nom du mois autonome (« janvier », et non « de janvier »),
        // le seul correct hors d'une date complète.
        $formatted = (string) \IntlDateFormatter::formatObject($createdAt, 'LLLL y', \Locale::getDefault());

        return mb_strtoupper(mb_substr($formatted, 0, 1)).mb_substr($formatted, 1);
    }
}
