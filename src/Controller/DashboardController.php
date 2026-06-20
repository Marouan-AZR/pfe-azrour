<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\StockStatus;
use App\Enum\InvoiceStatus;
use App\Enum\UserRole;
use App\Enum\OperationType;
use App\Repository\ClientRepository;
use App\Repository\ColdRoomRepository;
use App\Repository\InvoiceRepository;
use App\Repository\OperationRepository;
use App\Repository\PaletteRepository;
use App\Service\ColdRoomOccupancyService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;




class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(
        ColdRoomRepository $coldRoomRepository,
        InvoiceRepository $invoiceRepository,
        ColdRoomOccupancyService $occupancyService,
        OperationRepository $operationRepository,
        PaletteRepository $paletteRepository
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->hasRole(UserRole::CLIENT->value)) {
            return $this->clientDashboard($user, $paletteRepository, $operationRepository);
        }

        if ($user->hasRole(UserRole::PATRON->value)) {
            return $this->patronDashboard($coldRoomRepository, $invoiceRepository, $occupancyService, $paletteRepository, $operationRepository);
        }

        if ($user->hasRole(UserRole::DIRECTEUR->value)) {
            return $this->directeurDashboard($invoiceRepository, $coldRoomRepository, $occupancyService, $paletteRepository);
        }

        // Chef de stock / Contrôleur dashboard
        return $this->operationalDashboard($coldRoomRepository, $occupancyService, $operationRepository, $paletteRepository);
    }


    #[Route('/rapports', name: 'app_reports')]
    #[IsGranted('ROLE_DIRECTEUR')]
    public function reports(
        Request $request,
        PaletteRepository $paletteRepository,
        ClientRepository $clientRepository,
        ColdRoomRepository $coldRoomRepository,
        InvoiceRepository $invoiceRepository,
        OperationRepository $operationRepository,
        ColdRoomOccupancyService $occupancyService,
    ): Response {
        // ── Période sélectionnée ─────────────────────────────────────────────
        $dateFromStr = $request->query->get('date_from', date('Y-m-01'));
        $dateToStr   = $request->query->get('date_to',   date('Y-m-d'));

        try {
            $from = new \DateTime($dateFromStr . ' 00:00:00');
            $to   = new \DateTime($dateToStr   . ' 23:59:59');
        } catch (\Throwable) {
            $from = new \DateTime('first day of this month midnight');
            $to   = new \DateTime('now');
        }
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $coldRooms = $coldRoomRepository->findBy(['isActive' => true]);
        $clients   = $clientRepository->findBy(['isActive' => true]);

        // Stock réel depuis les palettes (snapshot actuel)
        $stockByClientRaw = $paletteRepository->getStockGroupedByClient();
        $totalStock       = round(array_sum(array_column($stockByClientRaw, 'quantity')), 2);

        $stockByClient = array_slice(array_map(fn($c) => [
            'name'       => $c['name'],
            'quantity'   => $c['quantity'],
            'percentage' => $totalStock > 0 ? round($c['quantity'] / $totalStock * 100, 1) : 0.0,
        ], $stockByClientRaw), 0, 5);

        // Occupation chambres (snapshot actuel)
        $statsByRoomId = $occupancyService->getOccupancyStatsForRooms($coldRooms);
        $totalCapacity = array_reduce($coldRooms, fn($sum, $r) => $sum + (float) $r->getMaxCapacityTons(), 0.0);
        $usedCapacity  = array_reduce($coldRooms, fn($sum, $r) => $sum + ($statsByRoomId[$r->getId()]['usedCapacity'] ?? 0.0), 0.0);
        $occupancyRate = $totalCapacity > 0 ? ($usedCapacity / $totalCapacity) * 100 : 0.0;

        $roomOccupancy = array_map(fn($r) => [
            'name' => $r->getName(),
            'rate' => $statsByRoomId[$r->getId()]['occupancyRate'] ?? 0.0,
        ], $coldRooms);

        // CA sur la période sélectionnée
        $periodRevenue = $invoiceRepository->getTotalRevenue($from, $to);

        // Mouvements filtrés par période
        $recentOpsEntry = $operationRepository->findValidatedInRange(OperationType::ENTRY, $from, $to, 10);
        $recentOpsExit  = $operationRepository->findValidatedInRange(OperationType::EXIT,  $from, $to, 10);

        $recentMovements = [];
        foreach ($recentOpsEntry as $op) {
            $recentMovements[] = [
                'date'     => $op->getValidatedAt() ?? $op->getCreatedAt(),
                'type'     => 'entry',
                'client'   => $op->getClient()->getCompanyName(),
                'product'  => implode(', ', $op->getEspeces()) ?: '—',
                'quantity' => round($op->getPoidsTotalTonnes(), 3),
                'code'     => $op->getCode(),
            ];
        }
        foreach ($recentOpsExit as $op) {
            $recentMovements[] = [
                'date'     => $op->getValidatedAt() ?? $op->getCreatedAt(),
                'type'     => 'exit',
                'client'   => $op->getClient()->getCompanyName(),
                'product'  => implode(', ', $op->getEspeces()) ?: '—',
                'quantity' => round($op->getPoidsTotalSortis() / 1000, 3),
                'code'     => $op->getCode(),
            ];
        }
        usort($recentMovements, fn($a, $b) => $b['date'] <=> $a['date']);
        $recentMovements = array_slice($recentMovements, 0, 10);

        return $this->render('dashboard/reports.html.twig', [
            'totalStock'      => $totalStock,
            'activeClients'   => count($clients),
            'occupancyRate'   => $occupancyRate,
            'periodRevenue'   => $periodRevenue,
            'stockByClient'   => $stockByClient,
            'roomOccupancy'   => $roomOccupancy,
            'recentMovements' => $recentMovements,
            'coldRoomsCount'  => count($coldRooms),
            'dateFrom'        => $from->format('Y-m-d'),
            'dateTo'          => $to->format('Y-m-d'),
        ]);
    }

    private function operationalDashboard(
        ColdRoomRepository $coldRoomRepository,
        ColdRoomOccupancyService $occupancyService,
        OperationRepository $operationRepository,
        PaletteRepository $paletteRepository
    ): Response {
        /** @var User $user */
        $user   = $this->getUser();
        $isChef = $user->hasRole(UserRole::CHEF_STOCK->value);

        if ($isChef) {
            $entryStatuses       = [StockStatus::PENDING];
            $exitStatuses        = [StockStatus::EN_ATTENTE_CONTROLE, StockStatus::EN_ATTENTE_VALIDATION];
            $pendingEntries      = $operationRepository->findForNotifications(OperationType::ENTRY, $entryStatuses, 10);
            $pendingExits        = $operationRepository->findForNotifications(OperationType::EXIT, $exitStatuses, 10);
            $pendingEntriesCount = $operationRepository->countByTypeAndStatuses(OperationType::ENTRY, $entryStatuses);
            $pendingExitsCount   = $operationRepository->countByTypeAndStatuses(OperationType::EXIT, $exitStatuses);
            $pallettesEnStock    = $paletteRepository->countActivePalettes();
            $entriesToday        = $operationRepository->countTodayByType(OperationType::ENTRY);
            $exitesToday         = $operationRepository->countTodayByType(OperationType::EXIT);
        } else {
            // Contrôleur : uniquement les opérations qui lui sont assignées
            $pendingEntries      = $operationRepository->findAssignedAwaitingControl($user, OperationType::ENTRY, 10);
            $pendingExits        = $operationRepository->findAssignedAwaitingControl($user, OperationType::EXIT, 10);
            $pendingEntriesCount = $operationRepository->countAssignedAwaitingControl($user, OperationType::ENTRY);
            $pendingExitsCount   = $operationRepository->countAssignedAwaitingControl($user, OperationType::EXIT);
            $pallettesEnStock    = null;
            $entriesToday        = null;
            $exitesToday         = null;
        }

        $coldRooms     = $coldRoomRepository->findBy(['isActive' => true]);
        $statsByRoomId = $occupancyService->getOccupancyStatsForRooms($coldRooms);
        $alertRooms    = array_filter($coldRooms, fn($room) => ($statsByRoomId[$room->getId()]['occupancyRate'] ?? 0) > 90);
        $stockByClient = $isChef ? $paletteRepository->getStockGroupedByClient() : [];

        return $this->render('dashboard/operational.html.twig', [
            'pendingEntries'      => $pendingEntries,
            'pendingExits'        => $pendingExits,
            'coldRooms'           => $coldRooms,
            'statsByRoomId'       => $statsByRoomId,
            'alertRooms'          => $alertRooms,
            'pendingEntriesCount' => $pendingEntriesCount,
            'pendingExitsCount'   => $pendingExitsCount,
            'pallettesEnStock'    => $pallettesEnStock,
            'entriesToday'        => $entriesToday,
            'exitesToday'         => $exitesToday,
            'stockByClient'       => $stockByClient,
        ]);
    }


    private function directeurDashboard(
        InvoiceRepository $invoiceRepository,
        ColdRoomRepository $coldRoomRepository,
        ColdRoomOccupancyService $occupancyService,
        PaletteRepository $paletteRepository
    ): Response {
        $pendingInvoices = $invoiceRepository->findBy(
            ['status' => InvoiceStatus::PENDING_VALIDATION],
            ['createdAt' => 'DESC'],
            10
        );

        $currentMonth = new \DateTime('first day of this month midnight');
        $lastMonth    = new \DateTime('first day of last month midnight');

        $revenueThisMonth = $invoiceRepository->getTotalRevenue($currentMonth);
        $revenueLastMonth = $invoiceRepository->getTotalRevenue($lastMonth, $currentMonth);

        // CA réel des 6 derniers mois (mois complets)
        $caMonthly = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = new \DateTime("first day of -$i months midnight");
            $monthEnd   = new \DateTime("last day of -$i months 23:59:59");
            $caMonthly[] = [
                'label'   => $monthStart->format('M Y'),
                'revenue' => $invoiceRepository->getTotalRevenue($monthStart, $monthEnd),
            ];
        }

        $coldRooms     = $coldRoomRepository->findBy(['isActive' => true]);
        $statsByRoomId = $occupancyService->getOccupancyStatsForRooms($coldRooms);

        // Stock réel depuis les palettes (même source que le chef de stock)
        $stockByClient   = $paletteRepository->getStockGroupedByClient();
        $totalStock      = round(array_sum(array_column($stockByClient, 'quantity')), 2);

        // Même métrique que la liste clients : poids_restant / capacité_totale_chambres
        $totalCapacityKg = array_reduce(
            $coldRooms,
            fn(float $s, $r) => $s + (float)$r->getMaxCapacityTons() * 1000,
            0.0
        );
        $criticalClients = $paletteRepository->findClientsWithLowCapacityPct($totalCapacityKg, 0.20);

        return $this->render('dashboard/directeur.html.twig', [
            'pendingInvoices'      => $pendingInvoices,
            'pendingInvoicesCount' => $invoiceRepository->count(['status' => InvoiceStatus::PENDING_VALIDATION]),
            'revenueThisMonth'     => $revenueThisMonth,
            'revenueLastMonth'     => $revenueLastMonth,
            'caMonthly'            => $caMonthly,
            'coldRooms'            => $coldRooms,
            'statsByRoomId'        => $statsByRoomId,
            'totalStock'           => $totalStock,
            'stockByClient'        => $stockByClient,
            'criticalClients'      => $criticalClients,
        ]);
    }

    private function patronDashboard(
        ColdRoomRepository $coldRoomRepository,
        InvoiceRepository $invoiceRepository,
        ColdRoomOccupancyService $occupancyService,
        PaletteRepository $paletteRepository,
        OperationRepository $operationRepository
    ): Response {
        $coldRooms     = $coldRoomRepository->findBy(['isActive' => true]);
        $statsByRoomId = $occupancyService->getOccupancyStatsForRooms($coldRooms);

        $currentMonth = new \DateTime('first day of this month midnight');
        $currentYear  = new \DateTime('first day of january this year midnight');

        $revenueThisMonth = $invoiceRepository->getTotalRevenue($currentMonth);
        $revenueThisYear  = $invoiceRepository->getTotalRevenue($currentYear);

        $totalCapacity   = array_reduce($coldRooms, fn($sum, $r) => $sum + (float) $r->getMaxCapacityTons(), 0.0);
        $usedCapacity    = array_reduce($statsByRoomId, fn($sum, $s) => $sum + $s['usedCapacity'], 0.0);
        $roomCount       = count($coldRooms);
        $avgOccupancy    = $roomCount > 0
            ? array_reduce($statsByRoomId, fn($sum, $s) => $sum + $s['occupancyRate'], 0.0) / $roomCount
            : 0.0;
        $usedCapacityPct = $totalCapacity > 0 ? round(($usedCapacity / $totalCapacity) * 100, 1) : 0.0;

        $stockByClient = $paletteRepository->getStockGroupedByClient();

        // Données réelles des 6 derniers mois (entrées, sorties, CA)
        $monthlyStats = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = new \DateTime("first day of -$i months midnight");
            $monthEnd   = new \DateTime("last day of -$i months 23:59:59");
            $monthlyStats[] = [
                'label'   => $monthStart->format('M Y'),
                'entries' => $operationRepository->getTotalWeightValidatedInRange(OperationType::ENTRY, $monthStart, $monthEnd),
                'exits'   => $operationRepository->getTotalWeightValidatedInRange(OperationType::EXIT,  $monthStart, $monthEnd),
                'ca'      => $invoiceRepository->getTotalRevenue($monthStart, $monthEnd),
            ];
        }

        return $this->render('dashboard/patron.html.twig', [
            'coldRooms'        => $coldRooms,
            'statsByRoomId'    => $statsByRoomId,
            'revenueThisMonth' => $revenueThisMonth,
            'revenueThisYear'  => $revenueThisYear,
            'avgOccupancy'     => $avgOccupancy,
            'totalCapacity'    => $totalCapacity,
            'usedCapacity'     => $usedCapacity,
            'usedCapacityPct'  => $usedCapacityPct,
            'stockByClient'    => $stockByClient,
            'monthlyStats'     => $monthlyStats,
        ]);
    }

    private function clientDashboard(
        User $user,
        PaletteRepository $paletteRepository,
        OperationRepository $operationRepository
    ): Response {
        $client = $user->getClient();

        if (!$client) {
            return $this->render('dashboard/client_no_account.html.twig');
        }

        // ── Stock actuel par palette ──
        $palettes = $paletteRepository->findStockByClient($client, []);

        $stockByEspece = [];
        $totalPalettes = 0;
        $totalCartons  = 0;
        $totalPoids    = 0.0;
        $lastEntryDate = null;

        foreach ($palettes as $p) {
            $espece = $p->getEspece();
            if (!isset($stockByEspece[$espece])) {
                $stockByEspece[$espece] = ['cartons' => 0, 'poids' => 0.0, 'palettes' => 0, 'qualite' => $p->getQualite(), 'famille' => $p->getFamille()];
            }
            $stockByEspece[$espece]['cartons']  += $p->getCartonsRestants();
            $stockByEspece[$espece]['poids']    += round((float) $p->getPoidsRestant() / 1000.0, 3);
            $stockByEspece[$espece]['palettes'] ++;
            $totalCartons  += $p->getCartonsRestants();
            $totalPoids    += (float) $p->getPoidsRestant() / 1000.0;
            $totalPalettes ++;
            $d = $p->getCreatedAt();
            if ($lastEntryDate === null || $d > $lastEntryDate) {
                $lastEntryDate = $d;
            }
        }
        arsort($stockByEspece); // tri par poids desc

        // ── Mouvements mensuels (6 derniers mois) ──
        $monthlyData = [];
        for ($i = 5; $i >= 0; $i--) {
            $from = new \DateTime("first day of -$i months midnight");
            $to   = new \DateTime("last day of -$i months 23:59:59");
            $ops  = $operationRepository->findByClientAndPeriod($client, $from, $to);

            $entries = 0;
            $exits   = 0;
            foreach ($ops as $op) {
                if ($op->getStatus() !== StockStatus::VALIDATED) {
                    continue;
                }
                foreach ($op->getPalettes() as $pal) {
                    if ($op->getType() === OperationType::ENTRY) {
                        $entries += $pal->getNombreCartons();
                    } else {
                        $exits += max(0, $pal->getNombreCartons() - $pal->getCartonsRestants());
                    }
                }
            }
            $monthlyData[] = [
                'label'   => $from->format('M Y'),
                'entries' => $entries,
                'exits'   => $exits,
            ];
        }

        return $this->render('dashboard/client.html.twig', [
            'client'        => $client,
            'palettes'      => $palettes,
            'stockByEspece' => $stockByEspece,
            'totalPalettes' => $totalPalettes,
            'totalCartons'  => $totalCartons,
            'totalPoids'    => round($totalPoids, 2),
            'nbEspeces'     => count($stockByEspece),
            'lastEntryDate' => $lastEntryDate,
            'monthlyData'   => $monthlyData,
        ]);
    }
}
