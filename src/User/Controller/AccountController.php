<?php

declare(strict_types=1);

namespace App\User\Controller;

use App\User\StaticAccount;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Espace compte « Paramètre du profil » (spec profil) : layout à sidebar
 * persistante + favoris (3 onglets), liste de favoris, notifications,
 * parrainage et confirmation de déconnexion. Écrans statiques de démo ;
 * les états vide/rempli des maquettes se pilotent par l'URL (?vide=1).
 */
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
            'user' => StaticAccount::user(),
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
            'user' => StaticAccount::user(),
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
            'user' => StaticAccount::user(),
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
            'user' => StaticAccount::user(),
            'menu' => StaticAccount::menu(),
            'active' => 'Parrainage',
        ]);
    }

    #[Route('/compte/deconnexion', name: 'app_account_logout_confirm')]
    public function logoutConfirm(): Response
    {
        return $this->render('account/deconnexion.html.twig', [
            'user' => StaticAccount::user(),
            'menu' => StaticAccount::menu(),
            'active' => 'Déconnexion',
        ]);
    }
}
