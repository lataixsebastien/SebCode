# Bounded Context — `Assistant`

> Conversation avec un LLM : sessions, messages, completion. Cœur fonctionnel de SebCode.

**Statut d'avancement :**

| Layer | Statut |
|---|---|
| Domain | ✅ STEP-02 |
| Application | ✅ STEP-03 |
| Infrastructure persistence | ✅ STEP-04 |
| Infrastructure LLM (Ollama) | ⏳ STEP-05 |
| UI (CLI + TUI) | ⏳ STEP-06 |

---

## 1. Domain (`App/src/Assistant/Domain/`)

### 1.1 Aggregates

#### `Session` (mutable)

Root d'agrégat pour une conversation.

| Champ | Type | Note |
|---|---|---|
| `id` | `SessionId` | readonly, préfixe `ses_` |
| `model` | `ModelName` | readonly, modèle LLM associé |
| `title` | `string` | mutable via `rename()` |
| `createdAt` | `\DateTimeImmutable` | readonly |
| `updatedAt` | `\DateTimeImmutable` | mutable via `rename`/`archive`/`touch` |
| `archived` | `bool` | mutable via `archive()` |

**API :**

```php
Session::start(SessionId, ModelName, string $title, \DateTimeImmutable $now): self
$session->rename(string $title, \DateTimeImmutable $now): void
$session->archive(\DateTimeImmutable $now): void
$session->touch(\DateTimeImmutable $now): void        // bump updatedAt sans changement
$session->title(): string
$session->updatedAt(): \DateTimeImmutable
$session->isArchived(): bool
```

**Invariants :** à la création, `createdAt === updatedAt`. Toute mutation met à jour `updatedAt`.

#### `Message` (immutable)

Entry append-only dans une session.

| Champ | Type |
|---|---|
| `id` | `MessageId` (`msg_*`) |
| `sessionId` | `SessionId` |
| `role` | `MessageRole` (enum user/assistant/system/tool) |
| `content` | `MessageContent` |
| `createdAt` | `\DateTimeImmutable` |

Pas d'API mutable — `final readonly class`.

### 1.2 Value Objects

| VO | Invariants |
|---|---|
| `SessionId` | string, préfixe `ses_`, corps non vide. Factory `fromString(string)`. `equals(self)`. |
| `MessageId` | idem avec préfixe `msg_`. |
| `MessageRole` | enum backed string : `user|assistant|system|tool`. |
| `MessageContent` | wrap d'un string brut. Pas de validation "non vide" (un assistant peut répondre du vide). `isEmpty()` pour check explicite. |
| `ModelName` | string non vide (trim). Factory `of(string)`. `equals(self)`. |

### 1.3 Ports (interfaces)

| Port | Méthodes |
|---|---|
| `Clock` | `now(): \DateTimeImmutable` |
| `IdGenerator` | `nextSessionId(): SessionId`, `nextMessageId(): MessageId` |
| `SessionRepository` | `save`, `findById`, `all`, `delete` |
| `MessageRepository` | `append`, `forSession(SessionId): list<Message>` (ordre chrono ASC) |
| `LlmPort` | `complete(ModelName, list<Message>): LlmReply` — peut lever `LlmUnavailable` |

`LlmReply` (DTO) : `content: string`, `promptTokens?: int`, `completionTokens?: int`.

### 1.4 Exceptions

- `SessionNotFound::withId(SessionId)`
- `LlmUnavailable::fromUpstream(string $reason, ?\Throwable $previous)`
- `InvalidArgument` (générique métier)

### 1.5 Garanties

- Aucun `use Symfony\…` / `use Doctrine\…` dans `Domain/`.
- Aucun event dispatcher pour l'instant — sera ajouté si besoin.
- Tous les VOs / aggregates sont pleinement testés en pur PHP (22 tests, STEP-02).

---

## 2. Application (`App/src/Assistant/Application/`)

Trois use cases CQRS, **pas d'attribut Symfony Messenger** : ce sont de simples classes invokables instanciées par autowire. Cf. [ADR-0002](../adr/0002-no-messenger-wiring.md).

### 2.1 `StartSession`

