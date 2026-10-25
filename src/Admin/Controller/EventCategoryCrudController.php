<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Event\Entity\EventCategory;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;

/** @extends AbstractCrudController<EventCategory> */
#[IsGranted('ROLE_ADMIN')]
class EventCategoryCrudController extends AbstractCrudController
{
    use UniqueSlugCrudTrait;

    protected function slugSourceField(): string
    {
        return 'name';
    }

    public static function getEntityFqcn(): string
    {
        return EventCategory::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('catégorie d’événement')->setEntityLabelInPlural('catégories d’événements')
            ->setPageTitle(Crud::PAGE_INDEX, 'Catégories des événements')->setDefaultSort(['position' => 'ASC', 'name' => 'ASC'])->setSearchFields(['name', 'slug']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nom')->setFormTypeOption('constraints', [new Assert\NotBlank(), new Assert\Length(max: 80)]);
        yield SlugField::new('slug', 'Identifiant')->setTargetFieldName('name')->setFormTypeOption('constraints', [new Assert\Length(max: 100)]);
        yield ChoiceField::new('color', 'Couleur du badge')->setChoices(['Bleu' => 'blue', 'Orange' => 'orange', 'Vert' => 'green', 'Violet' => 'violet', 'Bleu foncé' => 'navy']);
        yield IntegerField::new('position', 'Ordre d’affichage');
    }
}
