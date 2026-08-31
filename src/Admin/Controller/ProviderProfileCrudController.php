<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Provider\Entity\ProviderProfile;
use App\Provider\Enum\ProviderStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

/**
 * Prestataires : les professionnels qui publient des activités.
 *
 * POURQUOI CET ÉCRAN COMPTE
 * Toute activité du catalogue appartient à un prestataire — c'est une
 * association obligatoire. Jusqu'ici, aucun écran ne permettait d'en créer :
 * les seuls existants venaient des fixtures, c'est-à-dire d'une dépendance de
 * développement absente en production. Autrement dit, personne n'aurait pu
 * saisir la première activité réelle.
 *
 * LE STATUT EST LA DÉCISION MÉTIER
 * « Brouillon » et « En vérification » sont des états d'attente ; « Vérifié »
 * signifie que les pièces du dossier ont été contrôlées. C'est cette bascule
 * que Loïc actionne quand une candidature « Devenir partenaire » aboutit —
 * l'écran des candidatures est à côté.
 *
 * @extends AbstractCrudController<ProviderProfile>
 */
class ProviderProfileCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ProviderProfile::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('prestataire')
            ->setEntityLabelInPlural('prestataires')
            ->setPageTitle(Crud::PAGE_INDEX, 'Prestataires')
            ->setHelp(
                Crud::PAGE_INDEX,
                'Chaque activité du catalogue appartient à un prestataire. Un compte rattaché ici devrait aussi porter le rôle « Prestataire » sur l\'écran des membres.',
            )
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['displayName', 'companyName']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('status');
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('displayName', 'Nom affiché')
            ->setHelp('Le nom que voient les visiteurs sur la fiche d\'une activité.');

        yield TextField::new('companyName', 'Raison sociale')->hideOnIndex();

        yield ChoiceField::new('status', 'Statut')
            ->setChoices(array_combine(
                array_map(static fn (ProviderStatus $s): string => self::statusLabel($s), ProviderStatus::cases()),
                ProviderStatus::cases(),
            ))
            // Même piège que partout : le libellé des choix ne sert qu'au
            // formulaire, la liste afficherait « pending_verification ».
            ->formatValue(static fn (mixed $v, ProviderProfile $profil): string => self::statusLabel($profil->getStatus()))
            ->renderAsBadges()
            ->setHelp('« Vérifié » atteste que le dossier a été contrôlé. C\'est la bascule à actionner quand une candidature aboutit.');

        // Le compte qui pilotera l'espace professionnel. Peut rester vide le
        // temps que le prestataire crée le sien.
        yield AssociationField::new('user', 'Compte rattaché')
            ->setHelp('Le membre qui administrera ce prestataire. Peut être renseigné plus tard.')
            ->autocomplete();

        yield TextareaField::new('bio', 'Présentation')->hideOnIndex();

        yield UrlField::new('websiteUrl', 'Site web')->hideOnIndex();
        yield UrlField::new('facebookUrl', 'Facebook')->hideOnIndex();
        yield UrlField::new('instagramUrl', 'Instagram')->hideOnIndex();
        yield UrlField::new('linkedinUrl', 'LinkedIn')->hideOnIndex();
    }

    private static function statusLabel(ProviderStatus $status): string
    {
        return match ($status) {
            ProviderStatus::Draft => 'Brouillon',
            ProviderStatus::PendingVerification => 'En vérification',
            ProviderStatus::Verified => 'Vérifié',
            ProviderStatus::Suspended => 'Suspendu',
        };
    }
}
