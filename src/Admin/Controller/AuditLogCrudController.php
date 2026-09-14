<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Entity\AuditLog;
use App\Admin\Enum\AuditAction;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Journal des actions sensibles d'administration (§18.1 du CDC).
 *
 * TOUT EN LECTURE SEULE, y compris la SUPPRESSION : un journal d'audit
 * qu'on peut modifier ou effacer depuis l'écran qu'il est censé surveiller
 * ne prouve plus rien.
 *
 * @extends AbstractCrudController<AuditLog>
 */
class AuditLogCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $urls,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return AuditLog::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('entrée du journal')
            ->setEntityLabelInPlural('journal d\'audit')
            ->setPageTitle(Crud::PAGE_INDEX, 'Journal d\'audit')
            ->setHelp(
                Crud::PAGE_INDEX,
                'Historique des opérations sensibles : suspension, anonymisation, changement de rôle, traitement d\'un signalement. '
                .'Lecture seule, y compris la suppression — un journal qu\'on peut effacer ne prouve plus rien.',
            )
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('action');
    }

    public function configureFields(string $pageName): iterable
    {
        yield DateTimeField::new('createdAt', 'Date');

        yield AssociationField::new('actor', 'Auteur')
            ->setFormTypeOption('choice_label', 'email')
            ->formatValue(static fn (mixed $v, AuditLog $log): string => null !== $log->getActor() ? sprintf('%s %s', $log->getActor()->getFirstName(), $log->getActor()->getLastName()) : '—');

        yield ChoiceField::new('action', 'Action')
            ->setChoices(array_combine(
                array_map(self::actionLabel(...), AuditAction::cases()),
                AuditAction::cases(),
            ))
            ->formatValue(static fn (mixed $v, AuditLog $log): string => self::actionLabel($log->getAction()))
            ->renderAsBadges();

        yield TextField::new('targetLabel', 'Cible');
        yield TextField::new('details', 'Détails')->hideOnIndex();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_DETAIL, Action::EDIT)
            ->remove(Crud::PAGE_DETAIL, Action::DELETE);
    }

    public function new(AdminContext $context): KeyValueStore|Response
    {
        $this->addFlash('danger', 'Une entrée du journal ne se crée pas à la main : elle vient des actions sensibles réellement effectuées.');

        return $this->backToIndex();
    }

    public function edit(AdminContext $context): KeyValueStore|Response
    {
        $this->addFlash('danger', 'Le journal d\'audit ne se modifie jamais : il doit rester le reflet exact de ce qui s\'est passé.');

        return $this->backToIndex();
    }

    public function delete(AdminContext $context): KeyValueStore|Response
    {
        $this->addFlash('danger', 'Le journal d\'audit ne se supprime jamais, même une ligne : un journal qu\'on peut effacer ne prouve plus rien.');

        return $this->backToIndex();
    }

    private function backToIndex(): RedirectResponse
    {
        return new RedirectResponse(
            $this->urls->setController(self::class)->setAction(Action::INDEX)->generateUrl(),
        );
    }

    private static function actionLabel(AuditAction $action): string
    {
        return match ($action) {
            AuditAction::AccountSuspended => 'Compte suspendu',
            AuditAction::AccountAnonymized => 'Compte anonymisé',
            AuditAction::RoleChanged => 'Rôle modifié',
            AuditAction::ContentDeleted => 'Contenu supprimé',
            AuditAction::ReportProcessed => 'Signalement traité',
            AuditAction::SubscriptionManualAction => 'Action manuelle sur un abonnement',
        };
    }
}
