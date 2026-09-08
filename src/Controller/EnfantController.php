<?php

namespace App\Controller;

use App\Entity\AjustementNiveau;
use App\Entity\Enfant;
use App\Entity\Enum\Correction;
use App\Entity\Enum\StatutSession;
use App\Entity\Enum\TypeErreur;
use App\Entity\Enum\TypeSession;
use App\Entity\Feedback;
use App\Entity\Reponse;
use App\Entity\Session;
use App\Repository\SessionRepository;
use App\Service\AjustementService;
use App\Service\DiagnosticService;
use App\Service\Dto\ResultatTexteDiagnostic;
use App\Service\Llm\AnalyseReponseService;
use App\Service\Llm\FeedbackService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Écrans côté enfant (cf. Product Specification §2.1, §2.2, §2.3).
 *
 * L'enfant n'a pas de compte séparé : ces routes exigent seulement que l'adulte
 * soit connecté (cf. config/packages/security.yaml et §0 de la Product Specification).
 */
#[IsGranted('ROLE_ADULTE')]
class EnfantController extends AbstractController
{
    #[Route('/diagnostic/{id}', name: 'enfant_diagnostic')]
    public function diagnostic(int $id, SessionRepository $sessionRepository): Response
    {
        $session = $sessionRepository->find($id);

        if (null === $session || StatutSession::VALIDEE !== $session->getStatut()) {
            throw $this->createNotFoundException('Ce texte de diagnostic n\'est pas encore disponible.');
        }

        // Barre de progression (cf. Product Specification §2.1) : où on en est dans le lot.
        $sessionsDiagnostic = $sessionRepository->sessionsDiagnostic($session->getEnfant());
        $position = 1;
        foreach ($sessionsDiagnostic as $index => $sessionDuLot) {
            if ($sessionDuLot->getId() === $session->getId()) {
                $position = $index + 1;
                break;
            }
        }

        return $this->render('enfant/diagnostic.html.twig', [
            'session' => $session,
            'texte' => $session->getTexteGenere(),
            'position' => $position,
            'total' => count($sessionsDiagnostic),
        ]);
    }

    #[Route('/exercice/{id}', name: 'enfant_exercice')]
    public function exercice(int $id, SessionRepository $sessionRepository): Response
    {
        $session = $sessionRepository->find($id);

        if (null === $session || StatutSession::VALIDEE !== $session->getStatut()) {
            // Règle non négociable (cf. Product Specification §2.4) : jamais de texte non relu proposé à l'enfant.
            throw $this->createNotFoundException('Cette session n\'est pas encore disponible — le texte doit d\'abord être validé par un adulte.');
        }

        return $this->render('enfant/exercice.html.twig', [
            'session' => $session,
            'texte' => $session->getTexteGenere(),
        ]);
    }

    #[Route('/exercice/{id}/repondre', name: 'enfant_exercice_repondre', methods: ['POST'])]
    public function repondre(
        int $id,
        Request $request,
        SessionRepository $sessionRepository,
        AnalyseReponseService $analyseReponseService,
        FeedbackService $feedbackService,
        AjustementService $ajustementService,
        DiagnosticService $diagnosticService,
        EntityManagerInterface $em,
    ): Response {
        $session = $sessionRepository->find($id);
        $texte = $session?->getTexteGenere();

        if (null === $session || null === $texte) {
            throw $this->createNotFoundException();
        }

        // Un seul essai par question (cf. Product Specification §2.2) : chaque réponse
        // soumise déclenche directement l'Appel 2 puis, si besoin, l'Appel 3.
        foreach ($texte->getQuestions() as $question) {
            $contenu = (string) $request->request->get('reponse_'.$question->getId(), '');

            $reponse = new Reponse($contenu);
            $question->setReponse($reponse);

            $analyse = $analyseReponseService->analyser(
                $texte->getContenu(),
                $question->getEnonce(),
                $question->getReponseAttendueOuCriteres(),
                $contenu,
            );

            $typeErreur = null !== $analyse['type_erreur_propose']
                ? TypeErreur::from($analyse['type_erreur_propose'])
                : null;

            $reponse->enregistrerAnalyse(
                Correction::from($analyse['correcte']),
                $typeErreur,
                $analyse['justification_courte'],
            );

            if (Correction::OUI !== $reponse->getCorrecte()) {
                $feedback = $feedbackService->genererFeedback(
                    $session->getNiveauVise(),
                    $texte->getContenu(),
                    $question->getEnonce(),
                    $contenu,
                    $typeErreur?->value,
                );
                $reponse->setFeedback(new Feedback($feedback['texte_feedback']));
            }

            $em->persist($reponse);
        }

        $session->setStatut(StatutSession::JOUEE);
        // Flush immédiat : la suite (diagnostic ou ajustement) a besoin de relire l'état
        // à jour des sessions en base (cf. les deux branches ci-dessous).
        $em->flush();

        $enfant = $session->getEnfant();

        if (TypeSession::DIAGNOSTIC === $session->getType()) {
            $this->finaliserEtapeDiagnostic($session, $enfant, $sessionRepository, $ajustementService, $diagnosticService, $em);
        } else {
            $this->appliquerAjustement($session, $enfant, $sessionRepository, $ajustementService, $em);
        }

        // Diagnostic en cours et texte suivant déjà validé : on enchaîne directement
        // dessus plutôt que de repasser par l'écran de feedback (cf. Product
        // Specification §2.1 — "répondre, continuer").
        if (TypeSession::DIAGNOSTIC === $session->getType() && !$enfant->isDiagnosticTermine()) {
            $prochain = $this->prochainTexteDiagnostic($enfant, $sessionRepository);
            if (null !== $prochain) {
                return $this->redirectToRoute('enfant_diagnostic', ['id' => $prochain->getId()]);
            }
        }

        return $this->redirectToRoute('enfant_feedback', ['id' => $id]);
    }

