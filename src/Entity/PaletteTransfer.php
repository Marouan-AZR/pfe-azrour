<?php

namespace App\Entity;

use App\Repository\PaletteTransferRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaletteTransferRepository::class)]
#[ORM\Table(name: 'palette_transfers')]
#[ORM\HasLifecycleCallbacks]
class PaletteTransfer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Palette::class, inversedBy: 'transfers')]
    #[ORM\JoinColumn(nullable: false)]
    private Palette $palette;

    #[ORM\ManyToOne(targetEntity: ColdRoom::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ColdRoom $fromColdRoom;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $fromRayon = null;

    #[ORM\ManyToOne(targetEntity: ColdRoom::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ColdRoom $toColdRoom;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $toRayon = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $transferredBy;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $transferredAt;

    public function getId(): ?int { return $this->id; }

    public function getPalette(): Palette { return $this->palette; }
    public function setPalette(Palette $palette): static { $this->palette = $palette; return $this; }

    public function getFromColdRoom(): ColdRoom { return $this->fromColdRoom; }
    public function setFromColdRoom(ColdRoom $fromColdRoom): static { $this->fromColdRoom = $fromColdRoom; return $this; }

    public function getFromRayon(): ?string { return $this->fromRayon; }
    public function setFromRayon(?string $fromRayon): static { $this->fromRayon = $fromRayon; return $this; }

    public function getToColdRoom(): ColdRoom { return $this->toColdRoom; }
    public function setToColdRoom(ColdRoom $toColdRoom): static { $this->toColdRoom = $toColdRoom; return $this; }

    public function getToRayon(): ?string { return $this->toRayon; }
    public function setToRayon(?string $toRayon): static { $this->toRayon = $toRayon; return $this; }

    public function getTransferredBy(): User { return $this->transferredBy; }
    public function setTransferredBy(User $transferredBy): static { $this->transferredBy = $transferredBy; return $this; }

    public function getTransferredAt(): \DateTimeInterface { return $this->transferredAt; }

    #[ORM\PrePersist]
    public function setTransferredAtValue(): void
    {
        $this->transferredAt = new \DateTime();
    }
}
