<?php

namespace App\Controller;

use App\Repository\AuditLogRepository;
use App\Repository\UserRepository;
use App\Service\AuditService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/historique')]
#[IsGranted('ROLE_CHEF_STOCK')]
class HistoryController extends AbstractController
{
    public function __construct(
        private AuditService $auditService,
        private AuditLogRepository $repository,
        private UserRepository $userRepository
    ) {}

    #[Route('', name: 'app_history_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $userId = $request->query->get('user_id');
        $user = $userId ? $this->userRepository->find($userId) : null;
        
        $filters = [
            'action' => $request->query->get('action'),
            'entityType' => $request->query->get('entity_type'),
            'user' => $user,
            'dateFrom' => $request->query->get('date_from'),
            'dateTo' => $request->query->get('date_to'),
        ];

        $logs = $this->auditService->getHistory($filters);

        // Get unique entity types for filter dropdown
        $entityTypes = $this->repository->getDistinctEntityTypes();
        
        // Get all users for filter dropdown
        $users = $this->userRepository->findAll();

        return $this->render('history/index.html.twig', [
            'logs' => $logs,
            'filters' => $filters,
            'entityTypes' => $entityTypes,
            'users' => $users,
            'selectedUserId' => $userId,
        ]);
    }

    #[Route('/export', name: 'app_history_export', methods: ['GET'])]
    public function export(Request $request): StreamedResponse
    {
        $userId = $request->query->get('user_id');
        $user = $userId ? $this->userRepository->find($userId) : null;
        
        $filters = [
            'action' => $request->query->get('action'),
            'entityType' => $request->query->get('entity_type'),
            'user' => $user,
            'dateFrom' => $request->query->get('date_from'),
            'dateTo' => $request->query->get('date_to'),
        ];

        $csv = $this->auditService->exportHistory($filters, 'csv');

        $response = new StreamedResponse(function () use ($csv) {
            echo $csv;
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="historique_' . date('Y-m-d') . '.csv"');

        return $response;
    }
}
