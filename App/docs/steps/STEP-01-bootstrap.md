# STEP-01 — Bootstrap : branche, CLAUDE.md, workflow docs

**Statut :** en cours
**Branche :** `feat/sebcode-foundation` (depuis `main`)

## But

Poser les fondations *méta* du projet avant d'écrire du code métier :

1. Une branche propre dédiée à la reconstruction hexa/DDD.
2. Un `CLAUDE.md` racine qui décrit les règles du jeu (archi, naming, layers, workflow).
3. Un dossier `App/docs/steps/` où chaque grande étape est documentée dans un markdown dédié.
4. Un `.gitignore` racine sain (les `.md` n'étaient plus tracés, et la référence opencode polluait le statut).

## Décisions clés

| Décision | Choix | Raison |
|---|---|---|
| Point de départ | `main` (vierge) | L'utilisateur a confirmé "tout vierge depuis main". La branche `feat/step2-hexa-ddd-foundations` est stashée comme backup (`stash@{0}`). |
| Nom de branche | `feat/sebcode-foundation` | Reflète l'objectif global (fonder le projet), pas un step précis. |
| Emplacement docs | `App/docs/steps/STEP-NN-<slug>.md` | Un fichier par step, près de l'app. Plus lisible qu'un mega `STEPS.md`. |
| Langue docs | Français pour la doc utilisateur, anglais pour le code/commentaires | Préférence utilisateur (FR), conventions code (EN). |
| `.gitignore` racine | Réécrit (ancienne version ignorait tous les `.md`) | Permettre CLAUDE.md et docs. Ignorer `_opencode_ref/` + `opencode-dev.zip` (lourd, juste local). |

## Fichiers touchés

- ➕ `CLAUDE.md` (racine) — règles projet (archi, layers, naming, workflow).
- ➕ `App/docs/steps/STEP-01-bootstrap.md` (ce fichier).
- ✏️ `.gitignore` (racine) — propre, n'ignore plus les `.md`, ignore reference opencode.

## État de l'app sur `main` (point de départ)

À noter pour les steps suivants, sont déjà présents :

- `App/composer.json` : `symfony/ai-bundle ^0.9`, `symfony/ai-platform`, `symfony/ai-ollama-platform`, `symfony/ai-agent`, `symfony/tui ^8.1@beta`, `doctrine/orm ^3.6`, `symfony/messenger 8.1.*`, etc.
- `App/src/{Domain,Application,Infrastructure,UI}` : dossiers vides (squelette nu).
- `App/config/packages/` : `doctrine.yaml`, `messenger.yaml`, `ai.yaml`, `ai_ollama_platform.yaml`, `framework.yaml`, `lock.yaml`, etc. déjà présents.
- `docker-compose.yml` : `php`, `nginx`, `postgres:16`, `redis:7`.
- `App/.env` : `DATABASE_URL` Postgres, `MESSENGER_TRANSPORT_DSN` Redis, mais **pas encore** les vars Ollama (à ajouter en STEP-04).
- `App/vendor/` : composer install déjà passé.

⚠️ Ce qui **manque** par rapport à ce qu'il y avait sur `step2-hexa-ddd-foundations` :
- Vars Ollama dans `.env` (`OLLAMA_ENDPOINT`, `OLLAMA_HTTP_TIMEOUT`, `LLM_PLATFORM`, `LLM_MODEL`).
- `phpstan.neon.dist`, `.php-cs-fixer.dist.php`, `phpunit.dist.xml`.
- Scripts `composer cs/stan/test/qa`.
- Le contexte `Assistant/` entier.

À recréer dans les steps qui suivent.

## Comment vérifier

```bash
# Branche bien créée et active
git -C C:/Users/latai/Documents/Professionnel/SebCode branch --show-current
# → feat/sebcode-foundation

# Fichiers présents
ls CLAUDE.md
ls App/docs/steps/STEP-01-bootstrap.md
cat .gitignore   # ne contient plus *.md
```

## Step suivante

**STEP-02** — Bounded Context `Assistant` : Domain layer.

Créer les aggregates `Session` et `Message`, les Value Objects (`SessionId`, `MessageId`, `MessageRole`, `Content`, `ModelName`), les exceptions Domain, et les Ports (`SessionRepository`, `MessageRepository`, `LlmPort`, `Clock`). Pur PHP, zéro Symfony, zéro Doctrine. Tests unitaires PHPUnit en parallèle.
