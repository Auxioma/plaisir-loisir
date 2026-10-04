<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Payment\Entity\Payment;
use App\Payment\Enum\PaymentStatus;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Paiements des réservations (« Revenus & Paiements » côté pro, 02/10).
 * Lecture seule hors statut : les montants viennent du prestataire de
 * paiement.
 *
 * @extends AbstractCrudController<Payment>
 */
class PaymentCrudController extends AbstractCrudController
{
    private const STATUSES = ['En attente' => PaymentStatus::Pending, 'Payé' => PaymentStatus::Paid, 'Échoué' => PaymentStatus::Failed, 'Remboursé' => PaymentStatus::Refunded];

    public static function getEntityFqcn(): string
    {
        return Payment::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('paiement')
            ->setEntityLabelInPlural('paiements')
            ->setPageTitle(Crud::PAGE_INDEX, 'Paiements')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['reference']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(ChoiceFilter::new('status', 'Statut')->setChoices(self::STATUSES))->add('createdAt');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_DETAIL, Action::DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield DateTimeField::new('createdAt', 'Date')->hideOnForm();
        yield AssociationField::new('booking', 'Réservation')->hideOnForm()
            ->formatValue(static fn (mixed $v, Payment $p): string => '#'.$p->getBooking()?->getReference().' — '.$p->getBooking()?->getService()?->getTitle());
        yield MoneyField::new('amount', 'Montant')->setCurrency('EUR')->setStoredAsCents(false)->hideOnForm();
        yield ChoiceField::new('method', 'Type')->setChoices(['Carte' => 'card', 'PayPal' => 'paypal', 'Virement' => 'transfer']);
        yield ChoiceField::new('status', 'Statut')->setChoices(self::STATUSES)
            ->formatValue(static fn (mixed $v, Payment $p): string => (string) array_search($p->getStatus(), self::STATUSES, true))
            ->renderAsBadges(['pending' => 'warning', 'paid' => 'success', 'failed' => 'danger', 'refunded' => 'secondary']);
        yield TextField::new('reference', 'Référence prestataire')->hideOnForm()->hideOnIndex();
    }

    /** Pas de création manuelle : un paiement vient du prestataire de paiement. */
    public function new(AdminContext $context): KeyValueStore|Response
    {
        $this->addFlash('danger', 'Un paiement est enregistré par le prestataire de paiement, pas à la main.');

        return new RedirectResponse($this->container->get(AdminUrlGenerator::class)->setController(self::class)->setAction(Action::INDEX)->generateUrl());
    }
}
