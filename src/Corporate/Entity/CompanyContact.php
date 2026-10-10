<?php

declare(strict_types=1);

namespace App\Corporate\Entity;

use App\Corporate\Repository\CompanyContactRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Coordonnées publiques de TrouveMoi (page « Contactez-nous », espace pro).
 *
 * Demande du client (07/10) : pouvoir les modifier depuis le back-office,
 * l'adresse du siège n'étant pas encore connue. UNE SEULE ligne ; tant
 * qu'elle n'existe pas, CompanyContactProvider fournit les valeurs par
 * défaut. Une adresse vide n'est pas affichée.
 */
#[ORM\Entity(repositoryClass: CompanyContactRepository::class)]
#[ORM\Table(name: 'company_contact')]
#[ORM\HasLifecycleCallbacks]
class CompanyContact
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email = '';

    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Length(max: 30)]
    private ?string $phone = null;

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $openingHours = null;

    /** Adresse postale du siège, sur plusieurs lignes ; vide = non affichée. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $address = null;

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = trim($email);

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = null !== $phone && '' !== trim($phone) ? trim($phone) : null;

        return $this;
    }

    public function getOpeningHours(): ?string
    {
        return $this->openingHours;
    }

    public function setOpeningHours(?string $openingHours): static
    {
        $this->openingHours = null !== $openingHours && '' !== trim($openingHours) ? trim($openingHours) : null;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = null !== $address && '' !== trim($address) ? trim($address) : null;

        return $this;
    }

    public function __toString(): string
    {
        return 'Coordonnées de TrouveMoi';
    }
}
