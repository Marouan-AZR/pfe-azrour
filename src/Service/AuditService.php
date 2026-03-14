<?php

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\User;
use App\Repository\AuditLogRepository;
use Doctrine\ORM\EntityManagerInterface;

class AuditService
{
    public function __construct(
        private EntityManagerInterface $em,
        private AuditLogRepository $auditRepo
    ) {}

    public function log(string $action, object $entity, User $user, ?array $oldValues = null, ?array $newValues = null): void
    {
        $log = new AuditLog();
        $log->setAction($action);
        $log->setEntityType($this->getEntityType($entity));
        $log->setEntityId($entity->getId() ?? 0);
        $log->setUser($user);
        $log->setOldValues($oldValues);
        $log->setNewValues($newValues);

        $this->em->persist($log);
        $this->em->flush();
    }

    private function getEntityType(object $entity): string
    {
        $className = get_class($entity);
        $parts = explode('\\', $className);
        return end($parts);
    }

    public function getHistory(array $filters = []): array
    {
        $dateFrom = null;
        $dateTo = null;
        
        if (!empty($filters['dateFrom'])) {
            $dateFrom = new \DateTime($filters['dateFrom']);
        }
        if (!empty($filters['dateTo'])) {
            $dateTo = new \DateTime($filters['dateTo'] . ' 23:59:59');
        }
        
        return $this->auditRepo->findByFilters(
            $filters['action'] ?? null,
            $filters['entityType'] ?? null,
            $filters['user'] ?? null,
            $dateFrom,
            $dateTo
        );
    }

    public function getRecentHistory(int $limit = 50): array
    {
        return $this->auditRepo->findRecent($limit);
    }

    public function exportHistory(array $filters, string $format = 'csv'): string
    {
        $logs = $this->getHistory($filters);
        
        if ($format === 'csv') {
            return $this->exportToCsv($logs);
        }
        
        return '';
    }

    public function exportToCsv(array $logs): string
    {
        $csv = "Date,Action,Type,ID,Utilisateur\n";
        foreach ($logs as $log) {
            $csv .= sprintf(
                "%s,%s,%s,%d,%s\n",
                $log->getCreatedAt()->format('Y-m-d H:i:s'),
                $log->getAction(),
                $log->getEntityType(),
                $log->getEntityId(),
                $log->getUser()->getFullName()
            );
        }
        return $csv;
    }
}
