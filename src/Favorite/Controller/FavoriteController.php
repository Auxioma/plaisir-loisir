<?php

declare(strict_types=1);

namespace App\Favorite\Controller;

use App\Catalog\Repository\DestinationRepository;
use App\Catalog\Repository\ServiceRepository;
use App\Event\Entity\Event;
use App\Event\Entity\Group;
use App\Favorite\Service\FavoriteService;
use App\PrivateActivity\Entity\PrivateActivity;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

/**
 * Ajout et retrait d'un favori, depuis le cœur des cartes.
 *
 * La route répond en JSON parce que le cœur ne recharge pas la page : la
 * maquette le montre dans une grille, et faire repartir toute la page à chaque
 * clic ferait perdre la position de défilement et les filtres en cours.
 *
 * Elle est volontairement HORS de « ^/compte » : un visiteur non connecté doit
 * pouvoir l'atteindre pour recevoir une réponse claire — sans quoi le pare-feu
 * renverrait une redirection vers la page de connexion, qu'une requête en
 * arrière-plan ne sait pas suivre.
 */
final class FavoriteController extends AbstractController
{
    // Point d'entree technique appele en POST par le JavaScript : il ne
    // s'affiche jamais, n'est jamais indexe, donc pas de variante anglaise.
    #[Route('/favoris/basculer', name: 'app_favorite_toggle', methods: ['POST'])]
    public function toggle(
        Request $request,
        FavoriteService $favorites,
        ServiceRepository $services,
        DestinationRepository $destinations,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            // 401 plutôt qu'une redirection : le JavaScript s'en sert pour
            // envoyer la personne se connecter, en gardant la page en tête.
            return new JsonResponse([
                'erreur' => 'connexion_requise',
                'message' => 'Connectez-vous pour enregistrer vos favoris.',
                'connexion' => $this->generateUrl('app_login'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            return new JsonResponse([
                'erreur' => 'jeton_invalide',
                'message' => 'Votre session a expiré, rechargez la page.',
            ], Response::HTTP_FORBIDDEN);
        }

        $type = (string) $request->request->get('type');
        $slug = (string) $request->request->get('slug');

        $favori = match ($type) {
            'activite' => $this->toggleActivity($favorites, $services, $user, $slug),
            'destination' => $this->toggleDestination($favorites, $destinations, $user, $slug),
            // Univers Event (01/10) : `slug` porte le slug d'un événement ou
            // d'un groupe, l'identifiant d'une activité privée ou d'un
            // organisateur.
            'evenement' => $this->toggleTarget($favorites, $user, 'event', $entityManager->getRepository(Event::class)->findOneBy(['slug' => $slug])),
            'groupe' => $this->toggleTarget($favorites, $user, 'group', $entityManager->getRepository(Group::class)->findOneBy(['slug' => $slug])),
            'activite-privee' => $this->toggleTarget($favorites, $user, 'privateActivity', Ulid::isValid($slug) ? $entityManager->getRepository(PrivateActivity::class)->find(Ulid::fromString($slug)) : null),
            'organisateur' => $this->toggleTarget($favorites, $user, 'organizer', Ulid::isValid($slug) ? $entityManager->getRepository(User::class)->find(Ulid::fromString($slug)) : null),
            default => null,
        };

        if (null === $favori) {
            return new JsonResponse([
                'erreur' => 'introuvable',
                'message' => 'Cet élément n\'existe pas.',
            ], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['favori' => $favori]);
    }

    private function toggleActivity(FavoriteService $favorites, ServiceRepository $services, User $user, string $slug): ?bool
    {
        $service = $services->findOneBySlug($slug);

        return null !== $service ? $favorites->toggleService($user, $service) : null;
    }

    private function toggleDestination(FavoriteService $favorites, DestinationRepository $destinations, User $user, string $slug): ?bool
    {
        $destination = $destinations->findOneBySlug($slug);

        return null !== $destination ? $favorites->toggleDestination($user, $destination) : null;
    }

    /**
     * @param 'event'|'group'|'privateActivity'|'organizer' $field
     */
    private function toggleTarget(FavoriteService $favorites, User $user, string $field, Event|Group|PrivateActivity|User|null $target): ?bool
    {
        if (null === $target || $target === $user) {
            return null;
        }

        return $favorites->toggle($user, $field, $target);
    }
}
