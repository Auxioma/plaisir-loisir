<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Catalog\Entity\Destination;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Villes et régions mises en avant.
 *
 * La note et le nombre d'avis ne sont pas proposés à la saisie : ils
 * proviendront des avis déposés. Le nombre d'activités reste saisissable tant
 * qu'aucun rattachement automatique n'existe entre une activité et sa
 * destination — c'est un affichage de maquette, pas encore un calcul.
 *
 * @extends AbstractCrudController<Destination>
 */
class DestinationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Destination::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('destination')
            ->setEntityLabelInPlural('destinations')
            ->setPageTitle(Crud::PAGE_INDEX, 'Destinations')
            ->setDefaultSort(['position' => 'ASC', 'name' => 'ASC'])
            ->setSearchFields(['name', 'country', 'region', 'tagline']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nom');
        yield SlugField::new('slug', 'Adresse de la page')
            ->setTargetFieldName('name')
            ->setHelp('Ce texte apparaît dans l\'adresse : /destinations/mon-nom.')
            ->hideOnIndex();
        yield TextField::new('country', 'Pays');
        yield TextField::new('region', 'Région')->hideOnIndex();
        yield TextField::new('tagline', 'Accroche')
            ->setHelp('Une phrase courte affichée sous le nom, par exemple « Entre lac et montagnes ».');
        yield TextareaField::new('description', 'Description')->hideOnIndex();
        yield ImageField::new('heroImage', 'Photo')
            // Pas de setBasePath : le gabarit d'EasyAdmin passe deja par
            // asset(). Lui donner un chemin absolu court-circuiterait
            // l'AssetMapper, et les photos livrees avec le site
            // (images/...) s'afficheraient cassees. Sans lui, asset() rend
            // /assets/images/...-EMPREINTE.jpg pour les unes et
            // /uploads/... pour les autres.
            // Le motif de nom porte deja « uploads/ » : le dossier de
            // destination est donc « public », pas « public/uploads », sinon
            // le fichier atterrit dans public/uploads/uploads/ alors que le
            // chemin enregistre, lui, vise public/uploads/. La valeur stockee
            // reste relative a public/, exactement comme « images/... ».
            ->setUploadDir('public')
            ->setUploadedFileNamePattern('uploads/[slug]-[timestamp].[extension]')
            ->setHelp('Sans photo, la carte affiche une image de remplacement.');
        yield TextField::new('badge', 'Badge')
            ->setHelp('Populaire, Bestseller ou Tendance. Laissez vide s\'il n\'y en a pas.')
            ->hideOnIndex();
        yield IntegerField::new('priceFrom', 'Prix à partir de')->hideOnIndex();
        yield IntegerField::new('activitiesCount', 'Nombre d\'activités affiché')->hideOnIndex();
        yield IntegerField::new('position', 'Ordre d\'affichage')
            ->setHelp('Le plus petit nombre passe en premier.');
    }
}
