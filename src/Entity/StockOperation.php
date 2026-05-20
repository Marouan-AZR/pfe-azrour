<?php

namespace App\Entity;

use App\Enum\StockStatus;
use App\Repository\StockOperationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockOperationRepository::class)]
#[ORM\Table(name: 'stock_operations')]
#[ORM\HasLifecycleCallbacks]
class StockOperation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $number = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Client $client;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $operationDate;

    #[ORM\Column(type: 'string', enumType: StockStatus::class)]
    private StockStatus $status = StockStatus::PENDING;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $createdBy;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\OneToMany(mappedBy: 'operation', targetEntity: StockEntry::class, cascade: ['persist', 'remove'])]
    private Collection $stockEntries;

    public function __construct()
    {
        $this->stockEntries = new ArrayCollection();
        $this->operationDate = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getNumber(): ?string { return $this->number; }
    public function setNumber(string $number): self { $this->number = $number; return $this; }

    public function getClient(): Client { return $this->client; }
    public function setClient(Client $client): self { $this->client = $client; return $this; }

    public function getOperationDate(): \DateTimeInterface { return $this->operationDate; }
    public function setOperationDate(\DateTimeInterface $date): self { $this->operationDate = $date; return $this; }

    public function getStatus(): StockStatus { return $this->status; }
    public function setStatus(StockStatus $status): self { $this->status = $status; return $this; }

    public function getCreatedBy(): User { return $this->createdBy; }
    public function setCreatedBy(User $user): self { $this->createdBy = $user; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }

    /** @return Collection<int, StockEntry> */
    public function getStockEntries(): Collection { return $this->stockEntries; }

    public function addStockEntry(StockEntry $entry): self
    {
        if (!$this->stockEntries->contains($entry)) {
            $this->stockEntries->add($entry);
            $entry->setOperation($this);
        }
        return $this;
    }

    public function getTotalPalettes(): int { return $this->stockEntries->count(); }

    public function getTotalCartons(): int
    {
        return array_reduce($this->stockEntries->toArray(), fn($sum, $e) => $sum + $e->getNombreCartons(), 0);
    }

    public function getTotalWeight(): float
    {
        return array_reduce($this->stockEntries->toArray(), fn($sum, $e) => $sum + (float)$e->getQuantityTons(), 0.0);
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void { $this->createdAt = new \DateTime(); }
}
