<?php

declare(strict_types=1);

namespace App\User\Controller;

use App\Catalog\Entity\Destination;
use App\Catalog\Entity\Service;
use App\Catalog\Presenter\ActivityPresenter;
use App\Catalog\Presenter\DestinationPresenter;
use App\Event\Repository\EventRepository;
use App\Favorite\Repository\FavoriteRepository;
use App\Messaging\Repository\MessageRepository;
use App\PrivateActivity\Entity\Participation;
use App\PrivateActivity\Enum\ParticipationStatus;
use App\PrivateActivity\Repository\ParticipationRepository;
use App\PrivateActivity\Repository\PrivateActivityRepository;
use App\Quote\Repository\ServiceRequestRepository;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use App\User\Service\AccountAnonymizer;
use App\User\Service\AvatarStorageService;
use App\User\StaticAccount;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
        private readonly ServiceRequestRepository $requests,
        private readonly PrivateActivityRepository $privateActivities,
        private readonly ParticipationRepository $participations,
        private readonly MessageRepository $messages,
        private readonly AvatarStorageService $avatars,
        private readonly AccountAnonymizer $anonymizer,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventRepository $events,
    ) {
    }

    /**
     * Aperçu du compte client (spec profil, entrée « Tableau de bord » de la
     * sidebar). Signalé le 14/09 juste après le même correctif côté pro :
     * cette entrée existait dans le menu depuis le début, mais pointait vers
     * une route nulle — l'avatar du header y menait donc avec un libellé
     * « Profil » qui, faute d'écran, redirigeait en réalité vers Favoris.
     *
     * Ce qui est réellement compté ici (demandes de devis, activités
     * privées, favoris) existe déjà comme entité. Albums photos, activités
     * créées (domaine Event) et réservations n'en ont encore aucune : la
     * sidebar les affiche « Bientôt disponible » plutôt que comme des liens
     * morts silencieux (account/_sidebar.html.twig).
     */
    #[Route(path: ['fr' => '/compte/tableau-de-bord', 'en' => '/en/account/dashboard'], name: 'app_account_dashboard')]
    public function dashboard(): Response
    {
        $user = $this->currentUser();

        $requests = $this->requests->findByClient($user);
        $organized = $this->privateActivities->findByOrganizer($user);
        $joined = array_filter(
            $this->participations->findByParticipant($user),
            static fn (Participation $p): bool => ParticipationStatus::Cancelled !== $p->getStatus(),
        );
        $favoriteActivitiesCount = \count($this->favorites->findServicesForUser($user));
        $favoriteDestinationsCount = \count($this->favorites->findDestinationsForUser($user));

        return $this->render('account/tableau_de_bord.html.twig', [
            'user' => $this->accountUser(),
            'menu' => StaticAccount::menu(),
            'active' => 'Tableau de bord',
            'requests_count' => \count($requests),
            'recent_requests' => \array_slice($requests, 0, 5),
            'organized_count' => \count($organized),
            'joined_count' => \count($joined),
            'favorites_count' => $favoriteActivitiesCount + $favoriteDestinationsCount,
        ]);
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

    #[Route(path: ['fr' => '/compte/favoris', 'en' => '/en/account/favorites'], name: 'app_account_favorites')]
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

    #[Route(path: ['fr' => '/compte/favoris/listes/{slug}', 'en' => '/en/account/favorites/lists/{slug}'], name: 'app_account_favorites_list', defaults: ['slug' => 'alsace-2026'])]
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

    #[Route(path: ['fr' => '/compte/notifications', 'en' => '/en/account/notifications'], name: 'app_account_notifications')]
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

    #[Route(path: ['fr' => '/compte/parrainage', 'en' => '/en/account/referral'], name: 'app_account_referral')]
    public function referral(): Response
    {
        return $this->render('account/parrainage.html.twig', [
            'user' => $this->accountUser(),
            'menu' => StaticAccount::menu(),
            'active' => 'Parrainage',
        ]);
    }

    /**
     * « Mes activités créées » de la sidebar (Lot J, 15/09) : les événements
     * (domaine Event) que l'utilisateur a lui-même organisés.
     */
    #[Route(path: ['fr' => '/compte/mes-evenements', 'en' => '/en/account/my-events'], name: 'app_account_events')]
    public function events(): Response
    {
        return $this->render('account/mes_evenements.html.twig', [
            'user' => $this->accountUser(),
            'menu' => StaticAccount::menu(),
            'active' => 'Mes activités créées',
            'events' => $this->events->findByOrganizer($this->currentUser()),
        ]);
    }

    /**
     * « Mes réservations » de la sidebar (Lot J, 15/09), rebaptisée
     * « Historique » : le catalogue à réservation directe (Booking) étant en
     * pause, l'historique s'appuie sur ce que le modèle actuel produit
     * réellement — demandes de devis clôturées, activités privées passées.
     */
    #[Route(path: ['fr' => '/compte/historique', 'en' => '/en/account/history'], name: 'app_account_history')]
    public function history(): Response
    {
        $user = $this->currentUser();
        $now = new \DateTimeImmutable();

        $closedRequests = array_values(array_filter(
            $this->requests->findByClient($user),
            static fn ($request): bool => !$request->isOpen(),
        ));

        $pastParticipations = array_values(array_filter(
            $this->participations->findByParticipant($user),
            static fn (Participation $p): bool => ParticipationStatus::Cancelled !== $p->getStatus()
                && null !== $p->getPrivateActivity()?->getScheduledAt()
                && $p->getPrivateActivity()->getScheduledAt() < $now,
        ));

        return $this->render('account/historique.html.twig', [
            'user' => $this->accountUser(),
            'menu' => StaticAccount::menu(),
            'active' => 'Mes réservations',
            'closed_requests' => $closedRequests,
            'past_participations' => $pastParticipations,
        ]);
    }

    /**
     * Paramètres du compte (Lot I, 15/09) : nom, téléphone, photo de profil,
     * et désactivation/suppression. Aucune maquette — entrée de la sidebar
     * restée « Bientôt disponible » depuis le câblage du 14/09.
     */
    #[Route(path: ['fr' => '/compte/parametres', 'en' => '/en/account/settings'], name: 'app_account_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request): Response
    {
        $user = $this->currentUser();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

                return $this->redirectToRoute('app_account_settings');
            }

            $firstName = trim((string) $request->request->get('prenom', ''));
            $lastName = trim((string) $request->request->get('nom', ''));
            $phone = trim((string) $request->request->get('telephone', ''));

            if ('' === $firstName || '' === $lastName) {
                $this->addFlash('error', 'Le nom et le prénom ne peuvent pas être vides.');

                return $this->redirectToRoute('app_account_settings');
            }

            $user->setFirstName(mb_substr($firstName, 0, 100));
            $user->setLastName(mb_substr($lastName, 0, 100));
            $user->setPhone('' !== $phone ? mb_substr($phone, 0, 30) : null);

            $photo = $request->files->get('photo');
            if ($photo instanceof UploadedFile) {
                try {
                    $this->avatars->store($user, $photo);
                } catch (\InvalidArgumentException $e) {
                    $this->addFlash('error', $e->getMessage());

                    return $this->redirectToRoute('app_account_settings');
                }
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Vos informations ont été mises à jour.');

            return $this->redirectToRoute('app_account_settings');
        }

        return $this->render('account/parametres.html.twig', [
            'user' => $this->accountUser(),
            'menu' => StaticAccount::menu(),
            'active' => 'Paramètres du compte',
            'account' => $user,
        ]);
    }

    /**
     * Désactivation : statut suspendu, réversible par l'administration —
     * distincte de la suppression ci-dessous, qui efface les données.
     */
    #[Route(path: ['fr' => '/compte/parametres/desactiver', 'en' => '/en/account/settings/deactivate'], name: 'app_account_deactivate', methods: ['POST'])]
    public function deactivate(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

            return $this->redirectToRoute('app_account_settings');
        }

        $user = $this->currentUser();
        $user->setStatus(UserStatus::Suspended);
        $this->entityManager->flush();

        $this->addFlash('success', 'Votre compte a été désactivé. Contactez-nous pour le réactiver.');

        return $this->redirectToRoute('app_account_logout_confirm');
    }

    /**
     * Suppression : irréversible, efface les données personnelles
     * (AccountAnonymizer, voir ce service pour le détail RGPD).
     */
    #[Route(path: ['fr' => '/compte/parametres/supprimer', 'en' => '/en/account/settings/delete'], name: 'app_account_delete', methods: ['POST'])]
    public function delete(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

            return $this->redirectToRoute('app_account_settings');
        }

        $this->anonymizer->anonymize($this->currentUser());

        $this->addFlash('success', 'Votre compte et vos données personnelles ont été supprimés.');

        return $this->redirectToRoute('app_account_logout_confirm');
    }

    #[Route(path: ['fr' => '/compte/deconnexion', 'en' => '/en/account/sign-out'], name: 'app_account_logout_confirm')]
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
     * @return array{name: string, firstName: string, email: string, avatar: string, memberSince: string, unreadMessages: int, unreadNotifications: int}
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
            // Photo réelle depuis le Lot I ; tant que personne n'en a
            // déposé une, on garde celle de la maquette plutôt que
            // d'afficher un cadre vide.
            'avatar' => $user->getAvatarPath() ?? $demo['avatar'],
            'memberSince' => $this->formatMemberSince($user->getCreatedAt()),
            // Messages non lus : réel depuis le Lot G (MessageRepository).
            'unreadMessages' => $this->messages->countUnreadForUser($user),
            // Notifications non lues : encore la démo, faute d'écran de
            // préférences pour les compter (Lot H, non fait).
            'unreadNotifications' => $demo['unreadNotifications'],
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
