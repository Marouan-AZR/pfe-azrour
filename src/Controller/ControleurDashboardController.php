<?php

namespace App\Controller;

use App\Repository\OperationRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ControleurDashboardController extends AbstractController
{
    #[Route('/controleurs/performances', name: 'app_controleur_dashboard')]
    public function index(Request $request, OperationRepository $operationRepo, UserRepository $userRepo): Response
    {
        if ($this->isGranted('ROLE_PATRON') || (!$this->isGranted('ROLE_CHEF_STOCK') && !$this->isGranted('ROLE_DIRECTEUR'))) {
            throw $this->createAccessDeniedException();
        }

        $month = (int)$request->query->get('month', date('m'));
        $year = (int)$request->query->get('year', date('Y'));

        $controleurs = $userRepo->findByRole('ROLE_CONTROLEUR');
        $rawStats = $operationRepo->getControleurStatsForMonth($month, $year);

        $stats = [];
        foreach ($controleurs as $ctrl) {
            $stats[$ctrl->getId()] = ['user' => $ctrl, 'entries' => 0, 'exits' => 0, 'total' => 0, 'tonnage' => 0, 'cartons' => 0];
        }
        foreach ($rawStats as $row) {
            $id = $row['controleur_id'];
            if (!isset($stats[$id])) continue;
            if ($row['type']->value === 'entry') {
                $stats[$id]['entries'] = (int)$row['total'];
            } else {
                $stats[$id]['exits'] = (int)$row['total'];
            }
            $stats[$id]['total'] += (int)$row['total'];
        }

        // Calculate tonnage and cartons per controleur
        foreach ($controleurs as $ctrl) {
            $allOps = $operationRepo->findByControleurAndDates(
                $ctrl,
                new \DateTime("$year-$month-01"),
                (new \DateTime("$year-$month-01"))->modify('last day of this month')->setTime(23, 59, 59)
            );
            $tonnage = 0;
            $cartons = 0;
            foreach ($allOps as $op) {
                $tonnage += $op->getPoidsTotalTonnes();
                $cartons += $op->getTotalCartons();
            }
            $stats[$ctrl->getId()]['tonnage'] = round($tonnage, 2);
            $stats[$ctrl->getId()]['cartons'] = $cartons;
        }

        usort($stats, fn($a, $b) => $b['total'] - $a['total']);
        $top5 = array_slice($stats, 0, 5);

        $maxOps      = max(1, max(array_column($stats, 'total')   ?: [0]));
        $maxTonnage  = max(1, max(array_column($stats, 'tonnage') ?: [0]));
        $maxCartons  = max(1, max(array_column($stats, 'cartons') ?: [0]));
        $totalOps    = array_sum(array_column($stats, 'total'));
        $totalTonnage = round(array_sum(array_column($stats, 'tonnage')), 2);
        $totalCartons = array_sum(array_column($stats, 'cartons'));
        $avgOps      = count($stats) > 0 ? round($totalOps / count($stats), 1) : 0;

        return $this->render('controleur_dashboard/index.html.twig', [
            'stats'        => $stats,
            'top5'         => $top5,
            'month'        => $month,
            'year'         => $year,
            'maxOps'       => $maxOps,
            'maxTonnage'   => $maxTonnage,
            'maxCartons'   => $maxCartons,
            'totalOps'     => $totalOps,
            'totalTonnage' => $totalTonnage,
            'totalCartons' => $totalCartons,
            'avgOps'       => $avgOps,
        ]);
    }
}
