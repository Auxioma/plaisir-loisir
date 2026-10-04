<?php

declare(strict_types=1);

namespace App\Provider\Entity;

use App\Provider\Repository\ClientNoteRepository;
use App\Shared\Doctrine\TimestampableTrait;
use App\Shared\Doctrine\UlidIdentifierTrait;
use App\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Note privée d'un professionnel sur un de ses clients (« Clients &
 * Messages », 02/10). Jamais visible du client.
 */
#[ORM\Entity(repositoryClass: ClientNoteRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_client_note', columns: ['provider_id', 'client_id'])]
#[ORM\HasLifecycleCallbacks]
class ClientNote
{
    use UlidIdentifierTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: ProviderProfile::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ProviderProfile $provider;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $client;

    #[ORM\Column(type: 'text')]
    private string $body = '';

    public function __construct(ProviderProfile $provider, User $client)
    {
        $this->provider = $provider;
        $this->client = $client;
    }

    public function getProvider(): ProviderProfile
    {
        return $this->provider;
    }

    public function getClient(): User
    {
        return $this->client;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = $body;

        return $this;
    }
}
