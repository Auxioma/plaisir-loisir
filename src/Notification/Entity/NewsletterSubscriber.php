<?php

declare(strict_types=1);

namespace App\Notification\Entity;

use App\Notification\Repository\NewsletterSubscriberRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * Inscription à la lettre d'information (« Ne manquez aucune offre ! »,
 * page Offres du moment, 04/10). Liste consultable et exportable dans le
 * back-office ; la désinscription met `unsubscribedAt`.
 */
#[ORM\Entity(repositoryClass: NewsletterSubscriberRepository::class)]
#[ORM\HasLifecycleCallbacks]
class NewsletterSubscriber
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\Column(length: 180, unique: true)]
    private string $email = '';

    #[ORM\Column(length: 5, options: ['default' => 'fr'])]
    private string $locale = 'fr';

    /** Page d'origine de l'inscription (ex. « offres »). */
    #[ORM\Column(length: 40, nullable: true)]
    private ?string $source = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $unsubscribedAt = null;

    public function __toString(): string
    {
        return $this->email;
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

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getUnsubscribedAt(): ?\DateTimeImmutable
    {
        return $this->unsubscribedAt;
    }

    public function setUnsubscribedAt(?\DateTimeImmutable $unsubscribedAt): static
    {
        $this->unsubscribedAt = $unsubscribedAt;

        return $this;
    }

    public function isActive(): bool
    {
        return null === $this->unsubscribedAt;
    }
}
