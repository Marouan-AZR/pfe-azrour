<?php

namespace App\Twig;

use App\Enum\OperationType;
use App\Enum\StockStatus;
use App\Repository\ColdRoomRepository;
use App\Repository\OperationRepository;
use App\Repository\PaletteRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class NotificationsExtension extends AbstractExtension
{
    public function __construct(
        private OperationRepository $operationRepository,
        private PaletteRepository   $paletteRepository,
        private ColdRoomRepository  $coldRoomRepository,
        private Security            $security,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_notifications', [$this, 'getNotifications']),
        ];
    }

    public function getNotifications(): array
    {
        if (!$this->security->getUser()) {
            return ['groups' => [], 'total' => 0];
        }

        $groups = [];
        $total  = 0;

        // ── Ordre : du rôle le plus élevé au plus bas ──────────────────
        // IMPORTANT : vérifier PATRON/DIRECTEUR AVANT CHEF_STOCK,
        // car avec la hiérarchie DIRECTEUR > CHEF_STOCK, isGranted('ROLE_CHEF_STOCK')
        // est vrai pour le Directeur — on doit donc l'intercepter en premier.

        if ($this->security->isGranted('ROLE_PATRON')) {

            // Patron voit les mêmes alertes de management que le Directeur
            [$groups, $total] = $this->buildDirecteurNotifications();

        } elseif ($this->security->isGranted('ROLE_DIRECTEUR')) {

            [$groups, $total] = $this->buildDirecteurNotifications();

        } elseif ($this->security->isGranted('ROLE_CHEF_STOCK')) {

            [$groups, $total] = $this->buildChefStockNotifications();

        } elseif ($this->security->isGranted('ROLE_CONTROLEUR')) {

            [$groups, $total] = $this->buildControleurNotifications();
        }

        return ['groups' => $groups, 'total' => $total];
    }

    // ──────────────────────────────────────────────────────────────────────
    // Notifications DIRECTEUR / PATRON
    // Clients dont le poids restant < 20 % de la capacité totale des chambres
    // (même métrique que la liste clients : poids / capacité)
    // ──────────────────────────────────────────────────────────────────────
    private function buildDirecteurNotifications(): array
    {
        $groups = [];
        $total  = 0;

        $coldRooms        = $this->coldRoomRepository->findBy(['isActive' => true]);
        $totalCapacityKg  = array_reduce(
            $coldRooms,
            fn(float $s, $r) => $s + (float)$r->getMaxCapacityTons() * 1000,
            0.0
        );

        $criticalClients = $this->paletteRepository->findClientsWithLowCapacityPct($totalCapacityKg, 0.20);
        if ($criticalClients) {
            $groups[] = [
                'label'   => 'Stock critique',
                'icon'    => 'bi-exclamation-triangle-fill',
                'color'   => '#ef4444',
                'bg'      => 'rgba(239,68,68,.12)',
                'route'   => 'app_stock_index',
                'subtype' => 'stock',
                'items'   => $criticalClients,
            ];
            $total += \count($criticalClients);
        }

        return [$groups, $total];
    }

    // ──────────────────────────────────────────────────────────────────────
    // Notifications CHEF DE STOCK
    // 1) Entrées en attente de validation
    // 2) Sorties en attente (contrôle ou validation)
    // 3) Opérations rejetées (7 derniers jours)
    // ──────────────────────────────────────────────────────────────────────
    private function buildChefStockNotifications(): array
    {
        $groups = [];
        $total  = 0;

        $entries = $this->operationRepository->findForNotifications(
            OperationType::ENTRY,
            [StockStatus::PENDING]
        );
        if ($entries) {
            $groups[] = [
                'label'   => 'Entrées à valider',
                'icon'    => 'bi-arrow-down-circle-fill',
                'color'   => '#10b981',
                'bg'      => 'rgba(16,185,129,.12)',
                'route'   => 'app_operation_entry_index',
                'subtype' => 'operation',
                'items'   => $entries,
            ];
            $total += count($entries);
        }

        $exits = $this->operationRepository->findForNotifications(
            OperationType::EXIT,
            [StockStatus::EN_ATTENTE_CONTROLE, StockStatus::EN_ATTENTE_VALIDATION]
        );
        if ($exits) {
            $groups[] = [
                'label'   => 'Sorties en attente',
                'icon'    => 'bi-arrow-up-circle-fill',
                'color'   => '#ef4444',
                'bg'      => 'rgba(239,68,68,.12)',
                'route'   => 'app_operation_exit_index',
                'subtype' => 'operation',
                'items'   => $exits,
            ];
            $total += count($exits);
        }

        $rejected = $this->operationRepository->findRejectedOperations();
        if ($rejected) {
            $groups[] = [
                'label'   => 'Opérations rejetées',
                'icon'    => 'bi-x-circle-fill',
                'color'   => '#f97316',
                'bg'      => 'rgba(249,115,22,.12)',
                'route'   => 'app_operation_entry_index',
                'subtype' => 'operation',
                'items'   => $rejected,
            ];
            $total += count($rejected);
        }

        return [$groups, $total];
    }

    // ──────────────────────────────────────────────────────────────────────
    // Notifications CONTRÔLEUR
    // 1) Entrées à contrôler
    // 2) Sorties à contrôler
    // ──────────────────────────────────────────────────────────────────────
    private function buildControleurNotifications(): array
    {
        $groups = [];
        $total  = 0;

        $entries = $this->operationRepository->findForNotifications(
            OperationType::ENTRY,
            [StockStatus::PENDING]
        );
        if ($entries) {
            $groups[] = [
                'label'   => 'Entrées à contrôler',
                'icon'    => 'bi-arrow-down-circle-fill',
                'color'   => '#f59e0b',
                'bg'      => 'rgba(245,158,11,.12)',
                'route'   => 'app_operation_entry_index',
                'subtype' => 'operation',
                'items'   => $entries,
            ];
            $total += count($entries);
        }

        $exits = $this->operationRepository->findForNotifications(
            OperationType::EXIT,
            [StockStatus::EN_ATTENTE_CONTROLE]
        );
        if ($exits) {
            $groups[] = [
                'label'   => 'Sorties à contrôler',
                'icon'    => 'bi-clipboard-check-fill',
                'color'   => '#3b82f6',
                'bg'      => 'rgba(59,130,246,.12)',
                'route'   => 'app_operation_exit_index',
                'subtype' => 'operation',
                'items'   => $exits,
            ];
            $total += count($exits);
        }

        return [$groups, $total];
    }
}
