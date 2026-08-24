<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Catalog\Entity\Media;
use App\Catalog\Presenter\ActivityPresenter;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

/**
 * Photos des activités.
 *
 * OÙ VONT LES FICHIERS, ET POURQUOI
 * Les images livrées avec le site vivent dans `assets/` et passent par
 * l'AssetMapper, qui les renomme avec une empreinte de contenu à la
 * compilation. Un fichier téléversé après la mise en ligne ne peut pas suivre
 * ce chemin : il n'existait pas au moment de la compilation.
 *
 * Ils sont donc déposés dans `public/uploads/`, et le chemin enregistré en
 * base commence par « uploads/ ». Vérifié : `asset('uploads/photo.jpg')`
 * renvoie « /uploads/photo.jpg » tel quel, sans erreur. Aucun gabarit n'a donc
 * eu à changer — les cartes affichent indifféremment une image livrée et une
 * image téléversée.
 *
 * @extends AbstractCrudController<Media>
 */
class MediaCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Media::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('photo')
            ->setEntityLabelInPlural('photos')
            ->setPageTitle(Crud::PAGE_INDEX, 'Photos des activités')
            ->setHelp(
                Crud::PAGE_NEW,
                'La photo de couverture est celle qui apparaît sur la carte du catalogue : '
                .'il en faut une par activité. Les autres alimentent le carrousel de la fiche.',
            )
            ->setDefaultSort(['position' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('service', 'Activité')
            ->setFormTypeOption('choice_label', 'title')
            // Sans cela, la liste affiche « Service #01M0K3ZP... » : EasyAdmin
            // se rabat sur l'identifiant faute de __toString sur l'entite.
            ->formatValue(static fn (mixed $v, Media $media): ?string => $media->getService()?->getTitle());
        yield ChoiceField::new('type', 'Rôle de la photo')->setChoices([
            'Couverture (carte du catalogue)' => ActivityPresenter::MEDIA_COVER,
            'Galerie (carrousel de la fiche)' => ActivityPresenter::MEDIA_GALLERY,
        ]);
        yield ImageField::new('path', 'Fichier')
            // Pas de setBasePath : le gabarit d'EasyAdmin passe deja par
            // asset(). Lui donner un chemin absolu court-circuiterait
            // l'AssetMapper, et les photos livrees avec le site
            // (images/...) s'afficheraient cassees. Sans lui, asset() rend
            // /assets/images/...-EMPREINTE.jpg pour les unes et
            // /uploads/... pour les autres.
            ->setUploadDir('public/uploads')
            ->setUploadedFileNamePattern('uploads/[slug]-[timestamp].[extension]')
            ->setHelp('Format paysage conseillé. Le fichier est renommé automatiquement pour éviter d\'écraser une photo existante.');
        yield IntegerField::new('position', 'Ordre dans la galerie')
            ->setHelp('Le plus petit nombre passe en premier.');
    }
}
