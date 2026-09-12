<?php

namespace App\Controller;

use App\Entity\Enfant;
use App\Entity\Enum\StatutSession;
use App\Entity\Enum\TypeErreur;
use App\Entity\Enum\TypeQuestion;
use App\Entity\Enum\TypeSession;
use App\Entity\Question;
use App\Entity\Session;
use App\Entity\TexteGenere;
use App\Repository\EnfantRepository;
use App\Repository\ReponseRepository;
use App\Repository\SessionRepository;
use App\Service\AjustementService;
use App\Service\Llm\TexteGenerationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Écrans côté adulte (cf. Product Specification §2.4 et §2.5).
 */
#[IsGranted('ROLE_ADULTE')]
class AdulteController extends AbstractController
{
    /**
     * Niveaux des textes du diagnostic initial, dans l'ordre de complexité croissante
     * (cf. Product Specification §4). 3 textes plutôt que 5 : conforme au minimum
     * spécifié (3 à 5) tout en limitant le nombre d'appels LLM séquentiels.
     */
    private const NIVEAUX_DIAGNOSTIC = [1, 3, 5];

    #[Route('/adulte/suivi', name: 'adulte_suivi')]
    public function suivi(
        Request $request,
        EnfantRepository $enfantRepository,
        SessionRepository $sessionRepository,
        ReponseRepository $reponseRepository,
        AjustementService $ajustementService,
    ): Response {
        $enfants = $enfantRepository->findBy([], ['id' => 'ASC']);
        $enfant = $this->enfantSelectionne($request, $enfants);

        $sessionsJouees = $enfant instanceof Enfant
            ? $sessionRepository->lesPlusRecentesJouees($enfant, 10)
            : [];

        // Du plus ancien au plus récent (chronologique) : c'est l'ordre attendu pour
        // tracer un sparkline de gauche à droite (cf. maquette Suivi adulte).
        $tendance = array_reverse(array_map(
            static fn ($session) => $ajustementService->tauxReussite($session),
            $sessionsJouees,
        ));

        // Affichage de la liste (tous statuts, y compris en attente de relecture) : distinct
        // de $sessionsJouees ci-dessus, qui sert uniquement au calcul de tendance.
        $sessionsAffichees = $enfant instanceof Enfant
            ? $sessionRepository->toutesRecentes($enfant, 10)
            : [];

        $reponsesAConfirmer = $enfant instanceof Enfant
            ? $reponseRepository->aConfirmer($enfant, 10)
            : [];

        $orthographeRecente = $enfant instanceof Enfant
            ? $reponseRepository->avecCorrectionsOrthographe($enfant, 10)
            : [];

        return $this->render('adulte/suivi.html.twig', [
            'enfant' => $enfant,
            'enfants' => $enfants,
            'sessions' => $sessionsAffichees,
            'tendance' => $tendance,
            'tendancePoints' => $this->pointsSparkline($tendance),
            'tendanceLabel' => $this->libelleTendance($tendance),
            'reponsesAConfirmer' => $reponsesAConfirmer,
            'orthographeRecente' => $orthographeRecente,
        ]);
    }

    /**
     * Mémorise l'enfant choisi dans le sélecteur du suivi (cf. sélection réelle de
     * l'enfant, TODO jour 11-12) — utile dès qu'il y a plusieurs fiches Enfant.
     */
    #[Route('/adulte/enfant/selectionner', name: 'adulte_enfant_selectionner', methods: ['POST'])]
    public function selectionnerEnfant(Request $request, EnfantRepository $enfantRepository): Response
    {
        $enfant = $enfantRepository->find($request->request->getInt('enfant_id'));

        if (null !== $enfant) {
            $request->getSession()->set('enfant_id_selectionne', $enfant->getId());
        }

        return $this->redirectToRoute('adulte_suivi');
    }

    /**
     * Renvoie l'enfant actuellement sélectionné, mémorisé en session HTTP (cf. TODO jour
     * 11-12 — sélection réelle de l'enfant). Sans sélection mémorisée, ou si l'ID mémorisé
     * ne correspond plus à un enfant existant (ex. supprimé), on retombe sur le premier
     * enfant connu et on mémorise ce choix pour la suite.
     *
     * @param Enfant[] $enfants
     */
    private function enfantSelectionne(Request $request, array $enfants): ?Enfant
    {
        if ([] === $enfants) {
            return null;
        }

        $idMemorise = $request->getSession()->get('enfant_id_selectionne');

        foreach ($enfants as $enfant) {
            if ($enfant->getId() === $idMemorise) {
                return $enfant;
            }
        }

        $premier = $enfants[0];
        $request->getSession()->set('enfant_id_selectionne', $premier->getId());

        return $premier;
    }

