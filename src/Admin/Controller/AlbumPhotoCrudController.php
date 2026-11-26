<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Service\PrivateActivityAdminService;
use App\PrivateActivity\Entity\Album;
use App\PrivateActivity\Entity\Photo;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Photos déposées dans les albums des activités gratuites (26/11).
 *
 * Modération seulement : consulter et retirer. Une photo appartient à son
 * auteur, l'équipe ne la remplace pas. Le retrait supprime aussi le fichier,
 * est historisé et l'auteur est prévenu.
 *
 * @extends AbstractCrudController<Photo>
 */
#[IsGranted('ROLE_ADMIN')]
class AlbumPhotoCrudController extends AbstractCrudController
{
    public function __construct(private readonly PrivateActivityAdminService $management)
    {
    }

    public static function getEntityFqcn(): string
    {
        return Photo::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('photo d’album')
            ->setEntityLabelInPlural('photos des albums')
            ->setPageTitle(Crud::PAGE_INDEX, 'Photos des albums (activités gratuites)')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['album.privateActivity.title', 'author.email', 'author.firstName', 'author.lastName']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('album', 'Album')->setFormTypeOption('value_type_options.choice_label', static fn (Album $a): string => $a->getPrivateActivity()?->getTitle() ?? '—'))
            ->add(EntityFilter::new('author', 'Auteur')->setFormTypeOption('value_type_options.choice_label', 'email'));
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield ImageField::new('path', 'Photo');
        yield AssociationField::new('album', 'Activité')
            ->formatValue(static fn (mixed $v, Photo $p): string => $p->getAlbum()?->getPrivateActivity()?->getTitle() ?? '—');
        yield AssociationField::new('author', 'Auteur')
            ->formatValue(static fn (mixed $v, Photo $p): string => null === ($u = $p->getAuthor()) ? '—' : trim($u->getFirstName().' '.$u->getLastName()).' — '.$u->getEmail());
        yield DateTimeField::new('createdAt', 'Déposée le');
    }

    public function deleteEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        $actor = $this->getUser();
        $this->management->deletePhoto($entityInstance, $actor instanceof User ? $actor : null);
    }
}
