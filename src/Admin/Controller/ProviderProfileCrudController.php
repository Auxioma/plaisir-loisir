<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Provider\Entity\ProviderProfile;
use App\Provider\Enum\ProviderStatus;
use App\User\Entity\User;
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
        //
        // `choice_label` N'EST PAS FACULTATIF ICI : l'entité User n'a aucune
        // représentation textuelle, et le formulaire tombait sur « Object of
        // class User could not be converted to string ». Modifier un
        // prestataire renvoyait donc une erreur 500 — défaut trouvé par le
        // balayage du back-office, pas à l'œil.
        yield AssociationField::new('user', 'Compte rattaché')
            ->setFormTypeOption('choice_label', static fn (User $membre): string => self::describe($membre))
            ->formatValue(static fn (mixed $v, ProviderProfile $profil): string => null !== $profil->getUser() ? self::describe($profil->getUser()) : '—')
            ->setHelp('Le membre qui administrera ce prestataire. Peut être renseigné plus tard.')
            ->autocomplete();

        yield TextareaField::new('bio', 'Présentation')->hideOnIndex();

        yield UrlField::new('websiteUrl', 'Site web')->hideOnIndex();
        yield UrlField::new('facebookUrl', 'Facebook')->hideOnIndex();
        yield UrlField::new('instagramUrl', 'Instagram')->hideOnIndex();
        yield UrlField::new('linkedinUrl', 'LinkedIn')->hideOnIndex();
    }

    /**
     * Comment un compte se lit dans une liste déroulante.
     *
     * L'adresse e-mail est incluse : deux membres peuvent porter le même
     * nom, et c'est l'adresse qui les distingue sans ambiguïté.
     */
    private static function describe(User $membre): string
    {
        return sprintf('%s %s (%s)', $membre->getFirstName(), $membre->getLastName(), $membre->getEmail());
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
