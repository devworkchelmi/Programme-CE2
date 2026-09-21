<?php

namespace App\Service\Orthographe;

use App\Entity\Enum\TypeExerciceOrthographe;

/**
 * Banque de règles/mots d'orthographe CE2 fixe (cf. partie « Orthographe ») — pas de
 * génération par IA : chaque exercice est tiré au hasard dans ces phrases toutes prêtes,
 * donc pas d'appel API ni de relecture adulte nécessaire avant que l'enfant y accède
 * (à la différence de la compréhension de texte).
 *
 * Chaque entrée porte une phrase modèle avec un mot cible ({{mot}}), sa forme correcte et
 * 1 à 2 formes incorrectes plausibles — à partir de là, les 3 formats d'exercice (texte à
 * trous, correction, choix de mot) sont construits de façon uniforme par
 * genererExercice(), quel que soit le format tiré au hasard.
 *
 * Notions couvertes, en complexité croissante (niveau 1 à 6) : pluriels et accords
 * sujet-verbe simples, puis les homophones grammaticaux les plus fréquents en CE2
 * (a/à, on/ont, son/sont, ou/où, ce/se, ces/ses, c'est/s'est...).
 */
class BanqueExercicesService
{
    public const NIVEAU_MIN = 1;
    public const NIVEAU_MAX = 6;

    /**
     * @var array<int, list<array{regle: string, phrase: string, mot_correct: string, mots_incorrects: list<string>}>>
     */
    private const BANQUE = [
        1 => [
            ['regle' => 'Pluriel des noms (-s)', 'phrase' => 'Dans le jardin, les {{mot}} jouent au ballon.', 'mot_correct' => 'enfants', 'mots_incorrects' => ['enfant', 'enfantes']],
            ['regle' => 'Pluriel des noms (-s)', 'phrase' => 'Maman a acheté deux {{mot}} rouges.', 'mot_correct' => 'pommes', 'mots_incorrects' => ['pomme', 'pommes']],
            ['regle' => 'Accord sujet-verbe (il / elle)', 'phrase' => 'Le chat {{mot}} sur le canapé.', 'mot_correct' => 'dort', 'mots_incorrects' => ['dor', 'dores']],
            ['regle' => 'Accord sujet-verbe (il / elle)', 'phrase' => 'Elle {{mot}} très vite pour aller à l\'école.', 'mot_correct' => 'marche', 'mots_incorrects' => ['marches', 'marchent']],
            ['regle' => 'Homophone et / est', 'phrase' => 'Léo {{mot}} content d\'aller à la piscine.', 'mot_correct' => 'est', 'mots_incorrects' => ['et']],
        ],
        2 => [
            ['regle' => 'Pluriel des noms (-s)', 'phrase' => 'Les {{mot}} dorment dans leur panier.', 'mot_correct' => 'chiens', 'mots_incorrects' => ['chien', 'chients']],
            ['regle' => 'Accord sujet-verbe (ils / elles)', 'phrase' => 'Les oiseaux {{mot}} dans le ciel bleu.', 'mot_correct' => 'volent', 'mots_incorrects' => ['vole', 'volet']],
            ['regle' => 'Accord sujet-verbe (ils / elles)', 'phrase' => 'Mes cousines {{mot}} au foot tous les mercredis.', 'mot_correct' => 'jouent', 'mots_incorrects' => ['joue', 'jouons']],
            ['regle' => 'Homophone on / ont', 'phrase' => 'Les enfants {{mot}} fini leurs devoirs.', 'mot_correct' => 'ont', 'mots_incorrects' => ['on']],
            ['regle' => 'Homophone on / ont', 'phrase' => '{{mot}} va bientôt partir en vacances.', 'mot_correct' => 'On', 'mots_incorrects' => ['Ont']],
        ],
        3 => [
            ['regle' => 'Homophone a / à', 'phrase' => 'Il {{mot}} un joli vélo bleu.', 'mot_correct' => 'a', 'mots_incorrects' => ['à']],
            ['regle' => 'Homophone a / à', 'phrase' => 'Nous allons {{mot}} la bibliothèque samedi.', 'mot_correct' => 'à', 'mots_incorrects' => ['a']],
            ['regle' => 'Accord de l\'adjectif (féminin)', 'phrase' => 'La maison est {{mot}} et très confortable.', 'mot_correct' => 'grande', 'mots_incorrects' => ['grand', 'grandes']],
            ['regle' => 'Pluriel en -aux', 'phrase' => 'Les {{mot}} galopent dans le pré.', 'mot_correct' => 'chevaux', 'mots_incorrects' => ['chevals', 'chevaus']],
            ['regle' => 'Pluriel en -aux', 'phrase' => 'Ce zoo accueille beaucoup d\'{{mot}} sauvages.', 'mot_correct' => 'animaux', 'mots_incorrects' => ['animals', 'animaus']],
        ],
        4 => [
            ['regle' => 'Homophone son / sont', 'phrase' => 'Paul a perdu {{mot}} cahier de poésies.', 'mot_correct' => 'son', 'mots_incorrects' => ['sont']],
            ['regle' => 'Homophone son / sont', 'phrase' => 'Mes parents {{mot}} déjà arrivés à la fête.', 'mot_correct' => 'sont', 'mots_incorrects' => ['son']],
            ['regle' => 'Homophone ou / où', 'phrase' => 'Tu préfères le chocolat {{mot}} la vanille ?', 'mot_correct' => 'ou', 'mots_incorrects' => ['où']],
            ['regle' => 'Homophone ou / où', 'phrase' => 'Dis-moi {{mot}} tu as caché le trésor.', 'mot_correct' => 'où', 'mots_incorrects' => ['ou']],
            ['regle' => 'Accord de l\'adjectif (pluriel)', 'phrase' => 'Ces gâteaux au chocolat sont {{mot}}.', 'mot_correct' => 'délicieux', 'mots_incorrects' => ['délicieuse', 'délicieuses']],
        ],
        5 => [
            ['regle' => 'Homophone ce / se', 'phrase' => '{{mot}} matin, il pleut encore un peu.', 'mot_correct' => 'Ce', 'mots_incorrects' => ['Se']],
            ['regle' => 'Homophone ce / se', 'phrase' => 'Le chat {{mot}} cache toujours sous le lit.', 'mot_correct' => 'se', 'mots_incorrects' => ['ce']],
            ['regle' => 'Accord sujet-verbe (nous / vous)', 'phrase' => 'Nous {{mot}} à la mer tous les étés.', 'mot_correct' => 'partons', 'mots_incorrects' => ['partez', 'part']],
            ['regle' => 'Pluriel en -eurs', 'phrase' => 'Les {{mot}} de l\'école sont très gentils.', 'mot_correct' => 'directeurs', 'mots_incorrects' => ['directeures', 'directeur']],
            ['regle' => 'Homophone leur / leurs', 'phrase' => 'Les enfants rangent {{mot}} affaires avant de partir.', 'mot_correct' => 'leurs', 'mots_incorrects' => ['leur']],
        ],
        6 => [
            ['regle' => 'Homophone ces / ses', 'phrase' => '{{mot}} nouvelles chaussures sont vraiment confortables.', 'mot_correct' => 'Ces', 'mots_incorrects' => ['Ses']],
            ['regle' => 'Homophone ces / ses', 'phrase' => 'Il a rangé {{mot}} jouets dans le grand coffre.', 'mot_correct' => 'ses', 'mots_incorrects' => ['ces']],
            ['regle' => 'Homophone c\'est / s\'est', 'phrase' => '{{mot}} elle qui a gagné la course hier.', 'mot_correct' => 'C\'est', 'mots_incorrects' => ['S\'est']],
            ['regle' => 'Homophone c\'est / s\'est', 'phrase' => 'Il {{mot}} blessé en tombant de son vélo.', 'mot_correct' => 's\'est', 'mots_incorrects' => ['c\'est']],
            ['regle' => 'Homophone peu / peux / peut', 'phrase' => 'Je {{mot}} t\'aider à ranger si tu veux.', 'mot_correct' => 'peux', 'mots_incorrects' => ['peu', 'peut']],
        ],
    ];

