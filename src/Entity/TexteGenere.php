<?php

namespace App\Entity;

use App\Entity\Enum\StatutRelecture;
use App\Repository\TexteGenereRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un texte n'est jamais proposé à l'enfant tant que statutRelecture !== VALIDE
 * (règle non négociable, cf. Product Specification §2.4).
 */
#[ORM\Entity(repositoryClass: TexteGenereRepository::class)]
class TexteGenere
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Session::class, inversedBy: 'texteGenere')]
    #[ORM\JoinColumn(nullable: false)]
    private Session $session;

    #[ORM\Column(length: 255)]
    private string $titre;

    #[ORM\Column(type: 'text')]
    private string $contenu;

    #[ORM\Column]
    private int $niveauVocabulaireVise;

    #[ORM\Column(nullable: true)]
    private ?float $longueurMoyennePhrase = null;

    #[ORM\Column(enumType: StatutRelecture::class)]
    private StatutRelecture $statutRelecture = StatutRelecture::EN_ATTENTE;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $reluPar = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateRelecture = null;

    /** @var Collection<int, Question> */
    #[ORM\OneToMany(targetEntity: Question::class, mappedBy: 'texteGenere', orphanRemoval: true, cascade: ['persist'])]
    private Collection $questions;

    public function __construct(string $titre, string $contenu, int $niveauVocabulaireVise)
    {
        $this->titre = $titre;
        $this->contenu = $contenu;
        $this->niveauVocabulaireVise = $niveauVocabulaireVise;
        $this->questions = new ArrayCollection();
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

    public function getTitre(): string
    {
        return $this->titre;
    }

    public function getContenu(): string
    {
        return $this->contenu;
    }

    public function setContenu(string $titre, string $contenu): static
    {
        $this->titre = $titre;
        $this->contenu = $contenu;

        return $this;
    }

    public function getNiveauVocabulaireVise(): int
    {
        return $this->niveauVocabulaireVise;
    }

    public function getLongueurMoyennePhrase(): ?float
    {
        return $this->longueurMoyennePhrase;
    }

    public function setLongueurMoyennePhrase(?float $longueurMoyennePhrase): static
    {
        $this->longueurMoyennePhrase = $longueurMoyennePhrase;

        return $this;
    }

    public function getStatutRelecture(): StatutRelecture
    {
        return $this->statutRelecture;
    }

    /**
     * Seul point d'entrée pour faire passer un texte en VALIDE : trace systématiquement
     * qui a validé et quand (cf. écran Relecture adulte, Product Specification §2.4).
     */
    public function valider(string $reluPar): static
    {
        $this->statutRelecture = StatutRelecture::VALIDE;
        $this->reluPar = $reluPar;
        $this->dateRelecture = new \DateTimeImmutable();

        return $this;
    }

    public function marquerModifie(string $reluPar): static
    {
        $this->statutRelecture = StatutRelecture::MODIFIE;
        $this->reluPar = $reluPar;
        $this->dateRelecture = new \DateTimeImmutable();

        return $this;
    }

    public function rejeter(string $reluPar): static
    {
        $this->statutRelecture = StatutRelecture::REJETE;
        $this->reluPar = $reluPar;
        $this->dateRelecture = new \DateTimeImmutable();

        return $this;
    }

    public function getReluPar(): ?string
    {
        return $this->reluPar;
    }

    public function getDateRelecture(): ?\DateTimeImmutable
    {
        return $this->dateRelecture;
    }

    /** @return Collection<int, Question> */
    public function getQuestions(): Collection
    {
        return $this->questions;
    }

    public function addQuestion(Question $question): static
    {
        if (!$this->questions->contains($question)) {
            $this->questions->add($question);
            $question->setTexteGenere($this);
        }

        return $this;
    }

    /**
     * Remplace entièrement les questions existantes (utilisé lors d'une régénération de
     * texte en relecture, cf. AdulteController::regenerer() et Product Specification §2.4).
     * Grâce à orphanRemoval sur la relation, les anciennes questions sont supprimées en
     * base au flush suivant.
     *
     * @param Question[] $questions
     */
    public function remplacerQuestions(array $questions): static
    {
        $this->questions->clear();

        foreach ($questions as $question) {
            $this->addQuestion($question);
        }

        return $this;
    }
}
