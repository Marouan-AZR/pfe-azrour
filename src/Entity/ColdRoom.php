<?php

namespace App\Entity;

use App\Repository\ColdRoomRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ColdRoomRepository::class)]
#[ORM\Table(name: 'cold_rooms')]
#[ORM\HasLifecycleCallbacks]
class ColdRoom
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private string $name;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\Positive]
    private string $maxCapacityTons;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $targetTemperature;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\OneToMany(mappedBy: 'coldRoom', targetEntity: StockItem::class)]
    private Collection $stockItems;

    public function __construct()
    {
        $this->stockItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getMaxCapacityTons(): string
    {
        return $this->maxCapacityTons;
    }

    public function setMaxCapacityTons(string $maxCapacityTons): static
    {
        $this->maxCapacityTons = $maxCapacityTons;
        return $this;
    }

    public function getTargetTemperature(): string
    {
        return $this->targetTemperature;
    }

    public function setTargetTemperature(string $targetTemperature): static
    {
        $this->targetTemperature = $targetTemperature;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getStockItems(): Collection
    {
        return $this->stockItems;
    }

    public function getUsedCapacity(): float
    {
        return $this->stockItems->reduce(
            fn(float $total, StockItem $item) => $total + (float)$item->getQuantityTons(),
            0.0
        );
    }

    public function getAvailableCapacity(): float
    {
        return (float)$this->maxCapacityTons - $this->getUsedCapacity();
    }

    public function getOccupancyRate(): float
    {
        $maxCapacity = (float)$this->maxCapacityTons;
        return $maxCapacity > 0 ? ($this->getUsedCapacity() / $maxCapacity) * 100 : 0;
    }

    public function hasAvailableCapacity(float $tonnage): bool
    {
        return ($this->getUsedCapacity() + $tonnage) <= (float)$this->maxCapacityTons;
    }

    public function isNearCapacity(): bool
    {
        return $this->getOccupancyRate() >= 90;
    }

    public function canBeDeactivated(): bool
    {
        return $this->stockItems->isEmpty();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
