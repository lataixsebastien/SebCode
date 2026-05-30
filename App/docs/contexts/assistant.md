# Bounded Context — `Assistant`

> Conversation avec un LLM : sessions, messages, completion. Cœur fonctionnel de SebCode.

**Statut d'avancement :**

| Layer | Statut |
|---|---|
| Domain | ✅ STEP-02 |
| Application | ✅ STEP-03 |
| Infrastructure | ⏳ STEP-04 |
| UI (CLI + TUI) | ⏳ STEP-05 |

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

*À venir — STEP-04.*

Adapters prévus :

- `SystemClock` (implémente `Clock`).
- `SymfonyUidGenerator` (implémente `IdGenerator`, basé sur `Symfony\Component\Uid\Uuid::v7()`).
- `DoctrineSessionRepository` + `DoctrineMessageRepository` (Postgres).
- `SymfonyAiOllamaAdapter` (implémente `LlmPort`, basé sur `symfony/ai-platform` + `symfony/ai-ollama-platform`).

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
