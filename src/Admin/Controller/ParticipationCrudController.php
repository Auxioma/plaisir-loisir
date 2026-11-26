<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Service\PrivateActivityAdminService;
use App\PrivateActivity\Entity\Participation;
use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Enum\ParticipationStatus;
use App\PrivateActivity\Service\PrivateActivityService;
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
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Participations aux activités gratuites (26/11) : confirmer, refuser,
 * mettre en liste d'attente, annuler ou retirer une place, et inscrire un
 * membre à la main. Même logique que l'écran « Inscriptions aux
 * événements » ; la capacité et la liste d'attente suivent
 * PrivateActivityService, et chaque membre concerné est prévenu.
 *
 * @extends AbstractCrudController<Participation>
 */
#[IsGranted('ROLE_ADMIN')]
class ParticipationCrudController extends AbstractCrudController
{
    public const STATUS_LABELS = [
        'pending' => 'En attente de validation',
        'accepted' => 'Confirmée',
        'waiting_list' => 'Liste d’attente',
        'refused' => 'Refusée',
        'cancelled' => 'Annulée',
    ];

    public function __construct(
        private readonly PrivateActivityAdminService $management,
        private readonly PrivateActivityService $activities,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Participation::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('participation')
            ->setEntityLabelInPlural('participations')
            ->setPageTitle(Crud::PAGE_INDEX, 'Participations aux activités gratuites')
            ->setPageTitle(Crud::PAGE_EDIT, 'Modifier la participation')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['privateActivity.title', 'participant.email', 'participant.firstName', 'participant.lastName'])
            ->setHelp(Crud::PAGE_INDEX, 'Une place confirmée qui se libère profite automatiquement au premier de la liste d’attente. Supprimer une ligne permet au membre de redemander à participer.')
            ->setFormOptions(['constraints' => [new Assert\Callback($this->validateParticipation(...))]]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('privateActivity', 'Activité')->setFormTypeOption('value_type_options.choice_label', 'title'))
            ->add(EntityFilter::new('participant', 'Participant')->setFormTypeOption('value_type_options.choice_label', 'email'))
            ->add(ChoiceFilter::new('status', 'Statut')->setChoices(self::choices()));
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    /** Formulaire dédié : comme pour les événements, l'inscription passe par la règle métier. */
    public function new(AdminContext $context): KeyValueStore|Response
    {
        $form = $this->createFormBuilder(['status' => ParticipationStatus::Accepted])
            ->add('activity', EntityType::class, ['class' => PrivateActivity::class, 'label' => 'Activité', 'choice_label' => 'title', 'placeholder' => 'Choisir une activité', 'constraints' => [new Assert\NotNull()]])
            ->add('member', EntityType::class, [
                'class' => User::class, 'label' => 'Membre', 'placeholder' => 'Choisir un membre',
                'choice_label' => static fn (User $u): string => trim($u->getFirstName().' '.$u->getLastName()).' — '.$u->getEmail(),
                'choice_filter' => static fn (?User $u): bool => null === $u || !$u->isProvider(),
                'constraints' => [new Assert\NotNull()],
            ])
            ->add('status', ChoiceType::class, ['label' => 'Statut', 'choice_value' => PrivateActivityCrudController::enumValue(...), 'choices' => [
                self::STATUS_LABELS['accepted'] => ParticipationStatus::Accepted,
                self::STATUS_LABELS['pending'] => ParticipationStatus::Pending,
                self::STATUS_LABELS['waiting_list'] => ParticipationStatus::WaitingList,
            ]])
            ->add('save', SubmitType::class, ['label' => 'Inscrire le membre'])
            ->getForm();
        $form->handleRequest($context->getRequest());

        if ($form->isSubmitted() && $form->isValid()) {
            $values = $form->getData();
            try {
                $this->activities->enrolByAdministration($values['activity'], $values['member'], $values['status']);
                $this->addFlash('success', 'Membre inscrit. Il a été prévenu.');

                return $this->redirect($this->container->get(AdminUrlGenerator::class)->unsetAll()->setController(self::class)->setAction(Action::INDEX)->generateUrl());
            } catch (\InvalidArgumentException $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('admin/simple_form.html.twig', [
            'title' => 'Inscrire un membre à une activité gratuite',
            'form' => $form->createView(),
        ], new Response(status: $form->isSubmitted() ? 422 : 200));
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('privateActivity', 'Activité')->hideOnForm()
            ->formatValue(static fn (mixed $v, Participation $p): string => $p->getPrivateActivity()?->getTitle() ?? '—');
        yield AssociationField::new('participant', 'Participant')->hideOnForm()
            ->formatValue(static fn (mixed $v, Participation $p): string => null === ($u = $p->getParticipant()) ? '—' : trim($u->getFirstName().' '.$u->getLastName()).' — '.$u->getEmail());
        yield ChoiceField::new('status', 'Statut')
            ->setChoices(self::choices())
            ->setFormTypeOption('choice_value', PrivateActivityCrudController::enumValue(...))
            ->formatValue(static fn (mixed $v, Participation $p): string => self::STATUS_LABELS[$p->getStatus()->value])
            ->renderAsBadges();
        yield DateTimeField::new('createdAt', 'Demande le')->hideOnForm();
        yield DateTimeField::new('updatedAt', 'Dernière modification')->onlyOnDetail();
    }

    public function validateParticipation(Participation $participation, ExecutionContextInterface $context): void
    {
        $activity = $participation->getPrivateActivity();
        // La collection reflète déjà le nouveau statut : au-delà de la
        // capacité, c'est que cette confirmation est de trop.
        if (null !== $activity && $participation->isAccepted() && null !== $activity->getMaxParticipants() && $activity->countAccepted() > $activity->getMaxParticipants()) {
            $context->buildViolation('L’activité est complète : augmentez son nombre de places avant de confirmer ce participant.')->atPath('status')->addViolation();
        }
    }

    public function updateEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        $this->management->updateParticipation($entityInstance);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        $this->management->deleteParticipation($entityInstance);
    }

    /** @return array<string, ParticipationStatus> */
    private static function choices(): array
    {
        $choices = [];
        foreach (ParticipationStatus::cases() as $case) {
            $choices[self::STATUS_LABELS[$case->value]] = $case;
        }

        return $choices;
    }
}