    #[Route('/feedback/{id}', name: 'enfant_feedback')]
    public function feedback(int $id, SessionRepository $sessionRepository): Response
    {
        $session = $sessionRepository->find($id);

        if (null === $session) {
            throw $this->createNotFoundException();
        }

        return $this->render('enfant/feedback.html.twig', [
            'session' => $session,
            'texte' => $session->getTexteGenere(),
            'enfant' => $session->getEnfant(),
        ]);
    }

    /**
     * Cf. Product Specification §4 : une fois qu'au moins 2 textes du lot de diagnostic
     * ont été joués, on peut calculer un niveau de départ ; on ne l'applique et on ne
     * marque le diagnostic terminé que lorsque tout le lot a été joué.
     */
    private function finaliserEtapeDiagnostic(
        Session $session,
        Enfant $enfant,
        SessionRepository $sessionRepository,
        AjustementService $ajustementService,
        DiagnosticService $diagnosticService,
        EntityManagerInterface $em,
    ): void {
        $sessionsDiagnostic = $sessionRepository->sessionsDiagnostic($enfant);
        $sessionsJouees = array_values(array_filter(
            $sessionsDiagnostic,
            static fn (Session $s) => StatutSession::JOUEE === $s->getStatut(),
        ));

        $resultats = array_map(
            static fn (Session $s) => new ResultatTexteDiagnostic($s->getNiveauVise(), $ajustementService->tauxReussite($s)),
            $sessionsJouees,
        );

        $niveauDepart = $diagnosticService->calculerNiveauDepart($resultats);

        if (null !== $niveauDepart && count($sessionsJouees) === count($sessionsDiagnostic)) {
            $enfant->setNiveauActuel($niveauDepart);
            $enfant->setDiagnosticTermine(true);
            $em->flush();
        }
    }

    /**
     * Ajustement de niveau (cf. Product Specification §5) pour une session standard.
     */
    private function appliquerAjustement(
        Session $session,
        Enfant $enfant,
        SessionRepository $sessionRepository,
        AjustementService $ajustementService,
        EntityManagerInterface $em,
    ): void {
        $tauxReussiteRecents = [$ajustementService->tauxReussite($session)];

        foreach ($sessionRepository->lesPlusRecentesJouees($enfant, 2) as $sessionRecente) {
            if ($sessionRecente->getId() === $session->getId()) {
                continue;
            }
            $tauxReussiteRecents[] = $ajustementService->tauxReussite($sessionRecente);
            if (count($tauxReussiteRecents) >= 2) {
                break;
            }
        }

        $resultat = $ajustementService->calculer($enfant->getNiveauActuel(), $tauxReussiteRecents);

        $ajustementNiveau = new AjustementNiveau($resultat->niveauAvant, $resultat->niveauApres, $resultat->regleAppliquee);
        $session->setAjustementNiveau($ajustementNiveau);
        $em->persist($ajustementNiveau);

        $enfant->setNiveauActuel($resultat->niveauApres);

        $em->flush();
    }

    /**
     * Premier texte du lot de diagnostic déjà validé par l'adulte mais pas encore joué —
     * cf. cas limite "reprise au texte suivant" (Product Specification §2.1).
     */
    private function prochainTexteDiagnostic(Enfant $enfant, SessionRepository $sessionRepository): ?Session
    {
        foreach ($sessionRepository->sessionsDiagnostic($enfant) as $session) {
            if (StatutSession::VALIDEE === $session->getStatut()) {
                return $session;
            }
        }

        return null;
    }
}
