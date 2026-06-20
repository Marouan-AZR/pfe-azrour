<?php

namespace App\Service;

use App\Enum\OperationType;
use App\Enum\PaletteStatus;
use App\Enum\StockStatus;
use App\Repository\ColdRoomRepository;
use App\Repository\OperationRepository;
use App\Repository\PaletteRepository;
use App\Service\ColdRoomOccupancyService;

class AlertService
{
    // ── Seuils paramétrables ──────────────────────────────────────
    private const ROOM_FULL_PCT         = 100.0;
    private const ROOM_ALMOST_FULL_PCT  = 90.0;
    private const ROOM_UNDERUSED_PCT    = 20.0;
    private const ROOM_IMBALANCE_PCT    = 30.0;
    private const STOCK_LOW_RATIO       = 0.20;   // ≤ 20 % cartons restants
    private const STOCK_CRITICAL_KG     = 200.0;  // < 200 kg → rupture imminente
    private const STOCK_RAPID_DROP_PCT  = 0.30;   // 30 % du stock sorti en 7 j
    private const OP_PENDING_WARN_H     = 24;     // en attente > 24 h → warning
    private const OP_PENDING_URGENT_H   = 48;     // en attente > 48 h → danger
    private const OP_UNCONTROLLED_H     = 6;      // non contrôlée > 6 h
    private const PERF_HIGH_ACTIVITY    = 10;     // ops/jour seuil activité forte
    private const PERF_FLUX_RATIO       = 2.5;    // déséquilibre flux
    private const PERF_DOMINANT_CLIENT  = 50.0;   // % stock pour client dominant

    public function __construct(
        private readonly ColdRoomRepository      $coldRoomRepo,
        private readonly OperationRepository     $operationRepo,
        private readonly PaletteRepository       $paletteRepo,
        private readonly ColdRoomOccupancyService $occupancyService,
    ) {}

    /** Toutes les alertes triées par priorité (1 = plus urgent). */
    public function getAlerts(): array
    {
        $alerts = array_merge(
            $this->getColdRoomAlerts(),
            $this->getStockAlerts(),
            $this->getOperationAlerts(),
            $this->getPerformanceAlerts(),
        );

        usort($alerts, fn($a, $b) => $a['priority'] <=> $b['priority']);

        return $alerts;
    }

    /** Nombre d'alertes non-succès (pour le badge dans la nav). */
    public function getAlertCount(): int
    {
        return count(array_filter($this->getAlerts(), fn($a) => $a['type'] !== 'success'));
    }

