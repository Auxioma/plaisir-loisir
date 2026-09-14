<?php

declare(strict_types=1);

namespace App\PrivateActivity\Repository;

use App\PrivateActivity\Entity\Participation;
use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Enum\ParticipationStatus;
use App\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Participation>
 */
class ParticipationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Participation::class);
    }

    public function findOneByActivityAndParticipant(PrivateActivity $activity, User $participant): ?Participation
    {
        return $this->findOneBy(['privateActivity' => $activity, 'participant' => $participant]);
    }

    public function countAccepted(PrivateActivity $activity): int
    {
        return $this->count(['privateActivity' => $activity, 'status' => ParticipationStatus::Accepted]);
    }

    /**
     * Premier arrivé, premier servi : la liste d'attente se vide dans l'ordre
     * d'inscription. Les ULID sont chronologiques (voir UlidIdentifierTrait),
     * trier par identifiant suffit et évite un doublon de colonne.
     *
     * @return list<Participation>
     */
    public function findOldestWaiting(PrivateActivity $activity): array
    {
        /** @var list<Participation> $results */
        $results = $this->findBy(
            ['privateActivity' => $activity, 'status' => ParticipationStatus::WaitingList],
            ['id' => 'ASC'],
        );

        return $results;
    }

    /**
     * @return list<Participation>
     */
    public function findByParticipant(User $user): array
    {
        /** @var list<Participation> $results */
        $results = $this->findBy(['participant' => $user], ['createdAt' => 'DESC']);

        return $results;
    }
}
