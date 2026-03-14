<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\ColdRoom;
use App\Entity\StockEntry;
use App\Entity\StockExit;
use App\Entity\StockItem;
use App\Entity\User;
use App\Enum\StockStatus;
use App\Repository\StockEntryRepository;
use App\Repository\StockExitRepository;
use App\Repository\StockItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class StockService
{
    public function __construct(
        private EntityManagerInterface $em,
        private StockEntryRepository $entryRepo,
        private StockExitRepository $exitRepo,
        private StockItemRepository $itemRepo,
        private AuditService $auditService
    ) {}

    public function createEntry(Client $client, ColdRoom $coldRoom, string $productName, string $quantityTons, User $createdBy): StockEntry
    {
        if (!$client->isActive()) {
            throw new BadRequestHttpException('Ce client est désactivé');
        }

        if (!$coldRoom->isActive()) {
            throw new BadRequestHttpException('Cette chambre froide est désactivée');
        }

        $entry = new StockEntry();
        $entry->setClient($client);
        $entry->setColdRoom($coldRoom);
        $entry->setProductName($productName);
        $entry->setQuantityTons($quantityTons);
        $entry->setCreatedBy($createdBy);
        $entry->setStatus(StockStatus::PENDING);

        $this->em->persist($entry);
        $this->em->flush();

        $this->auditService->log('create', $entry, $createdBy);

        return $entry;
    }

    public function validateEntry(StockEntry $entry, User $validatedBy): void
    {
        if (!$entry->isPending()) {
            throw new BadRequestHttpException('Cette entrée ne peut pas être validée');
        }

        $coldRoom = $entry->getColdRoom();
        $quantity = (float) $entry->getQuantityTons();

        if (!$coldRoom->hasAvailableCapacity($quantity)) {
            throw new BadRequestHttpException(
                sprintf('Capacité insuffisante. Disponible: %.2f tonnes', $coldRoom->getAvailableCapacity())
            );
        }

        $entry->setStatus(StockStatus::VALIDATED);
        $entry->setValidatedBy($validatedBy);
        $entry->setValidatedAt(new \DateTime());

        $stockItem = new StockItem();
        $stockItem->setClient($entry->getClient());
        $stockItem->setColdRoom($coldRoom);
        $stockItem->setStockEntry($entry);
        $stockItem->setProductName($entry->getProductName());
        $stockItem->setQuantityTons($entry->getQuantityTons());
        $stockItem->setEntryDate(new \DateTime());

        $entry->setStockItem($stockItem);

        $this->em->persist($stockItem);
        $this->em->flush();

        $this->auditService->log('validate', $entry, $validatedBy);
    }

    public function rejectEntry(StockEntry $entry, User $rejectedBy, string $reason): void
    {
        if (!$entry->isPending()) {
            throw new BadRequestHttpException('Cette entrée ne peut pas être rejetée');
        }

        $entry->setStatus(StockStatus::REJECTED);
        $entry->setValidatedBy($rejectedBy);
        $entry->setValidatedAt(new \DateTime());
        $entry->setRejectionReason($reason);

        $this->em->flush();

        $this->auditService->log('reject', $entry, $rejectedBy, null, ['reason' => $reason]);
    }

    public function createExit(StockItem $stockItem, string $quantityTons, User $createdBy): StockExit
    {
        $quantity = (float) $quantityTons;

        if (!$stockItem->hasAvailableQuantity($quantity)) {
            throw new BadRequestHttpException(
                sprintf('Stock insuffisant. Disponible: %.3f tonnes', $stockItem->getRemainingQuantity())
            );
        }

        $exit = new StockExit();
        $exit->setStockItem($stockItem);
        $exit->setQuantityTons($quantityTons);
        $exit->setCreatedBy($createdBy);
        $exit->setStatus(StockStatus::PENDING);

        $this->em->persist($exit);
        $this->em->flush();

        $this->auditService->log('create', $exit, $createdBy);

        return $exit;
    }

    public function validateExit(StockExit $exit, User $validatedBy): void
    {
        if (!$exit->isPending()) {
            throw new BadRequestHttpException('Cette sortie ne peut pas être validée');
        }

        $stockItem = $exit->getStockItem();
        $quantity = (float) $exit->getQuantityTons();

        if (!$stockItem->hasAvailableQuantity($quantity)) {
            throw new BadRequestHttpException('Stock insuffisant pour cette sortie');
        }

        $exit->setStatus(StockStatus::VALIDATED);
        $exit->setValidatedBy($validatedBy);
        $exit->setValidatedAt(new \DateTime());
        $exit->setStorageDays($exit->calculateStorageDays());

        $this->em->flush();

        $this->auditService->log('validate', $exit, $validatedBy);
    }

    public function rejectExit(StockExit $exit, User $rejectedBy, string $reason): void
    {
        if (!$exit->isPending()) {
            throw new BadRequestHttpException('Cette sortie ne peut pas être rejetée');
        }

        $exit->setStatus(StockStatus::REJECTED);
        $exit->setValidatedBy($rejectedBy);
        $exit->setValidatedAt(new \DateTime());
        $exit->setRejectionReason($reason);

        $this->em->flush();

        $this->auditService->log('reject', $exit, $rejectedBy, null, ['reason' => $reason]);
    }

    public function getStockByClient(Client $client): array
    {
        return $this->itemRepo->findByClient($client);
    }

    public function getStockByColdRoom(ColdRoom $coldRoom): array
    {
        return $this->itemRepo->findByColdRoom($coldRoom);
    }

    public function getPendingEntries(): array
    {
        return $this->entryRepo->findPending();
    }

    public function getPendingExits(): array
    {
        return $this->exitRepo->findPending();
    }
}
