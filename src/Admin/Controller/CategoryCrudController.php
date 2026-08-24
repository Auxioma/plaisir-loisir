<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Catalog\Entity\Category;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Catégories du catalogue.
 *
 * Le slug n'est pas cosmétique : les pastilles de filtre du catalogue
 * fabriquent leur lien avec (/activites?categorie=mon-slug). Le changer
 * casserait les liens déjà partagés.
 *
 * @extends AbstractCrudController<Category>
 */
class CategoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Category::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('catégorie')
            ->setEntityLabelInPlural('catégories')
            ->setPageTitle(Crud::PAGE_INDEX, 'Catégories')
            ->setDefaultSort(['position' => 'ASC', 'name' => 'ASC'])
            ->setSearchFields(['name']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nom');
        yield SlugField::new('slug', 'Identifiant dans les filtres')
            ->setTargetFieldName('name')
            ->setHelp('Employé par les pastilles de filtre : /activites?categorie=mon-slug.');
        yield AssociationField::new('parent', 'Catégorie parente')
            ->setFormTypeOption('choice_label', 'name')
            ->formatValue(static fn (mixed $v, Category $c): ?string => $c->getParent()?->getName())
            ->setHelp('Laissez vide pour une catégorie de premier niveau.')
            ->hideOnIndex();
        yield IntegerField::new('position', "Ordre d'affichage");
    }
}
