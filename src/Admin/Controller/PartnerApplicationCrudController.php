<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Corporate\Entity\PartnerApplication;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Candidatures reçues par le formulaire « Devenir partenaire ».
 *
 * LE DÉFAUT QUE CET ÉCRAN CORRIGE
 * Le formulaire fonctionnait : la candidature était validée, enregistrée en
 * base, et l'expéditeur voyait un message de confirmation. Mais AUCUN code du
 * projet ne relisait jamais cette table. Le site recueillait des candidatures
 * et les jetait — en promettant une réponse.
 *
 * L'entité portait pourtant déjà un champ « traitée le », et une méthode
 * markHandled() : l'écran manquant était prévu, il n'avait jamais été écrit.
 *
 * TOUT EST EN LECTURE SEULE, SAUF LE FAIT DE MARQUER COMME TRAITÉE
 * Ces informations viennent d'un tiers. Les corriger ici les rendrait
 * différentes de ce que la personne a réellement écrit, sans trace du
 * changement — et c'est sur cette base qu'on décide de la suite.
 *
 * @extends AbstractCrudController<PartnerApplication>
 */
class PartnerApplicationCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminUrlGenerator $urls,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return PartnerApplication::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('candidature')
            ->setEntityLabelInPlural('candidatures')
            ->setPageTitle(Crud::PAGE_INDEX, 'Candidatures partenaires')
            ->setHelp(
                Crud::PAGE_INDEX,
                'Dossiers reçus par le formulaire « Devenir partenaire ». Une candidature retenue se poursuit sur l\'écran des prestataires : '
                .'on y crée le profil, puis on passe son statut à « Vérifié ».',
            )
            // Les plus récentes d'abord : une candidature se traite vite.
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['siteName', 'companyName', 'contactName', 'email', 'city']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('sector')
            ->add('createdAt')
            ->add('handledAt');
    }

    public function configureFields(string $pageName): iterable
    {
        yield DateTimeField::new('createdAt', 'Reçue le')->hideOnForm();

        yield DateTimeField::new('handledAt', 'Traitée le')
            ->hideOnForm()
            ->setHelp('Vide tant que personne ne s\'en est occupé.');

        yield TextField::new('siteName', 'Nom du site');
        yield TextField::new('companyName', 'Société');
        yield TextField::new('contactName', 'Contact');
        yield EmailField::new('email', 'E-mail');
        yield TextField::new('phone', 'Téléphone')->hideOnIndex();

        yield UrlField::new('siteUrl', 'Adresse du site')->hideOnIndex();
        yield TextField::new('sector', 'Secteur');
        yield TextField::new('traffic', 'Trafic annoncé')->hideOnIndex();

        yield TextField::new('address', 'Adresse')->hideOnIndex();
        yield TextField::new('postalCode', 'Code postal')->hideOnIndex();
        yield TextField::new('city', 'Ville')->hideOnIndex();

        yield TextareaField::new('description', 'Présentation')->hideOnIndex();

        yield BooleanField::new('termsAccepted', 'Conditions acceptées')
            ->renderAsSwitch(false)
            ->hideOnIndex();

        // Conservée pour la lutte contre les envois automatisés ; sans usage
        // au quotidien, donc hors de la liste.
        yield TextField::new('ipAddress', 'Adresse IP')->hideOnIndex();
    }

    public function configureActions(Actions $actions): Actions
    {
        $traiter = Action::new('markHandled', 'Marquer comme traitée', 'fa fa-check')
            ->linkToCrudAction('markHandled')
            ->displayIf(static fn (PartnerApplication $candidature): bool => null === $candidature->getHandledAt());

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $traiter)
            ->add(Crud::PAGE_DETAIL, $traiter)
            // Une candidature vient d'un tiers : on la lit, on ne la réécrit
            // pas, et on ne l'invente pas.
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_DETAIL, Action::EDIT);
    }

    /**
     * @param AdminContext<PartnerApplication> $context
     */
    #[AdminRoute(path: '/{entityId}/mark-handled', name: 'mark_handled')]
    public function markHandled(AdminContext $context): Response
    {
        $candidature = $context->getEntity()->getInstance();

        if (!$candidature instanceof PartnerApplication) {
            throw $this->createNotFoundException('Candidature introuvable.');
        }

        if (null === $candidature->getHandledAt()) {
            $candidature->markHandled();
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('Candidature de %s marquée comme traitée.', $candidature->getSiteName()));
        }

        return new RedirectResponse(
            $this->urls->setController(self::class)->setAction(Action::INDEX)->generateUrl(),
        );
    }

    /*
     * ------------------------------------------------------------------------
     *  Retirer un bouton NE FERME PAS la route : /admin/partner-application/new
     *  et .../edit restent atteignables en tapant l'adresse. Ces deux
     *  méthodes sont la vraie protection.
     *
     *  Ce qui est en jeu n'est pas cosmétique : une candidature fabriquée ou
     *  retouchée depuis le back-office serait indiscernable d'une vraie, et
     *  c'est sur elle qu'on décide de la suite.
     * ------------------------------------------------------------------------
     */

    public function new(AdminContext $context): KeyValueStore|Response
    {
        $this->addFlash('danger', 'Une candidature ne se crée pas depuis le back-office : elle vient du formulaire public.');

        return $this->backToIndex();
    }

    public function edit(AdminContext $context): KeyValueStore|Response
    {
        $this->addFlash('danger', 'Une candidature ne se modifie pas : elle doit rester ce que le candidat a écrit.');

        return $this->backToIndex();
    }

    private function backToIndex(): RedirectResponse
    {
        return new RedirectResponse(
            $this->urls->setController(self::class)->setAction(Action::INDEX)->generateUrl(),
        );
    }
}
