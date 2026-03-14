<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\ColdRoom;
use App\Entity\StockItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class StockItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockItem::class);
    }

    public function findByClient(Client $client): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.client = :client')
            ->setParameter('client', $client)
            ->orderBy('s.entryDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByColdRoom(ColdRoom $coldRoom): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.coldRoom = :coldRoom')
            ->setParameter('coldRoom', $coldRoom)
            ->orderBy('s.entryDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByFilters(?Client $client, ?ColdRoom $coldRoom, ?\DateTimeInterface $from, ?\DateTimeInterface $to): array
    {
        $qb = $this->createQueryBuilder('s');

        if ($client) {
            $qb->andWhere('s.client = :client')->setParameter('client', $client);
        }
        if ($coldRoom) {
            $qb->andWhere('s.coldRoom = :coldRoom')->setParameter('coldRoom', $coldRoom);
        }
        if ($from) {
            $qb->andWhere('s.entryDate >= :from')->setParameter('from', $from);
        }
        if ($to) {
            $qb->andWhere('s.entryDate <= :to')->setParameter('to', $to);
        }

        return $qb->orderBy('s.entryDate', 'DESC')->getQuery()->getResult();
    }

    public function findWithAvailableQuantity(): array
    {
        $items = $this->findAll();
        return array_filter($items, fn(StockItem $item) => $item->getRemainingQuantity() > 0);
    }

    public function findWithFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('s')
            ->join('s.client', 'c')
            ->join('s.coldRoom', 'r');

        if (!empty($filters['client'])) {
            $qb->andWhere('c.id = :clientId')->setParameter('clientId', $filters['client']);
        }
        if (!empty($filters['coldRoom'])) {
            $qb->andWhere('r.id = :coldRoomId')->setParameter('coldRoomId', $filters['coldRoom']);
        }

        return $qb->orderBy('s.entryDate', 'DESC')->getQuery()->getResult();
    }
}
