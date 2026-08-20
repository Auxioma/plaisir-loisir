<?php

declare(strict_types=1);

namespace App\Corporate\Entity;

use App\Corporate\Repository\ContactMessageRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Message envoyé depuis « Contactez-nous ».
 *
 * POURQUOI EN BASE ET PAS SEULEMENT PAR E-MAIL
 * Les e-mails du projet partent par la file Messenger : sans worker en service,
 * ils attendent. Un message de contact qui ne serait qu'un e-mail serait donc
 * perdu au premier incident, et personne ne le saurait — ni l'expéditeur, à qui
 * on vient d'afficher « message envoyé », ni l'équipe. La base garde la trace ;
 * l'e-mail n'est qu'une notification.
 */
#[ORM\Entity(repositoryClass: ContactMessageRepository::class)]
#[ORM\Table(name: 'contact_message')]
#[ORM\Index(name: 'idx_contact_message_handled', columns: ['handled_at', 'created_at'])]
#[ORM\HasLifecycleCallbacks]
class ContactMessage
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Veuillez saisir vos nom et prénom.')]
    #[Assert\Length(max: 180)]
    private string $name = '';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Veuillez saisir votre adresse e-mail.')]
    #[Assert\Email(message: 'Veuillez saisir une adresse e-mail valide.')]
    private string $email = '';

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank(message: 'Veuillez indiquer le sujet de votre demande.')]
    #[Assert\Length(max: 200)]
    private string $subject = '';

    /**
     * Le champ de la maquette porte `maxlength="150"`. On le vérifie aussi
     * côté serveur : un attribut HTML ne protège de rien, il suffit de poster
     * la requête sans passer par le navigateur.
     */
    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Veuillez écrire votre message.')]
    #[Assert\Length(max: 150, maxMessage: 'Votre message ne doit pas dépasser {{ limit }} caractères.')]
    private string $message = '';

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    /** Date de prise en charge par l'équipe ; nulle tant que c'est à traiter. */
    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $handledAt = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = trim($subject);

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = trim($message);

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getHandledAt(): ?\DateTimeImmutable
    {
        return $this->handledAt;
    }

    public function markHandled(): static
    {
        $this->handledAt = new \DateTimeImmutable();

        return $this;
    }
}
