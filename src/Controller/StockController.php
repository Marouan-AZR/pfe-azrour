<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\ClientRepository;
use App\Repository\ColdRoomRepository;
use App\Repository\PaletteRepository;
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
        private StockItemRepository $stockItemRepository,
        private PaletteRepository $paletteRepository
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
            'espece' => $request->query->get('espece'),
            'moule' => $request->query->get('moule'),
            'qualite' => $request->query->get('qualite'),
            'famille' => $request->query->get('famille'),
            'rayon' => $request->query->get('rayon'),
            'dateFrom' => $request->query->get('dateFrom'),
            'dateTo' => $request->query->get('dateTo'),
        ];

        $client = null;
        if ($user->hasRole(UserRole::CLIENT->value)) {
            $client = $user->getClient();
            $filters['client'] = $client?->getId();
        } elseif ($filters['client']) {
            $client = $clientRepository->find($filters['client']);
        }

        $palettes = $this->paletteRepository->findStockByClient($client, $filters);
        $stockItems = $user->hasRole(UserRole::CLIENT->value)
            ? $this->stockService->getStockByClient($user->getClient())
            : $this->stockItemRepository->findWithFilters($filters);

        // Get distinct values for filter dropdowns
        $distinctValues = $this->paletteRepository->getDistinctFilterValues();

        return $this->render('stock/index.html.twig', [
            'stockItems' => $stockItems,
            'palettes' => $palettes,
            'clients' => $user->hasRole(UserRole::CLIENT->value) ? [] : $clientRepository->findBy(['isActive' => true]),
            'coldRooms' => $coldRoomRepository->findBy(['isActive' => true]),
            'filters' => $filters,
            'distinctValues' => $distinctValues,
        ]);
    }

    #[Route('/groupe', name: 'app_stock_group_details', methods: ['GET'])]
    public function groupDetails(
        Request $request,
        ClientRepository $clientRepository,
        ColdRoomRepository $coldRoomRepository
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $filters = [
            'client'   => $request->query->get('client'),
            'espece'   => $request->query->get('espece'),
            'coldRoom' => $request->query->get('cold_room'),
            'rayon'    => $request->query->get('rayon') ?: null,
            'qualite'  => $request->query->get('qualite') ?: null,
            'moule'    => $request->query->get('moule') ?: null,
            'famille'  => $request->query->get('famille') ?: null,
        ];

        $client = null;
        if ($user->hasRole(UserRole::CLIENT->value)) {
            $client = $user->getClient();
            $filters['client'] = $client?->getId();
        } elseif ($filters['client']) {
            $client = $clientRepository->find($filters['client']);
        }

        $palettes = $this->paletteRepository->findStockByClient($client, $filters);
        $coldRoom = $filters['coldRoom'] ? $coldRoomRepository->find($filters['coldRoom']) : null;

        return $this->render('stock/group_details.html.twig', [
            'palettes' => $palettes,
            'client'   => $client,
            'coldRoom' => $coldRoom,
            'espece'   => $filters['espece'],
            'rayon'    => $filters['rayon'],
            'qualite'  => $filters['qualite'],
            'moule'    => $filters['moule'],
            'famille'  => $filters['famille'],
        ]);
    }

    #[Route('/palette/{id}/details', name: 'app_stock_palette_details', methods: ['GET'])]
    public function paletteDetails(int $id): Response
    {
        $palette = $this->paletteRepository->find($id);
        if (!$palette) {
            throw $this->createNotFoundException();
        }

        // Exit sub-palettes that reference this entry palette
        $exitPalettes = $this->paletteRepository->findBy(['sourcePalette' => $palette]);

        $movements = [];

        // Entry event
        $op = $palette->getOperation();
        $movements[] = [
            'date'       => $palette->getCreatedAt(),
            'type'       => 'entry',
            'cartons'    => $palette->getNombreCartons(),
            'controleur' => $op->getControleur() ?? $op->getCreatedBy(),
            'detail'     => null,
        ];

        // Exit events
        foreach ($exitPalettes as $ep) {
            $exitOp  = $ep->getOperation();
            $cartons = $ep->getCartonsReellementSortis() ?? $ep->getNombreCartons();
            $movements[] = [
                'date'       => $exitOp->getCreatedAt(),
                'type'       => 'exit',
                'cartons'    => $cartons,
                'controleur' => $exitOp->getControleur() ?? $exitOp->getCreatedBy(),
                'detail'     => null,
            ];
        }

        // Transfer events
        foreach ($palette->getTransfers() as $tr) {
            $from = $tr->getFromColdRoom()->getName() . ($tr->getFromRayon() ? '/'.$tr->getFromRayon() : '');
            $to   = $tr->getToColdRoom()->getName()   . ($tr->getToRayon()   ? '/'.$tr->getToRayon()   : '');
            $movements[] = [
                'date'       => $tr->getTransferredAt(),
                'type'       => 'transfer',
                'cartons'    => 0,
                'controleur' => $tr->getTransferredBy(),
                'detail'     => $from . ' → ' . $to,
            ];
        }

        // Sort chronologically ASC
        usort($movements, fn($a, $b) => $a['date'] <=> $b['date']);

        // Compute running balance
        $running = 0;
        foreach ($movements as &$m) {
            if ($m['type'] === 'entry') {
                $running += $m['cartons'];
            } elseif ($m['type'] === 'exit') {
                $running -= $m['cartons'];
            }
            $m['restant']      = $running;
            $m['poidsRestant'] = $running * (float)$palette->getPoidsCarton();
        }
        unset($m);

        return $this->render('stock/palette_details.html.twig', [
            'palette'   => $palette,
            'movements' => $movements,
        ]);
    }

    #[Route('/export/csv', name: 'app_stock_export_csv', methods: ['GET'])]
    public function exportCsv(Request $request, ClientRepository $clientRepository): StreamedResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $filters = [
            'client'   => $request->query->get('client'),
            'coldRoom' => $request->query->get('cold_room'),
            'espece'   => $request->query->get('espece'),
            'rayon'    => $request->query->get('rayon'),
        ];

        if ($user->hasRole(UserRole::CLIENT->value)) {
            $filters['client'] = $user->getClient()?->getId();
        }

        $client = $filters['client'] ? $clientRepository->find($filters['client']) : null;
        $palettes = $this->paletteRepository->findStockByClient($client, $filters);

        $response = new StreamedResponse(function () use ($palettes) {
            $handle = fopen('php://output', 'w');

            // BOM for Excel UTF-8
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header
            fputcsv($handle, ['Client', 'Code palette', 'Espèce', 'Qualité', 'Moule', 'Chambre froide', 'Rayon', 'Cartons initiaux', 'Cartons restants', 'Poids restant (kg)', 'Date entrée', 'Jours stockage'], ';');

            foreach ($palettes as $palette) {
                $days = $palette->getCreatedAt()->diff(new \DateTime())->days;
                fputcsv($handle, [
                    $palette->getOperation()->getClient()->getCompanyName(),
                    $palette->getCodePalette(),
                    $palette->getEspece(),
                    $palette->getQualite() ?? '',
                    $palette->getMoule() ?? '',
                    $palette->getColdRoom()?->getName() ?? '—',
                    $palette->getRayon() ?? '—',
                    $palette->getNombreCartons(),
                    $palette->getCartonsRestants(),
                    number_format((float)$palette->getPoidsRestant(), 3, ',', ''),
                    $palette->getCreatedAt()->format('d/m/Y'),
                    $days,
                ], ';');
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="stock_' . date('Y-m-d') . '.csv"');

        return $response;
    }
}
