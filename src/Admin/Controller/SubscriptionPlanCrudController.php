<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Payment\Entity\SubscriptionPlan;
use App\Payment\Enum\BillingPeriod;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Offres d'abonnement professionnel (§17.1, §18 du CDC — « Abonnements :
 * gérer les offres et consulter les états Stripe »).
 *
 * Le rapprochement avec Stripe se fait par `stripePriceId` : un Price créé
 * côté tableau de bord Stripe, recopié ici. Rien d'autre ne relie une offre à
 * Stripe — la logique de souscription vit dans SubscriptionGateway.
 *
 * @extends AbstractCrudController<SubscriptionPlan>
 */
class SubscriptionPlanCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return SubscriptionPlan::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('offre d\'abonnement')
            ->setEntityLabelInPlural('abonnements')
            ->setPageTitle(Crud::PAGE_INDEX, 'Abonnements professionnels')
            ->setHelp(
                Crud::PAGE_INDEX,
                'Seul modèle de revenu autorisé par le cahier des charges (§1.2, §3.2) : les prestations '
                .'et les activités privées ne sont jamais encaissées par la plateforme.',
            )
            ->setDefaultSort(['position' => 'ASC'])
            ->setSearchFields(['name']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nom de l\'offre');
        yield TextField::new('slug', 'Adresse')
            ->setHelp('Identifiant technique, utilisé dans le lien de souscription.')
            ->hideOnIndex();
        yield TextareaField::new('description', 'Description')->hideOnIndex();

        yield ChoiceField::new('billingPeriod', 'Périodicité')
            ->setChoices(['Mensuelle' => BillingPeriod::Monthly, 'Annuelle' => BillingPeriod::Yearly])
            ->formatValue(static fn (mixed $v, SubscriptionPlan $p): string => BillingPeriod::Yearly === $p->getBillingPeriod() ? 'Annuelle' : 'Mensuelle');

        yield TextField::new('priceAmount', 'Prix');
        yield TextField::new('currency', 'Devise')->hideOnIndex();

        yield IntegerField::new('maxRequestsPerMonth', 'Demandes / mois')
            ->setHelp('Laisser vide pour illimité.')
            ->hideOnIndex();
        yield IntegerField::new('maxResponsesPerMonth', 'Devis / mois')
            ->setHelp('Laisser vide pour illimité.')
            ->hideOnIndex();
        yield IntegerField::new('maxCategories', 'Catégories déclarables')
            ->setHelp('Laisser vide pour illimité.')
            ->hideOnIndex();

        yield BooleanField::new('featured', 'Mise en avant');
        yield BooleanField::new('advancedStatistics', 'Statistiques avancées')->hideOnIndex();

        yield TextField::new('stripePriceId', 'Price Stripe')
            ->setHelp('Identifiant du Price créé côté tableau de bord Stripe (price_...). Sans lui, la souscription réelle est impossible.')
            ->hideOnIndex();

        yield BooleanField::new('active', 'En vente');
        yield IntegerField::new('position', 'Ordre d\'affichage')->hideOnIndex();
    }
}
