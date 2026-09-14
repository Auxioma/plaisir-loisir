<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Entity\Report;
use App\Admin\Enum\ReportReason;
use App\Admin\Enum\ReportStatus;
use App\Admin\Enum\ReportSubjectType;
use App\Admin\Service\ReportService;
use App\User\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signalements (§16.4, §18 du CDC : « traiter, classer, commenter et
 * historiser les décisions »).
 *
 * Chaque décision (traiter/classer) passe par ReportService, qui écrit
 * aussitôt dans AuditLog — jamais un simple changement de statut ici, sans
 * quoi la décision de modération ne laisserait aucune trace (§18.1).
 *
 * @extends AbstractCrudController<Report>
 */
class ReportCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ReportService $reportService,
        private readonly Security $security,
        private readonly AdminUrlGenerator $urls,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Report::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('signalement')
            ->setEntityLabelInPlural('signalements')
            ->setPageTitle(Crud::PAGE_INDEX, 'Signalements')
            ->setHelp(
                Crud::PAGE_INDEX,
                'Les plus anciens en attente d\'abord. « Traiter » = le signalement est fondé et une action a été prise ailleurs '
                .'(suspension du compte, retrait de contenu…) ; « Classer sans suite » = examiné, aucune action nécessaire.',
            )
            ->setDefaultSort(['createdAt' => 'ASC'])
            ->setSearchFields(['subjectLabel']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('status')
            ->add('subjectType')
            ->add('reason');
    }

    public function configureFields(string $pageName): iterable
    {
        yield DateTimeField::new('createdAt', 'Signalé le')->hideOnForm();

        yield AssociationField::new('reporter', 'Signalé par')
            ->setFormTypeOption('choice_label', static fn (User $u): string => sprintf('%s %s (%s)', $u->getFirstName(), $u->getLastName(), $u->getEmail()))
            ->formatValue(static fn (mixed $v, Report $r): string => null !== $r->getReporter() ? sprintf('%s %s', $r->getReporter()->getFirstName(), $r->getReporter()->getLastName()) : '—')
            ->hideOnIndex();

        yield ChoiceField::new('subjectType', 'Type de contenu')
            ->setChoices(array_combine(
                array_map(self::subjectTypeLabel(...), ReportSubjectType::cases()),
                ReportSubjectType::cases(),
            ))
            ->formatValue(static fn (mixed $v, Report $r): string => self::subjectTypeLabel($r->getSubjectType()));

        yield TextField::new('subjectLabel', 'Contenu visé')
            ->setHelp('Intitulé figé au moment du signalement : reste lisible même si le contenu a depuis changé ou disparu.');

        yield ChoiceField::new('reason', 'Motif')
            ->setChoices(array_combine(
                array_map(self::reasonLabel(...), ReportReason::cases()),
                ReportReason::cases(),
            ))
            ->formatValue(static fn (mixed $v, Report $r): string => self::reasonLabel($r->getReason()));

        yield TextareaField::new('message', 'Message du signalant')->hideOnIndex();

        yield ChoiceField::new('status', 'Statut')
            ->setChoices(array_combine(
                array_map(self::statusLabel(...), ReportStatus::cases()),
                ReportStatus::cases(),
            ))
            ->formatValue(static fn (mixed $v, Report $r): string => self::statusLabel($r->getStatus()))
            ->renderAsBadges()
            ->hideOnForm();

        yield TextareaField::new('moderatorNote', 'Note de modération')->hideOnIndex();
        yield DateTimeField::new('processedAt', 'Traité le')->hideOnIndex()->hideOnForm();
    }

    public function configureActions(Actions $actions): Actions
    {
        $traiter = Action::new('resolve', 'Traiter', 'fa fa-check')
            ->linkToCrudAction('resolve')
            ->displayIf(static fn (Report $r): bool => ReportStatus::Pending === $r->getStatus());

        $classer = Action::new('dismiss', 'Classer sans suite', 'fa fa-xmark')
            ->linkToCrudAction('dismiss')
            ->displayIf(static fn (Report $r): bool => ReportStatus::Pending === $r->getStatus());

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $traiter)
            ->add(Crud::PAGE_INDEX, $classer)
            ->add(Crud::PAGE_DETAIL, $traiter)
            ->add(Crud::PAGE_DETAIL, $classer)
            // Un signalement vient d'un membre : on le lit et on décide, on ne
            // le réécrit pas, même principe que PartnerApplicationCrudController.
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_DETAIL, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_DETAIL, Action::DELETE);
    }

    /**
     * @param AdminContext<Report> $context
     */
    #[AdminRoute(path: '/{entityId}/resolve', name: 'resolve')]
    public function resolve(AdminContext $context): Response
    {
        return $this->decide($context, resolve: true);
    }

    /**
     * @param AdminContext<Report> $context
     */
    #[AdminRoute(path: '/{entityId}/dismiss', name: 'dismiss')]
    public function dismiss(AdminContext $context): Response
    {
        return $this->decide($context, resolve: false);
    }

    /**
     * @param AdminContext<Report> $context
     */
    private function decide(AdminContext $context, bool $resolve): Response
    {
        $report = $context->getEntity()->getInstance();

        if (!$report instanceof Report) {
            throw $this->createNotFoundException('Signalement introuvable.');
        }

        $moderator = $this->security->getUser();

        if (!$moderator instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (ReportStatus::Pending === $report->getStatus()) {
            if ($resolve) {
                $this->reportService->resolve($report, $moderator, null);
                $this->addFlash('success', 'Signalement traité.');
            } else {
                $this->reportService->dismiss($report, $moderator, null);
                $this->addFlash('success', 'Signalement classé sans suite.');
            }
        }

        return $this->backToIndex();
    }

    public function new(AdminContext $context): KeyValueStore|Response
    {
        $this->addFlash('danger', 'Un signalement ne se crée pas depuis le back-office : il vient d\'un membre du site.');

        return $this->backToIndex();
    }

    public function edit(AdminContext $context): KeyValueStore|Response
    {
        $this->addFlash('danger', 'Un signalement ne se modifie pas : utilisez « Traiter » ou « Classer sans suite ».');

        return $this->backToIndex();
    }

    private function backToIndex(): RedirectResponse
    {
        return new RedirectResponse(
            $this->urls->setController(self::class)->setAction(Action::INDEX)->generateUrl(),
        );
    }

    private static function subjectTypeLabel(ReportSubjectType $type): string
    {
        return match ($type) {
            ReportSubjectType::Profile => 'Profil',
            ReportSubjectType::Message => 'Message',
            ReportSubjectType::Review => 'Avis',
            ReportSubjectType::Activity => 'Activité',
            ReportSubjectType::Photo => 'Photo ou document',
            ReportSubjectType::Behavior => 'Comportement',
        };
    }

    private static function reasonLabel(ReportReason $reason): string
    {
        return match ($reason) {
            ReportReason::Scam => 'Arnaque',
            ReportReason::Spam => 'Spam',
            ReportReason::DisguisedCommercialActivity => 'Activité commerciale déguisée',
            ReportReason::OffensiveContent => 'Contenu offensant',
            ReportReason::DangerousActivity => 'Activité dangereuse',
            ReportReason::Other => 'Autre motif',
        };
    }

    private static function statusLabel(ReportStatus $status): string
    {
        return match ($status) {
            ReportStatus::Pending => 'En attente',
            ReportStatus::Resolved => 'Traité',
            ReportStatus::Dismissed => 'Classé sans suite',
        };
    }
}
