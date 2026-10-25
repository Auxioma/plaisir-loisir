<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Service\EventAdminService;
use App\Event\Entity\Event;
use App\Event\Entity\EventRegistration;
use App\Event\Repository\EventRegistrationRepository;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
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
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** @extends AbstractCrudController<EventRegistration> */
#[IsGranted('ROLE_ADMIN')]
class EventRegistrationCrudController extends AbstractCrudController
{
    public function __construct(private readonly EventAdminService $management, private readonly EntityManagerInterface $em, private readonly EventRegistrationRepository $registrations)
    {
    }

    public static function getEntityFqcn(): string
    {
        return EventRegistration::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('inscription')->setEntityLabelInPlural('inscriptions aux événements')
            ->setPageTitle(Crud::PAGE_INDEX, 'Inscriptions aux événements')->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['event.title', 'user.email', 'user.firstName', 'user.lastName'])
            ->setHelp(Crud::PAGE_INDEX, 'Ajoutez un participant, confirmez une place, gérez la liste d’attente ou retirez une inscription. Le compteur est recalculé automatiquement.')
            ->setFormOptions(['constraints' => [new Assert\Callback($this->validateRegistration(...))]]);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DELETE, static fn (Action $action): Action => $action->displayIf(static fn (EventRegistration $r): bool => $r->getUser() !== $r->getEvent()->getOrganizer()))
            ->update(Crud::PAGE_DETAIL, Action::DELETE, static fn (Action $action): Action => $action->displayIf(static fn (EventRegistration $r): bool => $r->getUser() !== $r->getEvent()->getOrganizer()));
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('event', 'Événement')->setFormTypeOption('value_type_options.choice_label', 'title'))
            ->add(EntityFilter::new('user', 'Participant')->setFormTypeOption('value_type_options.choice_label', 'email'))
            ->add(ChoiceFilter::new('status', 'Statut')->setChoices(['Inscrit' => EventRegistration::GOING, 'Liste d’attente' => EventRegistration::WAITLIST]));
    }

    /** Formulaire dédié : l'inscription garde son constructeur métier obligatoire. */
    public function new(AdminContext $context): KeyValueStore|Response
    {
        $form = $this->createFormBuilder(['status' => EventRegistration::GOING], ['constraints' => [new Assert\Callback(function (array $values, ExecutionContextInterface $validation): void {
            $event = $values['event'] ?? null;
            $user = $values['user'] ?? null;
            if (!$event instanceof Event || !$user instanceof User) {
                return;
            }
            if (null !== $this->registrations->findOneFor($event, $user)) {
                $validation->buildViolation('Ce membre est déjà inscrit à cet événement.')->atPath('[user]')->addViolation();
            }
            if (EventRegistration::GOING === ($values['status'] ?? '') && null !== $event->getCapacity() && $this->registrations->countGoing($event) >= $event->getCapacity()) {
                $validation->buildViolation('L’événement est complet. Choisissez la liste d’attente ou augmentez sa capacité.')->atPath('[status]')->addViolation();
            }
        })]])
            ->add('event', EntityType::class, ['class' => Event::class, 'label' => 'Événement', 'choice_label' => 'title', 'placeholder' => 'Choisir un événement', 'constraints' => [new Assert\NotNull()]])
            ->add('user', EntityType::class, ['class' => User::class, 'label' => 'Participant', 'choice_label' => 'email', 'placeholder' => 'Choisir un membre', 'choice_filter' => static fn (?User $user): bool => null === $user || !$user->isProvider(), 'constraints' => [new Assert\NotNull()]])
            ->add('status', ChoiceType::class, ['label' => 'Statut', 'choices' => ['Inscrit' => EventRegistration::GOING, 'Liste d’attente' => EventRegistration::WAITLIST]])
            ->add('save', SubmitType::class, ['label' => 'Ajouter l’inscription'])
            ->getForm();
        $form->handleRequest($context->getRequest());
        if ($form->isSubmitted() && $form->isValid()) {
            $values = $form->getData();
            $this->management->createRegistration(new EventRegistration($values['event'], $values['user'], $values['status']));
            $this->addFlash('success', 'Inscription ajoutée.');

            return $this->redirect($this->container->get(AdminUrlGenerator::class)->setController(self::class)->setAction(Action::INDEX)->generateUrl());
        }

        return $this->render('admin/event_registration_new.html.twig', ['registrationForm' => $form->createView()], new Response(status: $form->isSubmitted() ? 422 : 200));
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('event', 'Événement')->hideOnForm()->formatValue(static fn (mixed $v, EventRegistration $r): string => $r->getEvent()->getTitle());
        yield AssociationField::new('user', 'Participant')->hideOnForm()->formatValue(static fn (mixed $v, EventRegistration $r): string => trim($r->getUser()->getFirstName().' '.$r->getUser()->getLastName()).' — '.$r->getUser()->getEmail());
        yield ChoiceField::new('status', 'Statut')->setChoices(['Inscrit' => EventRegistration::GOING, 'Liste d’attente' => EventRegistration::WAITLIST])->renderAsBadges();
        yield DateTimeField::new('createdAt', 'Inscription le')->hideOnForm();
    }

    public function validateRegistration(EventRegistration $registration, ExecutionContextInterface $context): void
    {
        if ($registration->getUser() === $registration->getEvent()->getOrganizer() && EventRegistration::GOING !== $registration->getStatus()) {
            $context->buildViolation('Changez d’abord l’organisateur de l’événement.')->atPath('status')->addViolation();
        }
        $old = $this->em->getUnitOfWork()->getOriginalEntityData($registration)['status'] ?? null;
        $event = $registration->getEvent();
        if (EventRegistration::GOING === $registration->getStatus() && EventRegistration::GOING !== $old
            && null !== $event->getCapacity() && $this->registrations->countGoing($event) >= $event->getCapacity()) {
            $context->buildViolation('L’événement est complet. Augmentez sa capacité avant de confirmer une place.')->atPath('status')->addViolation();
        }
    }

    public function updateEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        $this->management->updateRegistration($entityInstance);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        if ($entityInstance->getUser() === $entityInstance->getEvent()->getOrganizer()) {
            throw $this->createAccessDeniedException('Changez d’abord l’organisateur de l’événement.');
        }
        $this->management->deleteRegistration($entityInstance);
    }
}
