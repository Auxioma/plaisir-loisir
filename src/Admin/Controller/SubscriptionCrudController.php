<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Payment\Entity\Subscription;
use App\Payment\Enum\SubscriptionStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * États d'abonnement (§18 du CDC : « Abonnements — gérer les offres et
 * consulter les états Stripe »).
 *
 * TOUT EN LECTURE SEULE, même principe que PartnerApplicationCrudController :
 * une ligne ici reflète l'état chez Stripe (synchronisée par webhook). La
 * modifier depuis le back-office la rendrait différente de la réalité
 * Stripe, silencieusement, jusqu'au prochain événement reçu.
 *
 * @extends AbstractCrudController<Subscription>
 */
class SubscriptionCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $urls,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Subscription::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('abonnement en cours')
            ->setEntityLabelInPlural('abonnements en cours')
            ->setPageTitle(Crud::PAGE_INDEX, 'États d\'abonnement')
            ->setHelp(
                Crud::PAGE_INDEX,
                'Consultation seule : ces lignes reflètent l\'état chez Stripe, synchronisé par webhook. '
                .'Pour créer ou modifier une OFFRE, voir l\'écran « Abonnements professionnels ».',
            )
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('status');
    }

    public function configureFields(string $pageName): iterable
    {
        yield DateTimeField::new('createdAt', 'Souscrit le')->hideOnForm();

        yield AssociationField::new('provider', 'Prestataire')
            ->setFormTypeOption('choice_label', 'displayName')
            ->formatValue(static fn (mixed $v, Subscription $s): string => $s->getProvider()?->getDisplayName() ?? '—');

        yield AssociationField::new('plan', 'Offre')
            ->setFormTypeOption('choice_label', 'name')
            ->formatValue(static fn (mixed $v, Subscription $s): string => $s->getPlan()?->getName() ?? '—');

        yield ChoiceField::new('status', 'Statut')
            ->setChoices(array_combine(
                array_map(self::statusLabel(...), SubscriptionStatus::cases()),
                SubscriptionStatus::cases(),
            ))
            ->formatValue(static fn (mixed $v, Subscription $s): string => self::statusLabel($s->getStatus()))
            ->renderAsBadges();

        yield BooleanField::new('cancelAtPeriodEnd', 'Résiliation programmée')->hideOnIndex();
        yield DateTimeField::new('currentPeriodEnd', 'Fin de période en cours')->hideOnIndex();

        yield TextField::new('stripeCustomerId', 'Client Stripe')->hideOnIndex();
        yield TextField::new('stripeSubscriptionId', 'Abonnement Stripe')->hideOnIndex();
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
        $this->addFlash('danger', 'Un abonnement ne se crée pas depuis le back-office : il vient de la souscription du prestataire.');

        return $this->backToIndex();
    }

    public function edit(AdminContext $context): KeyValueStore|Response
    {
        $this->addFlash('danger', 'Un abonnement ne se modifie pas ici : il doit rester le reflet exact de son état chez Stripe.');

        return $this->backToIndex();
    }

    private static function statusLabel(SubscriptionStatus $status): string
    {
        return match ($status) {
            SubscriptionStatus::Incomplete => 'En attente',
            SubscriptionStatus::Active => 'Actif',
            SubscriptionStatus::PastDue => 'Paiement en retard',
            SubscriptionStatus::Cancelled => 'Résilié',
        };
    }

    private function backToIndex(): RedirectResponse
    {
        return new RedirectResponse(
            $this->urls->setController(self::class)->setAction(Action::INDEX)->generateUrl(),
        );
    }
}
