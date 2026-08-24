<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Back-office : point d'entrée et menu.
 *
 * POURQUOI CE MODULE EXISTE
 * Jusqu'ici, tout ce que le site affiche vient de `src/DataFixtures`, qui est
 * une dépendance de DÉVELOPPEMENT : en production, ces données n'existent pas.
 * Personne ne pouvait saisir une activité réelle. Ce back-office est ce qui
 * permet de remplir le vrai catalogue.
 *
 * POURQUOI `src/Admin` ET NON UN DOSSIER PAR DOMAINE
 * Le reste du projet est découpé par domaine métier. L'administration n'est
 * pas un domaine : c'est une INTERFACE qui traverse plusieurs domaines. La
 * regrouper en un seul endroit donne une vue d'ensemble de ce qui est
 * administrable — et surtout, de ce qui ne l'est pas.
 *
 * CE QUI N'EST VOLONTAIREMENT PAS EXPOSÉ
 * Paiements, consentements, identités sociales, conversations : ce sont des
 * traces, pas du contenu. Les modifier à la main fausserait une comptabilité
 * ou une preuve de consentement.
 */
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(private readonly AdminUrlGenerator $urls)
    {
    }

    public function index(): Response
    {
        // Un tableau de bord vide n'apprend rien. Tant qu'il n'y a pas
        // d'indicateurs à montrer, on ouvre directement sur les activités :
        // c'est le premier écran utile.
        return $this->redirect(
            $this->urls->setController(ServiceCrudController::class)->generateUrl(),
        );
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('TrouveMoi — Administration')
            ->setFaviconPath('images/logo.svg')
            ->setLocales(['fr']);
    }

    public function configureMenuItems(): iterable
    {
        // EasyAdmin 5 désigne l'écran par son CONTRÔLEUR, non par l'entité
        // (`linkToCrud` des versions 4 n'existe plus).
        yield MenuItem::section('Catalogue');
        yield MenuItem::linkTo(ServiceCrudController::class, 'Activités', 'fa fa-compass');
        yield MenuItem::linkTo(ServicePackageCrudController::class, 'Tarifs', 'fa fa-euro-sign');
        yield MenuItem::linkTo(MediaCrudController::class, 'Photos', 'fa fa-image');

        yield MenuItem::section('Classement');
        yield MenuItem::linkTo(DestinationCrudController::class, 'Destinations', 'fa fa-map-location-dot');
        yield MenuItem::linkTo(CategoryCrudController::class, 'Catégories', 'fa fa-tags');

        yield MenuItem::section('Site');
        yield MenuItem::linkToUrl('Voir le site', 'fa fa-arrow-up-right-from-square', '/');
    }
}
