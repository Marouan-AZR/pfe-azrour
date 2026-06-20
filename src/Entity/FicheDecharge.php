<?php

namespace App\Entity;

use App\Enum\FicheStatus;
use App\Repository\FicheDechargeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FicheDechargeRepository::class)]
#[ORM\Table(name: 'fiches_decharge')]
#[ORM\HasLifecycleCallbacks]
class FicheDecharge
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $numero;

    #[ORM\OneToOne(inversedBy: 'ficheDecharge', targetEntity: Operation::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Operation $operation;

    #[ORM\Column(type: 'string', enumType: FicheStatus::class)]
    private FicheStatus $status = FicheStatus::EN_COURS_CONTROLE;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $controleur;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $validatedBy = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $validatedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observation = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaireRejet = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $temperature = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $matricule = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $numeroConteneur = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $numeroAgrement = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $carliste = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $heureDebutChargement = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $heureFinChargement = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    public static function generateNumero(): string
    {
        return str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT) . '/' . date('y');
    }

    public function getId(): ?int { return $this->id; }

    public function getNumero(): string { return $this->numero; }
    public function setNumero(string $numero): static { $this->numero = $numero; return $this; }

    public function getOperation(): Operation { return $this->operation; }
    public function setOperation(Operation $operation): static { $this->operation = $operation; return $this; }

    public function getStatus(): FicheStatus { return $this->status; }
    public function setStatus(FicheStatus $status): static { $this->status = $status; return $this; }

    public function getControleur(): User { return $this->controleur; }
    public function setControleur(User $controleur): static { $this->controleur = $controleur; return $this; }

    public function getValidatedBy(): ?User { return $this->validatedBy; }
    public function setValidatedBy(?User $validatedBy): static { $this->validatedBy = $validatedBy; return $this; }

    public function getValidatedAt(): ?\DateTimeInterface { return $this->validatedAt; }
    public function setValidatedAt(?\DateTimeInterface $validatedAt): static { $this->validatedAt = $validatedAt; return $this; }

    public function getObservation(): ?string { return $this->observation; }
    public function setObservation(?string $observation): static { $this->observation = $observation; return $this; }

    public function getCommentaireRejet(): ?string { return $this->commentaireRejet; }
    public function setCommentaireRejet(?string $commentaireRejet): static { $this->commentaireRejet = $commentaireRejet; return $this; }

    public function getTemperature(): ?string { return $this->temperature; }
    public function setTemperature(?string $temperature): static { $this->temperature = $temperature; return $this; }

    public function getMatricule(): ?string { return $this->matricule; }
    public function setMatricule(?string $matricule): static { $this->matricule = $matricule; return $this; }

    public function getNumeroConteneur(): ?string { return $this->numeroConteneur; }
    public function setNumeroConteneur(?string $numeroConteneur): static { $this->numeroConteneur = $numeroConteneur; return $this; }

    public function getNumeroAgrement(): ?string { return $this->numeroAgrement; }
    public function setNumeroAgrement(?string $numeroAgrement): static { $this->numeroAgrement = $numeroAgrement; return $this; }

    public function getCarliste(): ?string { return $this->carliste; }
    public function setCarliste(?string $carliste): static { $this->carliste = $carliste; return $this; }

    public function getHeureDebutChargement(): ?string { return $this->heureDebutChargement; }
    public function setHeureDebutChargement(?string $heureDebutChargement): static { $this->heureDebutChargement = $heureDebutChargement; return $this; }

    public function getHeureFinChargement(): ?string { return $this->heureFinChargement; }
    public function setHeureFinChargement(?string $heureFinChargement): static { $this->heureFinChargement = $heureFinChargement; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }

    // Computed from operation palettes
    public function getTotalCartons(): int { return $this->operation->getTotalCartons(); }
    public function getPoidsTotal(): float { return $this->operation->getPoidsTotal(); }
    public function getClient(): Client { return $this->operation->getClient(); }

    public function isEnCoursControle(): bool { return $this->status === FicheStatus::EN_COURS_CONTROLE; }
    public function isEnAttenteValidation(): bool { return $this->status === FicheStatus::EN_ATTENTE_VALIDATION; }
    public function isValidee(): bool { return $this->status === FicheStatus::VALIDEE; }
    public function isRejetee(): bool { return $this->status === FicheStatus::REJETEE; }

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
