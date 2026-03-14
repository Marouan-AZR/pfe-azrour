<?php

namespace App\Repository;

use App\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isActive = true')
            ->orderBy('c.companyName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function search(string $query): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.companyName LIKE :query')
            ->orWhere('c.email LIKE :query')
            ->orWhere('c.code LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('c.companyName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
