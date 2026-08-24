<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Catalog\Entity\ServicePackage;
use App\Catalog\Enum\PricingUnit;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Tarifs des activites.
 *
 * ATTENTION : le prix n'appartient PAS a l'activite, il appartient a sa
 * formule. Une activite sans formule s'affiche donc sans prix. C'est la
 * premiere chose qui surprend a la saisie, d'ou l'ecran dedie et le rappel
 * en tete de formulaire.
 *
 * @extends AbstractCrudController<ServicePackage>
 */
class ServicePackageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ServicePackage::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('tarif')
            ->setEntityLabelInPlural('tarifs')
            ->setPageTitle(Crud::PAGE_INDEX, 'Tarifs')
            ->setHelp(
                Crud::PAGE_NEW,
                "Le prix affiche sur la carte d'une activite vient d'ici. "
                ."Tant qu'une activite n'a aucun tarif, sa carte s'affiche sans prix.",
            )
            ->setSearchFields(['name']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('service', 'Activite')
            ->setFormTypeOption('choice_label', 'title')
            ->formatValue(static fn (mixed $v, ServicePackage $p): ?string => $p->getService()?->getTitle());
        yield TextField::new('name', 'Nom de la formule')
            ->setHelp('Par exemple « Tarif adulte » ou « Formule famille ».');
        yield TextareaField::new('description', 'Description')->hideOnIndex();
        yield TextField::new('price', 'Prix');
        yield TextField::new('currency', 'Devise')->hideOnIndex();
        yield ChoiceField::new('pricingUnit', 'Le prix vaut pour')->setChoices([
            'Une personne' => PricingUnit::PerPerson,
            'Un groupe' => PricingUnit::PerGroup,
            'Un forfait' => PricingUnit::FlatRate,
        ]);
        yield IntegerField::new('deliveryDays', 'Delai en jours')->hideOnIndex();
    }
}