    /**
     * Coordonnées d'un sparkline SVG (mini-graphique de tendance, cf. maquette Suivi
     * adulte) à partir des taux de réussite (0 à 1) des sessions récentes, du plus ancien
     * au plus récent. Null si moins de 2 points — pas de ligne possible.
     *
     * @param float[] $tauxReussite
     */
    private function pointsSparkline(array $tauxReussite, int $largeur = 140, int $hauteur = 50, int $marge = 6): ?string
    {
        $n = count($tauxReussite);

        if ($n < 2) {
            return null;
        }

        $points = [];
        foreach (array_values($tauxReussite) as $index => $taux) {
            $x = $marge + ($index / ($n - 1)) * ($largeur - 2 * $marge);
            $y = $marge + (1 - max(0.0, min(1.0, $taux))) * ($hauteur - 2 * $marge);
            $points[] = sprintf('%.1f,%.1f', $x, $y);
        }

        return implode(' ', $points);
    }

    /**
     * Libellé de tendance (cf. maquette Suivi adulte) : compare la moyenne de la première
     * moitié des sessions récentes à celle de la seconde moitié — moins sensible à un
     * accident isolé sur une seule session qu'une simple comparaison premier/dernier point.
     * Null si moins de 2 points.
     *
     * @param float[] $tauxReussite du plus ancien au plus récent
     */
    private function libelleTendance(array $tauxReussite): ?string
    {
        $n = count($tauxReussite);

        if ($n < 2) {
            return null;
        }

        $milieu = intdiv($n, 2);
        $debut = array_slice($tauxReussite, 0, max(1, $milieu));
        $fin = array_slice($tauxReussite, -max(1, $n - $milieu));

        $ecart = (array_sum($fin) / count($fin)) - (array_sum($debut) / count($debut));

        if ($ecart > 0.1) {
            return 'En progression';
        }

        if ($ecart < -0.1) {
            return 'En baisse';
        }

        return 'Stable';
    }

    /**
     * Démarre le travail suivant pour l'enfant du MVP :
     * - tant que le diagnostic initial n'est pas terminé, génère le lot complet de textes
     *   de diagnostic (cf. Product Specification §4) ;
     * - une fois le diagnostic terminé, génère une session standard au niveau courant.
     *
     * Dans les deux cas : Appel 1 (génération) puis envoi en relecture avant de proposer
     * quoi que ce soit à l'enfant (règle non négociable, cf. Product Specification §2.4).
     */
    #[Route('/adulte/session/demarrer', name: 'adulte_session_demarrer', methods: ['POST'])]
    public function demarrerSession(
        Request $request,
        EnfantRepository $enfantRepository,
        SessionRepository $sessionRepository,
        TexteGenerationService $texteGenerationService,
        EntityManagerInterface $em,
    ): Response {
        $enfant = $this->enfantSelectionne($request, $enfantRepository->findBy([], ['id' => 'ASC']));

        if (null === $enfant) {
            throw $this->createNotFoundException('Aucune fiche enfant — crée-en une avec la commande app:creer-enfant.');
        }

        $sessionsDiagnostic = $sessionRepository->sessionsDiagnostic($enfant);

        if (!$enfant->isDiagnosticTermine() && [] !== $sessionsDiagnostic) {
            // Le lot de diagnostic a déjà été généré — on ne le régénère pas tant qu'il n'est
            // pas terminé (relecture + jeu par l'enfant de tous les textes du lot).
            $this->addFlash('info', 'Le diagnostic initial est en cours — termine-le (relecture puis réponses de l\'enfant) avant de démarrer une session standard.');

            return $this->redirectToRoute('adulte_suivi');
        }

        if (!$enfant->isDiagnosticTermine()) {
            // Premier lancement : on génère tout le lot de textes du diagnostic d'un coup.
            $premiereSession = null;

            foreach (self::NIVEAUX_DIAGNOSTIC as $niveau) {
                $session = $this->genererSession($enfant, TypeSession::DIAGNOSTIC, $niveau, $texteGenerationService, $em);
                $premiereSession ??= $session;
            }

            $em->flush();

            $this->addFlash('info', sprintf(
                '%d textes de diagnostic générés — relis-les un par un avant de les proposer à l\'enfant.',
                count(self::NIVEAUX_DIAGNOSTIC),
            ));

            return $this->redirectToRoute('adulte_relecture', ['id' => $premiereSession->getId()]);
        }

        $session = $this->genererSession($enfant, TypeSession::STANDARD, $enfant->getNiveauActuel(), $texteGenerationService, $em);
        $em->flush();

        $this->addFlash('info', 'Nouveau texte généré — relis-le avant de le proposer à l\'enfant.');

        return $this->redirectToRoute('adulte_relecture', ['id' => $session->getId()]);
    }

