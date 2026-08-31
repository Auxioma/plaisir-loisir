<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Asset\Packages;
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
    public function __construct(
        private readonly AdminUrlGenerator $urls,
        private readonly Packages $assets,
    ) {
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
            // Le logo du site plutot qu'un titre ecrit : c'est le meme
            // fichier que celui de la barre de navigation publique, pas une
            // variante faite pour l'occasion.
            ->setTitle(sprintf(
                '<img src="%s" alt="TrouveMoi Plaisirs &amp; Loisirs">',
                $this->assets->getUrl('images/logo.svg'),
            ))
            ->setFaviconPath('images/logo.svg')
            ->setLocales(['fr']);
    }

    /**
     * Habillage aux couleurs de la plateforme.
     *
     * Les jetons et la police sont ceux du site, repris tels quels : le
     * back-office n'a pas son propre theme a maintenir. admin.css se
     * contente de les brancher sur ceux d'EasyAdmin.
     */
    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addCssFile('styles/design-system.css')
            ->addCssFile('styles/fonts.css')
            ->addCssFile('styles/admin.css');
    }

    public function configureMenuItems(): iterable
    {
        // EasyAdmin 5 désigne l'écran par son CONTRÔLEUR, non par l'entité
        // (`linkToCrud` des versions 4 n'existe plus).
        yield MenuItem::section('Catalogue');
        yield MenuItem::linkTo(ServiceCrudController::class, 'Activités', 'fa fa-compass');
        yield MenuItem::linkTo(ServicePackageCrudController::class, 'Tarifs', 'fa fa-euro-sign');
        yield MenuItem::linkTo(MediaCrudController::class, 'Photos', 'fa fa-image');
        // Sans fiche detaillee, la page publique d'une activite renvoie une
        // erreur 404 : l'ecran est aussi indispensable que l'activite elle-meme.
        yield MenuItem::linkTo(ServiceDetailCrudController::class, 'Fiches détaillées', 'fa fa-file-lines');
        // Sans creneau, une activite reste proposee a toutes les dates : le
        // filtre « Date » de la recherche n'a rien sur quoi mordre.
        yield MenuItem::linkTo(AvailabilityCrudController::class, 'Disponibilités', 'fa fa-calendar-days');

        yield MenuItem::section('Classement');
        yield MenuItem::linkTo(DestinationCrudController::class, 'Destinations', 'fa fa-map-location-dot');
        yield MenuItem::linkTo(CategoryCrudController::class, 'Catégories', 'fa fa-tags');

        // Demande du CTO le 29/08 : les textes juridiques et l'aide se gèrent
        // en base, parce qu'ils évoluent dans le temps et qu'une évolution ne
        // peut pas exiger un déploiement.
        yield MenuItem::section('Contenus éditoriaux');
        yield MenuItem::linkTo(LegalDocumentCrudController::class, 'Textes juridiques', 'fa fa-scale-balanced');
        yield MenuItem::linkTo(FaqEntryCrudController::class, 'FAQ', 'fa fa-circle-question');

        yield MenuItem::section('Site');
        yield MenuItem::linkToUrl('Voir le site', 'fa fa-arrow-up-right-from-square', '/');
    }
}
