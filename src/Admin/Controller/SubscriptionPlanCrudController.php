<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Payment\Entity\SubscriptionPlan;
use App\Payment\Enum\BillingPeriod;
use App\Payment\Repository\SubscriptionPlanRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Offres d'abonnement professionnel (§17.1, §18 du CDC — « Abonnements :
 * gérer les offres et consulter les états Stripe »).
 *
 * Abonnements encaissés par TrouveMoi via Stripe (17/10). `stripePriceId`
 * est facultatif : sans lui, StripeSubscriptionGateway construit le prix
 * récurrent depuis le tarif et la périodicité saisis ici.
 *
 * Saisie tolérante (17/10, l'ajout d'une offre échouait) : prix à la
 * française (« 29,00 »), adresse générée depuis le nom, ordre facultatif.
 *
 * @extends AbstractCrudController<SubscriptionPlan>
 */
class SubscriptionPlanCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly SubscriptionPlanRepository $plans,
        private readonly SluggerInterface $slugger,
    ) {
    }

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
        yield TextField::new('name', 'Nom de l\'offre')
            ->setFormTypeOption('constraints', [new Assert\NotBlank(message: 'Donnez un nom à l\'offre.')]);
        yield TextField::new('slug', 'Adresse')
            ->setRequired(false)
            ->setFormTypeOption('empty_data', '')
            ->setHelp('Facultatif : laissé vide, il est généré depuis le nom (ex. « offre-pro »).')
            ->hideOnIndex();
        yield TextareaField::new('description', 'Description')->hideOnIndex();

        yield ChoiceField::new('billingPeriod', 'Périodicité')
            ->setChoices(['Mensuelle' => BillingPeriod::Monthly, 'Annuelle' => BillingPeriod::Yearly])
            ->formatValue(static fn (mixed $v, SubscriptionPlan $p): string => BillingPeriod::Yearly === $p->getBillingPeriod() ? 'Annuelle' : 'Mensuelle');

        yield NumberField::new('priceAmount', 'Prix (€)')
            ->setStoredAsString()
            ->setNumDecimals(2)
            ->setFormTypeOption('constraints', [new Assert\NotBlank(message: 'Indiquez un prix.'), new Assert\Positive(message: 'Le prix doit être supérieur à 0.')])
            ->setHelp('Prix TTC par période, ex. 29,00.');
        yield TextField::new('currency', 'Devise')
            ->setFormTypeOption('empty_data', 'EUR')
            ->setRequired(false)
            ->hideOnIndex();

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
            ->setRequired(false)
            ->setHelp('Facultatif : identifiant d\'un Price créé dans le tableau de bord Stripe (price_...). Laissé vide, le prix ci-dessus est utilisé.')
            ->hideOnIndex();

        yield BooleanField::new('active', 'En vente');
        yield IntegerField::new('position', 'Ordre d\'affichage')
            ->setRequired(false)
            ->setFormTypeOption('empty_data', '0')
            ->hideOnIndex();
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->normalize($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->normalize($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    /** Adresse générée et rendue unique, devise en majuscules. */
    private function normalize(mixed $plan): void
    {
        if (!$plan instanceof SubscriptionPlan) {
            return;
        }

        $plan->setCurrency(strtoupper(trim($plan->getCurrency())) ?: 'EUR');

        $base = $this->slugger->slug('' !== trim($plan->getSlug()) ? $plan->getSlug() : $plan->getName())->lower()->toString() ?: 'offre';
        $slug = $base;
        for ($i = 2; null !== ($other = $this->plans->findOneBy(['slug' => $slug])) && $other !== $plan; ++$i) {
            $slug = $base.'-'.$i;
        }
        $plan->setSlug($slug);
    }
}
