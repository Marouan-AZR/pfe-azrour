<?php

namespace App\EventListener;

use App\Entity\AuditLog;
use App\Entity\User;
use App\Service\AuditService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
class AuditListener
{
    private array $oldValues = [];

    public function __construct(
        private AuditService $auditService,
        private Security $security
    ) {}

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        
        // Don't audit AuditLog itself
        if ($entity instanceof AuditLog) {
            return;
        }

        // Store old values for later
        $this->oldValues[spl_object_id($entity)] = $args->getEntityChangeSet();
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        
        if ($entity instanceof AuditLog) {
            return;
        }

        $user = $this->getUser();
        if (!$user) {
            return;
        }

        $this->auditService->log('create', $entity, $user, null, $this->getEntityData($entity));
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        
        if ($entity instanceof AuditLog) {
            return;
        }

        $user = $this->getUser();
        if (!$user) {
            return;
        }

        $objectId = spl_object_id($entity);
        $changeSet = $this->oldValues[$objectId] ?? [];
        unset($this->oldValues[$objectId]);

        if (empty($changeSet)) {
            return;
        }

        $oldValues = [];
        $newValues = [];
        foreach ($changeSet as $field => [$old, $new]) {
            $oldValues[$field] = $this->normalizeValue($old);
            $newValues[$field] = $this->normalizeValue($new);
        }

        $this->auditService->log('update', $entity, $user, $oldValues, $newValues);
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();
        
        if ($entity instanceof AuditLog) {
            return;
        }

        $user = $this->getUser();
        if (!$user) {
            return;
        }

        $this->auditService->log('delete', $entity, $user, $this->getEntityData($entity), null);
    }

    private function getUser(): ?User
    {
        $user = $this->security->getUser();
        return $user instanceof User ? $user : null;
    }

    private function getEntityData(object $entity): array
    {
        $data = [];
        $reflection = new \ReflectionClass($entity);
        
        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($entity);
            $data[$property->getName()] = $this->normalizeValue($value);
        }
        
        return $data;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if (is_object($value)) {
            if (method_exists($value, 'getId')) {
                return get_class($value) . '#' . $value->getId();
            }
            return get_class($value);
        }
        if (is_array($value)) {
            return json_encode($value);
        }
        return $value;
    }
}
