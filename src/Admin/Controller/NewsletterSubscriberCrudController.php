<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Notification\Entity\NewsletterSubscriber;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Abonnés à la lettre d'information (« Ne manquez aucune offre ! », 04/10).
 *
 * @extends AbstractCrudController<NewsletterSubscriber>
 */
class NewsletterSubscriberCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return NewsletterSubscriber::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('abonné')
            ->setEntityLabelInPlural('abonnés')
            ->setPageTitle(Crud::PAGE_INDEX, 'Abonnés à la newsletter')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['email']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::NEW);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('unsubscribedAt')->add('source')->add('locale');
    }

    public function configureFields(string $pageName): iterable
    {
        yield EmailField::new('email', 'E-mail');
        yield TextField::new('source', 'Inscrit depuis')->setFormTypeOption('disabled', true);
        yield TextField::new('locale', 'Langue')->setFormTypeOption('disabled', true);
        yield DateTimeField::new('createdAt', 'Inscrit le')->hideOnForm();
        yield DateTimeField::new('unsubscribedAt', 'Désinscrit le');
    }
}
