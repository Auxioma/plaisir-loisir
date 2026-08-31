<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\User\Entity\User;
use App\User\Enum\UserStatus;
use App\User\Service\AccountAnonymizer;
use App\User\Service\PasswordResetService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Comptes des membres.
 *
 * POURQUOI CET ÉCRAN EXISTE
 * Demande du CTO le 31/08 : Loïc doit avoir la main sur le site depuis le
 * back-office. Jusqu'ici, donner le rôle administrateur passait par une
 * commande en console (`app:admin:grant`), c'est-à-dire par un accès SSH au
 * serveur. Personne d'autre qu'un développeur ne pouvait le faire.
 *
 * CE QUE CET ÉCRAN NE PERMET PAS, ET POURQUOI
 *  - LIRE OU CHOISIR UN MOT DE PASSE. Ils sont stockés sous forme d'empreintes
 *    et rien ne permet de les retrouver, y compris à nous. Le champ n'est donc
 *    pas affiché ; pour dépanner quelqu'un, on déclenche une réinitialisation,
 *    qui envoie un code à SON adresse. Un administrateur qui choisirait le mot
 *    de passe d'un membre pourrait ensuite se connecter à sa place.
 *  - SUPPRIMER UN COMPTE. Les réservations, les paiements et les acceptations
 *    des conditions générales le référencent, avec des clés étrangères en
 *    RESTRICT : la base refuserait. Et il ne FAUT pas : une réservation payée
 *    est une pièce comptable à conserver dix ans. « Anonymiser » remplace la
 *    suppression et concilie les deux obligations.
 *
 * @extends AbstractCrudController<User>
 */
