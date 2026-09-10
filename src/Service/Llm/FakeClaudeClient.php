<?php

namespace App\Service\Llm;

/**
 * Client de démonstration : renvoie des réponses fabriquées localement, au même format
 * que l'API Claude, pour dérouler tout le parcours sans consommer de crédit API.
 *
 * Activé par APP_LLM_FAKE=1 dans .env.local (cf. README, section « Mode démo »).
 * Ce n'est PAS un bouchon aléatoire : l'analyse des réponses se base réellement sur ce
 * que l'enfant a écrit (recoupement de mots avec la réponse attendue), pour que les
 * règles de diagnostic et d'ajustement de niveau soient testables pour de vrai.
 */
class FakeClaudeClient implements ClaudeClientInterface
{
    /** @var array<int, array{titre: string, texte: string, question_qcm: array{enonce: string, choix: list<string>, attendue: string}, question_redigee: array{enonce: string, criteres: string}}> */
    private const TEXTES_PAR_NIVEAU = [
        1 => [
            'titre' => 'Le chat sur le mur',
            'texte' => "Minou est un chat gris. Il aime dormir sur le mur du jardin. Le matin, le soleil chauffe les pierres. Minou s'installe et ferme les yeux. Quand un oiseau passe, il lève une oreille, puis il se rendort.",
            'question_qcm' => [
                'enonce' => 'Où Minou aime-t-il dormir ?',
                'choix' => ['Sur le mur du jardin', 'Dans la cuisine', 'Sous la voiture'],
                'attendue' => 'Sur le mur du jardin',
            ],
            'question_redigee' => [
                'enonce' => 'Pourquoi Minou choisit-il le mur le matin ? Explique avec tes mots.',
                'criteres' => 'La réponse doit dire que le soleil chauffe les pierres du mur (mots-clés attendus : soleil, chauffe, pierres, chaud).',
            ],
        ],
        2 => [
            'titre' => 'La cabane de Noé',
            'texte' => "Noé a construit une cabane au fond du jardin avec trois planches et une vieille bâche. Il y range son carnet, une lampe de poche et des billes. Quand il pleut, la bâche fait un bruit de tambour. Noé dit que c'est sa musique préférée.",
            'question_qcm' => [
                'enonce' => 'Qu\'est-ce que Noé range dans sa cabane ?',
                'choix' => ['Son carnet, une lampe et des billes', 'Ses chaussures', 'Un ballon et un vélo'],
                'attendue' => 'Son carnet, une lampe et des billes',
            ],
            'question_redigee' => [
                'enonce' => 'Pourquoi Noé aime-t-il quand il pleut sur sa cabane ?',
                'criteres' => 'La réponse doit parler du bruit de tambour de la pluie sur la bâche (mots-clés attendus : bruit, tambour, pluie, bâche).',
            ],
        ],
        3 => [
            'titre' => 'La course des escargots',
            'texte' => "Dans la cour de l'école, Lina et Tom ont organisé une course d'escargots. Chacun a tracé une ligne à la craie. L'escargot de Tom est parti très vite, puis il s'est arrêté devant une feuille de salade. Celui de Lina, plus lent, a continué sans jamais s'arrêter. À la récréation suivante, c'est lui qui avait franchi la ligne.",
            'question_qcm' => [
                'enonce' => 'Pourquoi l\'escargot de Tom n\'a-t-il pas gagné ?',
                'choix' => ['Il s\'est arrêté devant une salade', 'Il est reparti en arrière', 'Il était trop petit'],
                'attendue' => 'Il s\'est arrêté devant une salade',
            ],
            'question_redigee' => [
                'enonce' => 'Que nous apprend cette histoire sur la façon de gagner une course ?',
                'criteres' => 'La réponse doit dire qu\'avancer sans s\'arrêter vaut mieux que partir vite (mots-clés attendus : avancer, arrêter, lentement, régulier).',
            ],
        ],
        4 => [
            'titre' => 'Le carnet retrouvé',
            'texte' => "Camille cherchait son carnet bleu depuis trois jours. Elle avait vidé son cartable, soulevé son lit, fouillé le placard de l'entrée. Ce matin, en ouvrant le réfrigérateur pour prendre le lait, elle l'a trouvé posé à côté du beurre. Elle a d'abord ri, puis elle s'est souvenue : samedi, elle notait la recette du gâteau pendant que sa grand-mère rangeait les courses.",
            'question_qcm' => [
                'enonce' => 'Où Camille a-t-elle retrouvé son carnet ?',
                'choix' => ['Dans le réfrigérateur', 'Sous son lit', 'Dans son cartable'],
                'attendue' => 'Dans le réfrigérateur',
            ],
            'question_redigee' => [
                'enonce' => 'D\'après le texte, comment le carnet a-t-il pu arriver là ? Explique.',
                'criteres' => 'La réponse doit relier la recette notée et les courses rangées par la grand-mère (mots-clés attendus : recette, courses, grand-mère, rangeait).',
            ],
        ],
        5 => [
            'titre' => 'Le phare et la tempête',
            'texte' => "Le gardien du phare notait chaque soir la force du vent dans un grand registre. Ce mardi-là, il écrivit seulement trois mots avant de refermer le cahier et de descendre en courant : la mer montait bien plus vite que la veille, et le canot du village était encore amarré au ponton. Les habitants racontent qu'il n'a pas dormi de la nuit, mais qu'au matin, aucun bateau ne manquait.",
            'question_qcm' => [
                'enonce' => 'Pourquoi le gardien descend-il en courant ?',
                'choix' => ['La mer monte vite et le canot est encore au ponton', 'Il a oublié son registre', 'Il veut prévenir sa famille'],
                'attendue' => 'La mer monte vite et le canot est encore au ponton',
            ],
            'question_redigee' => [
                'enonce' => 'Qu\'a probablement fait le gardien pendant la nuit ? Justifie avec le texte.',
                'criteres' => 'La réponse doit déduire qu\'il a mis les bateaux à l\'abri pendant la nuit (mots-clés attendus : bateaux, abri, sauver, nuit).',
            ],
        ],
        6 => [
            'titre' => 'La bibliothèque de la gare',
            'texte' => "Sur le quai numéro deux, une étagère de bois accueille des livres que les voyageurs déposent et empruntent librement. Personne ne surveille, personne ne note les emprunts. Depuis huit ans, le chef de gare affirme que l'étagère n'a jamais été aussi remplie qu'aujourd'hui — signe, dit-il en souriant, que les gens rapportent davantage qu'ils ne prennent.",
            'question_qcm' => [
                'enonce' => 'Comment fonctionne l\'étagère de la gare ?',
                'choix' => ['Chacun dépose et emprunte librement', 'Il faut une carte d\'abonné', 'Le chef de gare note les emprunts'],
                'attendue' => 'Chacun dépose et emprunte librement',
            ],
            'question_redigee' => [
                'enonce' => 'Que veut dire le chef de gare quand il sourit en parlant de l\'étagère ?',
                'criteres' => 'La réponse doit parler de confiance et d\'honnêteté des voyageurs (mots-clés attendus : confiance, honnêtes, rapportent, gentils).',
            ],
        ],
    ];

