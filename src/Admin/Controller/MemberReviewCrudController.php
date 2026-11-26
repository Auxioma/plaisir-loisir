<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Service\PrivateActivityAdminService;
use App\PrivateActivity\Entity\MemberReview;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Avis laissés par les participants sur l'organisateur d'une activité
 * gratuite (26/11). Ils alimentent la réputation affichée sur le profil du
 * membre (MemberReputation) : l'équipe peut les consulter et retirer un avis
 * abusif. Pas de modification : un avis reste la parole de son auteur.
 *
 * @extends AbstractCrudController<MemberReview>
 */
#[IsGranted('ROLE_ADMIN')]
class MemberReviewCrudController extends AbstractCrudController
{
    public function __construct(private readonly PrivateActivityAdminService $management)
    {
    }

    public static function getEntityFqcn(): string
    {
        return MemberReview::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('avis entre membres')
            ->setEntityLabelInPlural('avis entre membres')
            ->setPageTitle(Crud::PAGE_INDEX, 'Avis sur les organisateurs (activités gratuites)')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['privateActivity.title', 'author.email', 'organizer.email', 'comment']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('privateActivity', 'Activité')->setFormTypeOption('value_type_options.choice_label', 'title'))
            ->add(EntityFilter::new('organizer', 'Organisateur')->setFormTypeOption('value_type_options.choice_label', 'email'))
            ->add(EntityFilter::new('author', 'Auteur')->setFormTypeOption('value_type_options.choice_label', 'email'))
            ->add('rating');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('privateActivity', 'Activité')
            ->formatValue(static fn (mixed $v, MemberReview $r): string => $r->getPrivateActivity()?->getTitle() ?? '—');
        yield AssociationField::new('organizer', 'Organisateur noté')
            ->formatValue(static fn (mixed $v, MemberReview $r): string => $r->getOrganizer()?->getEmail() ?? '—');
        yield AssociationField::new('author', 'Auteur')
            ->formatValue(static fn (mixed $v, MemberReview $r): string => $r->getAuthor()?->getEmail() ?? '—');
        yield IntegerField::new('rating', 'Note /5');
        // Détail des quatre critères (getters de l'entité), sur la fiche seulement.
        foreach (MemberReview::CRITERIA as $key => $label) {
            yield IntegerField::new($key, $label)->onlyOnDetail();
        }
        yield TextareaField::new('comment', 'Commentaire')->setMaxLength(80);
        yield DateTimeField::new('createdAt', 'Déposé le');
    }

    public function deleteEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        $actor = $this->getUser();
        $this->management->deleteReview($entityInstance, $actor instanceof User ? $actor : null);
    }
}