    /** Statistiques groupées pour le contrôleur. */
    public function getStats(array $alerts): array
    {
        $byType     = array_count_values(array_column($alerts, 'type'));
        $byCategory = array_count_values(array_column($alerts, 'category'));

        return [
            'total'       => count($alerts),
            'danger'      => $byType['danger']      ?? 0,
            'warning'     => $byType['warning']     ?? 0,
            'info'        => $byType['info']         ?? 0,
            'success'     => $byType['success']      ?? 0,
            'chambre'     => $byCategory['chambre']  ?? 0,
            'stock'       => $byCategory['stock']    ?? 0,
            'operation'   => $byCategory['operation'] ?? 0,
            'performance' => $byCategory['performance'] ?? 0,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // 1. CHAMBRES FROIDES
    // ─────────────────────────────────────────────────────────────
    private function getColdRoomAlerts(): array
    {
        $alerts = [];
        $rooms  = $this->coldRoomRepo->findBy(['isActive' => true]);
        $rates  = [];

        foreach ($rooms as $room) {
            $rate           = $this->occupancyService->getOccupancyRate($room);
            $rates[$room->getId()] = $rate;
            $pct            = number_format($rate, 0);

            if ($rate >= self::ROOM_FULL_PCT) {
                $alerts[] = $this->make(
                    'danger', 1, 'thermometer-high', 'chambre',
                    'Chambre saturée — capacité atteinte',
                    "{$room->getName()} est à {$pct}% · Aucune place disponible. Redistribuer ou sortir du stock en urgence."
                );
            } elseif ($rate >= self::ROOM_ALMOST_FULL_PCT) {
                $alerts[] = $this->make(
                    'warning', 2, 'thermometer-snow', 'chambre',
                    'Chambre presque pleine',
                    "{$room->getName()} : {$pct}% d'occupation · Capacité résiduelle très limitée."
                );
            } elseif ($rate > 0 && $rate < self::ROOM_UNDERUSED_PCT) {
                $alerts[] = $this->make(
                    'info', 4, 'thermometer-low', 'chambre',
                    'Chambre sous-utilisée',
                    "{$room->getName()} : seulement {$pct}% d'occupation · Optimisation de l'espace recommandée."
                );
            }
        }

        if (count($rates) >= 2) {
            $maxRate = max($rates);
            $minRate = min($rates);
            $ecart   = $maxRate - $minRate;
            if ($ecart > self::ROOM_IMBALANCE_PCT) {
                $alerts[] = $this->make(
                    'warning', 3, 'arrow-left-right', 'chambre',
                    "Déséquilibre d'occupation entre chambres",
                    'Écart de ' . number_format($ecart, 0) . '% entre la chambre la plus chargée et la moins chargée · Redistribution conseillée.'
                );
            }
        }

        return $alerts;
    }

    // ─────────────────────────────────────────────────────────────
    // 2. STOCK
    // ─────────────────────────────────────────────────────────────
    private function getStockAlerts(): array
    {
        $alerts   = [];
        $excluded = [PaletteStatus::SORTIE_COMPLETE->value, PaletteStatus::REJETEE->value];

        // Stock faible : palettes ≤ 20 % cartons initiaux
        $lowRows = $this->paletteRepo->createQueryBuilder('p')
            ->select('IDENTITY(o.client) as cId, COUNT(p.id) as cnt')
            ->join('p.operation', 'o')
            ->where('p.cartonsRestants > 0')
            ->andWhere('p.status NOT IN (:excl)')
            ->andWhere('p.nombreCartons > 0')
            ->andWhere('p.cartonsRestants <= p.nombreCartons * :ratio')
            ->setParameter('excl', $excluded)
            ->setParameter('ratio', self::STOCK_LOW_RATIO)
            ->groupBy('o.client')
            ->getQuery()->getResult();

        if (count($lowRows) > 0) {
            $totalPal = (int) array_sum(array_column($lowRows, 'cnt'));
            $alerts[] = $this->make(
                'warning', 2, 'box-seam', 'stock',
                'Stock faible détecté',
                "{$totalPal} palette(s) à moins de 20 % de leur stock initial · " . count($lowRows) . ' client(s) concerné(s).'
            );
        }

        // Stock critique client : total poids < seuil
        $criticalRows = $this->paletteRepo->createQueryBuilder('p')
            ->select('IDENTITY(o.client) as cId, SUM(p.poidsRestant) as total')
            ->join('p.operation', 'o')
            ->join('o.client', 'c')
            ->addSelect('c.companyName as name')
            ->where('p.cartonsRestants > 0')
            ->andWhere('p.status NOT IN (:excl)')
            ->setParameter('excl', $excluded)
            ->groupBy('o.client, c.companyName')
            ->having('SUM(p.poidsRestant) < :threshold AND SUM(p.poidsRestant) > 0')
            ->setParameter('threshold', self::STOCK_CRITICAL_KG)
            ->getQuery()->getResult();

        foreach ($criticalRows as $row) {
            $kg = number_format((float) $row['total'], 0, ',', ' ');
            $alerts[] = $this->make(
                'danger', 1, 'exclamation-triangle-fill', 'stock',
                'Stock critique — risque de rupture',
                "{$row['name']} : seulement {$kg} kg restants · Réapprovisionnement urgent recommandé."
            );
        }

        // Diminution rapide : sorties des 7 derniers jours > 30 % du stock actuel
        $sevenDaysAgo = new \DateTime('-7 days');

        $recentExitKg = (float) ($this->paletteRepo->createQueryBuilder('p')
            ->select('SUM(p.poidsTotal)')
            ->join('p.operation', 'o')
            ->where('o.type = :exit')
            ->andWhere('o.validatedAt >= :since')
            ->andWhere('p.status IN (:exitStatuses)')
            ->setParameter('exit', OperationType::EXIT->value)
            ->setParameter('since', $sevenDaysAgo)
            ->setParameter('exitStatuses', [
                PaletteStatus::SORTIE_COMPLETE->value,
                PaletteStatus::SORTIE_PARTIELLE->value,
            ])
            ->getQuery()->getSingleScalarResult() ?? 0);

        $currentTotalKg = (float) ($this->paletteRepo->createQueryBuilder('p')
            ->select('SUM(p.poidsRestant)')
            ->where('p.cartonsRestants > 0')
            ->andWhere('p.status NOT IN (:excl)')
            ->setParameter('excl', $excluded)
            ->getQuery()->getSingleScalarResult() ?? 0);

        if ($currentTotalKg > 0 && $recentExitKg > $currentTotalKg * self::STOCK_RAPID_DROP_PCT) {
            $ratio = number_format($recentExitKg / $currentTotalKg * 100, 0);
            $kgFmt = number_format($recentExitKg, 0, ',', ' ');
            $alerts[] = $this->make(
                'warning', 2, 'graph-down-arrow', 'stock',
                'Diminution rapide du stock',
                "{$kgFmt} kg sortis ces 7 derniers jours · soit {$ratio}% du stock actuel — rythme de déstockage élevé."
            );
        }

        return $alerts;
    }

    // ─────────────────────────────────────────────────────────────
    // 3. OPÉRATIONS LOGISTIQUES
    // ─────────────────────────────────────────────────────────────
    private function getOperationAlerts(): array
    {
        $alerts   = [];
        $pending  = [
            StockStatus::PENDING->value,
            StockStatus::EN_ATTENTE_CONTROLE->value,
            StockStatus::EN_ATTENTE_VALIDATION->value,
        ];

        $twoDaysAgo  = new \DateTime('-' . self::OP_PENDING_URGENT_H . ' hours');
        $oneDayAgo   = new \DateTime('-' . self::OP_PENDING_WARN_H   . ' hours');
        $sixHoursAgo = new \DateTime('-' . self::OP_UNCONTROLLED_H   . ' hours');
        $todayStart  = new \DateTime('today midnight');

        // Bloquées > 48 h
        $urgent = (int) $this->operationRepo->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.status IN (:s)')
            ->andWhere('o.createdAt < :d')
            ->setParameter('s', $pending)
            ->setParameter('d', $twoDaysAgo)
            ->getQuery()->getSingleScalarResult();

        if ($urgent > 0) {
            $alerts[] = $this->make(
                'danger', 1, 'hourglass-bottom', 'operation',
                'Opérations bloquées — intervention requise',
                "{$urgent} opération(s) en attente depuis plus de 48h · Traitement urgent nécessaire."
            );
        }

        // En attente 24–48 h
        $warn = (int) $this->operationRepo->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.status IN (:s)')
            ->andWhere('o.createdAt < :d1')
            ->andWhere('o.createdAt >= :d2')
            ->setParameter('s', $pending)
            ->setParameter('d1', $oneDayAgo)
            ->setParameter('d2', $twoDaysAgo)
            ->getQuery()->getSingleScalarResult();

        if ($warn > 0) {
            $alerts[] = $this->make(
                'warning', 2, 'hourglass-split', 'operation',
                'Opérations en attente prolongée',
                "{$warn} opération(s) en attente depuis plus de 24h · Traitement recommandé."
            );
        }

        // Rejetées dans les 48 h
        $rejected = (int) $this->operationRepo->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.status = :s')
            ->andWhere('o.updatedAt >= :d')
            ->setParameter('s', StockStatus::REJECTED->value)
            ->setParameter('d', $twoDaysAgo)
            ->getQuery()->getSingleScalarResult();

        if ($rejected > 0) {
            $alerts[] = $this->make(
                'danger', 1, 'x-circle-fill', 'operation',
                'Opérations rejetées récentes',
                "{$rejected} opération(s) rejetée(s) dans les 48 dernières heures · Vérification et motif requis."
            );
        }

        // Palettes non contrôlées > 6 h
        $uncontrolled = (int) $this->operationRepo->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.status = :s')
            ->andWhere('o.createdAt < :d')
            ->setParameter('s', StockStatus::EN_ATTENTE_CONTROLE->value)
            ->setParameter('d', $sixHoursAgo)
            ->getQuery()->getSingleScalarResult();

        if ($uncontrolled > 0) {
            $alerts[] = $this->make(
                'warning', 3, 'clipboard-x', 'operation',
                'Palettes non contrôlées',
                "{$uncontrolled} opération(s) en attente de contrôle depuis plus de 6h · Assign un contrôleur."
            );
        }

        // Validées aujourd'hui — notification positive
        $validatedToday = (int) $this->operationRepo->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.status = :s')
            ->andWhere('o.validatedAt >= :d')
            ->setParameter('s', StockStatus::VALIDATED->value)
            ->setParameter('d', $todayStart)
            ->getQuery()->getSingleScalarResult();

        if ($validatedToday > 0) {
            $alerts[] = $this->make(
                'success', 5, 'check-circle-fill', 'operation',
                'Opérations validées aujourd\'hui',
                "{$validatedToday} opération(s) validée(s) avec succès aujourd'hui."
            );
        }

        return $alerts;
    }

    // ─────────────────────────────────────────────────────────────
    // 4. PERFORMANCE
    // ─────────────────────────────────────────────────────────────
    private function getPerformanceAlerts(): array
    {
        $alerts     = [];
        $excluded   = [PaletteStatus::SORTIE_COMPLETE->value, PaletteStatus::REJETEE->value];
        $todayStart = new \DateTime('today midnight');
        $twoDaysAgo = new \DateTime('-48 hours');
        $weekAgo    = new \DateTime('-7 days');

        // Forte activité aujourd'hui
        $opsToday = (int) $this->operationRepo->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.createdAt >= :d')
            ->setParameter('d', $todayStart)
            ->getQuery()->getSingleScalarResult();

        if ($opsToday >= self::PERF_HIGH_ACTIVITY) {
            $alerts[] = $this->make(
                'info', 4, 'graph-up-arrow', 'performance',
                'Forte activité logistique',
                "{$opsToday} opérations créées aujourd'hui · Activité supérieure au seuil normal ({" . self::PERF_HIGH_ACTIVITY . "})."
            );
        }

        // Baisse d'activité — 0 opération en 48 h
        $recentOps = (int) $this->operationRepo->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.createdAt >= :d')
            ->setParameter('d', $twoDaysAgo)
            ->getQuery()->getSingleScalarResult();

        if ($recentOps === 0) {
            $alerts[] = $this->make(
                'warning', 3, 'graph-down', 'performance',
                "Baisse d'activité anormale",
                'Aucune opération enregistrée depuis 48h · Vérifier que le système fonctionne normalement.'
            );
        }

        // Déséquilibre flux entrées / sorties (7 jours)
        $entryCount = (int) $this->operationRepo->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.type = :t')->andWhere('o.status = :s')->andWhere('o.createdAt >= :d')
            ->setParameter('t', OperationType::ENTRY->value)
            ->setParameter('s', StockStatus::VALIDATED->value)
            ->setParameter('d', $weekAgo)
            ->getQuery()->getSingleScalarResult();

        $exitCount = (int) $this->operationRepo->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.type = :t')->andWhere('o.status = :s')->andWhere('o.createdAt >= :d')
            ->setParameter('t', OperationType::EXIT->value)
            ->setParameter('s', StockStatus::VALIDATED->value)
            ->setParameter('d', $weekAgo)
            ->getQuery()->getSingleScalarResult();

        if ($exitCount > 0 && $entryCount > $exitCount * self::PERF_FLUX_RATIO) {
            $alerts[] = $this->make(
                'info', 4, 'arrow-up-right', 'performance',
                'Flux déséquilibré — entrées dominantes',
                "Cette semaine : {$entryCount} entrée(s) vs {$exitCount} sortie(s) · Stock en forte augmentation."
            );
        } elseif ($entryCount > 0 && $exitCount > $entryCount * self::PERF_FLUX_RATIO) {
            $alerts[] = $this->make(
                'warning', 3, 'arrow-down-right', 'performance',
                'Flux déséquilibré — sorties dominantes',
                "Cette semaine : {$exitCount} sortie(s) vs {$entryCount} entrée(s) · Déstockage important en cours."
            );
        } elseif ($entryCount === 0 && $exitCount === 0 && $recentOps > 0) {
            // Ops exist but none validated — info only
        }

        // Client dominant (> 50 % du stock total)
        $totalKg = (float) ($this->paletteRepo->createQueryBuilder('p')
            ->select('SUM(p.poidsRestant)')
            ->where('p.cartonsRestants > 0')
            ->andWhere('p.status NOT IN (:excl)')
            ->setParameter('excl', $excluded)
            ->getQuery()->getSingleScalarResult() ?? 0);

        if ($totalKg > 0) {
            $topClient = $this->paletteRepo->createQueryBuilder('p')
                ->select('c.companyName as name, SUM(p.poidsRestant) as kg')
                ->join('p.operation', 'o')
                ->join('o.client', 'c')
                ->where('p.cartonsRestants > 0')
                ->andWhere('p.status NOT IN (:excl)')
                ->setParameter('excl', $excluded)
                ->groupBy('o.client, c.companyName')
                ->orderBy('kg', 'DESC')
                ->setMaxResults(1)
                ->getQuery()->getOneOrNullResult();

            if ($topClient !== null) {
                $pct = (float) $topClient['kg'] / $totalKg * 100;
                if ($pct >= self::PERF_DOMINANT_CLIENT) {
                    $pctFmt = number_format($pct, 0);
                    $alerts[] = $this->make(
                        'info', 4, 'person-fill-check', 'performance',
                        'Concentration de stock — client dominant',
                        "{$topClient['name']} représente {$pctFmt}% du stock total de l'entrepôt · Dépendance élevée."
                    );
                }
            }
        }

        return $alerts;
    }

    // ─────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────
    private function make(
        string $type,
        int    $priority,
        string $icon,
        string $category,
        string $title,
        string $message,
    ): array {
        return compact('type', 'priority', 'icon', 'category', 'title', 'message');
    }
}
