<?php

declare(strict_types=1);

namespace App\Messaging\Repository;

use App\Messaging\Entity\Conversation;
use App\Provider\Entity\ProviderProfile;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    public function findOneByClientAndProvider(User $client, ProviderProfile $provider): ?Conversation
    {
        return $this->findOneBy(['client' => $client, 'provider' => $provider]);
    }
}
