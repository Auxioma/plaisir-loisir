<?php

declare(strict_types=1);

namespace App\User\Service;

use App\User\OAuth\SocialUser;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/** Preuve d'e-mail sociale isolée des codes d'inscription et de mot de passe oublié. */
final class SocialEmailChallenge
{
    private const KEY = 'social_email_challenge';

    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    public function start(SessionInterface $session, SocialUser $identity): void
    {
        $session->remove(self::KEY);
        $code = strtoupper(bin2hex(random_bytes(4)));
        $this->mailer->send((new Email())
            ->to($identity->email ?? '')
            ->subject('Confirmez votre connexion TrouveMoi')
            ->text(sprintf('Pour terminer votre connexion avec %s, saisissez le code %s sur TrouveMoi. Ce code est valable 15 minutes. Si vous n’êtes pas à l’origine de cette demande, ignorez cet e-mail.', $identity->provider->label(), $code)));
        $session->set(self::KEY, [
            'identity' => $identity,
            'hash' => password_hash($code, PASSWORD_DEFAULT),
            'expires' => time() + 900,
            'attempts' => 0,
        ]);
    }

    public function email(SessionInterface $session): ?string
    {
        $challenge = $session->get(self::KEY);
        if (!\is_array($challenge) || $challenge['expires'] < time()) {
            $session->remove(self::KEY);

            return null;
        }

        return $challenge['identity']->email;
    }

    public function confirm(SessionInterface $session, string $code): ?SocialUser
    {
        if (null === $this->email($session)) {
            return null;
        }
        $challenge = $session->get(self::KEY);
        if (!password_verify(strtoupper(trim($code)), $challenge['hash'])) {
            if (++$challenge['attempts'] >= 5) {
                $session->remove(self::KEY);
            } else {
                $session->set(self::KEY, $challenge);
            }

            return null;
        }
        $session->remove(self::KEY);
        $identity = $challenge['identity'];

        return new SocialUser($identity->provider, $identity->externalId, $identity->email, true, $identity->firstName, $identity->lastName, $identity->avatarUrl);
    }
}
