<?php

declare(strict_types=1);

namespace App\User\Service;

use App\Legal\Service\ConsentService;
use App\User\Entity\SocialIdentity;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use App\User\OAuth\SocialUser;
use App\User\Repository\SocialIdentityRepository;
use App\User\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Transforme une identité Google, Facebook ou Apple en compte de la plateforme.
 *
 * Trois situations, et une seule est délicate :
 *
 *  1. L'identité est déjà connue  -> on ouvre la session, c'est tout.
 *  2. L'identité est inconnue et l'adresse aussi -> on crée un compte.
 *  3. L'identité est inconnue mais l'adresse correspond à un compte existant
 *     -> on RATTACHE, et uniquement si le fournisseur atteste avoir vérifié
 *        cette adresse.
 *
 * Le troisième cas est celui où l'on se fait voler des comptes. Rattacher sur
 * la seule foi d'une adresse déclarée permettrait à qui saurait faire dire
 * « je suis alice@exemple.fr » à un fournisseur complaisant d'entrer dans le
 * compte d'Alice sans jamais connaître son mot de passe. D'où le refus net
 * quand l'adresse n'est pas attestée.
 */
final class SocialLoginService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $users,
        private readonly SocialIdentityRepository $identities,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ConsentService $consentService,
    ) {
    }

    /**
     * Ouvre ou retrouve le compte correspondant à cette identité externe.
     *
     * @throws SocialLoginException            si la liaison ne peut pas être faite en sécurité
     * @throws SocialEmailVerificationRequired si l’e-mail nécessite une preuve locale
     */
    public function resolve(SocialUser $socialUser, ?Request $request = null): User
    {
        $identity = $this->identities->findOneByExternal($socialUser->provider, $socialUser->externalId);

        // --- Cas 1 : identité déjà connue -------------------------------
        if (null !== $identity) {
            $user = $identity->getUser();

            if (null === $user) {
                throw new SocialLoginException('Cette identité n\'est reliée à aucun compte.');
            }

            $this->assertClientCanLogin($user);
            $this->verifyPendingClient($user, $socialUser);

            $this->refreshIdentity($identity, $socialUser);
            $this->entityManager->flush();

            return $user;
        }

        $email = null !== $socialUser->email ? mb_strtolower(trim($socialUser->email)) : null;

        if (null === $email || '' === $email) {
            // Arrive régulièrement avec Facebook : un compte ouvert avec un
            // simple numéro de téléphone n'a pas d'adresse à transmettre.
            throw new SocialLoginException(sprintf('%s n\'a transmis aucune adresse e-mail. Utilisez l’inscription ou la connexion avec votre adresse e-mail.', $socialUser->provider->label()));
        }

        if (mb_strlen($email) > 180 || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new SocialLoginException('Le fournisseur n’a pas transmis une adresse e-mail valide. Utilisez votre connexion habituelle.');
        }

        $existing = $this->users->findOneBy(['email' => $email]);

        // --- Cas 3 : l'adresse est déjà celle d'un compte ----------------
        if (null !== $existing) {
            $this->assertClientCanLogin($existing);
            if (!$socialUser->emailVerified) {
                throw new SocialEmailVerificationRequired();
            }
            $this->verifyPendingClient($existing, $socialUser);

            $this->linkIdentity($existing, $socialUser);
            $this->entityManager->flush();

            return $existing;
        }

        if (!$socialUser->emailVerified) {
            throw new SocialEmailVerificationRequired();
        }

        // --- Cas 2 : création d'un compte --------------------------------
        return $this->createUser($email, $socialUser, $request);
    }

    private function assertClientCanLogin(User $user): void
    {
        if ($user->isProvider() || \in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            throw new SocialLoginException('Cette connexion est réservée aux clients. Utilisez votre connexion habituelle pour accéder à votre espace professionnel ou administrateur.');
        }
        if ($user->isDeleted() || UserStatus::Suspended === $user->getStatus()) {
            throw new SocialLoginException('Ce compte n’est plus accessible. Contactez-nous si vous pensez qu’il s’agit d’une erreur.');
        }
    }

    private function verifyPendingClient(User $user, SocialUser $socialUser): void
    {
        if (UserStatus::Pending !== $user->getStatus()) {
            return;
        }
        if (!$socialUser->emailVerified || mb_strtolower(trim($socialUser->email ?? '')) !== $user->getEmail()) {
            throw new SocialLoginException('Vérifiez votre adresse e-mail avant de vous connecter.');
        }
        $user->markEmailVerified();
    }

    private function createUser(string $email, SocialUser $socialUser, ?Request $request): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName(mb_substr($socialUser->firstName ?? '', 0, 100));
        $user->setLastName(mb_substr($socialUser->lastName ?? '', 0, 100));
        // Le compte n'a pas de mot de passe choisi par son titulaire, mais la
        // colonne ne peut pas rester vide. On y met une valeur aléatoire que
        // personne ne connaît : la connexion par formulaire est ainsi
        // impossible tant que l'utilisateur n'en définit pas un par la
        // procédure « mot de passe oublié ».
        $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));
        // L'adresse est déjà vérifiée par le fournisseur : rien à confirmer.
        $user->setStatus(UserStatus::Active);

        $this->entityManager->persist($user);

        $this->linkIdentity($user, $socialUser);

        $this->entityManager->flush();

        // Même traçabilité des conditions que pour l'inscription classique.
        $this->consentService->recordRegistrationConsent(
            $user,
            $request,
            $request?->getLocale() ?? 'fr',
        );

        return $user;
    }

    private function linkIdentity(User $user, SocialUser $socialUser): SocialIdentity
    {
        $identity = new SocialIdentity();
        $identity->setUser($user)
            ->setProvider($socialUser->provider)
            ->setExternalId($socialUser->externalId);

        $this->refreshIdentity($identity, $socialUser);

        $this->entityManager->persist($identity);

        return $identity;
    }

    /**
     * Rafraîchit les informations d'affichage à chaque connexion.
     *
     * On ne touche JAMAIS à l'e-mail du compte au passage : la personne a pu
     * changer d'adresse chez Google sans vouloir changer celle de son compte
     * ici, et une adresse est aussi un identifiant de connexion.
     */
    private function refreshIdentity(SocialIdentity $identity, SocialUser $socialUser): void
    {
        $identity->setExternalEmail(null !== $socialUser->email ? mb_substr($socialUser->email, 0, 180) : null)
            ->setDisplayName(null !== $socialUser->fullName() ? mb_substr($socialUser->fullName(), 0, 180) : null)
            ->setAvatarUrl(null !== $socialUser->avatarUrl ? mb_substr($socialUser->avatarUrl, 0, 500) : null)
            ->touchLogin();
    }
}