    /**
     * Génère une session + son texte + ses questions (Appel 1) et la met en attente de
     * relecture. Factorisé entre le lot de diagnostic et la session standard. Ne flush pas :
     * à l'appelant de le faire (utile pour ne faire qu'un seul flush pour tout le lot).
     */
    private function genererSession(
        Enfant $enfant,
        TypeSession $type,
        int $niveauVise,
        TexteGenerationService $texteGenerationService,
        EntityManagerInterface $em,
    ): Session {
        $session = new Session($enfant, $type, $niveauVise);
        $em->persist($session);

        $resultat = $texteGenerationService->genererTexteEtQuestions($niveauVise);

        $texte = new TexteGenere($resultat['titre'], $resultat['texte'], $niveauVise);
        $session->setTexteGenere($texte);
        $em->persist($texte);

        foreach ($resultat['questions'] as $questionData) {
            $texte->addQuestion(new Question(
                TypeQuestion::from($questionData['type']),
                $questionData['enonce'],
                $questionData['reponse_attendue_ou_criteres'],
                $questionData['choix'] ?? null,
            ));
        }

        $session->setStatut(StatutSession::EN_ATTENTE_RELECTURE);

        return $session;
    }

    #[Route('/adulte/relecture/{id}', name: 'adulte_relecture')]
    public function relecture(int $id, Request $request, SessionRepository $sessionRepository): Response
    {
        $session = $sessionRepository->find($id);

        if (null === $session || null === $session->getTexteGenere()) {
            throw $this->createNotFoundException('Aucun texte à relire pour cette session.');
        }

        return $this->render('adulte/relecture.html.twig', [
            'session' => $session,
            'texte' => $session->getTexteGenere(),
            // ?edition=1 bascule l'écran en édition inline (cf. Product Specification §2.4)
            // plutôt qu'un écran séparé — pas de mutation ici, donc un simple lien suffit.
            'edition' => $request->query->getBoolean('edition'),
        ]);
    }

    /**
     * Édition inline d'un texte généré, avant validation (cf. Product Specification §2.4 —
     * actions Valider / Modifier / Régénérer). Distincte de valider() : une fois enregistrée,
     * la modification passe le texte en statutRelecture=MODIFIE, mais il faut toujours
     * cliquer sur « Valider » pour le rendre disponible à l'enfant (règle non négociable).
     */
    #[Route('/adulte/relecture/{id}/modifier', name: 'adulte_relecture_modifier', methods: ['POST'])]
    public function modifier(int $id, Request $request, SessionRepository $sessionRepository, EntityManagerInterface $em): Response
    {
        $session = $sessionRepository->find($id);
        $texte = $session?->getTexteGenere();

        if (null === $session || null === $texte) {
            throw $this->createNotFoundException();
        }

        $titre = trim((string) $request->request->get('titre'));
        $contenu = trim((string) $request->request->get('contenu'));

        if ('' === $titre || '' === $contenu) {
            $this->addFlash('info', 'Le titre et le texte ne peuvent pas être vides — modifications non enregistrées.');

            return $this->redirectToRoute('adulte_relecture', ['id' => $id, 'edition' => 1]);
        }

        $texte->setContenu($titre, $contenu);

        foreach ($texte->getQuestions() as $question) {
            $prefixe = 'question_'.$question->getId().'_';

            $enonce = trim((string) $request->request->get($prefixe.'enonce'));
            $criteres = trim((string) $request->request->get($prefixe.'criteres'));

            if ('' !== $enonce) {
                $question->setEnonce($enonce);
            }
            if ('' !== $criteres) {
                $question->setReponseAttendueOuCriteres($criteres);
            }

            if (TypeQuestion::QCM === $question->getType()) {
                $choixBrut = (string) $request->request->get($prefixe.'choix', '');
                $choix = array_values(array_filter(
                    array_map('trim', explode("\n", $choixBrut)),
                    static fn (string $c) => '' !== $c,
                ));

                if ([] !== $choix) {
                    $question->setChoix($choix);
                }
            }
        }

        // TODO (jour 5-6) : remplacer 'adulte' par l'identifiant réel une fois l'entité User pleinement câblée à la session.
        $texte->marquerModifie('adulte');
        $em->flush();

        $this->addFlash('success', 'Modifications enregistrées — pense à valider le texte pour le rendre disponible à l\'enfant.');

        return $this->redirectToRoute('adulte_relecture', ['id' => $id]);
    }

