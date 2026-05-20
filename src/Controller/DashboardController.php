<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\StockStatus;
use App\Enum\InvoiceStatus;
use App\Enum\UserRole;
use App\Repository\ClientRepository;
use App\Repository\ColdRoomRepository;
use App\Repository\InvoiceRepository;
use App\Repository\StockEntryRepository;
use App\Repository\StockExitRepository;
use App\Repository\StockItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller as BaseController;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use App\Service\ColdRoomOccupancyService;




class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(
        StockEntryRepository $entryRepository,
        StockExitRepository $exitRepository,
        ColdRoomRepository $coldRoomRepository,
        InvoiceRepository $invoiceRepository,
        StockItemRepository $stockItemRepository,
        ColdRoomOccupancyService $occupancyService,
        \App\Repository\FicheDechargeRepository $ficheDechargeRepository

    ): Response {



        /** @var User $user */
        $user = $this->getUser();

        // Route to appropriate dashboard based on role
        if ($user->hasRole(UserRole::CLIENT->value)) {
            return $this->clientDashboard($user, $stockItemRepository, $invoiceRepository, $exitRepository, $entryRepository);
        }

        if ($user->hasRole(UserRole::PATRON->value)) {
            return $this->patronDashboard($coldRoomRepository, $invoiceRepository);
        }

        if ($user->hasRole(UserRole::DIRECTEUR->value)) {
            return $this->directeurDashboard($invoiceRepository);
        }

        // Chef de stock / Contrôleur dashboard
        return $this->operationalDashboard($entryRepository, $exitRepository, $coldRoomRepository, $occupancyService, $ficheDechargeRepository);

















    }


    #[Route('/rapports', name: 'app_reports')]
    #[IsGranted('ROLE_USER')]
    public function reports(
        StockItemRepository $stockItemRepository,
        ClientRepository $clientRepository,
        ColdRoomRepository $coldRoomRepository,
        InvoiceRepository $invoiceRepository,
        StockEntryRepository $entryRepository,
        StockExitRepository $exitRepository
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user->hasRole(UserRole::DIRECTEUR->value) && !$user->hasRole(UserRole::PATRON->value)) {
            throw $this->createAccessDeniedException();
        }

        $coldRooms = $coldRoomRepository->findBy(['isActive' => true]);
        $clients = $clientRepository->findBy(['isActive' => true]);
        
        // Total stock
        $stockItems = $stockItemRepository->findAll();
        $totalStock = array_reduce($stockItems, fn($sum, $item) => $sum + $item->getRemainingQuantity(), 0);
        
        /** @var ColdRoomOccupancyService $occupancyService */
        $occupancyService = $this->container->get(ColdRoomOccupancyService::class);
        $statsByRoomId = $occupancyService->getOccupancyStatsForRooms($coldRooms);







        // Occupancy rate (Palette -> poidsRestant)
        $totalCapacity = array_reduce($coldRooms, fn($sum, $room) => $sum + (float) $room->getMaxCapacityTons(), 0);
        $usedCapacity = array_reduce($coldRooms, fn($sum, $room) => $sum + ($statsByRoomId[$room->getId()]['usedCapacity'] ?? 0.0), 0);
        $occupancyRate = $totalCapacity > 0 ? ($usedCapacity / $totalCapacity) * 100 : 0;
        

        
        // Monthly revenue
        $currentMonth = new \DateTime('first day of this month');
        $monthlyRevenue = $invoiceRepository->getTotalRevenue($currentMonth);
        
        // Stock by client
        $stockByClient = [];
        foreach ($clients as $client) {
            $clientStock = array_reduce(
                $stockItemRepository->findByClient($client),
                fn($sum, $item) => $sum + $item->getRemainingQuantity(),
                0
            );
            if ($clientStock > 0) {
                $stockByClient[] = [
                    'name' => $client->getCompanyName(),
                    'quantity' => $clientStock,
                    'percentage' => $totalStock > 0 ? ($clientStock / $totalStock) * 100 : 0,
                ];
            }
        }
        usort($stockByClient, fn($a, $b) => $b['quantity'] <=> $a['quantity']);
        
        // Room occupancy (Palette -> poidsRestant)
        $roomOccupancy = array_map(fn($room) => [
            'name' => $room->getName(),
            'rate' => $statsByRoomId[$room->getId()]['occupancyRate'] ?? 0.0,
        ], $coldRooms);

        // Recent movements
        $recentEntries = $entryRepository->findBy(['status' => StockStatus::VALIDATED], ['validatedAt' => 'DESC'], 5);
        $recentExits = $exitRepository->findBy(['status' => StockStatus::VALIDATED], ['validatedAt' => 'DESC'], 5);
        
        $recentMovements = [];
        foreach ($recentEntries as $entry) {
            $recentMovements[] = [
                'date' => $entry->getValidatedAt(),
                'type' => 'entry',
                'client' => $entry->getClient()->getCompanyName(),
                'product' => $entry->getProductName(),
                'quantity' => $entry->getQuantityTons(),
            ];
        }
        foreach ($recentExits as $exit) {
            $recentMovements[] = [
                'date' => $exit->getValidatedAt(),
                'type' => 'exit',
                'client' => $exit->getClient()->getCompanyName(),
                'product' => $exit->getProductName(),
                'quantity' => $exit->getQuantityTons(),
            ];
        }
        usort($recentMovements, fn($a, $b) => $b['date'] <=> $a['date']);
        $recentMovements = array_slice($recentMovements, 0, 10);

        return $this->render('dashboard/reports.html.twig', [
            'totalStock' => $totalStock,
            'activeClients' => count($clients),
            'occupancyRate' => $occupancyRate,
            'monthlyRevenue' => $monthlyRevenue,
            'stockByClient' => array_slice($stockByClient, 0, 5),
            'roomOccupancy' => $roomOccupancy,
            'recentMovements' => $recentMovements,
        ]);
    }

    private function operationalDashboard(
        StockEntryRepository $entryRepository,
        StockExitRepository $exitRepository,
        ColdRoomRepository $coldRoomRepository,
        ColdRoomOccupancyService $occupancyService,
        \App\Repository\FicheDechargeRepository $ficheDechargeRepository
    ): Response {



        /** @var User $user */
        $user = $this->getUser();

        $pendingEntries = $entryRepository->findPendingByCreatedBy($user, 10);
        // Les “sorties en attente” sont celles assignées au contrôleur dont la fiche est en cours de contrôle
        $pendingExits = $ficheDechargeRepository->findByControleur(
            $user,
            \App\Enum\FicheStatus::EN_COURS_CONTROLE
        );

        $coldRooms = $coldRoomRepository->findBy(['isActive' => true]);


        
        // Find rooms with high occupancy (Palette -> poidsRestant)
        $statsByRoomId = $occupancyService->getOccupancyStatsForRooms($coldRooms);
        $alertRooms = array_filter($coldRooms, fn($room) => ($statsByRoomId[$room->getId()]['occupancyRate'] ?? 0) > 90);



        return $this->render('dashboard/operational.html.twig', [
            'pendingEntries' => $pendingEntries,
            'pendingExits' => $pendingExits,
            'coldRooms' => $coldRooms,
            'alertRooms' => $alertRooms,
            'pendingEntriesCount' => $entryRepository->countPendingByCreatedBy($user),
            'pendingExitsCount' => $exitRepository->countPendingByCreatedBy($user),
        ]);
    }


    private function directeurDashboard(InvoiceRepository $invoiceRepository): Response
    {
        $pendingInvoices = $invoiceRepository->findBy(
            ['status' => InvoiceStatus::PENDING_VALIDATION],
            ['createdAt' => 'DESC'],
            10
        );

        $currentMonth = new \DateTime('first day of this month');
        $lastMonth = new \DateTime('first day of last month');
        
        $revenueThisMonth = $invoiceRepository->getTotalRevenue($currentMonth);
        $revenueLastMonth = $invoiceRepository->getTotalRevenue($lastMonth, $currentMonth);

        return $this->render('dashboard/directeur.html.twig', [
            'pendingInvoices' => $pendingInvoices,
            'pendingInvoicesCount' => $invoiceRepository->count(['status' => InvoiceStatus::PENDING_VALIDATION]),
            'revenueThisMonth' => $revenueThisMonth,
            'revenueLastMonth' => $revenueLastMonth,
        ]);
    }

    private function patronDashboard(
        ColdRoomRepository $coldRoomRepository,
        InvoiceRepository $invoiceRepository
    ): Response {
        $coldRooms = $coldRoomRepository->findBy(['isActive' => true]);
        
        $currentMonth = new \DateTime('first day of this month');
        $currentYear = new \DateTime('first day of january this year');
        
        $revenueThisMonth = $invoiceRepository->getTotalRevenue($currentMonth);
        $revenueThisYear = $invoiceRepository->getTotalRevenue($currentYear);

        // Calculate average occupancy
        $totalOccupancy = array_reduce($coldRooms, fn($sum, $room) => $sum + $room->getOccupancyRate(), 0);
        $avgOccupancy = count($coldRooms) > 0 ? $totalOccupancy / count($coldRooms) : 0;

        return $this->render('dashboard/patron.html.twig', [
            'coldRooms' => $coldRooms,
            'revenueThisMonth' => $revenueThisMonth,
            'revenueThisYear' => $revenueThisYear,
            'avgOccupancy' => $avgOccupancy,
            'totalCapacity' => array_reduce($coldRooms, fn($sum, $room) => $sum + (float)$room->getMaxCapacityTons(), 0),
            'usedCapacity' => array_reduce($coldRooms, fn($sum, $room) => $sum + $room->getUsedCapacity(), 0),
        ]);
    }

    private function clientDashboard(
        User $user,
        StockItemRepository $stockItemRepository,
        InvoiceRepository $invoiceRepository,
        StockExitRepository $exitRepository,
        StockEntryRepository $entryRepository
    ): Response {
        $client = $user->getClient();
        
        if (!$client) {
            return $this->render('dashboard/client_no_account.html.twig');
        }

        $stockItems = $stockItemRepository->findByClient($client);
        $recentInvoices = $invoiceRepository->findBy(['client' => $client], ['createdAt' => 'DESC'], 5);
        $recentEntries = $entryRepository->findRecentByClient($client, 5);
        $recentExits = $exitRepository->findRecentByClient($client, 5);

        // Calculate total stock
        $totalStock = array_reduce($stockItems, fn($sum, $item) => $sum + $item->getRemainingQuantity(), 0);

        return $this->render('dashboard/client.html.twig', [
            'client' => $client,
            'stockItems' => $stockItems,
            'recentInvoices' => $recentInvoices,
            'recentEntries' => $recentEntries,
            'recentExits' => $recentExits,
            'totalStock' => $totalStock,
        ]);
    }
}
