<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\StockExit;
use App\Enum\StockStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class StockExitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockExit::class);
    }

    public function findPending(): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.status = :status')
            ->setParameter('status', StockStatus::PENDING)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findValidatedByClientAndPeriod(Client $client, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('e')
            ->join('e.stockItem', 'si')
            ->where('si.client = :client')
            ->andWhere('e.status = :status')
            ->andWhere('e.validatedAt >= :start')
            ->andWhere('e.validatedAt <= :end')
            ->setParameter('client', $client)
            ->setParameter('status', StockStatus::VALIDATED)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('e.validatedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findRecentByClient(Client $client, int $limit = 5): array
    {
        return $this->createQueryBuilder('e')
            ->join('e.stockItem', 'si')
            ->where('si.client = :client')
            ->setParameter('client', $client)
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

}
