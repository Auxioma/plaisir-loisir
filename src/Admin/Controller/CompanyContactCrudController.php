<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Corporate\Entity\CompanyContact;
use App\Corporate\Repository\CompanyContactRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Coordonnées publiques du site (page « Contactez-nous », espace pro).
 *
 * Une seule fiche : le bouton « Créer » disparaît dès qu'elle existe, et
 * elle ne se supprime pas (on la modifie). Tant qu'elle n'existe pas, le
 * site affiche les valeurs par défaut de CompanyContactProvider.
 *
 * @extends AbstractCrudController<CompanyContact>
 */
class CompanyContactCrudController extends AbstractCrudController
{
    public function __construct(private readonly CompanyContactRepository $contacts)
    {
    }

    public static function getEntityFqcn(): string
    {
        return CompanyContact::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('coordonnées du site')
            ->setEntityLabelInPlural('coordonnées du site')
            ->setPageTitle(Crud::PAGE_INDEX, 'Coordonnées du site')
            ->setHelp(
                Crud::PAGE_INDEX,
                'E-mail, téléphone et adresse affichés sur « Contactez-nous ». Laissez l\'adresse vide tant que le siège social n\'est pas connu : elle ne sera pas affichée.',
            );
    }

    public function configureActions(Actions $actions): Actions
    {
        $actions = $actions->disable(Action::DELETE, Action::BATCH_DELETE);

        return null !== $this->contacts->findCurrent() ? $actions->disable(Action::NEW) : $actions;
    }

    public function configureFields(string $pageName): iterable
    {
        yield EmailField::new('email', 'E-mail de contact');
        yield TextField::new('phone', 'Téléphone')->setHelp('Tel qu\'il doit s\'afficher, par exemple 07 45 15 54 51.');
        yield TextField::new('openingHours', 'Horaires du téléphone')->setHelp('Par exemple « Lun. – Ven. 9h – 18h ».');
        yield TextareaField::new('address', 'Adresse postale')
            ->setRequired(false)
            ->setHelp('Une ligne par ligne d\'adresse. Vide = adresse non affichée.');
    }
}
