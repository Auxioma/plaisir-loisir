<?php

declare(strict_types=1);

namespace App\Support\Entity;

use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\Support\Enum\TicketCategory;
use App\Support\Enum\TicketStatus;
use App\Support\Repository\SupportTicketRepository;
use App\User\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ticket d'assistance ouvert depuis l'espace pro « Support » (02/10) :
 * ticket écrit, demande de rappel ou chat — tous suivis et traités par
 * l'équipe dans le back-office.
 */
#[ORM\Entity(repositoryClass: SupportTicketRepository::class)]
#[ORM\Index(columns: ['author_id'])]
#[ORM\HasLifecycleCallbacks]
class SupportTicket
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\Column(unique: true)]
    private int $number = 0;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $author = null;

    #[ORM\Column(length: 180)]
    private string $subject = '';

    #[ORM\Column(enumType: TicketCategory::class)]
    private TicketCategory $category = TicketCategory::Other;

    #[ORM\Column(enumType: TicketStatus::class)]
    private TicketStatus $status = TicketStatus::Open;

    /** Numéro à rappeler (demande de rappel). */
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastReplyAt = null;

    /**
     * @var Collection<int, SupportTicketMessage>
     */
    #[ORM\OneToMany(targetEntity: SupportTicketMessage::class, mappedBy: 'ticket', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $messages;

    public function __construct()
    {
        $this->messages = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->getReference().' — '.$this->subject;
    }

    public function getReference(): string
    {
        return '#TK-'.$this->number;
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    public function setNumber(int $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getCategory(): TicketCategory
    {
        return $this->category;
    }

    public function setCategory(TicketCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getStatus(): TicketStatus
    {
        return $this->status;
    }

    public function setStatus(TicketStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getLastReplyAt(): ?\DateTimeImmutable
    {
        return $this->lastReplyAt ?? $this->getCreatedAt();
    }

    /**
     * @return Collection<int, SupportTicketMessage>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(SupportTicketMessage $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setTicket($this);
            $this->lastReplyAt = new \DateTimeImmutable();
        }

        return $this;
    }
}