    #[Route('/adulte/relecture/{id}/valider', name: 'adulte_relecture_valider', methods: ['POST'])]
    public function valider(int $id, SessionRepository $sessionRepository, EntityManagerInterface $em): Response
    {
        $session = $sessionRepository->find($id);
        $texte = $session?->getTexteGenere();

        if (null === $texte) {
            throw $this->createNotFoundException();
        }

        // TODO (jour 5-6) : remplacer 'adulte' par l'identifiant réel une fois l'entité User pleinement câblée à la session.
        $texte->valider('adulte');
        $session->setStatut(StatutSession::VALIDEE);
        $em->flush();

        $this->addFlash('success', 'Texte validé — la session est maintenant disponible pour l\'enfant.');

        return $this->redirectToRoute('adulte_suivi');
    }

    #[Route('/adulte/relecture/{id}/regenerer', name: 'adulte_relecture_regenerer', methods: ['POST'])]
    public function regenerer(
        int $id,
        SessionRepository $sessionRepository,
        TexteGenerationService $texteGenerationService,
        EntityManagerInterface $em,
    ): Response {
        $session = $sessionRepository->find($id);
        $texte = $session?->getTexteGenere();

        if (null === $session || null === $texte) {
            throw $this->createNotFoundException();
        }

        $resultat = $texteGenerationService->genererTexteEtQuestions($session->getNiveauVise());
        $texte->setContenu($resultat['titre'], $resultat['texte']);

        // Les questions portaient sur l'ancien texte : elles n'ont plus de sens une fois le
        // texte régénéré, on les remplace entièrement (orphanRemoval s'occupe de supprimer
        // les anciennes en base, cf. TexteGenere::remplacerQuestions()).
        $texte->remplacerQuestions(array_map(
            static fn (array $questionData) => new Question(
                TypeQuestion::from($questionData['type']),
                $questionData['enonce'],
                $questionData['reponse_attendue_ou_criteres'],
                $questionData['choix'] ?? null,
            ),
            $resultat['questions'],
        ));

        $em->flush();

        $this->addFlash('info', 'Nouveau texte généré — à relire à nouveau avant validation.');

        return $this->redirectToRoute('adulte_relecture', ['id' => $id]);
    }

    /**
     * Confirme ou corrige le type d'erreur proposé par l'IA (Appel 2) pour une réponse
     * donnée (cf. Product Specification §2.5) — reste une proposition tant que l'adulte
     * ne l'a pas validée ici.
     */
    #[Route('/adulte/reponse/{id}/confirmer-erreur', name: 'adulte_reponse_confirmer_erreur', methods: ['POST'])]
    public function confirmerErreur(int $id, Request $request, ReponseRepository $reponseRepository, EntityManagerInterface $em): Response
    {
        $reponse = $reponseRepository->find($id);

        if (null === $reponse) {
            throw $this->createNotFoundException();
        }

        $valeur = $request->request->get('type_erreur');
        $typeErreurConfirme = is_string($valeur) && '' !== $valeur ? TypeErreur::from($valeur) : $reponse->getTypeErreurPropose();

        $reponse->confirmerTypeErreur($typeErreurConfirme);
        $em->flush();

        $this->addFlash('success', 'Type d\'erreur confirmé.');

        return $this->redirectToRoute('adulte_suivi');
    }
}
