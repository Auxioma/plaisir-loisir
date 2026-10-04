<?php

declare(strict_types=1);

namespace App\Notification\Repository;

use App\Notification\Entity\NewsletterSubscriber;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NewsletterSubscriber>
 */
class NewsletterSubscriberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewsletterSubscriber::class);
    }

    public function findOneByEmail(string $email): ?NewsletterSubscriber
    {
        return $this->findOneBy(['email' => mb_strtolower(trim($email))]);
    }
}
