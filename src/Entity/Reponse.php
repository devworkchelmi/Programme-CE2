<?php

namespace App\Entity;

use App\Entity\Enum\Correction;
use App\Entity\Enum\TypeErreur;
use App\Repository\ReponseRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * typeErreurPropose vient de l'Appel 2 (analyse LLM) et reste une PROPOSITION tant que
 * typeErreurConfirme n'a pas été renseigné par l'adulte (cf. Validation/Recherche §4 —
 * la distinction inattention/incompréhension n'est pas fiable à 100 % sur un seul signal).
 */
#[ORM\Entity(repositoryClass: ReponseRepository::class)]
class Reponse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Question::class, inversedBy: 'reponse')]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private Question $question;

    #[ORM\Column(type: 'text')]
    private string $contenuEnfant;

    #[ORM\Column(enumType: Correction::class, nullable: true)]
    private ?Correction $correcte = null;

    #[ORM\Column(enumType: TypeErreur::class, nullable: true)]
    private ?TypeErreur $typeErreurPropose = null;

    #[ORM\Column(enumType: TypeErreur::class, nullable: true)]
    private ?TypeErreur $typeErreurConfirme = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaireLlm = null;

    /**
     * Fautes d'orthographe relevées dans une réponse rédigée, sous la forme
     * [['mot_ecrit' => 'solei', 'correction' => 'soleil'], ...].
     *
     * Signal volontairement séparé de $correcte : l'orthographe n'entre jamais dans
     * l'évaluation de la compréhension (cf. Product Specification §2.3 — le focus reste
     * la compréhension, pas la note). Toujours vide pour les QCM.
     *
     * @var list<array{mot_ecrit: string, correction: string}>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $correctionsOrthographe = null;

    #[ORM\OneToOne(targetEntity: Feedback::class, mappedBy: 'reponse', cascade: ['persist', 'remove'])]
    private ?Feedback $feedback = null;

    public function __construct(string $contenuEnfant)
    {
        $this->contenuEnfant = $contenuEnfant;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuestion(): Question
    {
        return $this->question;
    }

    public function setQuestion(Question $question): static
    {
        $this->question = $question;

        return $this;
    }

    public function getContenuEnfant(): string
    {
        return $this->contenuEnfant;
    }

    public function getCorrecte(): ?Correction
    {
        return $this->correcte;
    }

    /**
     * Enregistre le résultat de l'Appel 2 (analyse LLM) — cf. Product Specification §3.
     */
    public function enregistrerAnalyse(Correction $correcte, ?TypeErreur $typeErreurPropose, ?string $commentaireLlm): static
    {
        $this->correcte = $correcte;
        $this->typeErreurPropose = $typeErreurPropose;
        $this->commentaireLlm = $commentaireLlm;

        return $this;
    }

    public function getTypeErreurPropose(): ?TypeErreur
    {
        return $this->typeErreurPropose;
    }

    public function getTypeErreurConfirme(): ?TypeErreur
    {
        return $this->typeErreurConfirme;
    }

    /**
     * Geste de l'adulte sur l'écran de suivi : confirme ou corrige la proposition du LLM
     * (cf. Product Specification §2.5).
     */
    public function confirmerTypeErreur(?TypeErreur $typeErreurConfirme): static
    {
        $this->typeErreurConfirme = $typeErreurConfirme;

        return $this;
    }

    public function getCommentaireLlm(): ?string
    {
        return $this->commentaireLlm;
    }

    /**
     * @return list<array{mot_ecrit: string, correction: string}>
     */
    public function getCorrectionsOrthographe(): array
    {
        return $this->correctionsOrthographe ?? [];
    }

    /**
     * @param list<array{mot_ecrit: string, correction: string}> $corrections
     */
    public function enregistrerCorrectionsOrthographe(array $corrections): static
    {
        $this->correctionsOrthographe = [] === $corrections ? null : $corrections;

        return $this;
    }

    public function getFeedback(): ?Feedback
    {
        return $this->feedback;
    }

    public function setFeedback(Feedback $feedback): static
    {
        $this->feedback = $feedback;
        $feedback->setReponse($this);

        return $this;
    }
}