```php
final readonly class StartSessionCommand {
    public ModelName $model;
    public string $title;
}

(new StartSessionHandler($sessions, $ids, $clock))($command): SessionId
```

- Crée un `Session` via `Session::start()` (timestamp via `Clock`, id via `IdGenerator`).
- Persiste via `SessionRepository::save()`.
- Retourne le `SessionId` créé.

### 2.2 `SendMessage`

```php
final readonly class SendMessageCommand {
    public SessionId $sessionId;
    public string $userText;     // non vide (validé)
}

(new SendMessageHandler($sessions, $messages, $llm, $ids, $clock))($command): SendMessageResult
```

Pipeline :

1. Validation : `$userText` non vide (trim) → sinon `InvalidArgument`.
2. Charge la session via `SessionRepository::findById()` → sinon `SessionNotFound`.
3. Crée et persiste le `Message` utilisateur (`MessageRole::User`).
4. Recharge l'historique complet via `MessageRepository::forSession()`.
5. Appelle `LlmPort::complete($session->model, $history)` → `LlmReply`.
6. Si l'appel LLM échoue (`LlmUnavailable`), **le message utilisateur reste persisté** (retry possible).
7. Crée et persiste le `Message` assistant (`MessageRole::Assistant`).
8. `$session->touch()` puis `SessionRepository::save()` (bump `updatedAt`).
9. Retourne `SendMessageResult { userMessage, assistantMessage, promptTokens?, completionTokens? }`.

### 2.3 `GetSessionMessages`

```php
final readonly class GetSessionMessagesQuery {
    public SessionId $sessionId;
}

(new GetSessionMessagesHandler($messages))($query): list<Message>
```

Lecture pure, ordre chronologique ASC. Liste vide si la session n'a pas de messages (pas d'erreur).

### 2.4 DTOs

- `Application/Command/*Command.php` — DTO d'intention (entrée).
- `Application/Query/*Query.php` — DTO de lecture (entrée).
- `Application/Dto/SendMessageResult.php` — DTO de retour.
- Les Handlers retournent directement des types `Domain` (`SessionId`, `Message`) ou des DTO `Application/Dto/`.

### 2.5 Doubles de test

Sous `App/tests/Support/Assistant/Doubles/`, réutilisables :

| Double | Implémente | Rôle |
|---|---|---|
| `FixedClock` | `Clock` | horloge figeable + `advanceSeconds()` |
| `SequenceIdGenerator` | `IdGenerator` | `ses_001`, `ses_002`, `msg_001`, … |
| `InMemorySessionRepository` | `SessionRepository` | stockage map |
| `InMemoryMessageRepository` | `MessageRepository` | stockage list + tri chrono |
| `ScriptedLlm` | `LlmPort` | scénarise réponses via `scriptReply()` / `scriptFailure()`, enregistre les `calls()` |

---

## 3. Infrastructure (`App/src/Assistant/Infrastructure/`)

### 3.1 `Clock/SystemClock`

Implémente `Clock` via `new \DateTimeImmutable('now')`. Aucune dépendance.

### 3.2 `Id/SymfonyUidGenerator`

Implémente `IdGenerator` via `Symfony\Component\Uid\Uuid::v7()->toBase58()`. Préfixe `ses_` / `msg_` ajouté. **UUIDv7 est time-ordered** : les ids triés alphabétiquement sont chronologiques — utile pour les listings sans index séparé.

### 3.3 Persistence Doctrine

Pattern **Aggregate ≠ Entity** (cf. [ADR-0003](../adr/0003-aggregate-vs-entity-separation.md)) :

```
Domain/Model/Session  ↔  Infrastructure/.../Mapper/SessionMapper  ↔  Infrastructure/.../Entity/SessionEntity (Doctrine)
Domain/Model/Message  ↔  Infrastructure/.../Mapper/MessageMapper  ↔  Infrastructure/.../Entity/MessageEntity
```

#### Entities Doctrine

| Entité | Table | Colonnes |
|---|---|---|
| `SessionEntity` | `assistant_sessions` | `id varchar(64) PK`, `model_name varchar(128)`, `title varchar(255)`, `created_at timestamptz`, `updated_at timestamptz`, `archived boolean default false` |
| `MessageEntity` | `assistant_messages` | `id varchar(64) PK`, `session_id varchar(64)`, `role varchar(16)`, `content text`, `created_at timestamptz` + index `(session_id, created_at)` |

