<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Corporate\Entity\PageBlock;
use App\Corporate\Enum\PageBlockSection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Contenus des pages « À propos », « Devenir partenaire » et « Carrières »
 * (09/11) : valeurs, équipe, témoignages, étapes… Le contenu de départ vient
 * de la commande app:contenu:importer.
 *
 * @extends AbstractCrudController<PageBlock>
 */
class PageBlockCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return PageBlock::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('bloc de page')
            ->setEntityLabelInPlural('blocs des pages institutionnelles')
            ->setPageTitle(Crud::PAGE_INDEX, 'Pages institutionnelles')
            ->setHelp(
                Crud::PAGE_INDEX,
                'Valeurs, équipe, témoignages et étapes des pages À propos, Devenir partenaire et Carrières. '
                .'Filtrez par section pour retrouver l\'ordre d\'affichage. L\'équipe et les témoignages importés sont ceux de la maquette : remplacez-les par les vrais.',
            )
            ->setDefaultSort(['section' => 'ASC', 'position' => 'ASC'])
            ->setSearchFields(['title', 'text', 'author', 'role']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('section')->add('published');
    }

    public function configureFields(string $pageName): iterable
    {
        yield ChoiceField::new('section', 'Section')
            ->setChoices(array_combine(
                array_map(static fn (PageBlockSection $s): string => $s->label(), PageBlockSection::cases()),
                PageBlockSection::cases(),
            ));

        yield TextField::new('title', 'Titre ou nom')
            ->setHelp('Intitulé de la carte (valeur, étape…) ou nom de la personne (équipe, témoignage).');

        yield TextareaField::new('text', 'Texte ou citation')
            ->setHelp('Description de la carte, ou citation d\'un témoignage.')
            ->hideOnIndex();

        yield TextField::new('role', 'Fonction')
            ->setHelp('Équipe et témoignages : fonction ou activité affichée sous le nom.')
            ->hideOnIndex();

        yield ImageField::new('image', 'Photo')
            ->setUploadDir('public')
            ->setUploadedFileNamePattern('uploads/[slug]-[timestamp].[extension]')
            ->setHelp('Équipe et témoignages uniquement.')
            ->hideOnIndex();

        yield TextField::new('icon', 'Icône')
            ->setHelp('Nom d\'icône du site : heart, users, star, leaf, rocket, shield_check, chart, calendar_check…')
            ->hideOnIndex();

        yield ChoiceField::new('tone', 'Couleur')
            ->setChoices(['Bleu' => 'blue', 'Vert' => 'green', 'Orange' => 'orange', 'Rouge' => 'red', 'Violet' => 'violet', 'Jaune' => 'yellow', 'Ambre' => 'amber', 'Marine' => 'navy'])
            ->setRequired(false)
            ->hideOnIndex();

        yield IntegerField::new('position', 'Rang')
            ->setHelp('Du plus petit au plus grand dans la section.');

        yield BooleanField::new('featured', 'Mise en avant')->hideOnIndex();

        yield BooleanField::new('published', 'Publié');
    }
}
