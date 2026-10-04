<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Notification\Enum\NotificationCategory;
use App\Notification\Service\NotificationService;
use App\Support\Entity\SupportTicket;
use App\Support\Entity\SupportTicketMessage;
use App\Support\Enum\TicketCategory;
use App\Support\Enum\TicketStatus;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tickets d'assistance des professionnels (« Support », 02/10) : l'équipe
 * lit le fil, répond (champ « Réponse ») et change le statut. Le
 * professionnel est notifié à chaque réponse.
 *
 * @extends AbstractCrudController<SupportTicket>
 */
class SupportTicketCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return SupportTicket::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('ticket')
            ->setEntityLabelInPlural('tickets')
            ->setPageTitle(Crud::PAGE_INDEX, 'Tickets support')
            ->setHelp(Crud::PAGE_EDIT, 'Écrivez votre réponse puis enregistrez : elle s’ajoute au fil et le professionnel est notifié.')
            ->setDefaultSort(['lastReplyAt' => 'DESC'])
            ->setSearchFields(['number', 'subject', 'author.email']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        $statuses = [];
        foreach (TicketStatus::cases() as $s) {
            $statuses[$s->label()] = $s;
        }

        return $filters->add(ChoiceFilter::new('status', 'Statut')->setChoices($statuses))->add('createdAt');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->update(Crud::PAGE_INDEX, Action::EDIT, static fn (Action $a): Action => $a->setLabel('Répondre'));
    }

    public function configureFields(string $pageName): iterable
    {
        $statuses = [];
        foreach (TicketStatus::cases() as $s) {
            $statuses[$s->label()] = $s;
        }
        $categories = [];
        foreach (TicketCategory::cases() as $c) {
            $categories[$c->label()] = $c;
        }

        yield TextField::new('reference', 'N°')->hideOnForm();
        yield TextField::new('subject', 'Sujet')->hideOnForm();
        yield AssociationField::new('author', 'Professionnel')->hideOnForm()
            ->formatValue(static fn (mixed $v, SupportTicket $t): string => trim($t->getAuthor()?->getFirstName().' '.$t->getAuthor()?->getLastName()).' — '.$t->getAuthor()?->getEmail());
        yield ChoiceField::new('category', 'Catégorie')->setChoices($categories)->formatValue(static fn (mixed $v, SupportTicket $t): string => $t->getCategory()->label());
        yield TextField::new('phone', 'À rappeler au')->hideOnIndex();
        yield ChoiceField::new('status', 'Statut')->setChoices($statuses)
            ->formatValue(static fn (mixed $v, SupportTicket $t): string => $t->getStatus()->label())
            ->renderAsBadges(['open' => 'info', 'in_progress' => 'warning', 'resolved' => 'success', 'closed' => 'secondary']);
        yield DateTimeField::new('lastReplyAt', 'Dernière réponse')->hideOnForm();
        yield Field::new('thread', 'Fil')->onlyOnDetail()
            ->setVirtual(true)
            ->formatValue(static fn (mixed $v, SupportTicket $t): string => implode("\n\n", array_map(
                static fn (SupportTicketMessage $m): string => sprintf('[%s] %s : %s', $m->getCreatedAt()?->format('d/m/Y H:i'), $m->isFromStaff() ? 'Équipe' : 'Pro', $m->getBody()),
                $t->getMessages()->toArray(),
            )));
        yield TextareaField::new('reply', 'Réponse à envoyer')->onlyOnForms()->setVirtual(true)->setFormTypeOption('mapped', false)->setRequired(false)
            ->setHelp('Le fil complet est visible sur la page « Consulter » du ticket.');
    }

    /**
     * @param SupportTicket $entityInstance
     */
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();
        $data = $request?->request->all('SupportTicket') ?? [];
        $reply = trim((string) ($data['reply'] ?? ''));

        if ('' !== $reply) {
            $entityInstance->addMessage((new SupportTicketMessage())->setFromStaff(true)->setBody($reply));
            if (TicketStatus::Open === $entityInstance->getStatus()) {
                $entityInstance->setStatus(TicketStatus::InProgress);
            }
            if (null !== $author = $entityInstance->getAuthor()) {
                $this->notifications->notify($author, NotificationCategory::System, 'Réponse du support', sprintf('Nouvelle réponse sur votre ticket %s : %s', $entityInstance->getReference(), $entityInstance->getSubject()));
            }
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    /** Pas de création manuelle : les tickets viennent de l’espace pro. */
    public function new(AdminContext $context): KeyValueStore|Response
    {
        $this->addFlash('danger', 'Un ticket se crée depuis l’espace « Support » du professionnel.');

        return new RedirectResponse($this->container->get(AdminUrlGenerator::class)->setController(self::class)->setAction(Action::INDEX)->generateUrl());
    }
}
