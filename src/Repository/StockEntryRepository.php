<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\ColdRoom;
use App\Entity\StockEntry;
use App\Enum\StockStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class StockEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockEntry::class);
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

    public function findByClient(Client $client): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.client = :client')
            ->setParameter('client', $client)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByFilters(?Client $client, ?ColdRoom $coldRoom, ?\DateTimeInterface $from, ?\DateTimeInterface $to): array
    {
        $qb = $this->createQueryBuilder('e');

        if ($client) {
            $qb->andWhere('e.client = :client')->setParameter('client', $client);
        }
        if ($coldRoom) {
            $qb->andWhere('e.coldRoom = :coldRoom')->setParameter('coldRoom', $coldRoom);
        }
        if ($from) {
            $qb->andWhere('e.createdAt >= :from')->setParameter('from', $from);
        }
        if ($to) {
            $qb->andWhere('e.createdAt <= :to')->setParameter('to', $to);
        }

        return $qb->orderBy('e.createdAt', 'DESC')->getQuery()->getResult();
    }

    public function findRecentByClient(Client $client, int $limit = 5): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.client = :client')
            ->setParameter('client', $client)
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

}