    /**
     * Vocabulaire de consigne présent dans les critères d'évaluation, sans valeur de
     * contenu : ignoré au moment de comparer la réponse de l'enfant aux attentes.
     *
     * @var list<string>
     */
    private const MOTS_DE_CONSIGNE = [
        'reponse', 'doit', 'mentionner', 'exprimer', 'deduire', 'expliquer', 'relier',
        'interpreter', 'parler', 'idee', 'texte', 'justifier', 'attendus', 'attendue',
        'mots', 'cles', 'enfant', 'elle', 'dire', 'comme', 'pendant', 'vaut', 'mieux',
        'appuyant', 'notee', 'rangees',
    ];

    /**
     * Fautes lexicales fréquentes en CE2, détectées telles quelles en mode démo.
     *
     * @var array<string, string>
     */
    private const FAUTES_FREQUENTES = [
        'jai' => "j'ai",
        'quil' => "qu'il",
        'quelle' => "qu'elle",
        'parceque' => 'parce que',
        'beacoup' => 'beaucoup',
        'beaucoups' => 'beaucoup',
        'toujour' => 'toujours',
        'fesait' => 'faisait',
        'etait' => 'était',
        'aparu' => 'apparu',
        'apele' => 'appelle',
        'sest' => "s'est",
        'cest' => "c'est",
    ];

