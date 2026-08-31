<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Legal\Entity\LegalDocument;
use App\Legal\Enum\LegalDocumentType;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Textes juridiques : CGU, CGV, confidentialité, mentions légales, cookies.
 *
 * POURQUOI CET ÉCRAN EXISTE
 * Le CTO a demandé le 29/08 que ces textes soient gérés depuis la base : les
 * conditions générales évoluent dans le temps, et une évolution ne peut pas
 * exiger un déploiement. Le modèle existait déjà — table versionnée, service
 * de publication, commande de reprise — mais AUCUN écran ne permettait d'en
 * saisir : la gestion restait donc théorique.
 *
 * LA RÈGLE QUI SURPREND, ET QUI EST LE CŒUR DE CET ÉCRAN
 * UN TEXTE PUBLIÉ NE SE MODIFIE PLUS. Ce n'est pas une précaution excessive :
 * la table `legal_acceptance` retient quel document chaque membre a accepté à
 * l'inscription. Corriger le texte des CGU en place réécrirait rétroactivement
 * ce que 100 % des membres sont réputés avoir accepté — et ferait disparaître
 * la seule preuve utile en cas de litige.
 *
 * Le geste correct est donc toujours le même : PUBLIER UNE NOUVELLE VERSION.
 * Le site affiche automatiquement la plus récente entrée en vigueur ; les
 * acceptations passées continuent de pointer vers l'ancienne, qui reste
 * consultable ici.
 *
 * COMMENT ON TRAVAILLE SUR CET ÉCRAN
 *   1. « Créer » enregistre un BROUILLON : rien n'est visible sur le site.
 *   2. Tant qu'il est brouillon, on le modifie et on le supprime librement.
 *   3. « Publier » le met en ligne — et le verrouille définitivement.
 *
 * @extends AbstractCrudController<LegalDocument>
 */
class LegalDocumentCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminUrlGenerator $urls,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return LegalDocument::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        $regle = 'Un texte publié ne se modifie jamais : la table des acceptations retient ce que chaque membre a accepté. '
            .'Pour corriger ou faire évoluer un texte, publiez une NOUVELLE version — le site affichera automatiquement la plus récente.';

        return $crud
            ->setEntityLabelInSingular('texte juridique')
            ->setEntityLabelInPlural('textes juridiques')
            ->setPageTitle(Crud::PAGE_INDEX, 'Textes juridiques')
            ->setHelp(Crud::PAGE_INDEX, $regle)
            ->setHelp(Crud::PAGE_NEW, $regle)
            ->setHelp(Crud::PAGE_EDIT, 'Ce texte est encore un brouillon : il n\'est pas visible sur le site. Une fois publié, il ne sera plus modifiable.')
            // Le plus récent en premier : on ouvre cet écran pour voir ce qui
            // est en ligne aujourd'hui, pas ce qui l'était il y a deux ans.
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['title', 'version', 'content']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('type')
            ->add('locale');
    }

    public function configureFields(string $pageName): iterable
    {
        yield ChoiceField::new('type', 'Document')
            ->setChoices(array_combine(
                array_map(static fn (LegalDocumentType $t): string => $t->label(), LegalDocumentType::cases()),
                LegalDocumentType::cases(),
            ))
            ->setHelp('Le document dont ceci est une version. Chaque document a sa propre suite de versions.');

        yield ChoiceField::new('locale', 'Langue')
            ->setChoices(['Français' => 'fr', 'Anglais' => 'en'])
            ->setHelp('La version française et la version anglaise sont deux textes distincts, chacun avec sa propre numérotation.');

        yield TextField::new('version', 'Version')
            ->setHelp('Une référence lisible : « 1.0 », « 2.1 », « 2026-08 ». Elle ne peut pas déjà exister pour ce document dans cette langue.');

        yield TextField::new('title', 'Titre affiché')
            ->setHelp('Le titre en haut de la page publique.');

        yield TextEditorField::new('content', 'Texte')
            ->setHelp('CHAQUE TITRE DE NIVEAU 2 OUVRE UN ARTICLE : le sommaire « Sur cette page », la numérotation et les ancres en découlent tout seuls. Vous n\'avez ni sommaire à tenir, ni article à renuméroter.')
            ->hideOnIndex();

        yield TextField::new('changeSummary', 'Ce qui change')
            ->setHelp('Résumé des différences avec la version précédente. Sert à expliquer au membre pourquoi son accord est redemandé.')
            ->hideOnIndex();

        yield BooleanField::new('requiresReacceptance', 'Redemander l\'accord')
            ->setHelp('Une faute de frappe corrigée : non. Un changement dans le traitement des données : oui.')
            ->renderAsSwitch(false);

        // publishedAt et effectiveAt ne sont PAS proposés au formulaire :
        // l'entité n'expose aucun moyen de les écrire directement, seulement
        // de publier. Les rendre saisissables permettrait d'antidater une mise
        // en vigueur, c'est-à-dire de rendre un texte opposable avant qu'il
        // n'existe.
        yield DateTimeField::new('publishedAt', 'Publié le')->hideOnForm();
        yield DateTimeField::new('effectiveAt', 'En vigueur depuis')->hideOnForm();
    }

    public function configureActions(Actions $actions): Actions
    {
        $publier = Action::new('publish', 'Publier', 'fa fa-upload')
            ->linkToCrudAction('publish')
            ->displayIf(static fn (LegalDocument $document): bool => !$document->isPublished())
            ->addCssClass('btn btn-primary');

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $publier)
            ->add(Crud::PAGE_DETAIL, $publier)
            // Modifier et supprimer restent possibles TANT QUE le texte est un
            // brouillon. Un brouillon n'engage personne ; un texte publié est
            // une preuve, et une preuve ne se corrige pas.
            ->update(Crud::PAGE_INDEX, Action::EDIT, static fn (Action $a): Action => $a->displayIf(
                static fn (LegalDocument $document): bool => !$document->isPublished(),
            ))
            ->update(Crud::PAGE_INDEX, Action::DELETE, static fn (Action $a): Action => $a->displayIf(
                static fn (LegalDocument $document): bool => !$document->isPublished(),
            ))
            ->setPermission(Action::NEW, 'ROLE_ADMIN')
            ->setPermission(Action::DELETE, 'ROLE_ADMIN');
    }

    /**
     * Met le brouillon en ligne.
     *
     * La publication s'applique immédiatement : l'entité sait aussi différer
     * l'entrée en vigueur, mais laisser choisir cette date depuis un bouton
     * demanderait un formulaire, et un formulaire mal rempli rendrait un texte
     * opposable à une date passée. Le jour où un préavis sera nécessaire, ce
     * sera un écran à part, pas une case de plus.
     *
     * @param AdminContext<LegalDocument> $context
     */
    #[AdminRoute(path: '/{entityId}/publish', name: 'publish')]
    public function publish(AdminContext $context): Response
    {
        $document = $context->getEntity()->getInstance();

        if (!$document instanceof LegalDocument) {
            throw $this->createNotFoundException('Document introuvable.');
        }

        if ($document->isPublished()) {
            $this->addFlash('warning', 'Ce texte était déjà publié : rien n\'a été modifié.');
        } else {
            $document->publish();
            $this->entityManager->flush();

            $this->addFlash('success', sprintf(
                'La version %s de « %s » est en ligne. Elle remplace la précédente sur le site et ne peut plus être modifiée.',
                $document->getVersion(),
                $document->getType()->label(),
            ));
        }

        return new RedirectResponse(
            $this->urls->setController(self::class)->setAction(Action::INDEX)->generateUrl(),
        );
    }

    /**
     * Refuse l'accès direct au formulaire de modification d'un texte publié.
     *
     * Le bouton est déjà masqué, mais masquer un bouton ne protège rien :
     * l'adresse reste devinable. C'est ici que la règle est réellement tenue.
     */
    public function edit(AdminContext $context): KeyValueStore|Response
    {
        $document = $context->getEntity()->getInstance();

        if ($document instanceof LegalDocument && $document->isPublished()) {
            $this->addFlash('danger', 'Ce texte est publié : il ne peut plus être modifié. Créez une nouvelle version.');

            return new RedirectResponse(
                $this->urls->setController(self::class)->setAction(Action::INDEX)->generateUrl(),
            );
        }

        return parent::edit($context);
    }

    /**
     * Même garde-fou pour la suppression.
     */
    public function delete(AdminContext $context): KeyValueStore|Response
    {
        $document = $context->getEntity()->getInstance();

        if ($document instanceof LegalDocument && $document->isPublished()) {
            $this->addFlash('danger', 'Un texte publié ne se supprime pas : des acceptations le référencent.');

            return new RedirectResponse(
                $this->urls->setController(self::class)->setAction(Action::INDEX)->generateUrl(),
            );
        }

        return parent::delete($context);
    }
}
