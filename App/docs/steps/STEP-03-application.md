# STEP-03 — Application layer : Use cases CQRS

**Statut :** ✅ terminé
**Branche :** `feat/sebcode-foundation`

## But

Écrire la couche **Application** du contexte `Assistant` : trois use cases CQRS exposés comme des `Command`/`Query` + `Handler` invokables, sans dépendance Symfony/Doctrine et sans wiring Messenger.

Objectif : pouvoir dire en pseudo-code, depuis n'importe quelle UI (CLI, TUI, HTTP) :

```php
$sessionId = ($startSession)(new StartSessionCommand($model, $title));
$result    = ($sendMessage)(new SendMessageCommand($sessionId, 'Bonjour'));
$history   = ($getMessages)(new GetSessionMessagesQuery($sessionId));
```

## Décisions clés

| Décision | Choix | Raison |
|---|---|---|
| Naming | `XxxCommand` / `XxxQuery` + `XxxHandler` | Convention CQRS. **Pas d'attribut Symfony Messenger** (voir [ADR-0002](../adr/0002-no-messenger-wiring.md)). |
| Handler shape | `final readonly class` avec `__invoke()` | Simple à autowire, simple à tester. Constructeur = dépendances (Ports), `__invoke($command)` = exécution. |
| Retour des Handlers | Types Domain directs (`SessionId`, `Message`) ou DTO `Application/Dto/` (`SendMessageResult`) | UI a le droit de manipuler des VOs Domain. DTO seulement quand on agrège plusieurs valeurs. |
| Validation user input | Au tout début du Handler (ex : `trim($userText) === ''` → `InvalidArgument`) | Domain n'a pas à valider que `MessageContent` n'est pas vide (un LLM peut répondre du vide). C'est un invariant **applicatif**, pas domain. |
| Échec LLM | On lève `LlmUnavailable`, **on garde le message utilisateur persisté** | Permet le retry sans perdre la saisie. Testé. |
| Doubles de test | `App/tests/Support/Assistant/Doubles/` (autoload-dev `App\Tests\`) | Fakes réutilisables sur plusieurs tests : `FixedClock`, `SequenceIdGenerator`, `InMemorySessionRepository`, `InMemoryMessageRepository`, `ScriptedLlm`. |

## Layout produit

```
App/src/Assistant/Application/
├── Command/
│   ├── StartSessionCommand.php
│   ├── StartSessionHandler.php
│   ├── SendMessageCommand.php
│   └── SendMessageHandler.php
├── Query/
│   ├── GetSessionMessagesQuery.php
│   └── GetSessionMessagesHandler.php
└── Dto/
    └── SendMessageResult.php
```

```
App/tests/Support/Assistant/Doubles/
├── FixedClock.php
├── SequenceIdGenerator.php
├── InMemorySessionRepository.php
├── InMemoryMessageRepository.php
└── ScriptedLlm.php

App/tests/Unit/Assistant/Application/
├── Command/
│   ├── StartSessionHandlerTest.php
│   └── SendMessageHandlerTest.php
└── Query/
    └── GetSessionMessagesHandlerTest.php
```

## Pipeline `SendMessage` (le use case central)

```
SendMessageCommand(sessionId, userText)
        │
        ▼
[1] trim(userText) vide ? ──► throw InvalidArgument
        │
        ▼
[2] sessions.findById(sessionId) ──► null ? throw SessionNotFound
        │
        ▼
[3] append user Message  (role=user, content=userText, id via IdGenerator, at via Clock)
        │
        ▼
[4] history = messages.forSession(sessionId)   (inclut le user msg juste écrit)
        │
        ▼
[5] reply = llm.complete(session.model, history)   ─X─► throw LlmUnavailable
        │                                              (user msg reste persisté)
        ▼
[6] append assistant Message  (role=assistant, content=reply.content)
        │
        ▼
[7] session.touch(now) ; sessions.save(session)
        │
        ▼
return SendMessageResult { userMessage, assistantMessage, promptTokens?, completionTokens? }
```

## Tooling

- `composer.json` : ajout `autoload-dev` `App\Tests\` → `tests/` (nécessaire pour les Doubles).
- `composer dump-autoload` à passer après ajout.

## Comment vérifier

```bash
docker compose exec php vendor/bin/phpunit --testsuite Unit
# → OK (33 tests, 78 assertions) — 11 nouveaux tests Application
```

Tests Application couverts :
- `StartSessionHandlerTest` : crée + persiste ; ids séquentiels.
- `SendMessageHandlerTest` : happy path, historique passé au LLM, 2e tour avec historique complet, bump `updatedAt`, `SessionNotFound`, `InvalidArgument` sur vide, `LlmUnavailable` propagé sans perdre user msg.
- `GetSessionMessagesHandlerTest` : tri chrono ASC, isolation par session, liste vide ok.

## Garde-fou hexa respecté

```bash
grep -RnE "^use (Symfony|Doctrine)" App/src/Assistant/Application/   # → vide
```

## Step suivante

**STEP-04** — Infrastructure :

- `SystemClock` (implémente `Clock`, basé sur `\DateTimeImmutable`).
- `SymfonyUidGenerator` (implémente `IdGenerator`, basé sur `Symfony\Component\Uid\Uuid::v7()`).
- Entities Doctrine `SessionEntity` + `MessageEntity` séparées des aggregates Domain — mapping par attributs, dans `Infrastructure/Persistence/Doctrine/Entity/`.
- `DoctrineSessionRepository` + `DoctrineMessageRepository` (translation Aggregate ↔ Entity).
- Première migration Postgres.
- `SymfonyAiOllamaAdapter` implémente `LlmPort` via `symfony/ai-platform` + `symfony/ai-ollama-platform`.
- Wiring `config/services.yaml` pour binder les Ports → leurs adapters.
- Tests d'intégration (suite `Integration`) sur Postgres réel via Docker.
