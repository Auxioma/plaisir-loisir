<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Provider\Entity\ProviderDocument;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Enum\ProviderDocumentKind;
use App\Provider\Service\ProviderDocumentStorage;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Pièces justificatives déposées par les professionnels à leur inscription.
 *
 * À QUOI SERT CET ÉCRAN
 * L'écran de fin de l'inscription promet que « notre service client vous
 * contactera après vérification ». La vérification, c'est ici : Loïc lit les
 * pièces, puis passe le prestataire en « Vérifié » sur l'écran voisin.
 * Sans cet écran, les documents seraient déposés et jamais regardés — le même
 * trou que celui des candidatures partenaires avant le 24/08.
 *
 * LE FICHIER NE S'OUVRE PAS PAR SON ADRESSE
 * Les pièces vivent dans var/uploads/, hors racine web : aucun lien direct
 * n'existe. Le téléchargement passe donc par l'action ci-dessous, derrière
 * ROLE_ADMIN comme tout /admin. C'est le prix — voulu — du fait qu'un extrait
 * Kbis ne soit pas servi par le serveur web.
 *
 * TOUT EST EN LECTURE SEULE
 * Ces fichiers viennent d'un tiers. Les remplacer depuis le back-office
 * rendrait le dossier différent de ce que le prestataire a réellement fourni,
 * sans laisser de trace.
 *
 * @extends AbstractCrudController<ProviderDocument>
 */
class ProviderDocumentCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ProviderDocumentStorage $storage,
        private readonly AdminUrlGenerator $urls,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ProviderDocument::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('pièce justificative')
            ->setEntityLabelInPlural('pièces justificatives')
            ->setPageTitle(Crud::PAGE_INDEX, 'Pièces justificatives')
            ->setHelp(
                Crud::PAGE_INDEX,
                'Documents déposés par les professionnels à leur inscription. Une fois les pièces contrôlées, '
                .'passez le prestataire en « Vérifié » sur l\'écran des prestataires.',
            )
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['originalName']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('kind')
            ->add('createdAt');
    }

    public function configureFields(string $pageName): iterable
    {
        yield DateTimeField::new('createdAt', 'Déposée le')->hideOnForm();

        yield AssociationField::new('providerProfile', 'Prestataire')
            ->formatValue(static fn (mixed $v, ProviderDocument $piece): string => $piece->getProviderProfile()?->getDisplayName() ?? '—')
            ->setFormTypeOption('choice_label', static fn (ProviderProfile $profil): string => $profil->getDisplayName());

        yield ChoiceField::new('kind', 'Nature')
            ->setChoices(array_combine(
                array_map(static fn (ProviderDocumentKind $k): string => $k->label(), ProviderDocumentKind::cases()),
                ProviderDocumentKind::cases(),
            ))
            // Sans formatValue(), la liste afficherait « operating_licence » :
            // les libellés de setChoices() ne servent qu'au formulaire.
            ->formatValue(static fn (mixed $v, ProviderDocument $piece): string => $piece->getKind()->label())
            ->renderAsBadges();

        yield TextField::new('originalName', 'Nom du fichier');

        yield TextField::new('mimeType', 'Type')->hideOnIndex();

        // Le champ stocké est un entier d'octets ; c'est la version lisible
        // qu'on affiche, et elle ne se trie donc pas — l'ordre alphabétique
        // de « 900 Ko » et « 1,2 Mo » n'aurait aucun sens.
        yield TextField::new('readableSize', 'Taille')
            ->setSortable(false)
            ->hideOnForm();
    }

    public function configureActions(Actions $actions): Actions
    {
        $telecharger = Action::new('download', 'Télécharger', 'fa fa-download')
            ->linkToCrudAction('download');

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $telecharger)
            ->add(Crud::PAGE_DETAIL, $telecharger)
            // Une pièce vient du prestataire : on la lit, on ne la réécrit pas.
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_DETAIL, Action::EDIT);
    }

    /**
     * Sert le fichier depuis var/uploads/, sous son nom d'origine.
     *
     * @param AdminContext<ProviderDocument> $context
     */
    #[AdminRoute(path: '/{entityId}/download', name: 'download')]
    public function download(AdminContext $context): Response
    {
        $piece = $context->getEntity()->getInstance();

        if (!$piece instanceof ProviderDocument) {
            return $this->redirect($this->urls->setAction(Action::INDEX)->generateUrl());
        }

        $chemin = $this->storage->pathOf($piece);

        if (!is_file($chemin)) {
            // Le fichier a disparu du disque alors que la ligne subsiste :
            // mieux vaut le dire que de renvoyer une page blanche.
            $this->addFlash('danger', sprintf('Le fichier « %s » est introuvable sur le serveur.', $piece->getOriginalName()));

            return $this->redirect($this->urls->setAction(Action::INDEX)->generateUrl());
        }

        $reponse = new BinaryFileResponse($chemin);
        $reponse->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $piece->getOriginalName());

        return $reponse;
    }

    /**
     * @param AdminContext<ProviderDocument> $context
     */
    public function new(AdminContext $context): KeyValueStore|Response
    {
        // Le bouton est retiré de l'écran ; on refuse aussi côté serveur, car
        // une adresse forgée à la main y mènerait quand même.
        return new RedirectResponse($this->urls->setAction(Action::INDEX)->generateUrl());
    }

    /**
     * @param AdminContext<ProviderDocument> $context
     */
    public function edit(AdminContext $context): KeyValueStore|Response
    {
        return new RedirectResponse($this->urls->setAction(Action::INDEX)->generateUrl());
    }
}
