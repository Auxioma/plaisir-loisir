<?php

declare(strict_types=1);

namespace App\Shared\Doctrine;

use App\Kernel;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

/**
 * Aligne le fuseau de la session PostgreSQL sur celui de l'application
 * (Kernel::TIMEZONE). Sans cela, les TIMESTAMPTZ reviennent dans le fuseau
 * du serveur de base (« Africa/Porto-Novo » sur un poste de dev, UTC ailleurs)
 * et les heures affichées dépendent de la machine : un créneau de 10h
 * s'affichait 9h (04/10).
 */
#[AsMiddleware]
final class SessionTimezoneMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new class($driver) extends AbstractDriverMiddleware {
            public function connect(#[\SensitiveParameter] array $params): Connection
            {
                $connection = parent::connect($params);
                $connection->exec(sprintf("SET TIME ZONE '%s'", Kernel::TIMEZONE));

                return $connection;
            }
        };
    }
}
