<?php

declare(strict_types=1);

namespace App\Provider\Repository;

use App\Provider\Entity\ProviderDocument;
use App\Provider\Entity\ProviderProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProviderDocument>
 */
final class ProviderDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProviderDocument::class);
    }

    /**
     * Pièces d'un dossier, la plus ancienne d'abord — c'est l'ordre de dépôt,
     * donc celui dans lequel le prestataire les a présentées.
     *
     * findBy() ET NON UNE REQUÊTE DQL. Les identifiants du projet sont des
     * ULID (base32, 26 caractères) rangés dans des colonnes `uuid` : passer
     * l'entité à `setParameter()` fait lier le ULID tel quel et PostgreSQL
     * refuse — « invalid input syntax for type uuid ». Le persister de
     * Doctrine, lui, connaît le type de la clé de l'association et convertit.
     *
     * L'ordre repose aussi sur l'identifiant : les horodatages sont stockés à
     * la seconde, et deux pièces déposées d'un même envoi seraient sinon dans
     * un ordre indéterminé. Les ULID, eux, sont chronologiques.
     *
     * @return list<ProviderDocument>
     */
    public function findForProfile(ProviderProfile $profile): array
    {
        /** @var list<ProviderDocument> $pieces */
        $pieces = $this->findBy(
            ['providerProfile' => $profile],
            ['createdAt' => 'ASC', 'id' => 'ASC'],
        );

        return $pieces;
    }
}