class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminUrlGenerator $urls,
        private readonly AccountAnonymizer $anonymizer,
        private readonly PasswordResetService $passwordReset,
        private readonly Security $security,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('membre')
            ->setEntityLabelInPlural('membres')
            ->setPageTitle(Crud::PAGE_INDEX, 'Membres')
            ->setHelp(
                Crud::PAGE_INDEX,
                'Suspendre un compte l\'empêche réellement de se connecter. '
                .'Un compte ne se supprime pas : « Anonymiser » efface les données personnelles et conserve l\'historique des réservations, '
                .'qui est une pièce comptable.',
            )
            // Les derniers inscrits en tête : c'est ce qu'on vient vérifier.
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['email', 'firstName', 'lastName', 'phone']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('status')
            ->add('createdAt');
    }

    public function configureFields(string $pageName): iterable
    {
        yield EmailField::new('email', 'Adresse e-mail')
            ->setHelp('Sert d\'identifiant de connexion : la modifier change la façon dont la personne se connecte.');

        yield TextField::new('firstName', 'Prénom');
        yield TextField::new('lastName', 'Nom');

        yield TelephoneField::new('phone', 'Téléphone')->hideOnIndex();

        yield ChoiceField::new('status', 'Statut')
            ->setChoices(array_combine(
                array_map(static fn (UserStatus $s): string => self::statusLabel($s), UserStatus::cases()),
                UserStatus::cases(),
            ))
            ->setHelp('« Suspendu » interdit la connexion. « En attente » ne bloque rien aujourd\'hui : l\'inscription active immédiatement.');

        // Les rôles sont un tableau en base ; ROLE_USER est ajouté
        // implicitement par l'entité et n'a donc pas à être proposé.
        yield ChoiceField::new('roles', 'Rôles')
            ->setChoices([
                'Administrateur — accès complet au back-office' => 'ROLE_ADMIN',
                'Prestataire — publie des activités' => 'ROLE_PROVIDER',
            ])
            ->allowMultipleChoices()
            ->renderExpanded()
            ->setHelp('Tout membre a déjà les droits de base : n\'ajoutez ici que ce qui va au-delà.');

        yield DateTimeField::new('createdAt', 'Inscrit le')->hideOnForm();

        // Affichée pour qu'un compte anonymisé se reconnaisse au premier coup
        // d'œil dans la liste.
        yield DateTimeField::new('deletedAt', 'Anonymisé le')->hideOnForm();
    }

    public function configureActions(Actions $actions): Actions
    {
        $anonymiser = Action::new('anonymize', 'Anonymiser', 'fa fa-user-slash')
            ->linkToCrudAction('anonymize')
            ->displayIf(static fn (User $user): bool => !$user->isDeleted())
            ->addCssClass('btn btn-danger');

        $reinitialiser = Action::new('sendReset', 'Réinitialiser le mot de passe', 'fa fa-key')
            ->linkToCrudAction('sendReset')
            ->displayIf(static fn (User $user): bool => !$user->isDeleted());

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $anonymiser)
            ->add(Crud::PAGE_DETAIL, $anonymiser)
            ->add(Crud::PAGE_DETAIL, $reinitialiser)
            // La suppression est retirée partout : elle échouerait sur la
            // plupart des comptes et ne doit pas réussir sur les autres.
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_DETAIL, Action::DELETE)
            // Créer un membre depuis le back-office demanderait de lui choisir
            // un mot de passe, c'est-à-dire de pouvoir se connecter à sa place.
            // Les comptes se créent par l'inscription.
            ->remove(Crud::PAGE_INDEX, Action::NEW);
    }

    /**
     * Efface les données personnelles sans supprimer la ligne.
     *
     * @param AdminContext<User> $context
     */
    #[AdminRoute(path: '/{entityId}/anonymize', name: 'anonymize')]
    public function anonymize(AdminContext $context): Response
    {
        $membre = $context->getEntity()->getInstance();

        if (!$membre instanceof User) {
            throw $this->createNotFoundException('Membre introuvable.');
        }

        // On ne s'anonymise pas soi-même : la personne perdrait sa session en
        // cours et son propre accès au back-office, sans moyen de revenir.
        if ($this->security->getUser() === $membre) {
            $this->addFlash('danger', 'Vous ne pouvez pas anonymiser votre propre compte.');

            return $this->backToIndex();
        }

        if ($this->isLastAdmin($membre)) {
            $this->addFlash('danger', 'C\'est le dernier compte administrateur : l\'anonymiser fermerait le back-office à tout le monde.');

            return $this->backToIndex();
        }

        $adresse = $membre->getEmail();
        $this->anonymizer->anonymize($membre);

        $this->addFlash('success', sprintf(
            'Le compte %s a été anonymisé. Ses réservations et ses paiements restent en base, sans plus rien qui l\'identifie.',
            $adresse,
        ));

        return $this->backToIndex();
    }

    /**
     * Déclenche une réinitialisation de mot de passe.
     *
     * L'administrateur ne CHOISIT rien : il provoque l'envoi d'un code à
     * l'adresse du membre, exactement comme le ferait ce dernier depuis
     * « Mot de passe oublié ». C'est la seule façon d'aider quelqu'un sans
     * pouvoir se connecter à sa place — un administrateur qui fixerait le mot
     * de passe d'un membre aurait ensuite accès à son compte.
     *
     * @param AdminContext<User> $context
     */
    #[AdminRoute(path: '/{entityId}/send-reset', name: 'send_reset')]
    public function sendReset(AdminContext $context): Response
    {
        $membre = $context->getEntity()->getInstance();

        if (!$membre instanceof User) {
            throw $this->createNotFoundException('Membre introuvable.');
        }

        if ($membre->isDeleted()) {
            $this->addFlash('danger', 'Ce compte est anonymisé : il n\'a plus d\'adresse à laquelle écrire.');

            return $this->backToIndex();
        }

        $this->passwordReset->requestCode($membre->getEmail());

        $this->addFlash('success', sprintf(
            'Un code de réinitialisation part vers %s. Vous ne le connaissez pas, et c\'est voulu : seul le membre peut choisir son nouveau mot de passe.',
            $membre->getEmail(),
        ));

        return $this->backToIndex();
    }

    /**
     * Empêche de retirer le dernier rôle administrateur par le formulaire.
     *
     * Le bouton « Anonymiser » est déjà protégé, mais on peut arriver au même
     * résultat en décochant simplement la case : le back-office se fermerait
     * alors à tout le monde, et seul un accès SSH permettrait de le rouvrir.
     *
     * @param AdminContext<User> $context
     */
    public function edit(AdminContext $context): KeyValueStore|Response
    {
        $reponse = parent::edit($context);
        $membre = $context->getEntity()->getInstance();

        if ($membre instanceof User && !\in_array('ROLE_ADMIN', $membre->getRoles(), true) && $this->countAdmins() < 1) {
            $membre->setRoles(array_values(array_unique([...$membre->getRoles(), 'ROLE_ADMIN'])));
            $this->entityManager->flush();

            $this->addFlash('warning', 'Le rôle administrateur a été rétabli : c\'était le dernier, le retirer aurait fermé le back-office à tout le monde.');
        }

        return $reponse;
    }

    /**
     * Ferme réellement les routes dont on a retiré les boutons.
     *
     * Retirer une action d'EasyAdmin masque le bouton ; l'adresse
     * /admin/user/new reste tapable, et /admin/user/{id}/delete aussi. Sans
     * ces deux méthodes, la suppression d'un compte resterait possible à qui
     * connaît l'URL — elle échouerait sur la plupart des comptes à cause des
     * clés étrangères, mais réussirait sur un compte jamais utilisé, et ferait
     * disparaître une inscription sans trace.
     *
     * @param AdminContext<User> $context
     */
    public function new(AdminContext $context): KeyValueStore|Response
    {
        $this->addFlash('danger', "Un compte ne se crée pas depuis le back-office : il faudrait lui choisir un mot de passe, donc pouvoir se connecter à sa place. Les comptes se créent par l'inscription.");

        return $this->backToIndex();
    }

    /**
     * @param AdminContext<User> $context
     */
    public function delete(AdminContext $context): KeyValueStore|Response
    {
        $this->addFlash('danger', 'Un compte ne se supprime pas : ses réservations sont des pièces comptables. Utilisez « Anonymiser ».');

        return $this->backToIndex();
    }

    private function isLastAdmin(User $membre): bool
    {
        if (!\in_array('ROLE_ADMIN', $membre->getRoles(), true)) {
            return false;
        }

        return $this->countAdmins() <= 1;
    }

    /**
     * Compte les administrateurs encore actifs.
     *
     * Le rôle est stocké dans une colonne JSON : on ramène les comptes non
     * anonymisés et on filtre en PHP, ce qui reste trivial à l'échelle d'une
     * table de membres et évite une requête dépendante du dialecte SQL.
     */
    private function countAdmins(): int
    {
        $comptes = $this->entityManager->getRepository(User::class)->findBy(['deletedAt' => null]);

        return \count(array_filter(
            $comptes,
            static fn (User $membre): bool => \in_array('ROLE_ADMIN', $membre->getRoles(), true),
        ));
    }

    private function backToIndex(): RedirectResponse
    {
        return new RedirectResponse(
            $this->urls->setController(self::class)->setAction(Action::INDEX)->generateUrl(),
        );
    }

    private static function statusLabel(UserStatus $status): string
    {
        return match ($status) {
            UserStatus::Active => 'Actif',
            UserStatus::Pending => 'En attente',
            UserStatus::Suspended => 'Suspendu — connexion refusée',
        };
    }
}
