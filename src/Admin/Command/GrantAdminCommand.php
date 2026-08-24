<?php

declare(strict_types=1);

namespace App\Admin\Command;

use App\User\Entity\User;
use App\User\Enum\UserStatus;
use App\User\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Donne l'accès au back-office à un compte.
 *
 * POURQUOI CETTE COMMANDE EXISTE
 * Le pare-feu réserve /admin à ROLE_ADMIN, mais AUCUN compte ne portait ce
 * rôle : le back-office aurait été inaccessible à tout le monde, y compris à
 * celui qui vient de l'installer. Le rôle ne peut pas non plus s'attribuer
 * depuis le back-office lui-même — il faudrait déjà y être entré.
 *
 * La commande promeut un compte existant, ou le crée s'il n'existe pas. Elle
 * est volontairement le SEUL moyen d'obtenir ce rôle : il faut un accès au
 * serveur, ce qu'un visiteur n'a pas.
 *
 * ELLE SERT AUSSI À REDONNER UN MOT DE PASSE
 * Le site n'offre aucun écran « changer mon mot de passe » : le seul chemin est
 * « mot de passe oublié », qui envoie un code PAR E-MAIL. Or les e-mails
 * partent par la file asynchrone (config/packages/messenger.yaml) : sans un
 * worker `messenger:consume async`, personne ne peut récupérer son accès.
 *
 * Donner `--mot-de-passe` sur un compte qui existe déjà remplace donc le sien.
 * C'est le filet tant que le worker ne tourne pas. Le remplacement est annoncé
 * dans la sortie : on ne change pas le mot de passe de quelqu'un en silence.
 */
#[AsCommand(
    name: 'app:admin:grant',
    description: 'Donne le rôle administrateur à un compte (le crée si besoin)',
)]
final class GrantAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Adresse e-mail du compte')
            ->addOption('mot-de-passe', null, InputOption::VALUE_REQUIRED, 'Mot de passe. Obligatoire pour créer un compte ; sur un compte existant, remplace le sien')
            ->addOption('prenom', null, InputOption::VALUE_REQUIRED, 'Prénom, si le compte doit être créé', 'Admin')
            ->addOption('nom', null, InputOption::VALUE_REQUIRED, 'Nom, si le compte doit être créé', 'TrouveMoi');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');

        $user = $this->users->findOneBy(['email' => $email]);
        $existant = null !== $user;

        if (null === $user) {
            $password = $input->getOption('mot-de-passe');
            if (!\is_string($password) || '' === $password) {
                $io->error(sprintf(
                    'Aucun compte « %s ». Pour le créer, indiquez un mot de passe : --mot-de-passe=…',
                    $email,
                ));

                return Command::FAILURE;
            }

            $user = (new User())
                ->setEmail($email)
                ->setFirstName((string) $input->getOption('prenom'))
                ->setLastName((string) $input->getOption('nom'))
                // Un administrateur créé en ligne de commande n'a pas d'e-mail
                // de confirmation à valider : il est actif immédiatement.
                ->setStatus(UserStatus::Active);
            $user->setPassword($this->hasher->hashPassword($user, $password));

            $this->entityManager->persist($user);
            $io->text(sprintf('Compte « %s » créé.', $email));
        }

        // Compte DÉJÀ EXISTANT et mot de passe fourni : on le remplace. C'est le
        // seul moyen de rendre l'accès à quelqu'un tant que les e-mails ne
        // partent pas. Le test porte sur $existant, et non sur ce que Doctrine
        // gère : un compte qu'on vient de créer est lui aussi « géré », le
        // message annoncerait alors un remplacement qui n'a pas eu lieu.
        $nouveauMotDePasse = $input->getOption('mot-de-passe');
        if ($existant && \is_string($nouveauMotDePasse) && '' !== $nouveauMotDePasse) {
            $user->setPassword($this->hasher->hashPassword($user, $nouveauMotDePasse));
            $io->warning(sprintf('Le mot de passe de « %s » a été remplacé.', $email));
        }

        $roles = $user->getRoles();
        if (\in_array('ROLE_ADMIN', $roles, true)) {
            $this->entityManager->flush();
            $io->success(sprintf('« %s » avait déjà accès au back-office.', $email));

            return Command::SUCCESS;
        }

        // getRoles() ajoute ROLE_USER à la volée : on ne le réenregistre pas,
        // sinon il finirait stocké en double en base.
        $stored = array_values(array_filter($roles, static fn (string $role): bool => 'ROLE_USER' !== $role));
        $stored[] = 'ROLE_ADMIN';
        $user->setRoles($stored);

        $this->entityManager->flush();

        $io->success(sprintf('« %s » accède désormais au back-office : /admin', $email));

        return Command::SUCCESS;
    }
}
