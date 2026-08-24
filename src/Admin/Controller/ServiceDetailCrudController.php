<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Form\GuaranteeType;
use App\Admin\Form\KeyFactType;
use App\Admin\Form\LabelValueType;
use App\Catalog\Entity\ServiceDetail;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Le contenu de la page publique d'une activité.
 *
 * POURQUOI CET ÉCRAN EST INDISPENSABLE
 * `ActivityController::show()` renvoie une erreur 404 quand une activité n'a
 * pas de fiche détaillée — volontairement : une page à moitié vide serait pire
 * qu'une absence franche. Mais tant que cette fiche n'était pas administrable,
 * TOUTE activité saisie dans le back-office avait une page publique cassée.
 * Elle apparaissait dans le catalogue, et le clic tombait en 404. Constaté en
 * production sur « kayak-lac-rose ».
 *
 * LES BLOCS STRUCTURÉS
 * « En bref », « Rendez-vous » et « Garanties » ne sont pas de simples listes
 * de textes mais des listes de lignes à deux ou trois champs, stockées en
 * JSON. D'où trois petits types de formulaire (src/Admin/Form) : ce sont des
 * tableaux, pas des entités, leur `data_class` reste donc à null.
 *
 * @extends AbstractCrudController<ServiceDetail>
 */
class ServiceDetailCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ServiceDetail::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('fiche détaillée')
            ->setEntityLabelInPlural('fiches détaillées')
            ->setPageTitle(Crud::PAGE_INDEX, 'Fiches détaillées')
            ->setPageTitle(Crud::PAGE_NEW, 'Nouvelle fiche détaillée')
            ->setHelp(
                Crud::PAGE_NEW,
                'Sans fiche détaillée, la page d\'une activité renvoie une erreur : '
                .'elle reste visible dans le catalogue mais le clic ne mène nulle part. '
                .'Une activité publiée doit donc toujours avoir la sienne.',
            )
            ->setSearchFields(['organizer', 'presentationSubtitle']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addTab('Présentation');
        yield AssociationField::new('service', 'Activité')
            ->setFormTypeOption('choice_label', 'title')
            ->formatValue(static fn (mixed $v, ServiceDetail $d): ?string => $d->getService()?->getTitle());
        yield TextField::new('organizer', 'Organisateur')
            ->setHelp('Le nom affiché sous le titre, par exemple « Proposé par Aventure Nature ».');
        yield TextField::new('presentationSubtitle', 'Sous-titre de présentation')->hideOnIndex();
        yield TextareaField::new('presentationText', 'Texte de présentation')
            ->setNumOfRows(6)
            ->hideOnIndex();
        yield ArrayField::new('breadcrumb', 'Fil d\'Ariane')
            ->setHelp('Une entrée par niveau, par exemple : Accueil, Activités, Kayak.')
            ->hideOnIndex();

        yield FormField::addTab('Points forts');
        yield TextField::new('highlightsTitle', 'Titre du bloc')->hideOnIndex();
        yield ArrayField::new('highlights', 'Points forts')->hideOnIndex();
        yield ArrayField::new('included', 'Ce qui est compris')->hideOnIndex();
        yield ArrayField::new('excluded', 'Ce qui ne l\'est pas')->hideOnIndex();
        yield ArrayField::new('toBring', 'À apporter')->hideOnIndex();
        yield ArrayField::new('cannotParticipate', 'Ne peuvent pas participer')->hideOnIndex();

        yield FormField::addTab('En bref');
        yield CollectionField::new('keyFacts', 'Lignes du tableau')
            ->setEntryType(KeyFactType::class)
            ->allowAdd()
            ->allowDelete()
            ->setHelp('Durée, nombre de personnes, âge minimum… Une ligne par information.')
            ->hideOnIndex();

        yield FormField::addTab('Rendez-vous');
        yield ImageField::new('mapImage', 'Plan du point de départ')
            ->setUploadDir('public')
            ->setUploadedFileNamePattern('uploads/[slug]-[timestamp].[extension]')
            ->hideOnIndex();
        yield CollectionField::new('meetingPoints', 'Lignes du bloc')
            ->setEntryType(LabelValueType::class)
            ->allowAdd()
            ->allowDelete()
            ->hideOnIndex();
        yield CollectionField::new('guarantees', 'Garanties')
            ->setEntryType(GuaranteeType::class)
            ->allowAdd()
            ->allowDelete()
            ->setHelp('Affichées sous le plan : annulation, paiement, assistance…')
            ->hideOnIndex();

        yield FormField::addTab('Réservation et avis');
        yield IntegerField::new('price', 'Prix affiché')
            ->setHelp('Celui du bloc de réservation. Il peut différer du prix de la carte du catalogue.');
        yield TextField::new('modalTitle', 'Titre de la fenêtre de réservation')->hideOnIndex();
        yield TextField::new('reviewsScore', 'Note affichée')->hideOnIndex();
        yield IntegerField::new('reviewsOutOf', 'Note sur')->hideOnIndex();
        yield IntegerField::new('reviewsTotal', 'Nombre d\'avis affiché')->hideOnIndex();
    }
}
