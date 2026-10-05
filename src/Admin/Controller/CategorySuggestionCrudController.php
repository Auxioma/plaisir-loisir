<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Catalog\Entity\CategorySuggestion;
use App\Catalog\Service\CategorySuggestionService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Catégories proposées depuis les assistants de création (05/10).
 *
 * « Modifier » permet de corriger le nom et de choisir une catégorie
 * parente avant de valider. « Valider » crée la catégorie (aussitôt
 * proposée dans les listes) et prévient l'auteur ; « Refuser » le prévient
 * aussi, avec la note éventuellement saisie.
 *
 * @extends AbstractCrudController<CategorySuggestion>
 */
class CategorySuggestionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return CategorySuggestion::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('proposition de catégorie')
            ->setEntityLabelInPlural('propositions de catégories')
            ->setPageTitle(Crud::PAGE_INDEX, 'Catégories proposées')
            ->setDefaultSort(['status' => 'DESC', 'createdAt' => 'DESC'])
            ->setSearchFields(['name', 'description']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('status')->add('context');
    }

    public function configureActions(Actions $actions): Actions
    {
        $approve = Action::new('approve', 'Valider', 'fa fa-check')
            ->linkToCrudAction('approve')
            ->renderAsForm()
            ->addCssClass('btn btn-success')
            ->displayIf(static fn (CategorySuggestion $s): bool => $s->isPending());
        $reject = Action::new('reject', 'Refuser', 'fa fa-xmark')
            ->linkToCrudAction('reject')
            ->renderAsForm()
            ->addCssClass('btn btn-danger')
            ->displayIf(static fn (CategorySuggestion $s): bool => $s->isPending());

        return $actions
            ->disable(Action::NEW)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $approve)->add(Crud::PAGE_INDEX, $reject)
            ->add(Crud::PAGE_DETAIL, $approve)->add(Crud::PAGE_DETAIL, $reject)
            ->add(Crud::PAGE_EDIT, $approve)->add(Crud::PAGE_EDIT, $reject);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Catégorie proposée');
        yield TextareaField::new('description', 'Description')->hideOnIndex();
        yield ChoiceField::new('context', 'Depuis')->setChoices(['Activité entre membres' => 'private', 'Espace pro' => 'pro'])->setFormTypeOption('disabled', true);
        yield AssociationField::new('requestedBy', 'Proposée par')->setFormTypeOption('disabled', true)
            ->formatValue(static fn (mixed $v, CategorySuggestion $s): string => $s->getRequestedBy() ? $s->getRequestedBy()->getFirstName().' '.$s->getRequestedBy()->getLastName().' ('.$s->getRequestedBy()->getEmail().')' : '—');
        yield AssociationField::new('parent', 'Ranger sous (facultatif)')->setRequired(false)->setFormTypeOption('choice_label', 'name')
            ->setHelp('Laissez vide pour en faire une catégorie principale.');
        yield ChoiceField::new('status', 'Statut')->setChoices(['En attente' => 'pending', 'Validée' => 'approved', 'Refusée' => 'rejected'])
            ->renderAsBadges(['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'])->setFormTypeOption('disabled', true);
        yield TextField::new('adminNote', 'Note (envoyée en cas de refus)')->hideOnIndex();
        yield AssociationField::new('category', 'Catégorie créée')->hideOnForm()->formatValue(static fn (mixed $v, CategorySuggestion $s): string => $s->getCategory()?->getName() ?? '—');
        yield DateTimeField::new('createdAt', 'Proposée le')->hideOnForm();
        yield DateTimeField::new('decidedAt', 'Traitée le')->hideOnForm();
    }

    /**
     * @param AdminContext<CategorySuggestion> $context
     */
    #[AdminRoute(path: '/{entityId}/approve', name: 'approve', options: ['methods' => ['POST']])]
    public function approve(AdminContext $context, CategorySuggestionService $service, AdminUrlGenerator $urls): Response
    {
        $suggestion = $context->getEntity()->getInstance();
        if (!$suggestion instanceof CategorySuggestion) {
            throw $this->createNotFoundException();
        }
        try {
            $category = $service->approve($suggestion);
            $this->addFlash('success', sprintf('Catégorie « %s » créée ; son auteur est prévenu.', $category->getName()));
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return new RedirectResponse($urls->setController(self::class)->setAction(Action::INDEX)->generateUrl());
    }

    /**
     * @param AdminContext<CategorySuggestion> $context
     */
    #[AdminRoute(path: '/{entityId}/reject', name: 'reject', options: ['methods' => ['POST']])]
    public function reject(AdminContext $context, CategorySuggestionService $service, AdminUrlGenerator $urls): Response
    {
        $suggestion = $context->getEntity()->getInstance();
        if (!$suggestion instanceof CategorySuggestion) {
            throw $this->createNotFoundException();
        }
        try {
            $service->reject($suggestion, $suggestion->getAdminNote());
            $this->addFlash('success', sprintf('Proposition « %s » refusée ; son auteur est prévenu.', $suggestion->getName()));
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return new RedirectResponse($urls->setController(self::class)->setAction(Action::INDEX)->generateUrl());
    }
}
