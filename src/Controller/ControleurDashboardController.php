<?php

namespace App\Controller;

use App\Repository\OperationRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ControleurDashboardController extends AbstractController
{
    #[Route('/controleurs/performances', name: 'app_controleur_dashboard')]
    #[IsGranted('ROLE_CHEF_STOCK')]
    public function index(Request $request, OperationRepository $operationRepo, UserRepository $userRepo): Response
    {
        $month = (int)$request->query->get('month', date('m'));
        $year = (int)$request->query->get('year', date('Y'));

        $controleurs = $userRepo->findByRole('ROLE_CONTROLEUR');
        $rawStats = $operationRepo->getControleurStatsForMonth($month, $year);

        // Build stats per controleur
        $stats = [];
        foreach ($controleurs as $ctrl) {
            $stats[$ctrl->getId()] = ['user' => $ctrl, 'entries' => 0, 'exits' => 0, 'total' => 0, 'tonnage' => 0];
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

        // Calculate tonnage per controleur
        foreach ($controleurs as $ctrl) {
            $ops = $operationRepo->countByMonthAndControleur($ctrl, $month, $year);
            // Get actual operations for tonnage
            $allOps = $operationRepo->findByControleurAndDates(
                $ctrl,
                new \DateTime("$year-$month-01"),
                (new \DateTime("$year-$month-01"))->modify('last day of this month')->setTime(23, 59, 59)
            );
            $tonnage = 0;
            foreach ($allOps as $op) {
                $tonnage += $op->getPoidsTotalTonnes();
            }
            $stats[$ctrl->getId()]['tonnage'] = round($tonnage, 2);
        }

        // Sort by total desc for top 5
        usort($stats, fn($a, $b) => $b['total'] - $a['total']);
        $top5 = array_slice($stats, 0, 5);

        return $this->render('controleur_dashboard/index.html.twig', [
            'stats' => $stats,
            'top5' => $top5,
            'month' => $month,
            'year' => $year,
        ]);
    }
}
