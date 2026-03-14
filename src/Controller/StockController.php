<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\ClientRepository;
use App\Repository\ColdRoomRepository;
use App\Repository\StockItemRepository;
use App\Service\StockService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/stock')]
class StockController extends AbstractController
{
    public function __construct(
        private StockService $stockService,
        private StockItemRepository $stockItemRepository
    ) {}

    #[Route('', name: 'app_stock_index', methods: ['GET'])]
    public function index(
        Request $request,
        ClientRepository $clientRepository,
        ColdRoomRepository $coldRoomRepository
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        
        $filters = [
            'client' => $request->query->get('client'),
            'coldRoom' => $request->query->get('cold_room'),
        ];

        // Client can only see their own stock
        if ($user->hasRole(UserRole::CLIENT->value)) {
            $filters['client'] = $user->getClient()?->getId();
            $stockItems = $this->stockService->getStockByClient($user->getClient());
        } else {
            $stockItems = $this->stockItemRepository->findWithFilters($filters);
        }

        return $this->render('stock/index.html.twig', [
            'stockItems' => $stockItems,
            'clients' => $user->hasRole(UserRole::CLIENT->value) ? [] : $clientRepository->findBy(['isActive' => true]),
            'coldRooms' => $coldRoomRepository->findBy(['isActive' => true]),
            'filters' => $filters,
        ]);
    }

    #[Route('/export/csv', name: 'app_stock_export_csv', methods: ['GET'])]
    public function exportCsv(Request $request): StreamedResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $filters = [
            'client' => $request->query->get('client'),
            'coldRoom' => $request->query->get('cold_room'),
        ];

        if ($user->hasRole(UserRole::CLIENT->value)) {
            $filters['client'] = $user->getClient()?->getId();
        }

        $stockItems = $this->stockItemRepository->findWithFilters($filters);

        $response = new StreamedResponse(function () use ($stockItems) {
            $handle = fopen('php://output', 'w');
            
            // BOM for Excel UTF-8
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Header
            fputcsv($handle, ['Client', 'Produit', 'Chambre froide', 'Quantité (T)', 'Quantité restante (T)', 'Date entrée', 'Jours stockage'], ';');
            
            foreach ($stockItems as $item) {
                fputcsv($handle, [
                    $item->getClient()->getCompanyName(),
                    $item->getProductName(),
                    $item->getColdRoom()->getName(),
                    number_format((float)$item->getQuantityTons(), 3, ',', ''),
                    number_format($item->getRemainingQuantity(), 3, ',', ''),
                    $item->getEntryDate()->format('d/m/Y'),
                    $item->getStorageDays(),
                ], ';');
            }
            
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="stock_' . date('Y-m-d') . '.csv"');

        return $response;
    }
}
