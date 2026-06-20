<?php

namespace App\Controller;

use App\Service\AlertService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/alertes')]
#[IsGranted('ROLE_CONTROLEUR')]
class AlertController extends AbstractController
{
    #[Route('', name: 'app_alerts')]
    public function index(AlertService $alertService): Response
    {
        if ($this->isGranted('ROLE_PATRON')) {
            throw $this->createAccessDeniedException();
        }

        $alerts = $alertService->getAlerts();
        $stats  = $alertService->getStats($alerts);

        return $this->render('alerts/index.html.twig', [
            'alerts'        => $alerts,
            'stats'         => $stats,
            'lastRefreshed' => new \DateTime(),
        ]);
    }

    #[Route('/api', name: 'app_alerts_api', methods: ['GET'])]
    public function api(AlertService $alertService): JsonResponse
    {
        $alerts = $alertService->getAlerts();

        return $this->json([
            'alerts' => $alerts,
            'count'  => $alertService->getAlertCount(),
            'stats'  => $alertService->getStats($alerts),
        ]);
    }
}
