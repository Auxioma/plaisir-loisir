<?php

declare(strict_types=1);

namespace App\User\Controller;

use App\Catalog\Entity\Destination;
use App\Catalog\Entity\Service;
use App\Catalog\Presenter\ActivityPresenter;
use App\Catalog\Presenter\DestinationPresenter;
use App\Favorite\Repository\FavoriteRepository;
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
    public function __construct(
        private readonly FavoriteRepository $favorites,
        private readonly ActivityPresenter $activityPresenter,
        private readonly DestinationPresenter $destinationPresenter,
    ) {
    }

    /**
     * L'utilisateur en session, avec la garantie de type qu'attend PHPStan.
     *
     * La classe entière exige ROLE_USER : getUser() ne peut pas être nul ici.
     */
    private function currentUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    /**
     * Tout ce qui est affiché sur cet écran est, par définition, en favori :
     * les cœurs sont donc tous actifs, sans réinterroger la base.
     *
     * @return list<array<string, mixed>>
     */
    private function cardsForFavoriteActivities(User $user): array
    {
        $services = $this->favorites->findServicesForUser($user);
        $slugs = array_map(static fn (Service $service): string => $service->getSlug(), $services);

        return $this->activityPresenter->cards($services, favoriteSlugs: $slugs);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cardsForFavoriteDestinations(User $user): array
    {
        $destinations = $this->favorites->findDestinationsForUser($user);
        $slugs = array_map(static fn (Destination $destination): string => $destination->getSlug(), $destinations);

        return $this->destinationPresenter->cards($destinations, $slugs);
    }

    #[Route('/compte/favoris', name: 'app_account_favorites')]
    public function favorites(Request $request): Response
    {
        $tab = $request->query->get('onglet', 'activites');
        if (!\in_array($tab, ['activites', 'destinations', 'prestataires'], true)) {
            $tab = 'activites';
        }

        // Les onglets Activités et Destinations affichent désormais les VRAIS
        // favoris. L'onglet Prestataires reste en démonstration : mettre un
        // prestataire en favori n'existe pas encore côté entité Favorite, qui
        // ne connaît que les activités et les destinations.
        $user = $this->currentUser();

        $favorites = match ($tab) {
            'activites' => $this->cardsForFavoriteActivities($user),
            'destinations' => $this->cardsForFavoriteDestinations($user),
            default => StaticAccount::providers(),
        };

        // L'état vide n'est plus décidé d'avance : il découle de ce que la
        // personne a réellement mis en favori. La maquette fournit les deux
        // états ; « ?vide=1 » sert encore à les comparer en développement.
        $empty = $request->query->has('vide')
            ? $request->query->getBoolean('vide')
            : [] === $favorites;

        return $this->render('account/favoris.html.twig', [
            'user' => $this->accountUser(),
            'menu' => StaticAccount::menu(),
            'active' => 'Mes favoris',
            'tab' => $tab,
            'empty' => $empty,
            'favorites' => $favorites,
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
