<?php

namespace App\Service;

use App\Repository\ColdRoomRepository;
use App\Repository\OperationRepository;
use App\Repository\PaletteRepository;

class AlertService
{
    public function __construct(
        private ColdRoomRepository $coldRoomRepo,
        private OperationRepository $operationRepo,
        private PaletteRepository $paletteRepo,
    ) {}

    public function getAlerts(): array
    {
        $alerts = [];

        // Cold room alerts
        foreach ($this->coldRoomRepo->findActive() as $room) {
            $rate = $room->getOccupancyRate();
            if ($rate >= 100) {
                $alerts[] = ['type' => 'danger', 'icon' => 'thermometer-snow', 'message' => "Chambre {$room->getName()} saturée (100%)", 'category' => 'chambre'];
            } elseif ($rate >= 90) {
                $alerts[] = ['type' => 'warning', 'icon' => 'thermometer-snow', 'message' => "Chambre {$room->getName()} presque pleine (" . number_format($rate, 0) . "%)", 'category' => 'chambre'];
            } elseif ($rate < 20 && $rate > 0) {
                $alerts[] = ['type' => 'info', 'icon' => 'thermometer-snow', 'message' => "Chambre {$room->getName()} sous-utilisée (" . number_format($rate, 0) . "%)", 'category' => 'chambre'];
            }
        }

        // Pending operations alert
        $pendingCount = $this->operationRepo->count(['status' => 'pending']);
        if ($pendingCount > 5) {
            $alerts[] = ['type' => 'warning', 'icon' => 'hourglass-split', 'message' => "{$pendingCount} opérations en attente de validation", 'category' => 'operation'];
        }

        // Operations older than 3 days still pending
        $threeDaysAgo = new \DateTime('-3 days');
        $oldPending = $this->operationRepo->createQueryBuilder('o')
            ->where('o.status = :status')
            ->andWhere('o.createdAt < :date')
            ->setParameter('status', 'pending')
            ->setParameter('date', $threeDaysAgo)
            ->getQuery()->getResult();
        if (count($oldPending) > 0) {
            $alerts[] = ['type' => 'danger', 'icon' => 'exclamation-triangle', 'message' => count($oldPending) . " opération(s) en attente depuis plus de 3 jours", 'category' => 'operation'];
        }

        return $alerts;
    }

    public function getAlertCount(): int
    {
        return count($this->getAlerts());
    }
}
