<?php

declare(strict_types=1);

namespace App\User\Service;

use App\Notification\Enum\NotificationCategory;
use App\Notification\Service\NotificationService;
use App\User\Entity\User;
use App\User\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Vérification de l'adresse e-mail à l'inscription, en un seul écran (code
 * demandé, puis saisi) — contrairement à la réinitialisation de mot de passe
 * (PasswordResetService), il n'y a pas de troisième étape après le code.
 *
 * Mêmes choix de conception que PasswordResetService, pour les mêmes
 * raisons : code de 8 caractères alphanumériques sans caractères ambigus,
 * expiration à 15 minutes, 5 tentatives tolérées.
 */
final class EmailVerificationService
{
    public const CODE_LENGTH = 8;

    private const VALIDITY_MINUTES = 15;

    private const MAX_ATTEMPTS = 5;

    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly MailerInterface $mailer,
        private readonly NotificationService $notifications,
    ) {
    }

    /**
     * Génère un code, l'envoie par e-mail et enregistre son empreinte.
     */
    public function sendCode(User $user): void
    {
        $code = $this->generateCode();

        $user->startEmailVerification(
            password_hash($code, PASSWORD_DEFAULT),
            new \DateTimeImmutable(sprintf('+%d minutes', self::VALIDITY_MINUTES)),
        );

        $this->entityManager->flush();

        $this->send($user, $code);
    }

    /**
     * Renvoie un code à une adresse déjà en attente de vérification.
     *
     * Muet si l'adresse est inconnue ou déjà vérifiée : même politique que
     * PasswordResetService::requestCode(), pour ne rien révéler à un tiers.
     */
    public function resend(string $email): void
    {
        $user = $this->findUser($email);

        if (null === $user || null === $user->getEmailVerificationCodeHash()) {
            return;
        }

        $this->sendCode($user);
    }

    /**
     * Vérifie le code saisi et, s'il est valide, active le compte.
     *
     * Chaque échec incrémente le compteur ; au-delà de la limite, le code est
     * détruit et il faut en redemander un.
     */
    public function confirm(string $email, string $code): bool
    {
        $user = $this->findUser($email);

        if (null === $user || null === $user->getEmailVerificationCodeHash()) {
            return false;
        }

        $expiresAt = $user->getEmailVerificationExpiresAt();

        if (null === $expiresAt || $expiresAt < new \DateTimeImmutable()) {
            $user->clearEmailVerification();
            $this->entityManager->flush();

            return false;
        }

        $submitted = strtoupper(trim($code));

        if (!password_verify($submitted, $user->getEmailVerificationCodeHash())) {
            $user->registerFailedVerificationAttempt();

            if ($user->getEmailVerificationAttempts() >= self::MAX_ATTEMPTS) {
                $user->clearEmailVerification();
            }

            $this->entityManager->flush();

            return false;
        }

        $user->markEmailVerified();
        $this->entityManager->flush();

        $this->notifications->notify(
            $user,
            NotificationCategory::System,
            'Bienvenue sur TrouveMoi',
            'Votre adresse e-mail est vérifiée, votre compte est actif.',
        );

        return true;
    }

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
     * E-mail en texte brut, comme les notifications existantes : aucun
     * gabarit d'e-mail n'a été maquetté.
     */
    private function send(User $user, string $code): void
    {
        $body = <<<TEXT
            Bonjour {$user->getFirstName()},

            Bienvenue sur TrouveMoi Plaisirs & Loisirs !

            Pour activer votre compte, votre code de vérification est : {$code}

            Ce code est valable 15 minutes.

            L'équipe TrouveMoi Plaisirs & Loisirs
            TEXT;

        $this->mailer->send(
            (new Email())
                ->to($user->getEmail())
                ->subject('Vérifiez votre adresse e-mail TrouveMoi')
                ->text($body),
        );
    }
}