Mapping par **attributs PHP 8** (`#[ORM\Entity]`, `#[ORM\Column]`, …). Pas de FK déclarée entre `messages` et `sessions` au niveau ORM (volontaire : on garde la flexibilité de purge / archivage indépendante ; la cohérence est garantie au niveau application).

#### Repositories

| Repo | Méthodes | Notes |
|---|---|---|
| `DoctrineSessionRepository` | `save` (upsert via `find` + `persist|merge`), `findById`, `all` (ordre `updated_at DESC`), `delete` | |
| `DoctrineMessageRepository` | `append` (persist + flush immédiat), `forSession` (filtre + `ORDER BY created_at ASC, id ASC`) | append-only ; pas d'update/delete au repo |

### 3.4 Configuration

`config/packages/doctrine.yaml` — mapping `Assistant` :

```yaml
doctrine:
    orm:
        auto_mapping: false
        mappings:
            Assistant:
                type: attribute
                is_bundle: false
                dir: '%kernel.project_dir%/src/Assistant/Infrastructure/Persistence/Doctrine/Entity'
                prefix: 'App\Assistant\Infrastructure\Persistence\Doctrine\Entity'
                alias: Assistant
```

`config/services.yaml` — bind des Ports vers leurs adapters :

```yaml
services:
    App\Assistant\Domain\Port\Clock:
        alias: App\Assistant\Infrastructure\Clock\SystemClock
    App\Assistant\Domain\Port\IdGenerator:
        alias: App\Assistant\Infrastructure\Id\SymfonyUidGenerator
    App\Assistant\Domain\Port\SessionRepository:
        alias: App\Assistant\Infrastructure\Persistence\Doctrine\Repository\DoctrineSessionRepository
    App\Assistant\Domain\Port\MessageRepository:
        alias: App\Assistant\Infrastructure\Persistence\Doctrine\Repository\DoctrineMessageRepository
```

Resource `App\:` exclut les classes "non-services" : `Domain/Model/`, `Domain/Exception/`, `Application/Command/*Command.php`, `Application/Query/*Query.php`, `Application/Dto/`, `Infrastructure/Persistence/Doctrine/Entity/`, `Kernel.php`.

### 3.5 Migration

`App/migrations/Version20260530121839.php` — crée `assistant_sessions` + `assistant_messages` + index.

À appliquer après chaque setup :

```bash
docker compose exec php bin/console doctrine:migrations:migrate -n
```

### 3.6 `Llm/SymfonyAiOllamaAdapter`

*À venir — STEP-05.* Implémentera `LlmPort` via `symfony/ai-platform` + `symfony/ai-ollama-platform`.

---

## 4. UI (`App/src/Assistant/UI/`)

*À venir — STEP-05.*

- `Cli/AskCommand` (`assistant:ask "question"`) : one-shot, crée une session jetable et imprime la réponse.
- `Tui/TuiCommand` (`assistant:tui`) : interface TUI persistante (liste sessions à gauche, chat à droite).

---

## 5. Tests

```
App/tests/Unit/Assistant/Domain/    ← ✅ STEP-02 (22 tests, 40 assertions)
App/tests/Unit/Assistant/Application/    ← 🟡 STEP-03
App/tests/Integration/Assistant/Infrastructure/    ← ⏳ STEP-04 (Postgres réel)
```

---

## 6. Inspiration opencode

Schemas regardés dans `_opencode_ref/opencode-dev/packages/opencode/src/session/` :

- `schema.ts` : `SessionID = "ses_..."`, `MessageID = "msg_..."`, `PartID = "prt_..."`.
- `message-v2.ts` : structure `Message` avec `parts: list<Part>` (TextPart, SnapshotPart, PatchPart, ToolCallPart, ...).

**Ce qu'on a simplifié pour l'instant :** un message a un `content: string` direct, pas de liste de parts. Quand on attaquera les tools (`ToolCallPart`) et les attachments (`SnapshotPart`), on migrera `MessageContent` vers une `MessageBody = list<MessagePart>` — voir [ADR-0002](../adr/) (à écrire le moment venu).
