<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Review\Entity\Review;
use App\Review\Enum\ReviewStatus;
use App\Review\Service\ReviewModerationService;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Modération des avis (§16.2, §18 du CDC) : approuver ou rejeter un avis
 * publié automatiquement à son dépôt (Review::$status vaut « published » par
 * défaut) — la modération intervient donc après coup, comme pour les
 * signalements (voir ReportCrudController, même esprit).
 *
 * @extends AbstractCrudController<Review>
 */
class ReviewCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ReviewModerationService $moderation,
        private readonly AdminUrlGenerator $urls,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Review::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('avis')
            ->setEntityLabelInPlural('avis')
            ->setPageTitle(Crud::PAGE_INDEX, 'Avis')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('status');
    }

    public function configureFields(string $pageName): iterable
    {
        yield DateTimeField::new('createdAt', 'Déposé le')->hideOnForm();

        yield AssociationField::new('author', 'Client')
            ->formatValue(static fn (mixed $v, Review $r): string => null !== $r->getAuthor() ? sprintf('%s %s', $r->getAuthor()->getFirstName(), $r->getAuthor()->getLastName()) : '—')
            ->hideOnForm();

        yield AssociationField::new('provider', 'Professionnel noté')
            ->formatValue(static fn (mixed $v, Review $r): string => $r->getProvider()?->getDisplayName() ?? '—')
            ->hideOnForm();

        yield IntegerField::new('rating', 'Note')->hideOnForm();
        yield TextareaField::new('comment', 'Commentaire')->hideOnIndex()->hideOnForm();

        yield ChoiceField::new('status', 'Statut')
            ->setChoices(array_combine(
                array_map(self::statusLabel(...), ReviewStatus::cases()),
                ReviewStatus::cases(),
            ))
            ->formatValue(static fn (mixed $v, Review $r): string => self::statusLabel($r->getStatus()))
            ->renderAsBadges()
            ->hideOnForm();

        yield TextareaField::new('providerReply', 'Réponse du professionnel')->hideOnIndex()->hideOnForm();
    }

    public function configureActions(Actions $actions): Actions
    {
        $approuver = Action::new('approve', 'Approuver', 'fa fa-check')
            ->linkToCrudAction('approve')
            ->displayIf(static fn (Review $r): bool => ReviewStatus::Published !== $r->getStatus());

        $rejeter = Action::new('reject', 'Rejeter', 'fa fa-xmark')
            ->linkToCrudAction('reject')
            ->displayIf(static fn (Review $r): bool => ReviewStatus::Rejected !== $r->getStatus());

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $approuver)
            ->add(Crud::PAGE_INDEX, $rejeter)
            ->add(Crud::PAGE_DETAIL, $approuver)
            ->add(Crud::PAGE_DETAIL, $rejeter)
            // Un avis vient d'un client : on le lit et on modère, on ne le
            // réécrit pas, même principe que ReportCrudController.
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_DETAIL, Action::EDIT)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_DETAIL, Action::DELETE);
    }

    /**
     * @param AdminContext<Review> $context
     */
    #[AdminRoute(path: '/{entityId}/approve', name: 'approve')]
    public function approve(AdminContext $context): Response
    {
        $review = $this->reviewOrFail($context);
        $this->moderation->approve($review);
        $this->addFlash('success', 'Avis approuvé.');

        return $this->backToIndex();
    }

    /**
     * @param AdminContext<Review> $context
     */
    #[AdminRoute(path: '/{entityId}/reject', name: 'reject')]
    public function reject(AdminContext $context): Response
    {
        $review = $this->reviewOrFail($context);
        $this->moderation->reject($review);
        $this->addFlash('success', 'Avis rejeté.');

        return $this->backToIndex();
    }

    public function new(AdminContext $context): KeyValueStore|Response
    {
        $this->addFlash('danger', 'Un avis ne se crée pas depuis le back-office : il vient d\'un client.');

        return $this->backToIndex();
    }

    public function edit(AdminContext $context): KeyValueStore|Response
    {
        $this->addFlash('danger', 'Un avis ne se modifie pas : utilisez « Approuver » ou « Rejeter ».');

        return $this->backToIndex();
    }

    /**
     * @param AdminContext<Review> $context
     */
    private function reviewOrFail(AdminContext $context): Review
    {
        $review = $context->getEntity()->getInstance();

        if (!$review instanceof Review) {
            throw $this->createNotFoundException('Avis introuvable.');
        }

        return $review;
    }

    private function backToIndex(): RedirectResponse
    {
        return new RedirectResponse(
            $this->urls->setController(self::class)->setAction(Action::INDEX)->generateUrl(),
        );
    }

    private static function statusLabel(ReviewStatus $status): string
    {
        return match ($status) {
            ReviewStatus::Published => 'Publié',
            ReviewStatus::Pending => 'En attente',
            ReviewStatus::Rejected => 'Rejeté',
        };
    }
}
