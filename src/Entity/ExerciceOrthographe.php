<?php

namespace App\Entity;

use App\Entity\Enum\Correction;
use App\Entity\Enum\TypeExerciceOrthographe;
use App\Repository\ExerciceOrthographeRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un exercice de la partie « Orthographe » (CE2). Contrairement à TexteGenere, le
 * contenu vient d'une banque de règles/mots fixe (BanqueExercicesService) et non d'un
 * appel LLM : pas de statut de relecture, l'enfant y accède directement. Un seul essai
 * par exercice, comme pour les questions de compréhension (cf. OrthographeController).
 */
#[ORM\Entity(repositoryClass: ExerciceOrthographeRepository::class)]
class ExerciceOrthographe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Enfant::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Enfant $enfant;

    #[ORM\Column(enumType: TypeExerciceOrthographe::class)]
    private TypeExerciceOrthographe $type;

    #[ORM\Column(length: 100)]
    private string $regle;

    #[ORM\Column]
    private int $niveauVise;

    /**
     * Structure propre à chaque type (cf. BanqueExercicesService::construireContenu()) :
     * - texte_a_trous : {avant, apres, reponse}
     * - choix_mot     : {avant, apres, propositions, reponse}
     * - correction    : {phrase_incorrecte, phrase_correcte}
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $contenu = [];

    /** @var list<string>|null un seul élément : le mot tapé/choisi, ou la phrase entière retapée pour "correction" */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $reponseDonnee = null;

    #[ORM\Column(enumType: Correction::class, nullable: true)]
    private ?Correction $correcte = null;

    #[ORM\Column]
    private \DateTimeImmutable $dateCreation;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateReponse = null;

    /**
     * @param array<string, mixed> $contenu
     */
    public function __construct(Enfant $enfant, TypeExerciceOrthographe $type, string $regle, int $niveauVise, array $contenu)
    {
        $this->enfant = $enfant;
        $this->type = $type;
        $this->regle = $regle;
        $this->niveauVise = $niveauVise;
        $this->contenu = $contenu;
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

    public function getType(): TypeExerciceOrthographe
    {
        return $this->type;
    }

    public function getRegle(): string
    {
        return $this->regle;
    }

    public function getNiveauVise(): int
    {
        return $this->niveauVise;
    }

    /** @return array<string, mixed> */
    public function getContenu(): array
    {
        return $this->contenu;
    }

    /** @return list<string>|null */
    public function getReponseDonnee(): ?array
    {
        return $this->reponseDonnee;
    }

    public function getCorrecte(): ?Correction
    {
        return $this->correcte;
    }

    public function estRepondu(): bool
    {
        return null !== $this->dateReponse;
    }

    public function getDateCreation(): \DateTimeImmutable
    {
        return $this->dateCreation;
    }

    public function getDateReponse(): ?\DateTimeImmutable
    {
        return $this->dateReponse;
    }

    /**
     * Enregistre la réponse de l'enfant — un seul essai (cf. OrthographeController::repondre()),
     * jamais rappelée une seconde fois pour le même exercice.
     *
     * @param list<string> $reponseDonnee
     */
    public function enregistrerReponse(array $reponseDonnee, Correction $correcte): static
    {
        $this->reponseDonnee = $reponseDonnee;
        $this->correcte = $correcte;
        $this->dateReponse = new \DateTimeImmutable();

        return $this;
    }
}
