<?php

declare(strict_types=1);

namespace App\Shared\Doctrine;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * Identifiant primaire de type ULID, généré automatiquement par Doctrine
 * via le service Symfony "doctrine.ulid_generator" lors de la persistance.
 *
 * Les ULID sont triés chronologiquement : index PostgreSQL compact et performant.
 */
trait UlidIdentifierTrait
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.ulid_generator')]
    private ?Ulid $id = null;

    public function getId(): ?Ulid
    {
        return $this->id;
    }
}
