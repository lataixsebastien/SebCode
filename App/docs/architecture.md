# Architecture SebCode — Hexagonale + DDD

## 1. Objectif

Construire un agent IA de coding (équivalent fonctionnel d'opencode) dans une codebase PHP **durable, testable, et indépendante du framework** au cœur. Symfony, Doctrine et Symfony AI sont des outils, pas des dépendances métier.

## 2. Layout général

```
App/src/
└── <BoundedContext>/        # un dossier par contexte métier
    ├── Domain/              # cœur métier — pur PHP
    │   ├── Model/           # Aggregates, Entities, Value Objects
    │   │   └── ValueObject/
    │   ├── Port/            # interfaces que le domaine "demande" au monde
    │   └── Exception/
    │
    ├── Application/         # use cases — orchestration pure
    │   ├── Command/         # mutations (Cmd + Handler)
    │   ├── Query/           # lectures (Query + Handler)
    │   └── Dto/             # DTOs d'entrée/sortie
    │
    ├── Infrastructure/      # adapters concrets — Symfony/Doctrine/HTTP
    │   ├── Persistence/Doctrine/
    │   ├── Llm/             # adapters LLM (Ollama, OpenAI, ...)
    │   ├── Messaging/       # messenger handlers/transports
    │   ├── Clock/           # SystemClock
    │   ├── Id/              # SymfonyUidGenerator
    │   └── Symfony/         # bundles, DI extensions
    │
    └── UI/                  # points d'entrée
        ├── Cli/             # commandes console one-shot
        ├── Tui/             # commandes TUI symfony/tui
        └── Http/            # controllers, requests, responses
```

**Bounded Contexts prévus (créés au fil de l'eau) :**

| Contexte | Rôle | Statut |
|---|---|---|
| `Assistant` | chat avec un LLM, sessions, messages | ✅ foundation complète (Domain/Application/Infra/UI CLI+TUI) |
| `Tool` | outils du modèle : read/write/edit/shell/glob/grep/lsp/webfetch | 🟡 Domain + Application ✅ STEP-09 ; outils concrets STEP-10 |
| `Agent` | boucle d'orchestration multi-tool, sub-agents | à venir (la boucle vit dans `Assistant/Application` pour l'instant — ADR-0005) |
| `Workspace` | projet courant, git, snapshot | à venir |

## 3. Layers et règles de dépendance

```
            ┌──────────────┐
            │      UI      │  ─┐
            └──────┬───────┘   │
                   │           │
            ┌──────▼───────┐   │ peut dépendre de
            │  Application │   │
            └──────┬───────┘   │
                   │           │
            ┌──────▼───────┐   ▼
            │    Domain    │  ← pur PHP, ne dépend de rien
            └──────────────┘
                   ▲
                   │ implémente les Ports du Domain
            ┌──────┴───────┐
            │ Infrastructure│
            └──────────────┘
```

| Layer | Peut importer | Ne peut PAS importer |
|---|---|---|
| **Domain** | PHP stdlib uniquement | Symfony, Doctrine, AI bundle, autres contextes |
| **Application** | `Domain` (même contexte) | Symfony, Doctrine, Infrastructure |
| **Infrastructure** | `Domain` + `Application` du même contexte, n'importe quelle lib externe | UI |
| **UI** | `Application` + `Domain` (lecture VOs), services Symfony DI standards | `Infrastructure` directement (passe par Ports + DI) |

**Comment c'est appliqué :**

```bash
# Sanity check : aucun import framework dans Domain ou Application
grep -RnE "^use (Symfony|Doctrine)" App/src/*/Domain/ App/src/*/Application/
# → doit toujours retourner vide
```

À terme un PHPStan rule ou Deptrac fera respecter ça automatiquement (STEP-06).

## 4. Patterns appliqués

### 4.1 Aggregates DDD

- **Session** (mutable) : root d'agrégat, gère ses invariants (titre, état archivé, updatedAt).
- **Message** (immutable, `final readonly`) : append-only, jamais modifié après création.

Les aggregates **ne sont pas** des entities Doctrine — la traduction se fait dans `Infrastructure/Persistence/Doctrine/Repository/`.

### 4.2 Value Objects

- `final readonly class` PHP 8.4, constructeur privé + factory statique (`fromString`, `of`).
- Validation invariants dans le constructeur.
- `equals(self)` pour comparaison structurelle.
- `__toString()` quand pertinent (id, name).

### 4.3 Ports (Hexagonal)

Interfaces déclarées dans `Domain/Port/`, implémentations dans `Infrastructure/`. Le Domain ne connaît jamais l'implémentation.

| Port | Rôle | Adapter prévu |
|---|---|---|
| `Clock` | horloge testable | `SystemClock` (Infra) |
| `IdGenerator` | générer SessionId/MessageId | `SymfonyUidGenerator` (uuid v7, Infra) |
| `SessionRepository` | persister/charger Session | `DoctrineSessionRepository` (Infra) |
| `MessageRepository` | persister/charger Message | `DoctrineMessageRepository` (Infra) |
| `LlmPort` | appeler un LLM | `SymfonyAiOllamaAdapter` (Infra, basé sur `symfony/ai-platform`) |

### 4.4 Use Cases (Application)

Pattern Command/Query séparés :

- **Commands** mutent l'état (write side). Un `XxxCommand` (DTO immuable) + un `XxxHandler` (orchestrateur).
- **Queries** lisent l'état (read side). Un `XxxQuery` + un `XxxHandler` qui retourne un DTO de vue.

Au début, on **n'utilise pas** un bus Messenger : les handlers sont instanciés et appelés directement par l'UI (autowire). On ajoutera Messenger quand on aura besoin d'async / queue (workers, tools longs, etc.).

### 4.5 UI

- **CLI** : `Symfony\Component\Console\Command\Command` qui appelle un Handler Application.
- **TUI** : composant `symfony/tui` qui dispatch événements clavier vers des Commands.
- **HTTP** (plus tard) : controllers REST/SSE.

Le code UI **ne fait pas** de logique métier. Il traduit input utilisateur → Command Application, et présente le résultat.

## 5. Stack technique

| Couche | Outil |
|---|---|
| Langage | PHP 8.4 |
| Framework | Symfony 8.1.* (composants principaux) |
| TUI | `symfony/tui ^8.1@beta` |
| LLM | `symfony/ai-bundle ^0.9` + `symfony/ai-ollama-platform` |
| ORM | Doctrine ORM 3.6 (mapping attributs) |
| DB | PostgreSQL 16 (Docker) |
| Queue | Redis (Docker, transport Messenger) |
| LLM runtime | Ollama externe (host : `host.docker.internal:11434`) |
| Tests | PHPUnit 11.5 |
| QA (à venir) | PHPStan max + php-cs-fixer + Deptrac |

## 6. Inspiration opencode

opencode (TS) sert de **référence fonctionnelle**, pas de modèle d'implémentation. Mapping :

| Module opencode (TS) | Équivalent SebCode (PHP) |
|---|---|
| `packages/opencode/src/session/` | `Assistant/` (Domain + Application + Infra Doctrine) |
| `packages/opencode/src/tool/` | `Tool/` |
| `packages/opencode/src/agent/` | `Agent/` |
| `packages/opencode/src/provider/` | `Assistant/Infrastructure/Llm/` (port `LlmPort`) |
| `packages/opencode/src/server/` | `*/UI/Http/` (controllers Symfony) |
| `packages/console/app/src/` (TUI) | `*/UI/Tui/` (symfony/tui) |
| `packages/opencode/src/mcp/` | à voir — futur bounded context `Mcp/` |

On lit la source TS pour comprendre **quels champs / quels événements / quelle séquence**, puis on conçoit l'équivalent PHP idiomatique.