    /**
     * @return array{type: TypeExerciceOrthographe, regle: string, contenu: array<string, mixed>}
     */
    public function genererExercice(int $niveau, ?TypeExerciceOrthographe $typeForce = null): array
    {
        $niveau = max(self::NIVEAU_MIN, min(self::NIVEAU_MAX, $niveau));
        $items = self::BANQUE[$niveau];
        $item = $items[array_rand($items)];

        $types = TypeExerciceOrthographe::cases();
        $type = $typeForce ?? $types[array_rand($types)];

        return [
            'type' => $type,
            'regle' => $item['regle'],
            'contenu' => $this->construireContenu($type, $item),
        ];
    }

    /**
     * @param array{phrase: string, mot_correct: string, mots_incorrects: list<string>} $item
     *
     * @return array<string, mixed>
     */
    private function construireContenu(TypeExerciceOrthographe $type, array $item): array
    {
        [$avant, $apres] = array_pad(explode('{{mot}}', $item['phrase'], 2), 2, '');

        return match ($type) {
            TypeExerciceOrthographe::TEXTE_A_TROUS => [
                'avant' => $avant,
                'apres' => $apres,
                'reponse' => $item['mot_correct'],
            ],
            TypeExerciceOrthographe::CHOIX_MOT => [
                'avant' => $avant,
                'apres' => $apres,
                'propositions' => $this->propositionsMelangees($item),
                'reponse' => $item['mot_correct'],
            ],
            TypeExerciceOrthographe::CORRECTION => [
                'phrase_incorrecte' => $avant.$item['mots_incorrects'][array_rand($item['mots_incorrects'])].$apres,
                'phrase_correcte' => $avant.$item['mot_correct'].$apres,
            ],
        };
    }

    /**
     * @param array{mot_correct: string, mots_incorrects: list<string>} $item
     *
     * @return list<string>
     */
    private function propositionsMelangees(array $item): array
    {
        $propositions = array_merge([$item['mot_correct']], array_slice($item['mots_incorrects'], 0, 2));
        shuffle($propositions);

        return $propositions;
    }
}