    private const FEEDBACKS = [
        "Tu n'es pas loin ! Relis la phrase où l'on parle de ce moment de l'histoire : la réponse s'y cache.",
        "Bonne idée, mais il manque un détail. Reprends le texte doucement, la phrase importante est vers le milieu.",
        "Presque ! Essaie de te demander ce que le personnage voulait faire à ce moment-là.",
        "Tu as compris le début, continue comme ça ! Relis la fin du texte pour compléter ta réponse.",
    ];

    /**
     * @param array<string, mixed> $jsonSchema
     *
     * @return array<string, mixed>
     */
    public function demanderJson(string $prompt, array $jsonSchema, int $maxTokens = 1024): array
    {
        $proprietes = array_keys($jsonSchema['properties'] ?? []);

        if (in_array('questions', $proprietes, true)) {
            return $this->genererTexte($prompt);
        }

        if (in_array('correcte', $proprietes, true)) {
            $analyse = $this->analyserReponse($prompt);

            // Le schéma porte le signal : l'orthographe n'est demandée que pour les
            // réponses rédigées (cf. AnalyseReponseService::schema()).
            if (in_array('orthographe', $proprietes, true)) {
                $analyse['orthographe'] = $this->corrigerOrthographe($prompt);
            }

            return $analyse;
        }

        if (in_array('texte_feedback', $proprietes, true)) {
            return ['texte_feedback' => self::FEEDBACKS[random_int(0, count(self::FEEDBACKS) - 1)]];
        }

        throw new \RuntimeException('Mode démo : schéma JSON non reconnu par FakeClaudeClient.');
    }

    /**
     * @return array{titre: string, texte: string, questions: list<array<string, mixed>>}
     */
    private function genererTexte(string $prompt): array
    {
        $niveau = 3;
        if (1 === preg_match('/Niveau visé\s*:\s*(\d+)/u', $prompt, $correspondances)) {
            $niveau = max(1, min(6, (int) $correspondances[1]));
        }

        $modele = self::TEXTES_PAR_NIVEAU[$niveau];

        return [
            'titre' => sprintf('[DÉMO] %s', $modele['titre']),
            'texte' => $modele['texte'],
            'questions' => [
                [
                    'type' => 'qcm',
                    'enonce' => $modele['question_qcm']['enonce'],
                    'choix' => $modele['question_qcm']['choix'],
                    'reponse_attendue_ou_criteres' => $modele['question_qcm']['attendue'],
                ],
                [
                    'type' => 'redigee',
                    'enonce' => $modele['question_redigee']['enonce'],
                    'choix' => null,
                    'reponse_attendue_ou_criteres' => $modele['question_redigee']['criteres'],
                ],
            ],
        ];
    }

    /**
     * Compare réellement la réponse de l'enfant aux attentes (recoupement de mots
     * significatifs) : répondre juste fait monter le niveau, répondre à côté le fait
     * baisser — les règles §4 et §5 restent donc testables en mode démo.
     *
     * @return array{correcte: string, type_erreur_propose: ?string, justification_courte: string}
     */
    private function analyserReponse(string $prompt): array
    {
        $attendue = $this->extraire('/Réponse attendue ou critères\s*:\s*(.*)$/mu', $prompt);
        $donnee = $this->extraire('/Réponse de l\'enfant\s*:\s*(.*)$/mu', $prompt);

        if ('' === trim($donnee)) {
            return [
                'correcte' => 'non',
                'type_erreur_propose' => 'lecture_attention',
                'justification_courte' => '[DÉMO] Aucune réponse donnée.',
            ];
        }

        $motsAttendus = $this->motsAttendus($attendue);
        $motsDonnes = $this->motsSignificatifs($donnee);

        if ([] === $motsAttendus) {
            $recouvrement = 0.0;
        } else {
            $communs = array_intersect($motsAttendus, $motsDonnes);
            $recouvrement = count($communs) / count($motsAttendus);
        }

        if ($recouvrement >= 0.4) {
            return [
                'correcte' => 'oui',
                'type_erreur_propose' => null,
                'justification_courte' => '[DÉMO] La réponse reprend les éléments attendus.',
            ];
        }

        if ($recouvrement > 0.0) {
            return [
                'correcte' => 'partiel',
                'type_erreur_propose' => 'litterale',
                'justification_courte' => '[DÉMO] Une partie seulement des éléments attendus est présente.',
            ];
        }

        return [
            'correcte' => 'non',
            'type_erreur_propose' => 'inference',
            'justification_courte' => '[DÉMO] La réponse ne reprend aucun élément attendu.',
        ];
    }

