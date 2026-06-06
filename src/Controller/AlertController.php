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
        return $this->render('alerts/index.html.twig', [
            'alerts' => $alertService->getAlerts(),
        ]);
    }

    #[Route('/api', name: 'app_alerts_api', methods: ['GET'])]
    public function api(AlertService $alertService): JsonResponse
    {
        return $this->json([
            'alerts' => $alertService->getAlerts(),
            'count' => $alertService->getAlertCount(),
        ]);
    }
}
