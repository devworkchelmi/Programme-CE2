<?php

namespace App\Entity;

use App\Repository\AjustementNiveauRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Journal de chaque changement de niveau — reste visible dans le suivi adulte
 * (cf. Product Specification §5). Une ligne par session, produite par AjustementService.
 */
#[ORM\Entity(repositoryClass: AjustementNiveauRepository::class)]
class AjustementNiveau
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Session::class, inversedBy: 'ajustementNiveau')]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private Session $session;

    #[ORM\Column]
    private int $niveauAvant;

    #[ORM\Column]
    private int $niveauApres;

    #[ORM\Column(length: 50)]
    private string $regleAppliquee;

    #[ORM\Column]
    private \DateTimeImmutable $dateCreation;

    public function __construct(int $niveauAvant, int $niveauApres, string $regleAppliquee)
    {
        $this->niveauAvant = $niveauAvant;
        $this->niveauApres = $niveauApres;
        $this->regleAppliquee = $regleAppliquee;
        $this->dateCreation = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSession(): Session
    {
        return $this->session;
    }

    public function setSession(Session $session): static
    {
        $this->session = $session;

        return $this;
    }

    public function getNiveauAvant(): int
    {
        return $this->niveauAvant;
    }

    public function getNiveauApres(): int
    {
        return $this->niveauApres;
    }

    public function getRegleAppliquee(): string
    {
        return $this->regleAppliquee;
    }

    public function getDateCreation(): \DateTimeImmutable
    {
        return $this->dateCreation;
    }
}
