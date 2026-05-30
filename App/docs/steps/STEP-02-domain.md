# STEP-02 — Bounded Context `Assistant` : Domain layer

**Statut :** ✅ terminé
**Branche :** `feat/sebcode-foundation`

## But

Poser le coeur métier du contexte `Assistant` (chat avec un LLM) **en pur PHP**, sans aucune dépendance à Symfony, Doctrine ou symfony/ai. Le tout couvert par des tests unitaires.

Inspiré de la sémantique d'opencode (`packages/opencode/src/session/`) :
- ID préfixés `ses_` (sessions) / `msg_` (messages) — comme opencode.
- Roles `user|assistant|system|tool`.
- Une session a un modèle (`ModelName`) attaché.

À ce stade on **n'a pas** les concepts opencode plus avancés (`PartID`, `SnapshotPart`, `PatchPart`, tool calls, streaming events…). On y reviendra une fois le MVP chat fonctionnel.

## Décisions clés

| Décision | Choix | Raison |
|---|---|---|
| Aggregates | `Session` (mutable) + `Message` (immutable) | Une session évolue (titre, archive), un message est figé une fois écrit. |
| VOs | `SessionId`, `MessageId`, `ModelName`, `MessageContent`, `MessageRole` (enum) | `final readonly class` PHP 8.4. Validation invariants dans le constructeur privé + factory `fromString` / `of`. |
| Préfixes ID | `ses_` / `msg_` validés au constructeur | Compat sémantique avec opencode (`ses`, `msg`, `prt`). Facilite le debug. |
| Génération d'ID | Port `IdGenerator` (interface) | Génération concrète dans Infrastructure (uuid v7 via symfony/uid). Garde Domain pur. |
| Horloge | Port `Clock` | Tests deterministes (FixedClock dans Infrastructure). |
| LLM | Port `LlmPort` + DTO `LlmReply` | Abstraction au-dessus de `symfony/ai-platform` — permet de switcher Ollama/OpenAI/Anthropic, et de mocker en tests Application. |
| Events Domain | **Reportés** | Pas d'events ni dispatcher pour l'instant — pas nécessaire pour le MVP. À ajouter quand on en aura besoin (notification SSE, audit, etc.). |
| `MessageContent` | Pas de validation "non vide" | Une réponse LLM peut légitimement être vide / espaces. Validation au boundary Application si nécessaire. |
| Exceptions | `SessionNotFound`, `LlmUnavailable`, `InvalidArgument` | Classes `RuntimeException` / `InvalidArgumentException` natives, namespaces métier. |

## Layout produit

```
App/src/Assistant/Domain/
├── Model/
│   ├── Session.php
│   ├── Message.php
│   └── ValueObject/
│       ├── SessionId.php
│       ├── MessageId.php
│       ├── MessageRole.php       (enum)
│       ├── MessageContent.php
│       └── ModelName.php
├── Port/
│   ├── Clock.php
│   ├── IdGenerator.php
│   ├── SessionRepository.php
│   ├── MessageRepository.php
│   ├── LlmPort.php
│   └── LlmReply.php              (DTO de réponse LLM)
└── Exception/
    ├── SessionNotFound.php
    ├── LlmUnavailable.php
    └── InvalidArgument.php
```

## Tests

```
App/tests/Unit/Assistant/Domain/
└── Model/
    ├── SessionTest.php
    ├── MessageTest.php
    └── ValueObject/
        ├── SessionIdTest.php
        ├── MessageIdTest.php
        ├── ModelNameTest.php
        └── MessageContentTest.php
```

Résultat : **22 tests, 40 assertions, OK** (`docker compose exec php vendor/bin/phpunit --testsuite Unit`).

## Tooling ajouté

| Fichier | Rôle |
|---|---|
| `App/composer.json` | + `phpunit/phpunit ^11.5.55` + `symfony/phpunit-bridge ^8.1` en `require-dev`. Scripts `composer test` / `composer test:unit`. |
| `App/phpunit.dist.xml` | Config PHPUnit 11 (suites Unit + Integration, source coverage cible `src/`). |
| `App/tests/bootstrap.php` | Bootstrap Symfony dotenv pour env test. |
| `App/.env.test` | Recipe symfony/phpunit-bridge (KERNEL_CLASS, APP_SECRET). |
| `App/bin/phpunit` | Recipe phpunit/phpunit (entrée wrapper). |
| `App/.gitignore` | + ignore `phpunit.xml`, `.phpunit.cache/`. |

## Règles d'invariant respectées (CLAUDE.md §3.2)

```bash
# Aucun import Symfony/Doctrine dans Domain/
grep -RnE "^use (Symfony|Doctrine)" App/src/Assistant/Domain/   # → vide
```

## Comment vérifier

```bash
docker compose exec php vendor/bin/phpunit --testsuite Unit
# → OK (22 tests, 40 assertions)
```

## Step suivante

**STEP-03** — Application layer. Use cases :
- `StartSessionCommand` / `StartSessionHandler` → utilise `IdGenerator` + `Clock` + `SessionRepository`.
- `SendMessageCommand` / `SendMessageHandler` → enregistre le message user, appelle `LlmPort.complete()` avec l'historique, enregistre la réponse assistant, retourne le couple.
- `GetSessionMessagesQuery` / `GetSessionMessagesHandler` → lecture pure via `MessageRepository`.

Toujours zéro dépendance Symfony/Doctrine. Tests unitaires avec doubles in-memory des Ports.
