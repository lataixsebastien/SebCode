# CLAUDE.md — Consignes projet SebCode

> Ce fichier est lu automatiquement par Claude Code à chaque session. Toute modification ici s'applique au prochain run.

## 1. Mission du projet

SebCode est une **réimplémentation de [opencode](https://opencode.ai)** (agent IA de coding originellement écrit en TypeScript) sur la stack **Symfony 8.1** avec :

- **Symfony AI bundle** (`symfony/ai-bundle`, `symfony/ai-platform`, `symfony/ai-agent`, `symfony/ai-ollama-platform`) — moteur LLM, agents, tools.
- **Symfony TUI** (`symfony/tui ^8.1@beta`) — UI terminal interactive type opencode.
- **Architecture hexagonale + DDD tactique** — un *Bounded Context* par grand domaine fonctionnel.
- **Doctrine ORM + PostgreSQL** pour la persistance ; **Redis** pour Messenger / cache.

Le code source d'opencode est dépaqueté localement dans `_opencode_ref/opencode-dev/` (gitignored). C'est la **référence fonctionnelle** : quand on implémente une feature, on regarde d'abord ce que fait opencode dans `packages/opencode/src/<module>` avant de coder.

## 2. Workflow obligatoire

### 2.1. Markdown par step

**Pour chaque étape de travail**, créer/maintenir un fichier `App/docs/steps/STEP-<NN>-<slug>.md` avec :

- **But** — ce qu'on cherche à obtenir.
- **Décisions clés** — choix d'archi/lib, alternatives écartées.
- **Fichiers touchés** — ajoutés / modifiés / supprimés.
- **Comment vérifier** — commandes pour valider (composer, phpunit, docker, etc.).
- **Step suivante** — pointeur vers le prochain fichier.

Mettre à jour le markdown **pendant** le step, pas seulement à la fin. C'est un journal de bord.

### 2.2. Branches & commits

- Une branche par initiative : `feat/<slug>`, `fix/<slug>`, `chore/<slug>`.
- Commits petits, atomiques, message impératif en anglais.
- Pas de force-push sans demande explicite.

### 2.3. Tasks Claude Code

Utiliser `TaskCreate` / `TaskUpdate` pour suivre les steps (un task par STEP-XX).

## 3. Architecture hexagonale + DDD

### 3.1. Layout par Bounded Context

```
App/src/<Context>/
├── Domain/                # Pur PHP, zéro framework
│   ├── Model/             # Aggregates, Entities, Value Objects
│   ├── Event/             # Domain Events
│   ├── Exception/         # Domain Exceptions
│   └── Port/              # Interfaces (Repository, Service, ...)
│
├── Application/           # Use cases (orchestration), pur PHP
│   ├── Command/           # CommandHandler (mutation)
│   ├── Query/             # QueryHandler (lecture)
│   ├── Dto/               # DTOs entrée/sortie
│   └── Service/           # Services applicatifs si nécessaire
│
├── Infrastructure/        # Adapters concrets (Symfony, Doctrine, HTTP, ...)
│   ├── Persistence/
│   │   └── Doctrine/      # Entity, Repository impl, migrations
│   ├── Llm/               # Adapters LLM (Ollama, OpenAI, ...)
│   ├── Messaging/         # Messenger transports/handlers
│   └── Symfony/           # DI extensions, compiler passes
│
└── UI/                    # Points d'entrée
    ├── Cli/               # Commandes console one-shot
    ├── Tui/               # Composants & commandes TUI
    └── Http/              # Controllers, requests, responses
```

**Contextes prévus** (créés au fil de l'eau) :
- `Assistant` — chat, sessions, messages, LLM
- `Tool` — read/write/edit/shell/glob/grep/lsp/webfetch (plus tard)
- `Agent` — orchestration multi-tool (plus tard)
- `Workspace` — gestion projet/git/snapshot (plus tard)

### 3.2. Règles de dépendances (CRITIQUE)

| Layer | Peut dépendre de |
|---|---|
| `Domain` | **Rien d'autre que PHP stdlib + autres Domain du même contexte** |
| `Application` | `Domain` (même contexte) |
| `Infrastructure` | `Domain`, `Application` (même contexte) + librairies (Symfony, Doctrine, AI bundle, etc.) |
| `UI` | `Application`, `Domain` (jamais Infrastructure directement — passe par Ports) |

- **JAMAIS** d'`use Symfony\…` ou `use Doctrine\…` dans `Domain/` ou `Application/`.
- **JAMAIS** d'`use Entity\…` (Doctrine) dans `Domain/` — les aggregates sont des classes pures, séparées des entities Doctrine.
- Communication inter-contextes : via interfaces dans le `Domain` du contexte appelant, implémentations dans `Infrastructure`.

### 3.3. Naming

- Aggregates / Entities : `Session`, `Message`, `Task`.
- Value Objects : `SessionId`, `MessageRole`, `ModelName` (immutables, `readonly`).
- Ports : `SessionRepository`, `LlmPort`, `Clock` (interface).
- Adapters : `DoctrineSessionRepository`, `SymfonyAiOllamaAdapter`, `SystemClock`.
- Commands/Queries : `StartSessionCommand`, `SendMessageCommand`, `GetSessionMessagesQuery`.
- Handlers : `StartSessionHandler`, `SendMessageHandler`.
- Exceptions : `SessionNotFound`, `LlmUnavailable` (pas de suffixe `Exception` dans `Domain`).

### 3.4. Tests

- `App/tests/Unit/<Context>/Domain/...` — pur PHPUnit, zéro DB, zéro Symfony.
- `App/tests/Unit/<Context>/Application/...` — handlers avec doubles des Ports.
- `App/tests/Integration/<Context>/Infrastructure/...` — Doctrine + Postgres réel (via Docker).
- Pas de mock du DB en intégration : test sur Postgres réel.

## 4. Stack technique

### 4.1. Versions

- PHP `^8.4`.
- Symfony `8.1.*` (composants principaux), `^8.1@beta` pour `symfony/tui`.
- Doctrine ORM `^3.6`.
- PostgreSQL 16 (Docker).
- Redis 7 (Docker, pour Messenger).
- Ollama externe (host : `host.docker.internal:11434`).

### 4.2. Symfony AI

- Plateforme par défaut : **Ollama** (gratuite, locale). Modèle par défaut : `qwen2.5:3b` (rapide pour dev).
- Variables d'env : `LLM_PLATFORM`, `LLM_MODEL`, `OLLAMA_ENDPOINT`, `OLLAMA_HTTP_TIMEOUT`.
- Wrapper interne `LlmPort` (Domain) pour isoler du `symfony/ai-platform`. Permet de mocker en test et de switcher de provider.

### 4.3. Symfony TUI

- Une commande Symfony par "écran" principal (`assistant:tui`).
- Composants réutilisables dans `<Context>/UI/Tui/Component/`.
- Logique métier reste dans Application — le TUI ne fait que du rendering + dispatch.

### 4.4. Doctrine

- Mapping **attributs** (pas XML).
- Entities Doctrine **séparées** des aggregates Domain : mapping dans `Infrastructure/Persistence/Doctrine/Entity/`, traduction Aggregate ↔ Entity dans le Repository.
- Migrations dans `App/migrations/`.

## 5. Qualité (QA)

```bash
composer cs        # php-cs-fixer dry-run
composer cs:fix    # php-cs-fixer fix
composer stan      # phpstan level max
composer test      # phpunit unit + integration
composer qa        # tous les checks
```

Aucun commit ne doit casser `composer qa`. CI fail = blocker.

## 6. Docker

```bash
docker compose up -d                                          # start postgres + redis + php + nginx
docker compose exec php composer install                      # deps PHP
docker compose exec php bin/console doctrine:migrations:migrate
docker compose exec php bin/console assistant:ask "hello"     # CLI test
docker compose exec php bin/console assistant:tui             # TUI
```

L'app PHP est mountée dans `/var/www/App` côté container.

## 7. Référence opencode

Quand on veut implémenter une feature, regarder d'abord :

| Feature SebCode | Module opencode de réf |
|---|---|
| Session/Message | `_opencode_ref/opencode-dev/packages/opencode/src/session/` |
| Tools | `_opencode_ref/opencode-dev/packages/opencode/src/tool/` |
| Provider/LLM | `_opencode_ref/opencode-dev/packages/opencode/src/provider/` |
| Agent loop | `_opencode_ref/opencode-dev/packages/opencode/src/agent/` |
| TUI | `_opencode_ref/opencode-dev/packages/console/app/src/` |
| Server HTTP | `_opencode_ref/opencode-dev/packages/opencode/src/server/` |
| MCP | `_opencode_ref/opencode-dev/packages/opencode/src/mcp/` |

**On ne copie pas le code TS** — on s'inspire de la sémantique (champs, événements, états) et on adapte en PHP idiomatique.

## 8. Anti-patterns à éviter

- ❌ Mettre la logique métier dans un Controller / Command Symfony.
- ❌ Importer Doctrine ou Symfony dans `Domain/` ou `Application/`.
- ❌ Utiliser les Entities Doctrine comme aggregates Domain.
- ❌ Sur-ingéniérer : pas de CQRS bus complet, pas d'event sourcing tant que pas nécessaire.
- ❌ Skip les tests "parce que c'est petit".
- ❌ Commit sans mettre à jour `App/docs/steps/STEP-XX.md`.

## 9. Pour Claude (toi)

- Tu travailles **step par step**, un STEP-XX.md par grosse phase. Tu mets à jour le markdown au fur et à mesure.
- Tu **n'inventes pas** de chemins ou de fonctions opencode : tu vérifies dans `_opencode_ref/` avant.
- Tu **demandes** si une décision d'archi a plusieurs options viables (storage, naming, etc.).
- Tu écris les **conventions code en anglais**, mais la **doc utilisateur (CLAUDE.md, STEP-XX.md, ASSISTANT_RUNBOOK) en français**.
- Tu commit en français ou anglais, peu importe, mais reste cohérent dans un même commit.
