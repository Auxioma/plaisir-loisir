<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Service\EventAdminService;
use App\Event\Entity\Event;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimezoneField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** @extends AbstractCrudController<Event> */
#[IsGranted('ROLE_ADMIN')]
class EventCrudController extends AbstractCrudController
{
    use UniqueSlugCrudTrait;

    public const STATUSES = ['Brouillon' => 'draft', 'Programmé' => 'scheduled', 'Publié' => 'published', 'Suspendu par l’administration' => 'suspended'];
    public const VISIBILITIES = ['Public' => 'public', 'Membres connectés' => 'members', 'Privé, sur invitation' => 'private', 'Groupe' => 'group'];

    public function __construct(private readonly EventAdminService $management)
    {
    }

    protected function slugSourceField(): string
    {
        return 'title';
    }

    public static function getEntityFqcn(): string
    {
        return Event::class;
    }

    public function createEntity(string $entityFqcn): Event
    {
        $event = (new Event())->setStatus('draft');
        if (($admin = $this->getUser()) instanceof User && !$admin->isProvider()) {
            $event->setOrganizer($admin);
        }

        return $event;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('événement')->setEntityLabelInPlural('événements')
            ->setPageTitle(Crud::PAGE_INDEX, 'Événements')->setDefaultSort(['startsAt' => 'DESC'])
            ->setSearchFields(['title', 'slug', 'city', 'organizer.email'])
            ->setHelp(Crud::PAGE_EDIT, 'Le statut Suspendu retire l’événement du site et bloque sa republication par l’organisateur. Choisissez Publié pour le rétablir.')
            ->setFormOptions(['constraints' => [new Assert\Callback($this->validateEvent(...))]]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(ChoiceFilter::new('status', 'Statut')->setChoices(self::STATUSES))
            ->add(ChoiceFilter::new('visibility', 'Visibilité')->setChoices(self::VISIBILITIES))->add('startsAt')->add('city')
            ->add(EntityFilter::new('category', 'Catégorie')->setFormTypeOption('value_type_options.choice_label', 'name'))
            ->add(EntityFilter::new('organizer', 'Organisateur')->setFormTypeOption('value_type_options.choice_label', 'email'));
    }

    public function configureActions(Actions $actions): Actions
    {
        $preview = Action::new('preview', 'Voir sur le site', 'fa fa-arrow-up-right-from-square')
            ->linkToRoute('app_events_detail', static fn (Event $event): array => ['slug' => $event->getSlug()]);
        $participants = Action::new('participants', 'Inscriptions', 'fa fa-users')->linkToUrl(fn (Event $event): string => $this->container->get(AdminUrlGenerator::class)
            ->unsetAll()->setController(EventRegistrationCrudController::class)->setAction(Action::INDEX)
            ->set('filters[event][comparison]', '=')->set('filters[event][value]', (string) $event->getId())->generateUrl());

        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL)->add(Crud::PAGE_DETAIL, $preview)->add(Crud::PAGE_DETAIL, $participants);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addTab('Présentation');
        yield TextField::new('title', 'Titre')->setFormTypeOption('constraints', [new Assert\NotBlank(), new Assert\Length(min: 3, max: 180)]);
        yield SlugField::new('slug', 'Adresse de la page')->setTargetFieldName('title')->hideOnIndex()->setFormTypeOption('constraints', [new Assert\Length(max: 200)]);
        yield TextareaField::new('shortDescription', 'Accroche')->hideOnIndex()->setFormTypeOption('constraints', [new Assert\Length(max: 160)]);
        yield TextareaField::new('description', 'Description')->hideOnIndex();
        yield AssociationField::new('organizer', 'Organisateur')->setFormTypeOption('choice_label', static fn (User $user): string => trim($user->getFirstName().' '.$user->getLastName()).' — '.$user->getEmail())
            ->setFormTypeOption('choice_filter', static fn (?User $user): bool => null === $user || !$user->isProvider())
            ->formatValue(static fn (mixed $value, Event $event): string => $event->getOrganizer()?->getEmail() ?? 'Sans organisateur');
        yield AssociationField::new('category', 'Catégorie')->setFormTypeOption('choice_label', 'name')->formatValue(static fn (mixed $value, Event $event): ?string => $event->getCategory()?->getName());
        yield ChoiceField::new('eventType', 'Format')->setChoices(['Sortie' => 'sortie', 'Repas' => 'repas', 'Atelier' => 'atelier', 'Rencontre' => 'rencontre', 'Autre' => 'autre'])->hideOnIndex();
        yield ImageField::new('imagePath', 'Couverture')->setUploadDir('public')->setUploadedFileNamePattern('uploads/event-[slug]-[timestamp].[extension]')->hideOnIndex();
        yield ImageField::new('gallery', 'Galerie')->setRequired(false)->setUploadDir('public')->setUploadedFileNamePattern('uploads/event-[ulid].[extension]')
            ->setFormTypeOption('multiple', true)->setFormTypeOption('constraints', [new Assert\Count(max: 5)])
            ->setHelp('Cinq photos maximum.')->hideOnIndex();

        yield FormField::addTab('Publication et dates');
        yield ChoiceField::new('status', 'Statut')->setChoices(self::STATUSES)->renderAsBadges();
        yield ChoiceField::new('visibility', 'Visibilité')->setChoices(self::VISIBILITIES);
        yield DateTimeField::new('publishAt', 'Publication programmée')->hideOnIndex();
        yield DateTimeField::new('startsAt', 'Début');
        yield DateTimeField::new('endsAt', 'Fin')->hideOnIndex();
        yield BooleanField::new('allDay', 'Toute la journée')->renderAsSwitch(false)->hideOnIndex();
        yield TimezoneField::new('timezone', 'Fuseau horaire')->hideOnIndex();
        yield ChoiceField::new('dateMode', 'Type de dates')->setChoices(['Date précise' => 'precise', 'Période' => 'periode', 'Récurrent' => 'recurrent'])->hideOnIndex();
        yield ChoiceField::new('recurrence', 'Récurrence')->setChoices(['Chaque semaine' => 'weekly', 'Chaque mois' => 'monthly'])->hideOnIndex();
        yield ChoiceField::new('reminder', 'Rappel')->setChoices(['1 heure' => '1h', '3 heures' => '3h', '24 heures' => '24h', '48 heures' => '48h', '1 semaine' => '1w'])->hideOnIndex();
        yield BooleanField::new('showInCalendar', 'Afficher dans le calendrier')->renderAsSwitch(false)->hideOnIndex();
        yield IntegerField::new('position', 'Ordre d’affichage')->hideOnIndex();

        yield FormField::addTab('Lieu');
        yield ChoiceField::new('locationType', 'Type de lieu')->setChoices(['Précis' => 'precise', 'Approximatif' => 'approx', 'En ligne' => 'online', 'À définir' => 'tbd'])->hideOnIndex();
        foreach (['location' => ['Lieu affiché', 180], 'address' => ['Adresse', 255], 'city' => ['Ville', 120], 'postalCode' => ['Code postal', 10], 'arrivalTime' => ['Arrivée conseillée (HH:MM)', 5], 'meetingPoint' => ['Point de rendez-vous', 200], 'accessInstructions' => ['Instructions d’accès', 200]] as $property => [$label, $max]) {
            yield TextField::new($property, $label)->hideOnIndex()->setFormTypeOption('constraints', [new Assert\Length(max: $max)]);
        }
        yield TextField::new('latitude', 'Latitude')->hideOnIndex()->setFormTypeOption('constraints', [new Assert\Range(min: -90, max: 90)]);
        yield TextField::new('longitude', 'Longitude')->hideOnIndex()->setFormTypeOption('constraints', [new Assert\Range(min: -180, max: 180)]);
        yield TextField::new('onlineUrl', 'Lien de connexion')->hideOnIndex()->setFormTypeOption('constraints', [new Assert\Url(protocols: ['https', 'http']), new Assert\Length(max: 255)]);

        yield FormField::addTab('Participants');
        yield IntegerField::new('participantsCount', 'Inscrits')->hideOnForm();
        yield IntegerField::new('capacity', 'Nombre maximum de participants')->setHelp('Vide = illimité. Le compteur des inscrits est calculé automatiquement.')->setFormTypeOption('constraints', [new Assert\Positive()])->hideOnIndex();
        foreach (['registrationRequired' => 'Inscription obligatoire', 'waitlist' => 'Liste d’attente', 'showParticipants' => 'Afficher les participants', 'allowGuestInvites' => 'Autoriser les invitations', 'commentsEnabled' => 'Autoriser les commentaires', 'emailUpdates' => 'Notifications par e-mail'] as $property => $label) {
            yield BooleanField::new($property, $label)->renderAsSwitch(false)->hideOnIndex();
        }
    }

