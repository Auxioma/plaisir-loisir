<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Catalog\Entity\GiftCard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Bons cadeaux vendus (04/10) : suivi, envoi postal, passage en « utilisé ».
 *
 * @extends AbstractCrudController<GiftCard>
 */
class GiftCardCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return GiftCard::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('bon cadeau')
            ->setEntityLabelInPlural('bons cadeaux')
            ->setPageTitle(Crud::PAGE_INDEX, 'Bons cadeaux vendus')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['code', 'buyerName', 'buyerEmail', 'recipientName', 'recipientEmail', 'label']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::NEW, Action::DELETE)->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('status')->add('delivery')->add('createdAt');
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('code', 'Code')->setFormTypeOption('disabled', true);
        yield TextField::new('label', 'Bon')->setFormTypeOption('disabled', true);
        yield MoneyField::new('amount', 'Montant')->setCurrency('EUR')->setStoredAsCents(false)->setFormTypeOption('disabled', true);
        yield ChoiceField::new('status', 'Statut')->setChoices(['En attente de paiement' => GiftCard::STATUS_PENDING, 'Payé' => GiftCard::STATUS_PAID, 'Utilisé' => GiftCard::STATUS_REDEEMED, 'Annulé' => GiftCard::STATUS_CANCELLED]);
        yield ChoiceField::new('delivery', 'Envoi')->setChoices(['E-mail' => GiftCard::DELIVERY_EMAIL, 'À imprimer' => GiftCard::DELIVERY_PRINT, 'Postal' => GiftCard::DELIVERY_POSTAL])->setFormTypeOption('disabled', true);
        yield TextField::new('buyerName', 'Acheteur')->setFormTypeOption('disabled', true);
        yield EmailField::new('buyerEmail', 'E-mail acheteur')->hideOnIndex()->setFormTypeOption('disabled', true);
        yield TextField::new('buyerPhone', 'Téléphone')->hideOnIndex()->setFormTypeOption('disabled', true);
        yield TextField::new('recipientName', 'Bénéficiaire')->setFormTypeOption('disabled', true);
        yield EmailField::new('recipientEmail', 'E-mail bénéficiaire')->hideOnIndex()->setFormTypeOption('disabled', true);
        yield TextareaField::new('postalAddress', 'Adresse postale')->hideOnIndex()->setFormTypeOption('disabled', true);
        yield TextareaField::new('message', 'Message')->hideOnIndex()->setFormTypeOption('disabled', true);
        yield DateTimeField::new('createdAt', 'Créé le')->hideOnForm();
        yield DateTimeField::new('paidAt', 'Payé le')->hideOnForm();
        yield DateTimeField::new('expiresAt', 'Valable jusqu’au')->hideOnForm();
    }
}
