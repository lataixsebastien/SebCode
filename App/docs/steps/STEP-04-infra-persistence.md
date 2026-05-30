# STEP-04 — Infrastructure : Clock, IdGenerator, Doctrine persistence

**Statut :** ✅ terminé
**Branche :** `feat/sebcode-foundation`

## But

Câbler le contexte `Assistant` à des adapters réels :

- `SystemClock` (horloge système).
- `SymfonyUidGenerator` (ids préfixés type `ses_`/`msg_` + UUID v7 base58).
- Persistance Postgres via Doctrine ORM, **avec dédoublement Aggregate / Entity Doctrine** ([ADR-0003](../adr/0003-aggregate-vs-entity-separation.md)).
- Wiring DI : tous les Ports Domain bindés à leurs adapters Infrastructure.

L'adapter LLM (Ollama via `symfony/ai-platform`) est traité séparément en **STEP-05**.

## Décisions clés

| Décision | Choix | Raison |
|---|---|---|
| Aggregate ↔ Entity | Classes séparées + Mapper | Domain reste pur PHP ([ADR-0003](../adr/0003-aggregate-vs-entity-separation.md)) |
| Type ID en DB | `varchar(64)` | Les `SessionId`/`MessageId` sont des strings préfixés (`ses_`, `msg_`) — type natif string, pas UUID Postgres |
| Type timestamp | `datetimetz_immutable` → `TIMESTAMP(0) WITH TIME ZONE` | Garde le fuseau ; précision seconde suffisante pour un chat |
| FK `messages → sessions` | **Aucune FK** au niveau ORM | Volontaire : on garde la flexibilité de purge/archivage indépendant ; la cohérence est garantie côté Application |
| Index | `assistant_messages (session_id, created_at)` | Couvre la query unique `forSession()` qui fait `WHERE session_id = ? ORDER BY created_at` |
| Génération id | UUID v7 base58, préfixe `ses_`/`msg_` | UUIDv7 est time-ordered → tri chronologique gratuit ; base58 plus court qu'hex (~21 chars vs 36) |
| Migration | Editée à la main après `migrations:diff` (ordre des CREATE, description, suppression des comments générés) | Lisibilité / pas de bruit |
| Tests intégration Postgres | **Reportés à STEP-07 (QA)** | Mapper testé en unit (round-trip), repos testés indirectement par le boot du conteneur Symfony (`lint:container`). Tests d'intégration DB demandent setup TestKernel + drop/create schema — fait dans STEP-07 quand on configure le harness QA complet. |

## Layout produit

```
App/src/Assistant/Infrastructure/
├── Clock/
│   └── SystemClock.php                          (implements Clock)
├── Id/
│   └── SymfonyUidGenerator.php                  (implements IdGenerator, uses symfony/uid v7)
└── Persistence/Doctrine/
    ├── Entity/
    │   ├── SessionEntity.php                    (POPO Doctrine)
    │   └── MessageEntity.php
    ├── Mapper/
    │   ├── SessionMapper.php                    (Aggregate ↔ Entity)
    │   └── MessageMapper.php
    └── Repository/
        ├── DoctrineSessionRepository.php        (implements SessionRepository)
        └── DoctrineMessageRepository.php        (implements MessageRepository)
```

```
App/tests/Unit/Assistant/Infrastructure/
├── Clock/SystemClockTest.php
├── Id/SymfonyUidGeneratorTest.php
└── Persistence/Doctrine/Mapper/
    ├── SessionMapperTest.php
    └── MessageMapperTest.php
```

## Modifications config

### `config/packages/doctrine.yaml`

- `auto_mapping: false` (déjà).
- Mapping unique nommé `Assistant` qui pointe sur `src/Assistant/Infrastructure/Persistence/Doctrine/Entity` / namespace `App\Assistant\Infrastructure\Persistence\Doctrine\Entity`.

### `config/services.yaml`

- Resource `App\:` avec `exclude:` pour ne pas registrer les "non-services" (DTO, VO, Entity, Aggregate, Exception, Kernel).
- 4 alias explicites : chaque Port Domain pointe sur son adapter Infrastructure.

## Schema DB

```sql
CREATE TABLE assistant_sessions (
    id           VARCHAR(64)              NOT NULL,
    model_name   VARCHAR(128)             NOT NULL,
    title        VARCHAR(255)             NOT NULL,
    created_at   TIMESTAMP(0) WITH TZ     NOT NULL,
    updated_at   TIMESTAMP(0) WITH TZ     NOT NULL,
    archived     BOOLEAN  DEFAULT false   NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE assistant_messages (
    id           VARCHAR(64)              NOT NULL,
    session_id   VARCHAR(64)              NOT NULL,
    role         VARCHAR(16)              NOT NULL,
    content      TEXT                     NOT NULL,
    created_at   TIMESTAMP(0) WITH TZ     NOT NULL,
    PRIMARY KEY (id)
);

CREATE INDEX idx_msg_session_created
    ON assistant_messages (session_id, created_at);
```

## Comment vérifier

```bash
# Reset clean (la DB peut contenir d'anciennes tables du précédent branche)
docker compose exec postgres psql -U app -d app -c \
  'DROP SCHEMA public CASCADE; CREATE SCHEMA public;'

# Migration appliquée
docker compose exec php bin/console doctrine:migrations:migrate -n
# → Successfully migrated to version: Version20260530121839

# Schema en place
docker compose exec postgres psql -U app -d app -c '\dt'
# → assistant_sessions, assistant_messages, doctrine_migration_versions

# DI / wiring OK
docker compose exec php bin/console lint:container
# → [OK] The container was linted successfully

# Tests unit (round-trip Mapper inclus)
docker compose exec php vendor/bin/phpunit --testsuite Unit
# → OK (45 tests, 118 assertions)
```

## Garde-fou hexa respecté

```bash
grep -RnE "^use (Symfony|Doctrine)" App/src/Assistant/Domain App/src/Assistant/Application
# → vide
```

Le Domain et l'Application restent totalement étrangers à Doctrine / Symfony, même après l'arrivée de la persistance.

## Step suivante

**STEP-05** — `Infrastructure/Llm/SymfonyAiOllamaAdapter` : implémenter `LlmPort` via `symfony/ai-platform` + `symfony/ai-ollama-platform`. Traduire `list<Message>` Domain → `Symfony\AI\Platform\Message\MessageBag` (UserMessage/AssistantMessage/SystemMessage), invoke `qwen2.5:3b` côté Ollama (`host.docker.internal:11434`), récupérer le `TextResult`. Gérer `LlmUnavailable` sur les erreurs réseau / 5xx.
