<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Booking\Entity\Booking;
use App\Booking\Enum\BookingStatus;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réservations (espace pro « Réservations », 02/10) : consultation et
 * correction du statut ou de la séance par l'équipe. La création reste
 * réservée au tunnel de réservation (prix figé dans les lignes).
 *
 * @extends AbstractCrudController<Booking>
 */
class BookingCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Booking::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('réservation')
            ->setEntityLabelInPlural('réservations')
            ->setPageTitle(Crud::PAGE_INDEX, 'Réservations')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['client.email', 'client.lastName', 'service.title']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status', 'Statut')->setChoices(self::choices()))
            ->add('service')
            ->add('startsAt')
            ->add('createdAt');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->remove(Crud::PAGE_INDEX, Action::NEW);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('reference', 'Réf.')->onlyOnIndex();
        yield DateTimeField::new('createdAt', 'Réservée le')->hideOnForm();
        yield AssociationField::new('client', 'Client')->hideOnForm()
            ->formatValue(static fn (mixed $v, Booking $b): string => trim($b->getClient()?->getFirstName().' '.$b->getClient()?->getLastName()).' — '.$b->getClient()?->getEmail());
        yield AssociationField::new('service', 'Activité')->hideOnForm()
            ->formatValue(static fn (mixed $v, Booking $b): string => (string) $b->getService()?->getTitle());
        yield DateTimeField::new('startsAt', 'Séance');
        yield IntegerField::new('participants', 'Participants');
        yield MoneyField::new('totalPrice', 'Montant')->setCurrency('EUR')->setStoredAsCents(false)->hideOnForm();
        yield ChoiceField::new('status', 'Statut')->setChoices(self::choices())
            ->formatValue(static fn (mixed $v, Booking $b): string => $b->getStatus()->label())
            ->renderAsBadges(['pending' => 'info', 'confirmed' => 'success', 'in_progress' => 'warning', 'completed' => 'primary', 'cancelled' => 'danger', 'refunded' => 'secondary']);
    }

    /** @return array<string, BookingStatus> */
    private static function choices(): array
    {
        $choices = [];
        foreach (BookingStatus::cases() as $case) {
            $choices[$case->label()] = $case;
        }

        return $choices;
    }

    /** Pas de création manuelle : une réservation passe par le tunnel de réservation. */
    public function new(AdminContext $context): KeyValueStore|Response
    {
        $this->addFlash('danger', 'Une réservation se crée depuis la fiche d’une activité (prix figé à l’achat).');

        return new RedirectResponse($this->container->get(AdminUrlGenerator::class)->setController(self::class)->setAction(Action::INDEX)->generateUrl());
    }
}