    public function validateEvent(Event $event, ExecutionContextInterface $context): void
    {
        if (null !== $event->getEndsAt() && $event->getEndsAt() < $event->getStartsAt()) {
            $context->buildViolation('La fin doit être postérieure au début.')->atPath('endsAt')->addViolation();
        }
        if ('scheduled' === $event->getStatus() && (null === $event->getPublishAt() || $event->getPublishAt() <= new \DateTimeImmutable())) {
            $context->buildViolation('Choisissez une date de publication future.')->atPath('publishAt')->addViolation();
        }
        if ('online' === $event->getLocationType() && !$event->getOnlineUrl()) {
            $context->buildViolation('Le lien de connexion est obligatoire.')->atPath('onlineUrl')->addViolation();
        }
        if (null !== $event->getCapacity() && $event->getCapacity() < $this->management->requiredCapacity($event)) {
            $context->buildViolation('La capacité ne peut pas être inférieure au nombre d’inscrits, organisateur compris.')->atPath('capacity')->addViolation();
        }
        if ($event->getOrganizer()?->isProvider()) {
            $context->buildViolation('Choisissez un client ou un administrateur comme organisateur.')->atPath('organizer')->addViolation();
        }
    }

    public function persistEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        $this->management->save($entityInstance);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        $actor = $this->getUser();
        $this->management->delete($entityInstance, $actor instanceof User ? $actor : null);
    }

    public function updateEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        $this->management->save($entityInstance);
    }
}
