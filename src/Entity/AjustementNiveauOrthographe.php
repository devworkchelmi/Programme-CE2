<?php

namespace App\Entity;

use App\Repository\AjustementNiveauOrthographeRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Journal des changements de niveau côté orthographe — même rôle que AjustementNiveau
 * pour la compréhension (cf. Product Specification §5), et même règle appliquée
 * (AjustementService::calculer(), réutilisé tel quel : il ne dépend d'aucune notion
 * propre à Session, seule tauxReussite() en dépend et n'est pas utilisée ici). Rattaché
 * à l'Enfant directement, faute d'entité "Session" équivalente côté orthographe — chaque
 * exercice est autonome, pas regroupé en lot relu par un adulte.
 */
#[ORM\Entity(repositoryClass: AjustementNiveauOrthographeRepository::class)]
class AjustementNiveauOrthographe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Enfant::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Enfant $enfant;

    #[ORM\Column]
    private int $niveauAvant;

    #[ORM\Column]
    private int $niveauApres;

    #[ORM\Column(length: 50)]
    private string $regleAppliquee;

    #[ORM\Column]
    private \DateTimeImmutable $dateCreation;

    public function __construct(Enfant $enfant, int $niveauAvant, int $niveauApres, string $regleAppliquee)
    {
        $this->enfant = $enfant;
        $this->niveauAvant = $niveauAvant;
        $this->niveauApres = $niveauApres;
        $this->regleAppliquee = $regleAppliquee;
        $this->dateCreation = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEnfant(): Enfant
    {
        return $this->enfant;
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