    /**
     * Mots réellement attendus dans la réponse : ceux listés entre parenthèses
     * ("mots-clés attendus : ...") s'il y en a, sinon les mots du critère débarrassés
     * du vocabulaire de consigne — sans ce filtrage, "la réponse doit mentionner..."
     * pèse autant que le contenu et une bonne réponse passe sous le seuil.
     *
     * @return list<string>
     */
    private function motsAttendus(string $criteres): array
    {
        if (1 === preg_match('/mots-clés attendus\s*:\s*([^)]+)/u', $criteres, $correspondances)) {
            return $this->motsSignificatifs($correspondances[1]);
        }

        return array_values(array_diff($this->motsSignificatifs($criteres), self::MOTS_DE_CONSIGNE));
    }

    /**
     * Correcteur orthographique de démonstration : fautes fréquentes connues, puis mots
     * à une lettre près d'un mot du texte ou d'un mot-clé attendu.
     *
     * Volontairement prudent — on ne signale ni un accent oublié ni un pluriel, pour ne
     * pas noyer l'enfant sous des corrections qui n'en sont pas.
     *
     * @return list<array{mot_ecrit: string, correction: string}>
     */
    private function corrigerOrthographe(string $prompt): array
    {
        $donnee = $this->extraire('/Réponse de l\'enfant\s*:\s*(.*)$/mu', $prompt);

        if ('' === trim($donnee)) {
            return [];
        }

        $texte = 1 === preg_match('/Texte\s*:\s*(.*?)\n\s*Question\s*:/su', $prompt, $bouts)
            ? $bouts[1]
            : '';
        $attendue = $this->extraire('/Réponse attendue ou critères\s*:\s*(.*)$/mu', $prompt);

        $vocabulaire = array_merge($this->motsSignificatifs($texte), $this->motsSignificatifs($attendue));
        $corrections = [];

        foreach (preg_split('/[^\p{L}\p{N}]+/u', $donnee, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $motEcrit) {
            if (count($corrections) >= 5) {
                continue;
            }

            $normalise = $this->normaliser($motEcrit);

            // Les fautes connues sont fiables : pas de garde-fou de longueur ("jai").
            if (isset(self::FAUTES_FREQUENTES[$normalise])) {
                $corrections[$motEcrit] = self::FAUTES_FREQUENTES[$normalise];
                continue;
            }

            // La détection par proximité, elle, ne s'applique qu'aux mots assez longs :
            // sur des mots courts, une lettre d'écart désigne trop souvent un autre mot
            // parfaitement correct.
            if (mb_strlen($motEcrit) < 4 || in_array($normalise, $vocabulaire, true)) {
                continue;
            }

            foreach ($vocabulaire as $motReference) {
                if (1 !== levenshtein($normalise, $motReference) || $this->memeMotAccordeOuAccentue($normalise, $motReference)) {
                    continue;
                }

                $corrections[$motEcrit] = $motReference;
                break;
            }
        }

        $resultat = [];
        foreach ($corrections as $motEcrit => $correction) {
            $resultat[] = ['mot_ecrit' => (string) $motEcrit, 'correction' => $correction];
        }

        return $resultat;
    }

    /**
     * Vrai quand deux formes ne diffèrent que par un pluriel : ce n'est pas une faute
     * d'orthographe lexicale, et le signaler serait plus déroutant qu'utile.
     */
    private function memeMotAccordeOuAccentue(string $a, string $b): bool
    {
        return rtrim($a, 'sx') === rtrim($b, 'sx');
    }

    private function normaliser(string $mot): string
    {
        return strtr(mb_strtolower($mot, 'UTF-8'), [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
        ]);
    }

    private function extraire(string $motif, string $sujet): string
    {
        return 1 === preg_match($motif, $sujet, $correspondances) ? trim($correspondances[1]) : '';
    }

    /**
     * @return list<string>
     */
    private function motsSignificatifs(string $texte): array
    {
        $sansAccents = mb_strtolower($texte, 'UTF-8');
        $sansAccents = strtr($sansAccents, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
        ]);

        $mots = preg_split('/[^a-z0-9]+/u', $sansAccents, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $mots,
            static fn (string $mot): bool => mb_strlen($mot) > 3,
        )));
    }
}
