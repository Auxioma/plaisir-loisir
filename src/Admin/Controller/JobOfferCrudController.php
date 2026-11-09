<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Corporate\Entity\JobOffer;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Offres d'emploi des pages Carrières (09/11).
 *
 * @extends AbstractCrudController<JobOffer>
 */
class JobOfferCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return JobOffer::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('offre d\'emploi')
            ->setEntityLabelInPlural('offres d\'emploi')
            ->setPageTitle(Crud::PAGE_INDEX, 'Offres d\'emploi')
            ->setHelp(Crud::PAGE_INDEX, 'Affichées sur /carrieres et /carrieres/offres. Décochez « Publiée » pour retirer une offre pourvue sans la perdre.')
            ->setDefaultSort(['position' => 'ASC'])
            ->setSearchFields(['title', 'city', 'department']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('department')->add('published');
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('L\'offre');
        yield TextField::new('title', 'Intitulé');
        yield SlugField::new('slug', 'Adresse')->setTargetFieldName('title')->hideOnIndex();
        yield TextareaField::new('summary', 'Résumé')->hideOnIndex();
        yield TextField::new('city', 'Lieu');
        yield TextField::new('contract', 'Contrat');
        yield TextField::new('workTime', 'Temps de travail')->hideOnIndex();
        yield TextField::new('department', 'Service')->setHelp('Sert de filtre sur la page des offres.');
        yield TextField::new('manager', 'Supérieur hiérarchique')->hideOnIndex();
        yield TextField::new('division', 'Division')->hideOnIndex();

        yield FormField::addFieldset('Fiche détaillée')->setHelp('Une ligne par élément : chaque ligne devient une puce.');
        yield TextareaField::new('missions', 'Missions')->hideOnIndex();
        yield TextareaField::new('roleDescription', 'Le poste')->hideOnIndex();
        yield TextareaField::new('education', 'Formation')->hideOnIndex();
        yield TextareaField::new('experience', 'Expérience')->hideOnIndex();

        yield FormField::addFieldset('Affichage');
        yield TextField::new('icon', 'Icône')->setHelp('monitor, megaphone, headset, chart…')->hideOnIndex();
        yield TextField::new('tone', 'Couleur')->setHelp('blue, green, orange, violet…')->hideOnIndex();
        yield IntegerField::new('position', 'Rang');
        yield BooleanField::new('published', 'Publiée');
    }
}
