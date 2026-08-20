<?php

declare(strict_types=1);

namespace App\Corporate\Service;

use App\Corporate\Entity\ContactMessage;
use App\Corporate\Entity\PartnerApplication;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Réception des demandes envoyées par les formulaires institutionnels.
 *
 * L'ORDRE DES OPÉRATIONS EST LE POINT IMPORTANT : on enregistre d'abord, on
 * notifie ensuite. Si l'envoi de l'e-mail échoue, la demande est déjà en base
 * et l'utilisateur reçoit quand même sa confirmation — elle est vraie, son
 * message est bien arrivé. L'inverse aurait produit un message perdu pour une
 * panne de messagerie.
 */
final class CorporateInboxService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(CONTACT_INBOX_EMAIL)%')] private readonly string $inbox,
    ) {
    }

    /**
     * Enregistre un message de contact.
     *
     * @return list<string> les messages d'erreur ; tableau vide si tout va bien
     */
    public function submitContact(ContactMessage $message): array
    {
        $errors = $this->validate($message);

        if ([] !== $errors) {
            return $errors;
        }

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        $this->notify(
            sprintf('Nouveau message : %s', $message->getSubject()),
            <<<TEXTE
                De      : {$message->getName()} <{$message->getEmail()}>
                Sujet   : {$message->getSubject()}

                {$message->getMessage()}
                TEXTE,
            $message->getEmail(),
        );

        return [];
    }

    /**
     * Enregistre une candidature de partenaire.
     *
     * @return list<string>
     */
    public function submitPartnerApplication(PartnerApplication $application): array
    {
        $errors = $this->validate($application);

        if ([] !== $errors) {
            return $errors;
        }

        $this->entityManager->persist($application);
        $this->entityManager->flush();

        $this->notify(
            sprintf('Candidature partenaire : %s', $application->getSiteName()),
            <<<TEXTE
                Site        : {$application->getSiteName()} ({$application->getSiteUrl()})
                Secteur     : {$application->getSector()}
                Trafic      : {$application->getTraffic()}
                Entreprise  : {$application->getCompanyName()}
                Responsable : {$application->getContactName()}
                Téléphone   : {$application->getPhone()}
                Adresse     : {$application->getAddress()}, {$application->getPostalCode()} {$application->getCity()}
                E-mail      : {$application->getEmail()}

                {$application->getDescription()}
                TEXTE,
            $application->getEmail(),
        );

        return [];
    }

    /**
     * @return list<string>
     */
    private function validate(object $entity): array
    {
        $messages = [];

        foreach ($this->validator->validate($entity) as $violation) {
            $messages[] = (string) $violation->getMessage();
        }

        return $messages;
    }

    /**
     * Prévient l'équipe. Un échec ici ne remonte PAS à l'utilisateur : la
     * demande est enregistrée, c'est ce qui compte. On journalise pour que le
     * problème de messagerie se voie.
     */
    private function notify(string $subject, string $body, string $replyTo): void
    {
        try {
            $this->mailer->send(
                (new Email())
                    ->to($this->inbox)
                    // Répondre depuis la boîte de l'équipe écrit directement à
                    // la personne, sans avoir à recopier son adresse.
                    ->replyTo($replyTo)
                    ->subject($subject)
                    ->text($body),
            );
        } catch (\Throwable $e) {
            $this->logger->error('Notification de demande impossible : la demande est bien enregistrée.', [
                'sujet' => $subject,
                'exception' => $e,
            ]);
        }
    }
}
