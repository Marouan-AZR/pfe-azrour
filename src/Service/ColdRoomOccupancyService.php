<?php

namespace App\Service;

use App\Entity\ColdRoom;
use App\Enum\PaletteStatus;
use App\Repository\PaletteRepository;

class ColdRoomOccupancyService
{
    public function __construct(
        private PaletteRepository $paletteRepository
    ) {}

    /**
     * Occupation basée sur les palettes encore en chambre.
     * Retourne la capacité utilisée en TONNES.
     * Palette::poidsRestant est stocké en KG, on divise par 1000.
     */
    public function getUsedCapacity(ColdRoom $coldRoom): float
    {
        $excluded = [
            PaletteStatus::SORTIE_COMPLETE->value,
            PaletteStatus::REJETEE->value,
        ];

        $qb = $this->paletteRepository
            ->createQueryBuilder('p')
            ->select('SUM(p.poidsRestant)')
            ->join('p.operation', 'o')
            ->where('p.coldRoom = :coldRoom')
            ->setParameter('coldRoom', $coldRoom)
            ->andWhere('p.cartonsRestants > 0')
            ->andWhere('p.status NOT IN (:excludedStatuses)')
            ->setParameter('excludedStatuses', $excluded)
            ->andWhere('o.type = :entryType')
            ->setParameter('entryType', 'entry')
            ->andWhere('o.status = :validated')
            ->setParameter('validated', 'validated');

        $resultKg = (float) ($qb->getQuery()->getSingleScalarResult() ?? 0.0);

        // poidsRestant is stored in kg — convert to tonnes
        return $resultKg / 1000.0;
    }

    public function getAvailableCapacity(ColdRoom $coldRoom): float
    {
        return (float) $coldRoom->getMaxCapacityTons() - $this->getUsedCapacity($coldRoom);
    }

    public function getOccupancyRate(ColdRoom $coldRoom): float
    {
        $max = (float) $coldRoom->getMaxCapacityTons();
        if ($max <= 0.0) {
            return 0.0;
        }

        return ($this->getUsedCapacity($coldRoom) / $max) * 100.0;
    }

    /**
     * @param ColdRoom[] $coldRooms
     * @return array<int, array{usedCapacity: float, availableCapacity: float, occupancyRate: float}>
     */
    public function getOccupancyStatsForRooms(array $coldRooms): array
    {
        $stats = [];
        foreach ($coldRooms as $room) {
            if (!$room instanceof ColdRoom) {
                continue;
            }

            $used = $this->getUsedCapacity($room);
            $max = (float) $room->getMaxCapacityTons();
            $available = $max - $used;
            $rate = $max > 0.0 ? ($used / $max) * 100.0 : 0.0;

            $stats[$room->getId()] = [
                'usedCapacity' => $used,
                'availableCapacity' => $available,
                'occupancyRate' => $rate,
            ];
        }

        return $stats;
    }
}
