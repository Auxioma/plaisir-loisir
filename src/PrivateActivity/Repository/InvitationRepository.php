<?php

declare(strict_types=1);

namespace App\PrivateActivity\Repository;

use App\PrivateActivity\Entity\Invitation;
use App\PrivateActivity\Entity\PrivateActivity;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invitation>
 */
class InvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invitation::class);
    }

    public function findOneByActivityAndInvitee(PrivateActivity $activity, User $invitee): ?Invitation
    {
        return $this->findOneBy(['privateActivity' => $activity, 'invitee' => $invitee]);
    }
}
