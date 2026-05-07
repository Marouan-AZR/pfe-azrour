<?php

namespace App\Controller;

use App\Enum\OperationType;
use App\Repository\OperationRepository;
use App\Repository\PaletteTransferRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ControleurHistoryController extends AbstractController
{
    #[Route('/controleur/historique', name: 'app_controleur_history')]
    #[IsGranted('ROLE_CONTROLEUR')]
    public function index(Request $request, OperationRepository $operationRepo, PaletteTransferRepository $transferRepo): Response
    {
        $user = $this->getUser();
        $from = $request->query->get('from') ? new \DateTime($request->query->get('from')) : null;
        $to = $request->query->get('to') ? new \DateTime($request->query->get('to') . ' 23:59:59') : null;

        $operations = $operationRepo->findByControleurAndDates($user, $from, $to);
        $transfers = $transferRepo->findByControleur($user);

        // KPIs
        $entries = array_filter($operations, fn($o) => $o->getType() === OperationType::ENTRY);
        $exits = array_filter($operations, fn($o) => $o->getType() === OperationType::EXIT);
        $totalPalettes = array_sum(array_map(fn($o) => $o->getNombrePalettes(), $operations));
        $totalCartons = array_sum(array_map(fn($o) => $o->getTotalCartons(), $operations));
        $totalWeight = array_sum(array_map(fn($o) => $o->getPoidsTotal(), $operations));

        return $this->render('controleur_history/index.html.twig', [
            'operations' => $operations,
            'kpis' => [
                'entries' => count($entries),
                'exits' => count($exits),
                'transfers' => count($transfers),
                'palettes' => $totalPalettes,
                'cartons' => $totalCartons,
                'weight' => round($totalWeight / 1000, 2),
            ],
            'from' => $request->query->get('from'),
            'to' => $request->query->get('to'),
        ]);
    }
}
