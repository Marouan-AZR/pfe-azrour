<?php

namespace App\Entity;

use App\Enum\PaletteStatus;
use App\Repository\PaletteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaletteRepository::class)]
#[ORM\Table(name: 'palettes')]
#[ORM\HasLifecycleCallbacks]
class Palette
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $codePalette;

    #[ORM\ManyToOne(targetEntity: Operation::class, inversedBy: 'palettes')]
    #[ORM\JoinColumn(nullable: false)]
    private Operation $operation;

    #[ORM\Column(length: 255)]
    private string $espece;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $famille = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $qualite = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $moule = null;

    #[ORM\Column(type: 'integer')]
    private int $nombreCartons = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 3)]
    private string $poidsCarton = '0';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 3)]
    private string $poidsTotal = '0';

    #[ORM\ManyToOne(targetEntity: ColdRoom::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?ColdRoom $coldRoom = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $rayon = null;

    #[ORM\Column(type: 'string', enumType: PaletteStatus::class)]
    private PaletteStatus $status = PaletteStatus::EN_ATTENTE;

    #[ORM\Column(type: 'integer')]
    private int $cartonsRestants = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 3)]
    private string $poidsRestant = '0';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observation = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $motifRejet = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    /** @var Collection<int, PaletteTransfer> */
    #[ORM\OneToMany(mappedBy: 'palette', targetEntity: PaletteTransfer::class, cascade: ['persist'])]
    private Collection $transfers;

    public function __construct()
    {
        $this->transfers = new ArrayCollection();
    }

    public static function generateCode(string $operationCode, int $index): string
    {
        return $operationCode . '-' . str_pad((string)$index, 2, '0', STR_PAD_LEFT);
    }

    public function getId(): ?int { return $this->id; }

    public function getCodePalette(): string { return $this->codePalette; }
    public function setCodePalette(string $codePalette): static { $this->codePalette = $codePalette; return $this; }

    public function getOperation(): Operation { return $this->operation; }
    public function setOperation(Operation $operation): static { $this->operation = $operation; return $this; }

    public function getEspece(): string { return $this->espece; }
    public function setEspece(string $espece): static { $this->espece = $espece; return $this; }

    public function getFamille(): ?string { return $this->famille; }
    public function setFamille(?string $famille): static { $this->famille = $famille; return $this; }

    public function getQualite(): ?string { return $this->qualite; }
    public function setQualite(?string $qualite): static { $this->qualite = $qualite; return $this; }

    public function getMoule(): ?string { return $this->moule; }
    public function setMoule(?string $moule): static { $this->moule = $moule; return $this; }

    public function getNombreCartons(): int { return $this->nombreCartons; }
    public function setNombreCartons(int $nombreCartons): static
    {
        $this->nombreCartons = $nombreCartons;
        $this->cartonsRestants = $nombreCartons;
        return $this;
    }

    public function getPoidsCarton(): string { return $this->poidsCarton; }
    public function setPoidsCarton(string $poidsCarton): static
    {
        $this->poidsCarton = $poidsCarton;
        $this->poidsTotal = (string)($this->nombreCartons * (float)$poidsCarton);
        $this->poidsRestant = $this->poidsTotal;
        return $this;
    }

    public function getPoidsTotal(): string { return $this->poidsTotal; }
    public function setPoidsTotal(string $poidsTotal): static { $this->poidsTotal = $poidsTotal; return $this; }

    public function getColdRoom(): ?ColdRoom { return $this->coldRoom; }
    public function setColdRoom(?ColdRoom $coldRoom): static { $this->coldRoom = $coldRoom; return $this; }

    public function getRayon(): ?string { return $this->rayon; }
    public function setRayon(?string $rayon): static { $this->rayon = $rayon; return $this; }

    public function getStatus(): PaletteStatus { return $this->status; }
    public function setStatus(PaletteStatus $status): static { $this->status = $status; return $this; }

    public function getCartonsRestants(): int { return $this->cartonsRestants; }
    public function setCartonsRestants(int $cartonsRestants): static { $this->cartonsRestants = $cartonsRestants; return $this; }

    public function getPoidsRestant(): string { return $this->poidsRestant; }
    public function setPoidsRestant(string $poidsRestant): static { $this->poidsRestant = $poidsRestant; return $this; }

    public function getObservation(): ?string { return $this->observation; }
    public function setObservation(?string $observation): static { $this->observation = $observation; return $this; }

    public function getMotifRejet(): ?string { return $this->motifRejet; }
    public function setMotifRejet(?string $motifRejet): static { $this->motifRejet = $motifRejet; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }

    /** @return Collection<int, PaletteTransfer> */
    public function getTransfers(): Collection { return $this->transfers; }

    // Helper methods
    public function getClient(): Client { return $this->operation->getClient(); }

    public function isStockAvailable(): bool
    {
        return $this->cartonsRestants > 0 && $this->status !== PaletteStatus::SORTIE_COMPLETE;
    }

    public function sortir(int $cartons): void
    {
        $this->cartonsRestants -= $cartons;
        $this->poidsRestant = (string)($this->cartonsRestants * (float)$this->poidsCarton);
        if ($this->cartonsRestants <= 0) {
            $this->cartonsRestants = 0;
            $this->poidsRestant = '0';
            $this->status = PaletteStatus::SORTIE_COMPLETE;
        } else {
            $this->status = PaletteStatus::SORTIE_PARTIELLE;
        }
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
