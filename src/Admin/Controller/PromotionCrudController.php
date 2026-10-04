<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Catalog\Entity\Promotion;
use App\Catalog\Enum\PromotionKind;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Offres & promotions des professionnels (02/10) : modération (mise en
 * pause, correction) par l'équipe.
 *
 * @extends AbstractCrudController<Promotion>
 */
class PromotionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Promotion::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('offre')
            ->setEntityLabelInPlural('offres')
            ->setPageTitle(Crud::PAGE_INDEX, 'Offres & promotions des professionnels')
            ->setDefaultSort(['startsAt' => 'DESC'])
            ->setSearchFields(['title', 'subtitle']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('provider')->add('paused')->add('startsAt')->add('endsAt');
    }

    public function configureFields(string $pageName): iterable
    {
        $kinds = [];
        foreach (PromotionKind::cases() as $kind) {
            $kinds[$kind->label()] = $kind;
        }

        yield TextField::new('title', 'Offre');
        yield TextField::new('subtitle', 'Description')->hideOnIndex();
        yield AssociationField::new('provider', 'Professionnel')->setFormTypeOption('choice_label', 'displayName')
            ->formatValue(static fn (mixed $v, Promotion $p): string => (string) $p->getProvider()?->getDisplayName());
        yield AssociationField::new('service', 'Activité')->setFormTypeOption('choice_label', 'title')
            ->formatValue(static fn (mixed $v, Promotion $p): string => $p->getService()?->getTitle() ?? 'Toutes');
        yield ChoiceField::new('kind', 'Type')->setChoices($kinds)->formatValue(static fn (mixed $v, Promotion $p): string => $p->getKind()->label());
        yield IntegerField::new('discountPercent', 'Réduction (%)');
        yield DateTimeField::new('startsAt', 'Début');
        yield DateTimeField::new('endsAt', 'Fin');
        yield BooleanField::new('paused', 'En pause');
        yield IntegerField::new('viewsCount', 'Vues')->hideOnForm();
        yield IntegerField::new('clicksCount', 'Clics')->hideOnForm();
    }
}
