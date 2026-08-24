<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Catalog\Entity\Service;
use App\Catalog\Enum\ActivityLevel;
use App\Catalog\Enum\ActivityType;
use App\Catalog\Enum\BookingType;
use App\Catalog\Enum\CancellationPolicy;
use App\Catalog\Enum\OpeningPeriod;
use App\Catalog\Enum\ServiceStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Saisie des activités du catalogue.
 *
 * L'entité porte une quarantaine de champs. Les afficher à plat rendrait la
 * saisie impraticable : ils sont donc regroupés en onglets qui suivent l'ordre
 * de la fiche publique, pour qu'on retrouve à l'écran ce qu'on remplit.
 *
 * Ne sont PAS proposés à la saisie : la note moyenne et le nombre d'avis (ils
 * viennent des avis déposés), ainsi que les colonnes de recherche, qui sont
 * recalculées à la publication. Les rendre modifiables permettrait d'afficher
 * une note que personne n'a donnée.
 *
 * @extends AbstractCrudController<Service>
 */
class ServiceCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Service::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('activité')
            ->setEntityLabelInPlural('activités')
            ->setPageTitle(Crud::PAGE_INDEX, 'Activités du catalogue')
            ->setHelp(
                Crud::PAGE_NEW,
                'Une activité n\'apparaît en ligne que si son statut est « publiée ». '
                .'Pensez ensuite à lui ajouter une photo de couverture et un tarif.',
            )
            // La maquette ordonne les cartes à la main : le classement suit
            // donc « position », et non la date de création.
            ->setDefaultSort(['position' => 'ASC', 'title' => 'ASC'])
            ->setSearchFields(['title', 'placeLabel', 'city', 'shortDescription']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addTab('Présentation');
        yield TextField::new('title', 'Titre');
        yield SlugField::new('slug', 'Adresse de la page')
            ->setTargetFieldName('title')
            ->setHelp('Ce texte apparaît dans l\'adresse : /activites/mon-titre. Évitez de le changer une fois la page en ligne, les liens partagés cesseraient de fonctionner.')
            ->hideOnIndex();
        yield TextField::new('subtitle', 'Sous-titre')->hideOnIndex();
        yield TextareaField::new('shortDescription', 'Accroche')
            ->setHelp('Une ou deux phrases, affichées sur la carte du catalogue.')
            ->hideOnIndex();
        yield TextareaField::new('description', 'Description')->hideOnIndex();

        yield FormField::addTab('Classement');
        yield AssociationField::new('category', 'Catégorie')
            // Les entités du domaine n'ont pas de __toString : c'est au
            // formulaire de dire quel champ afficher, plutôt qu'au métier
            // de porter une méthode d'affichage.
            ->setFormTypeOption('choice_label', 'name');
        yield AssociationField::new('destination', 'Destination')
            ->setFormTypeOption('choice_label', 'name')->hideOnIndex();
        yield ChoiceField::new('status', 'Statut')
            ->setChoices(self::choices(ServiceStatus::cases()))
            ->renderAsBadges();
        yield TextField::new('badge', 'Badge')
            ->setHelp('Pastille affichée sur la carte : Bestseller, Populaire, Nouvelle activité… Laissez vide s\'il n\'y en a pas.')
            ->hideOnIndex();
        yield IntegerField::new('position', 'Ordre d\'affichage')
            ->setHelp('Le plus petit nombre passe en premier.');

        yield FormField::addTab('Lieu');
        yield TextField::new('placeLabel', 'Lieu affiché')
            ->setHelp('Tel qu\'il apparaît sur la carte, par exemple « Annecy, Haute-Savoie ».');
        yield TextField::new('address', 'Adresse')->hideOnIndex();
        yield TextField::new('city', 'Ville')->hideOnIndex();
        yield TextField::new('postalCode', 'Code postal')->hideOnIndex();
        yield TextField::new('country', 'Pays')->hideOnIndex();
        yield TextField::new('meetingPoint', 'Point de rendez-vous')->hideOnIndex();
        yield TextField::new('latitude', 'Latitude')
            ->setHelp('Sert à placer l\'activité sur la carte. Laissez vide si vous ne l\'avez pas.')
            ->hideOnIndex();
        yield TextField::new('longitude', 'Longitude')->hideOnIndex();

        yield FormField::addTab('Déroulé');
        yield TextField::new('durationLabel', 'Durée affichée')
            ->setHelp('Par exemple « 2h30 ».')
            ->hideOnIndex();
        yield IntegerField::new('durationMinutes', 'Durée en minutes')->hideOnIndex();
        yield IntegerField::new('capacity', 'Nombre de places')->hideOnIndex();
        yield IntegerField::new('minimumAge', 'Âge minimum')->hideOnIndex();
        yield ChoiceField::new('level', 'Niveau requis')
            ->setChoices(self::choices(ActivityLevel::cases()))->hideOnIndex();
        yield ChoiceField::new('activityType', 'Type d\'activité')
            ->setChoices(self::choices(ActivityType::cases()))->hideOnIndex();
        yield ChoiceField::new('openingPeriod', 'Période d\'ouverture')
            ->setChoices(self::choices(OpeningPeriod::cases()))->hideOnIndex();
        yield TextareaField::new('programme', 'Programme')->hideOnIndex();
        yield TextareaField::new('included', 'Ce qui est compris')->hideOnIndex();
        yield TextField::new('audience', 'Public visé')->hideOnIndex();

        yield FormField::addTab('Réservation');
        yield ChoiceField::new('bookingType', 'Mode de réservation')
            ->setChoices(self::choices(BookingType::cases()))->hideOnIndex();
        yield ChoiceField::new('cancellationPolicy', 'Conditions d\'annulation')
            ->setChoices(self::choices(CancellationPolicy::cases()))->hideOnIndex();
        yield TextField::new('currency', 'Devise')->hideOnIndex();
        yield AssociationField::new('provider', 'Prestataire')
            ->setFormTypeOption('choice_label', 'displayName')->hideOnIndex();
    }

    /**
     * Libellés français des énumérations.
     *
     * Les énumérations du domaine n'en portent pas : leurs valeurs ('draft',
     * 'all_levels') sont des identifiants techniques. Les traduire ICI plutôt
     * que dans le domaine évite de faire dépendre le métier d'un choix de
     * vocabulaire propre au back-office.
     */
    private const LABELS = [
        'draft' => 'Brouillon',
        'published' => 'Publiée',
        'archived' => 'Archivée',
        'beginner' => 'Débutant',
        'intermediate' => 'Intermédiaire',
        'advanced' => 'Confirmé',
        'all_levels' => 'Tous niveaux',
        'supervised' => 'Encadrée',
        'free' => 'Libre',
        'guided_tour' => 'Visite guidée',
        'service_product' => 'Prestation à date libre',
        'calendar' => 'Créneaux au calendrier',
        'quote' => 'Sur devis',
        'flexible' => 'Flexible',
        'moderate' => 'Modérée',
        'strict' => 'Stricte',
        'all_year' => "Toute l'année",
        'spring_summer' => 'Printemps et été',
        'autumn_winter' => 'Automne et hiver',
    ];

    /**
     * EasyAdmin attend un tableau « libellé => valeur ».
     *
     * @param list<\BackedEnum> $cases
     *
     * @return array<string, \BackedEnum>
     */
    private static function choices(array $cases): array
    {
        $choices = [];
        foreach ($cases as $case) {
            $value = (string) $case->value;
            $choices[self::LABELS[$value] ?? ucfirst($value)] = $case;
        }

        return $choices;
    }
}
