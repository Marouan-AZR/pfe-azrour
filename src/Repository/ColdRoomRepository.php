<?php

namespace App\Repository;

use App\Entity\ColdRoom;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ColdRoomRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ColdRoom::class);
    }

    public function findActive(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isActive = true')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findWithAvailableCapacity(float $requiredTonnage): array
    {
        $rooms = $this->findActive();
        return array_filter($rooms, fn(ColdRoom $room) => $room->hasAvailableCapacity($requiredTonnage));
    }

    public function findNearCapacity(): array
    {
        $rooms = $this->findActive();
        return array_filter($rooms, fn(ColdRoom $room) => $room->isNearCapacity());
    }
}
