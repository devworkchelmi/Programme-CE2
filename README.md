# Carnet CE2

Scaffold du projet — voir les documents de conception dans `Brainstorming/` (ou le
projet Programme CE2) : `02-validation-recherche.md`, `03-product-specification.md`,
`04-architecture-technique.md`.

Ce scaffold couvre les jours 1-2 du plan de réalisation (entités, config, Docker,
authentification) plus une bonne partie des jours 3-10 (services LLM, logique de
diagnostic/ajustement, écrans de base). Les `TODO` laissés dans le code pointent vers
ce qu'il reste à affiner — voir en particulier `AdulteController` et `EnfantController`.

## Prérequis

- PHP 8.2+ et Composer (pour lancer les commandes en dehors de Docker si tu préfères)
- Docker Desktop
- Node.js + npm (pour les assets front — Encore/Stimulus)
- Une clé API Claude sur [console.anthropic.com](https://console.anthropic.com)

## Installation

```bash
# 1. Dépendances PHP
composer install

# 2. Variables d'environnement — NE JAMAIS mettre la vraie clé API dans .env (versionné)
cp .env .env.local
# puis éditer .env.local et renseigner : ANTHROPIC_API_KEY=sk-ant-...

# 3. Conteneurs (PHP-FPM, Nginx, MariaDB)
docker compose up -d --build

# 4. Base de données
docker compose exec php bin/console doctrine:database:create
docker compose exec php bin/console make:migration      # première fois : génère la migration à partir des entités
docker compose exec php bin/console doctrine:migrations:migrate

# 5. Comptes de départ (un seul adulte, un seul enfant pour le MVP)
docker compose exec php bin/console app:creer-adulte parent@example.com
docker compose exec php bin/console app:creer-enfant "Prénom de l'enfant"

# 6. Assets front
npm install
npm run watch
```

L'app est ensuite disponible sur http://localhost:8000 (connexion avec le compte
adulte créé à l'étape 5).

## Tests

```bash
composer install --dev
vendor/bin/phpunit
```

Les tests unitaires (`tests/Unit/`) couvrent la logique pure : `AjustementService`
(règle de niveau, §5 de la Product Specification), `DiagnosticService` (calcul du
niveau de départ, §4), et `AnonymisationService` (garde-fou avant appel LLM, §6).
Ils ne nécessitent pas de base de données.

## Ce qui reste à faire (cf. architecture technique §4, jours 3-14)

- Créer le flux qui génère automatiquement une nouvelle session (diagnostic ou
  standard) — pour l'instant les sessions doivent être créées à la main ou via
  une commande à écrire.
- Écran de diagnostic initial multi-textes (actuellement une seule session à la fois).
- Brancher `AjustementService::calculer()` dans `EnfantController::repondre()`
  (le TODO est en place) et persister l'`AjustementNiveau` résultant.
- Écran de confirmation du type d'erreur côté suivi adulte (§2.5).
- Remplacer les questions existantes lors d'une régénération de texte (`AdulteController::regenerer`).
- Charte visuelle : reprendre plus finement le canevas de design (`01-parcours-produit-maquettes.html`).
