<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Service\PrivateActivityAdminService;
use App\Catalog\Enum\ActivityLevel;
use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Enum\ParticipationMode;
use App\PrivateActivity\Enum\PrivateActivityStatus;
use App\PrivateActivity\Enum\PrivateActivityVisibility;
use App\PrivateActivity\Repository\AlbumRepository;
use App\PrivateActivity\Service\PrivateActivityService;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Activités gratuites entre membres (PrivateActivity), 26/11.
 *
 * Pendant des « Activités payantes » (ServiceCrudController) : l'équipe voit
 * toutes les sorties créées par les membres, quelle que soit leur visibilité,
 * peut les corriger, en créer pour un membre, les suspendre (retrait du site,
 * l'organisateur ne peut plus les republier), les rétablir, les annuler ou
 * les supprimer — et gérer leurs participants, photos et avis depuis les
 * écrans liés.
 *
 * Les règles (capacité, liste d'attente, notifications) restent celles du
 * domaine : voir PrivateActivityAdminService.
 *
 * @extends AbstractCrudController<PrivateActivity>
 */
#[IsGranted('ROLE_ADMIN')]
class PrivateActivityCrudController extends AbstractCrudController
{
    public const STATUS_LABELS = [
        'draft' => 'Brouillon',
        'pending_category' => 'En attente de la catégorie',
        'open' => 'En ligne',
        'full' => 'Complète',
        'cancelled' => 'Annulée',
        'suspended' => 'Suspendue par l’administration',
    ];

    private const VISIBILITY_LABELS = [
        'public' => 'Publique',
        'members_only' => 'Membres connectés',
        'private' => 'Privée, sur invitation',
    ];

    private const MODE_LABELS = [
        'automatic' => 'Inscription directe',
        'validation' => 'Sur validation de l’organisateur',
    ];

    private const LEVEL_LABELS = [
        'beginner' => 'Débutant',
        'intermediate' => 'Intermédiaire',
        'advanced' => 'Confirmé',
        'all_levels' => 'Tous niveaux',
    ];

    public function __construct(
        private readonly PrivateActivityAdminService $management,
        private readonly AlbumRepository $albums,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return PrivateActivity::class;
    }

    public function createEntity(string $entityFqcn): PrivateActivity
    {
        $activity = (new PrivateActivity())->setStatus(PrivateActivityStatus::Open);
        if (($admin = $this->getUser()) instanceof User && !$admin->isProvider()) {
            $activity->setOrganizer($admin);
        }

        return $activity;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('activité gratuite')
            ->setEntityLabelInPlural('activités gratuites')
            ->setPageTitle(Crud::PAGE_INDEX, 'Activités gratuites entre membres')
            ->setPageTitle(Crud::PAGE_NEW, 'Nouvelle activité gratuite')
            ->setPageTitle(Crud::PAGE_EDIT, 'Modifier l’activité gratuite')
            ->setPageTitle(Crud::PAGE_DETAIL, static fn (PrivateActivity $a): string => $a->getTitle())
            ->setHelp(Crud::PAGE_INDEX, 'Sorties gratuites créées par les membres. « Suspendre » retire une activité du site sans la supprimer : son organisateur ne peut plus la modifier ni la republier, seule l’équipe la rétablit.')
            ->setHelp(Crud::PAGE_EDIT, '« En ligne » et « Complète » sont recalculés selon les places restantes. Organisateur et participants sont prévenus d’une suspension, d’une annulation ou d’un changement de date ou de lieu.')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['title', 'city', 'postalCode', 'organizer.email', 'organizer.firstName', 'organizer.lastName'])
            ->setFormOptions(['constraints' => [new Assert\Callback($this->validateActivity(...))]]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status', 'Statut')->setChoices(self::choices(PrivateActivityStatus::cases(), self::STATUS_LABELS)))
            ->add(ChoiceFilter::new('visibility', 'Visibilité')->setChoices(self::choices(PrivateActivityVisibility::cases(), self::VISIBILITY_LABELS)))
            ->add(EntityFilter::new('category', 'Catégorie')->setFormTypeOption('value_type_options.choice_label', 'name'))
            ->add(EntityFilter::new('organizer', 'Organisateur')->setFormTypeOption('value_type_options.choice_label', 'email'))
            ->add('scheduledAt')
            ->add('city');
    }

    public function configureActions(Actions $actions): Actions
    {
        $preview = Action::new('preview', 'Voir sur le site', 'fa fa-arrow-up-right-from-square')
            ->linkToRoute('app_private_activity_show', static fn (PrivateActivity $a): array => ['id' => (string) $a->getId()]);
        $participants = Action::new('participants', 'Participants', 'fa fa-users')
            ->linkToUrl(fn (PrivateActivity $a): string => $this->filteredUrl(ParticipationCrudController::class, 'privateActivity', (string) $a->getId()));
        $photos = Action::new('photos', 'Photos de l’album', 'fa fa-images')
            ->linkToUrl(fn (PrivateActivity $a): string => $this->filteredUrl(AlbumPhotoCrudController::class, 'album', (string) $this->albums->findOneByActivity($a)?->getId()))
            ->displayIf(fn (PrivateActivity $a): bool => null !== $this->albums->findOneByActivity($a));
        $reviews = Action::new('reviews', 'Avis', 'fa fa-star')
            ->linkToUrl(fn (PrivateActivity $a): string => $this->filteredUrl(MemberReviewCrudController::class, 'privateActivity', (string) $a->getId()));
        // POST uniquement, comme Valider / Suspendre des activités payantes.
        $suspend = Action::new('suspend', 'Suspendre', 'fa fa-ban')
            ->linkToCrudAction('suspend')->renderAsForm()->addCssClass('text-danger')
            ->displayIf(static fn (PrivateActivity $a): bool => \in_array($a->getStatus(), [PrivateActivityStatus::Open, PrivateActivityStatus::Full, PrivateActivityStatus::PendingCategory, PrivateActivityStatus::Draft], true));
        $reinstate = Action::new('reinstate', 'Remettre en ligne', 'fa fa-rotate-left')
            ->linkToCrudAction('reinstate')->renderAsForm()
            ->displayIf(static fn (PrivateActivity $a): bool => PrivateActivityStatus::Suspended === $a->getStatus());

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $suspend)->add(Crud::PAGE_INDEX, $reinstate)
            ->add(Crud::PAGE_DETAIL, $preview)->add(Crud::PAGE_DETAIL, $participants)->add(Crud::PAGE_DETAIL, $photos)->add(Crud::PAGE_DETAIL, $reviews)
            ->add(Crud::PAGE_DETAIL, $suspend)->add(Crud::PAGE_DETAIL, $reinstate)
            ->add(Crud::PAGE_EDIT, $suspend)->add(Crud::PAGE_EDIT, $reinstate);
    }

    /** @param AdminContext<PrivateActivity> $context */
    #[AdminRoute(path: '/{entityId}/suspend', name: 'suspend', options: ['methods' => ['POST']])]
    public function suspend(AdminContext $context, PrivateActivityService $activities): Response
    {
        $activity = $this->activityFrom($context);
        $activities->suspend($activity);
        $this->addFlash('success', \sprintf('« %s » est suspendue : elle n’apparaît plus sur le site.', $activity->getTitle()));

        return $this->backToIndex();
    }

    /** @param AdminContext<PrivateActivity> $context */
    #[AdminRoute(path: '/{entityId}/reinstate', name: 'reinstate', options: ['methods' => ['POST']])]
    public function reinstate(AdminContext $context, PrivateActivityService $activities): Response
    {
        $activity = $this->activityFrom($context);
        try {
            $activities->reinstate($activity);
            $this->addFlash('success', \sprintf('« %s » est de nouveau en ligne.', $activity->getTitle()));
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->backToIndex();
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addTab('Présentation');
        yield ImageField::new('coverImage', 'Photo')
            // Même convention que les autres écrans : chemin relatif à public/,
            // comme ceux déposés par l'assistant des membres.
            ->setUploadDir('public')
            ->setUploadedFileNamePattern('uploads/private-activities/[ulid].[extension]')
            ->setRequired(false)
            ->setHelp('Sans photo, l’image de la catégorie est affichée.');
        yield TextField::new('title', 'Titre')
            ->setFormTypeOption('constraints', [new Assert\NotBlank(), new Assert\Length(min: 3, max: 150)]);
        yield TextareaField::new('description', 'Description')->hideOnIndex();
        yield AssociationField::new('organizer', 'Organisateur')
            ->setFormTypeOption('choice_label', static fn (User $u): string => trim($u->getFirstName().' '.$u->getLastName()).' — '.$u->getEmail())
            // Retours client du 11/10 : le gratuit entre membres est réservé
            // aux particuliers.
            ->setFormTypeOption('choice_filter', static fn (?User $u): bool => null === $u || !$u->isProvider())
            ->setFormTypeOption('placeholder', 'Choisir un membre')
            ->formatValue(static fn (mixed $v, PrivateActivity $a): string => $a->getOrganizer()?->getEmail() ?? '—');
        yield AssociationField::new('category', 'Catégorie')
            ->setFormTypeOption('choice_label', 'name')
            ->setFormTypeOption('placeholder', 'Choisir une catégorie')
            ->setRequired(false)
            ->formatValue(static fn (mixed $v, PrivateActivity $a): string => $a->getCategory()?->getName() ?? ($a->getCategorySuggestion() ? 'Proposée : '.$a->getCategorySuggestion()->getName() : '—'));
        yield ChoiceField::new('level', 'Niveau')
            ->setChoices(self::choices(ActivityLevel::cases(), self::LEVEL_LABELS))->setFormTypeOption('choice_value', self::enumValue(...))->setRequired(false)->hideOnIndex();
        yield AssociationField::new('service', 'Activité du catalogue liée')
            ->setFormTypeOption('choice_label', 'title')
            ->setHelp('Facultatif : activité payante dont s’inspire la sortie.')
            ->formatValue(static fn (mixed $v, PrivateActivity $a): ?string => $a->getService()?->getTitle())
            ->hideOnIndex();

        yield FormField::addTab('Publication');
        yield ChoiceField::new('status', 'Statut')
            ->setChoices(self::choices(PrivateActivityStatus::cases(), self::STATUS_LABELS))
            ->setFormTypeOption('choice_value', self::enumValue(...))
            ->formatValue(static fn (mixed $v, PrivateActivity $a): string => self::STATUS_LABELS[$a->getStatus()->value])
            ->renderAsBadges();
        yield ChoiceField::new('visibility', 'Visibilité')
            ->setChoices(self::choices(PrivateActivityVisibility::cases(), self::VISIBILITY_LABELS))
            ->setFormTypeOption('choice_value', self::enumValue(...))
            ->formatValue(static fn (mixed $v, PrivateActivity $a): string => self::VISIBILITY_LABELS[$a->getVisibility()->value]);
        yield ChoiceField::new('participationMode', 'Inscription')
            ->setChoices(self::choices(ParticipationMode::cases(), self::MODE_LABELS))->setFormTypeOption('choice_value', self::enumValue(...))->hideOnIndex();
        yield IntegerField::new('viewsCount', 'Vues')->hideOnForm();
        yield DateTimeField::new('createdAt', 'Créée le')->hideOnForm()->hideOnIndex();

        yield FormField::addTab('Date et lieu');
        yield DateTimeField::new('scheduledAt', 'Date');
        yield DateTimeField::new('endsAt', 'Fin')->hideOnIndex();
        yield TextField::new('city', 'Ville')
            ->setHelp('Zone approximative, toujours visible.')
            ->setFormTypeOption('constraints', [new Assert\Length(max: 120)]);
        yield TextField::new('postalCode', 'Code postal')->hideOnIndex()->setFormTypeOption('constraints', [new Assert\Length(max: 10)]);
        yield TextField::new('location', 'Adresse exacte')
            ->setHelp('Montrée seulement à l’organisateur et aux participants acceptés.')
            ->hideOnIndex()->setFormTypeOption('constraints', [new Assert\Length(max: 255)]);
        yield BooleanField::new('showExactAddress', 'Communiquer l’adresse exacte aux participants')->renderAsSwitch(false)->hideOnIndex();
        yield TextareaField::new('meetingPoint', 'Point de rendez-vous')->hideOnIndex()->setFormTypeOption('constraints', [new Assert\Length(max: 500)]);
        yield TextField::new('latitude', 'Latitude')->hideOnIndex()->setFormTypeOption('constraints', [new Assert\Range(min: -90, max: 90)]);
        yield TextField::new('longitude', 'Longitude')->hideOnIndex()->setFormTypeOption('constraints', [new Assert\Range(min: -180, max: 180)]);
        yield TextareaField::new('toBring', 'À apporter')->hideOnIndex();

        yield FormField::addTab('Participants');
        // AssociationField et non TextField : la valeur est une collection,
        // qu'un champ texte refuse avant même formatValue (voir
        // ServiceCrudController, colonne « Fiche détaillée »).
        yield AssociationField::new('participations', 'Participants')
            ->formatValue(static fn (mixed $v, PrivateActivity $a): string => $a->countAccepted().(null !== $a->getMaxParticipants() ? ' / '.$a->getMaxParticipants() : ''))
            ->setSortable(false)
            ->hideOnForm();
        yield IntegerField::new('minParticipants', 'Minimum de participants')->hideOnIndex()->setFormTypeOption('constraints', [new Assert\Positive()]);
        yield IntegerField::new('maxParticipants', 'Nombre de places')
            ->setHelp('Vide = illimité. Des places ajoutées profitent aussitôt à la liste d’attente.')
            ->hideOnIndex()->setFormTypeOption('constraints', [new Assert\Positive()]);
    }

    public function validateActivity(PrivateActivity $activity, ExecutionContextInterface $context): void
    {
        $status = $activity->getStatus();
        if ($activity->getOrganizer()?->isProvider()) {
            $context->buildViolation('Un compte professionnel ne peut pas organiser une activité gratuite.')->atPath('organizer')->addViolation();
        }
        if (null === $activity->getCategory() && !\in_array($status, [PrivateActivityStatus::Draft, PrivateActivityStatus::PendingCategory, PrivateActivityStatus::Cancelled], true)) {
            $context->buildViolation('Choisissez une catégorie.')->atPath('category')->addViolation();
        }
        if (PrivateActivityStatus::PendingCategory === $status && (null !== $activity->getCategory() || !$activity->getCategorySuggestion()?->isPending())) {
            $context->buildViolation('« En attente de la catégorie » ne s’applique qu’à une activité dont la catégorie proposée attend encore sa validation.')->atPath('status')->addViolation();
        }
        if (null !== $activity->getEndsAt() && null !== $activity->getScheduledAt() && $activity->getEndsAt() < $activity->getScheduledAt()) {
            $context->buildViolation('La fin doit être postérieure au début.')->atPath('endsAt')->addViolation();
        }
        $min = $activity->getMinParticipants();
        $max = $activity->getMaxParticipants();
        if (null !== $min && null !== $max && $min > $max) {
            $context->buildViolation('Le minimum ne peut pas dépasser le nombre de places.')->atPath('minParticipants')->addViolation();
        }
        if (null !== $max && $max < $activity->countAccepted()) {
            $context->buildViolation(\sprintf('%d participants sont déjà confirmés : retirez-en avant de réduire le nombre de places.', $activity->countAccepted()))->atPath('maxParticipants')->addViolation();
        }
    }

    public function persistEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        $this->management->save($entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        $this->management->save($entityInstance);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        $actor = $this->getUser();
        $this->management->delete($entityInstance, $actor instanceof User ? $actor : null);
    }

    /** @param AdminContext<PrivateActivity> $context */
    private function activityFrom(AdminContext $context): PrivateActivity
    {
        $activity = $context->getEntity()->getInstance();
        if (!$activity instanceof PrivateActivity) {
            throw $this->createNotFoundException();
        }

        return $activity;
    }

    private function backToIndex(): RedirectResponse
    {
        return new RedirectResponse($this->container->get(AdminUrlGenerator::class)->unsetAll()->setController(self::class)->setAction(Action::INDEX)->generateUrl());
    }

    /** Lien vers un écran lié, filtré sur cette activité (même procédé que les inscriptions aux événements). */
    private function filteredUrl(string $controller, string $filter, string $id): string
    {
        return $this->container->get(AdminUrlGenerator::class)->unsetAll()
            ->setController($controller)->setAction(Action::INDEX)
            ->set('filters['.$filter.'][comparison]', '=')->set('filters['.$filter.'][value]', $id)
            ->generateUrl();
    }

    /** Valeur envoyée par le formulaire : celle de l'énumération (« open »), pas un index. */
    public static function enumValue(?\BackedEnum $case): ?string
    {
        return null === $case ? null : (string) $case->value;
    }

    /**
     * @param list<\BackedEnum>     $cases
     * @param array<string, string> $labels
     *
     * @return array<string, \BackedEnum>
     */
    private static function choices(array $cases, array $labels): array
    {
        $choices = [];
        foreach ($cases as $case) {
            $choices[$labels[(string) $case->value] ?? ucfirst((string) $case->value)] = $case;
        }

        return $choices;
    }
}
