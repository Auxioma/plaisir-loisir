<?php

declare(strict_types=1);

namespace App\Messaging\Entity;

use App\Catalog\Entity\Service;
use App\Messaging\Repository\ConversationRepository;
use App\Provider\Entity\ProviderProfile;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Fil de discussion entre un client et un annonceur (un fil par paire).
 * Peut être rattaché à une activité précise.
 */
#[ORM\Entity(repositoryClass: ConversationRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_conversation_client_provider', columns: ['client_id', 'provider_id'])]
#[ORM\HasLifecycleCallbacks]
class Conversation
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $client = null;

    #[ORM\ManyToOne(targetEntity: ProviderProfile::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ProviderProfile $provider = null;

    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Service $service = null;

    /**
     * @var Collection<int, Message>
     */
    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'conversation', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $messages;

    public function __construct()
    {
        $this->messages = new ArrayCollection();
    }

    public function getClient(): ?User
    {
        return $this->client;
    }

    public function setClient(?User $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getProvider(): ?ProviderProfile
    {
        return $this->provider;
    }

    public function setProvider(?ProviderProfile $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;

        return $this;
    }

    /**
     * @return Collection<int, Message>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Message $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setConversation($this);
        }

        return $this;
    }

    public function removeMessage(Message $message): static
    {
        if ($this->messages->removeElement($message) && $message->getConversation() === $this) {
            $message->setConversation(null);
        }

        return $this;
    }

    /**
     * Indique si l'utilisateur participe à la conversation (client ou annonceur).
     */
    public function hasParticipant(User $user): bool
    {
        return $this->client === $user
            || ($this->provider?->getUser() === $user);
    }
}
