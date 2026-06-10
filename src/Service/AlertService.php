<?php

namespace App\Service;

use App\Entity\ColdRoom;
use App\Enum\StockStatus;
use App\Repository\ColdRoomRepository;
use App\Repository\OperationRepository;
use App\Repository\PaletteRepository;

class AlertService
{
    public function __construct(
        private ColdRoomRepository $coldRoomRepo,
        private OperationRepository $operationRepo,
        private PaletteRepository $paletteRepo,
        private ColdRoomOccupancyService $occupancyService,
    ) {}

    public function getAlerts(): array
    {
        $alerts = [];

        $alerts = array_merge($alerts, $this->getColdRoomAlerts());
        $alerts = array_merge($alerts, $this->getOperationAlerts());
        $alerts = array_merge($alerts, $this->getStockAlerts());

        return $alerts;
    }

    public function getAlertCount(): int
    {
        return count($this->getAlerts());
    }

    private function getColdRoomAlerts(): array
    {
        $alerts = [];
        $rooms = $this->coldRoomRepo->findBy(['isActive' => true]);
        $rates = [];

        foreach ($rooms as $room) {
            $rate = $this->occupancyService->getOccupancyRate($room);
            $rates[] = $rate;

            if ($rate >= 100) {
                $alerts[] = [
                    'type' => 'danger',
                    'icon' => 'thermometer-snow',
                    'message' => "Chambre {$room->getName()} saturée (100%)",
                    'category' => 'chambre',
                ];
            } elseif ($rate >= 90) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => 'thermometer-snow',
                    'message' => "Chambre {$room->getName()} presque pleine (" . number_format($rate, 0) . "%)",
                    'category' => 'chambre',
                ];
            } elseif ($rate > 0 && $rate < 20) {
                $alerts[] = [
                    'type' => 'info',
                    'icon' => 'thermometer-snow',
                    'message' => "Chambre {$room->getName()} sous-utilisée (" . number_format($rate, 0) . "%)",
                    'category' => 'chambre',
                ];
            }
        }

        // Déséquilibre d'occupation (écart > 30% entre chambres)
        if (count($rates) >= 2) {
            $maxRate = max($rates);
            $minRate = min($rates);
            if (($maxRate - $minRate) > 30) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => 'balance-scale',
                    'message' => "Déséquilibre d'occupation entre chambres (écart " . number_format($maxRate - $minRate, 0) . "%)",
                    'category' => 'chambre',
                ];
            }
        }

        return $alerts;
    }

    private function getOperationAlerts(): array
    {
        $alerts = [];

        // Opérations en attente prolongée (> 24h)
        $oneDayAgo = new \DateTime('-24 hours');
        $oldPending = $this->operationRepo->createQueryBuilder('o')
            ->where('o.status IN (:statuses)')
            ->andWhere('o.createdAt < :date')
            ->setParameter('statuses', [
                StockStatus::PENDING->value,
                StockStatus::EN_ATTENTE_CONTROLE->value,
                StockStatus::EN_ATTENTE_VALIDATION->value,
            ])
            ->setParameter('date', $oneDayAgo)
            ->getQuery()->getResult();

        if (count($oldPending) > 0) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => 'hourglass-split',
                'message' => count($oldPending) . " opération(s) en attente depuis plus de 24h",
                'category' => 'operation',
            ];
        }

        // Opérations rejetées récentes (dernières 48h)
        $twoDaysAgo = new \DateTime('-48 hours');
        $rejectedCount = $this->operationRepo->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.status = :status')
            ->andWhere('o.updatedAt > :date')
            ->setParameter('status', StockStatus::REJECTED->value)
            ->setParameter('date', $twoDaysAgo)
            ->getQuery()->getSingleScalarResult();

        if ($rejectedCount > 0) {
            $alerts[] = [
                'type' => 'danger',
                'icon' => 'x-circle',
                'message' => "{$rejectedCount} opération(s) rejetée(s) dans les dernières 48h",
                'category' => 'operation',
            ];
        }

        return $alerts;
    }

    private function getStockAlerts(): array
    {
        $alerts = [];

        // Stock critique par client: clients ayant très peu de stock restant
        // Vérification via les palettes avec cartonsRestants très bas
        $lowStockClients = $this->paletteRepo->createQueryBuilder('p')
            ->select('IDENTITY(o.client) as clientId, SUM(p.poidsRestant) as totalPoids')
            ->join('p.operation', 'o')
            ->where('p.cartonsRestants > 0')
            ->groupBy('o.client')
            ->having('SUM(p.poidsRestant) < :threshold')
            ->setParameter('threshold', 500) // moins de 500 kg
            ->getQuery()->getResult();

        if (count($lowStockClients) > 0) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => 'box-seam',
                'message' => count($lowStockClients) . " client(s) avec stock très faible (< 500 kg)",
                'category' => 'stock',
            ];
        }

        return $alerts;
    }
}
