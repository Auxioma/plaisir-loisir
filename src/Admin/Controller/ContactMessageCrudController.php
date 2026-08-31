<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Corporate\Entity\ContactMessage;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Messages reçus par le formulaire « Contactez-nous ».
 *
 * MÊME DÉFAUT QUE LES CANDIDATURES, MÊME CORRECTION
 * Le formulaire enregistrait le message et affichait « Nous vous répondrons au
 * plus vite ». Personne ne pouvait le lire : aucun code du projet ne relisait
 * cette table. La promesse était donc intenable, et personne ne le savait.
 *
 * La page Contact annonce par ailleurs une réponse sous vingt-quatre heures.
 * C'est un engagement pris envers le visiteur ; cet écran est le minimum pour
 * pouvoir le tenir.
 *
 * @extends AbstractCrudController<ContactMessage>
 */
class ContactMessageCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminUrlGenerator $urls,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ContactMessage::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('message')
            ->setEntityLabelInPlural('messages')
            ->setPageTitle(Crud::PAGE_INDEX, 'Messages de contact')
            ->setHelp(
                Crud::PAGE_INDEX,
                'La page Contact promet une réponse sous 24 h. On répond depuis sa messagerie, à l\'adresse indiquée, puis on marque le message comme traité.',
            )
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['name', 'email', 'subject', 'message']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('createdAt')
            ->add('handledAt');
    }

    public function configureFields(string $pageName): iterable
    {
        yield DateTimeField::new('createdAt', 'Reçu le')->hideOnForm();
        yield DateTimeField::new('handledAt', 'Traité le')->hideOnForm();

        yield TextField::new('name', 'Expéditeur');
        yield EmailField::new('email', 'E-mail');
        yield TextField::new('subject', 'Sujet');

        yield TextareaField::new('message', 'Message')->hideOnIndex();

        yield TextField::new('ipAddress', 'Adresse IP')->hideOnIndex();
    }

    public function configureActions(Actions $actions): Actions
    {
        $traiter = Action::new('markHandled', 'Marquer comme traité', 'fa fa-check')
            ->linkToCrudAction('markHandled')
            ->displayIf(static fn (ContactMessage $message): bool => null === $message->getHandledAt());

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $traiter)
            ->add(Crud::PAGE_DETAIL, $traiter)
            // Le message vient de quelqu'un d'autre : on le lit, on n'y touche
            // pas.
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_DETAIL, Action::EDIT);
    }

    /**
     * @param AdminContext<ContactMessage> $context
     */
    #[AdminRoute(path: '/{entityId}/mark-handled', name: 'mark_handled')]
    public function markHandled(AdminContext $context): Response
    {
        $message = $context->getEntity()->getInstance();

        if (!$message instanceof ContactMessage) {
            throw $this->createNotFoundException('Message introuvable.');
        }

        if (null === $message->getHandledAt()) {
            $message->markHandled();
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Message de %s marqué comme traité.', $message->getName()));
        }

        return new RedirectResponse(
            $this->urls->setController(self::class)->setAction(Action::INDEX)->generateUrl(),
        );
    }

    /*
     * ------------------------------------------------------------------------
     *  Retirer un bouton NE FERME PAS la route : /admin/contact-message/new
     *  et .../edit restent atteignables en tapant l'adresse. Ces deux
     *  méthodes sont la vraie protection.
     *
     *  Ce qui est en jeu n'est pas cosmétique : un message retouché ne serait
     *  plus celui que la personne a envoyé, et rien n'en garderait trace.
     * ------------------------------------------------------------------------
     */

    public function new(AdminContext $context): KeyValueStore|Response
    {
        $this->addFlash('danger', 'Un message ne se crée pas depuis le back-office : il vient du formulaire de contact.');

        return $this->backToIndex();
    }

    public function edit(AdminContext $context): KeyValueStore|Response
    {
        $this->addFlash('danger', "Un message ne se modifie pas : il doit rester ce que l'expéditeur a écrit.");

        return $this->backToIndex();
    }

    private function backToIndex(): RedirectResponse
    {
        return new RedirectResponse(
            $this->urls->setController(self::class)->setAction(Action::INDEX)->generateUrl(),
        );
    }
}
