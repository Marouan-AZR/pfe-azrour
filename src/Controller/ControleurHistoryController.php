<?php

namespace App\Controller;

use App\Entity\Operation;
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
        $search = $request->query->get('q');

        $operations = $operationRepo->findByControleurAndDates($user, $from, $to);
        $transfers = $transferRepo->findByControleur($user);

        // Filter by search
        if ($search) {
            $operations = array_filter($operations, function ($o) use ($search) {
                $s = strtolower($search);
                return str_contains(strtolower($o->getCode()), $s)
                    || str_contains(strtolower($o->getClient()->getCompanyName()), $s)
                    || str_contains(strtolower(implode(' ', $o->getEspeces())), $s);
            });
        }

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
            'search' => $search,
        ]);
    }

    #[Route('/controleur/historique/{id}', name: 'app_controleur_history_detail')]
    #[IsGranted('ROLE_CONTROLEUR')]
    public function detail(Operation $operation): Response
    {
        // Security check - only own operations
        if ($operation->getControleur() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('controleur_history/detail.html.twig', [
            'operation' => $operation,
        ]);
    }
}
