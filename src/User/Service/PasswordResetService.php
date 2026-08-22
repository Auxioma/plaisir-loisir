<?php

declare(strict_types=1);

namespace App\User\Service;

use App\User\Entity\User;
use App\User\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Réinitialisation du mot de passe en trois temps, conformément aux trois
 * écrans de la maquette : on demande un code, on le vérifie, on définit le
 * nouveau mot de passe.
 *
 * Choix de conception :
 *
 *  - Le format du code suit la maquette, qui affiche « 7759BK5X » en exemple :
 *    HUIT caractères alphanumériques majuscules, et non six chiffres.
 *  - Les caractères ambigus (0/O, 1/I/L) sont exclus de l'alphabet : un code se
 *    recopie à la main depuis une boîte mail, souvent sur téléphone.
 *  - Une adresse inconnue ne provoque AUCUNE erreur visible. Répondre
 *    « ce compte n'existe pas » permettrait à n'importe qui de savoir qui est
 *    inscrit sur la plateforme.
 *  - Le code expire au bout de 15 minutes et tolère 5 essais. Passé cela il est
 *    effacé et il faut en redemander un.
 */
final class PasswordResetService
{
    /** Longueur du code, prise sur le gabarit de la maquette. */
    public const CODE_LENGTH = 8;

    /** Durée de validité du code, en minutes. */
    private const VALIDITY_MINUTES = 15;

    /** Nombre d'essais tolérés avant que le code ne soit détruit. */
    private const MAX_ATTEMPTS = 5;

    /** Alphabet sans 0, O, 1, I ni L, trop faciles à confondre à la saisie. */
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly MailerInterface $mailer,
    ) {
    }

    /**
     * Étape 1 — génère un code, l'envoie par e-mail et enregistre son empreinte.
     *
     * Ne renvoie rien et ne lève rien : que l'adresse existe ou non, l'appelant
     * affiche le même écran suivant.
     */
    public function requestCode(string $email): void
    {
        $user = $this->findUser($email);

        if (null === $user) {
            return;
        }

        $code = $this->generateCode();

        $user->startPasswordReset(
            // Le code est haché comme un mot de passe : lent à casser, et
            // inutilisable tel quel si la base est compromise.
            password_hash($code, PASSWORD_DEFAULT),
            new \DateTimeImmutable(sprintf('+%d minutes', self::VALIDITY_MINUTES)),
        );

        $this->entityManager->flush();

        $this->sendCode($user, $code);
    }

    /**
     * Étape 2 — vérifie le code saisi.
     *
     * Chaque échec incrémente le compteur ; au-delà de la limite, le code est
     * détruit et l'utilisateur doit recommencer depuis le premier écran.
     */
    public function verifyCode(string $email, string $code): bool
    {
        $user = $this->findUser($email);

        if (null === $user || null === $user->getResetCodeHash()) {
            return false;
        }

        $expiresAt = $user->getResetCodeExpiresAt();

        if (null === $expiresAt || $expiresAt < new \DateTimeImmutable()) {
            $user->clearPasswordReset();
            $this->entityManager->flush();

            return false;
        }

        // Le code est saisi tel qu'il est lu : on tolère les minuscules et les
        // espaces, sans quoi une recopie correcte serait refusée.
        $submitted = strtoupper(trim($code));

        if (!password_verify($submitted, $user->getResetCodeHash())) {
            $user->registerFailedResetAttempt();

            if ($user->getResetCodeAttempts() >= self::MAX_ATTEMPTS) {
                $user->clearPasswordReset();
            }

            $this->entityManager->flush();

            return false;
        }

        return true;
    }

    /**
     * Étape 3 — enregistre le nouveau mot de passe.
     *
     * Le code est revérifié ici : sans cela, atteindre le troisième écran
     * suffirait à changer le mot de passe de n'importe quel compte.
     */
    public function reset(string $email, string $code, string $newPassword): bool
    {
        if (!$this->verifyCode($email, $code)) {
            return false;
        }

        $user = $this->findUser($email);

        if (null === $user) {
            return false;
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        // Un code ne sert qu'une fois.
        $user->clearPasswordReset();

        $this->entityManager->flush();

        return true;
    }

    /**
     * Tire un code au hasard avec random_int, seul générateur adapté à un
     * usage de sécurité (rand() et mt_rand() sont prévisibles).
     */
    private function generateCode(): string
    {
        $max = \strlen(self::ALPHABET) - 1;
        $code = '';

        for ($i = 0; $i < self::CODE_LENGTH; ++$i) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return $code;
    }

    private function findUser(string $email): ?User
    {
        return $this->userRepository->findOneBy(['email' => mb_strtolower(trim($email))]);
    }

    /**
     * E-mail en texte brut, comme les notifications existantes : aucun gabarit
     * d'e-mail n'a été maquetté, et en inventer un ici reviendrait à figer une
     * mise en forme qui n'a été validée par personne.
     */
    private function sendCode(User $user, string $code): void
    {
        $body = <<<TEXT
            Bonjour {$user->getFirstName()},

            Vous avez demandé à réinitialiser le mot de passe de votre compte TrouveMoi.

            Votre code de vérification est : {$code}

            Ce code est valable 15 minutes. Si vous n'êtes pas à l'origine de cette
            demande, ignorez ce message : votre mot de passe reste inchangé.

            L'équipe TrouveMoi Plaisirs & Loisirs
            TEXT;

        $this->mailer->send(
            (new Email())
                ->to($user->getEmail())
                ->subject('Votre code de réinitialisation TrouveMoi')
                ->text($body),
        );
    }
}
