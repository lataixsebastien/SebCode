# Bounded Context — `Assistant`

> Conversation avec un LLM : sessions, messages, completion. Cœur fonctionnel de SebCode.

**Statut d'avancement :**

| Layer | Statut |
|---|---|
| Domain | ✅ STEP-02 |
| Application | ✅ STEP-03 |
| Infrastructure persistence | ✅ STEP-04 |
| Infrastructure LLM (Ollama) | ✅ STEP-05 + tool calling support STEP-11 |
| UI CLI | ✅ STEP-06 |
| UI TUI | ✅ STEP-07 (validation interactive à faire en TTY réel) |

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

Implémente `LlmPort` au-dessus de `symfony/ai-platform` + `symfony/ai-ollama-platform` (bundle).

**Pipeline `complete(ModelName, list<Message>)` :**

1. Traduit la conversation Domain → `Symfony\AI\Platform\Message\MessageBag` :
   - `MessageRole::System` → `SystemMessage($text)`
   - `MessageRole::User` → `UserMessage(new Text($text))`
   - `MessageRole::Assistant` → `AssistantMessage(new Text($text))`
   - `MessageRole::Tool` → **non supporté pour l'instant** → lève `LlmUnavailable` (sera levé quand on aura le contexte `Tool`).
2. Appelle `$platform->invoke($model->value, $bag)` → `DeferredResult`.
3. `$deferred->getResult()` → attend `TextResult`. Tout autre type → `LlmUnavailable`.
4. Lit le metadata `'token_usage'` (rempli par l'adapter Ollama via `OllamaResultConverter::getTokenUsageExtractor()`) → extrait `promptTokens`/`completionTokens`.
5. Toute exception remontée par la Platform → enveloppée dans `LlmUnavailable::fromUpstream($message, $previous)`.

**Wiring :**

```yaml
# config/services.yaml
App\Assistant\Domain\Port\LlmPort:
    alias: App\Assistant\Infrastructure\Llm\SymfonyAiOllamaAdapter

App\Assistant\Infrastructure\Llm\SymfonyAiOllamaAdapter:
    arguments:
        $platform: '@ai.platform.ollama'
```

```yaml
# config/packages/ai_ollama_platform.yaml
ai:
    platform:
        ollama:
            endpoint: '%env(OLLAMA_ENDPOINT)%'
```

**Variables d'env (`.env`) :**

| Variable | Défaut | Rôle |
|---|---|---|
| `OLLAMA_ENDPOINT` | `http://host.docker.internal:11434` | Endpoint HTTP de l'instance Ollama (Linux : ajuster vers le bridge Docker). |
| `OLLAMA_HTTP_TIMEOUT` | `600` | Timeout HTTP client par défaut (long parce que les LLM peuvent être lents au cold start). |
| `LLM_MODEL` | `qwen2.5:3b` | Modèle Ollama utilisé par défaut. Doit être préalablement pull dans Ollama (`ollama pull qwen2.5:3b`). |

**Tests :** 4 tests unitaires sur `SymfonyAiOllamaAdapter` (`tests/Unit/Assistant/Infrastructure/Llm/`) :
- Translation role-par-role + ordre préservé.
- Extraction des tokens via metadata `'token_usage'`.
- Enveloppe les exceptions de la Platform dans `LlmUnavailable`.
- Refuse `MessageRole::Tool` proprement.

Pas de test d'intégration avec Ollama réel dans cette step — la validation end-to-end viendra avec `assistant:ask` en STEP-06.

---

## 4. UI (`App/src/Assistant/UI/`)

### 4.1 CLI (`Cli/`)

#### `assistant:ask` — pose une question one-shot ou continue une session

```bash
# Nouvelle session
bin/console assistant:ask "Explique-moi le hexagonal en 1 phrase"
# → Started new session ses_xxx (model=qwen2.5:3b)
# → You / Assistant transcripts
# → session=ses_xxx  prompt_tokens=N  completion_tokens=M

# Continuer une session existante
bin/console assistant:ask "Continue..." --session=ses_xxx

# Override modèle / titre lors de la création
bin/console assistant:ask "Hello" --model=qwen2.5:7b --title="Big chat"
```

Options :
- `--session`/`-s` (string `ses_*`) : continuer une session existante au lieu d'en créer une.
- `--model`/`-m` (string) : modèle Ollama pour une nouvelle session (défaut : `LLM_MODEL` env).
- `--title`/`-t` (string) : titre pour une nouvelle session (défaut : `CLI ask`).

Codes retour :
- `0` ok ; `1` failure (SessionNotFound, LlmUnavailable…) ; `2` invalid input.

Sur `LlmUnavailable`, le message user est **toujours persisté** : la commande indique de re-run avec `--session=…` pour retry.

#### `assistant:sessions` — liste ou affiche

```bash
# Lister
bin/console assistant:sessions
# → table id, title, model, updated_at, archived

# Voir le transcript complet d'une session
bin/console assistant:sessions ses_xxx
# → header + user/assistant messages chronologiques avec timestamps
```

#### Architecture des commandes

Les `*Command` Symfony Console sont des **wrappers très minces** : zéro logique métier, ils ne font que :
1. Parser l'input.
2. Construire un `*Command`/`*Query` Application.
3. Invoquer le `*Handler` correspondant (autowire).
4. Pretty-print le résultat.

Le defaultModel (`LLM_MODEL` env) est injecté explicitement dans `AskCommand` via `services.yaml`. Les autres dépendances (Handlers, SessionRepository) sont autowired.

### 4.2 TUI (`Tui/`)

`UI/Tui/TuiCommand` — interface interactive avec `symfony/tui ^8.1@beta`.

```bash
# Nouvelle session
bin/console assistant:tui

# Reprendre une session existante
bin/console assistant:tui --session=ses_xxx

# Custom model / title
bin/console assistant:tui --model=qwen2.5:7b --title="Big chat"
```

**Layout (vertical) :**

```
┌──────────────────────────────────────────────┐
│ SebCode TUI — <title> · model=… · ses_…     │  <- header (TextWidget, .header)
├──────────────────────────────────────────────┤
│                                              │
│ ▶ You                                        │
│ Bonjour !                                    │  <- transcript (ContainerWidget,
│                                              │     expandVertically, .transcript)
│ ◀ Assistant                                  │     contient un TextWidget par
│ Bonjour ! Que puis-je faire ?                │     message
│                                              │
├──────────────────────────────────────────────┤
│ › Tape ton message…                          │  <- input (InputWidget, .input)
└──────────────────────────────────────────────┘
```

**Composants Tui utilisés (`symfony/tui`) :**
- `Tui` (root), `ContainerWidget` (transcript), `TextWidget` (header, lignes), `InputWidget` (saisie).
- `Padding::xy()`, `Style(background, color)` pour la stylesheet.
- `InputWidget::onSubmit()` reçoit un `SubmitEvent` quand l'utilisateur tape Entrée.

**Pipeline d'un tour :**

1. User tape un message + Entrée.
2. Si message = `:q`, `:quit`, `exit`, `quit` → `$tui->stop()`.
3. Sinon : on append un widget `▶ You … <message>` au transcript.
4. On append un placeholder `◀ Assistant — (thinking…)`.
5. `$tui->requestRender()` → l'UI est repeinte immédiatement (l'utilisateur voit le "thinking…").
6. `EventLoop::queue(...)` — défère l'appel LLM **au prochain tick** pour ne pas bloquer le rendu en cours.
7. Au tick suivant : appel `SendMessageHandler` (bloquant, mais le rendu "thinking" est déjà affiché).
8. On `remove()` le placeholder et on `add()` la vraie réponse (ou un widget `⚠ Error` si `LlmUnavailable`).
9. `requestRender()` à nouveau.

**Limites connues du MVP :**

- **Pas de streaming** — la réponse arrive en bloc une fois le LLM terminé. Pour avoir l'effet "live typing" il faut consommer le stream Ollama (SSE) et utiliser `EditorWidget` ou append progressif au `TextWidget`. À faire dans un STEP ultérieur.
- **Pas de session sidebar** — une seule session à la fois. Choisir au démarrage via `--session=` ou créer une nouvelle. Pour la liste, utiliser `bin/console assistant:sessions` séparément.
- **Pas de scroll explicite** — le transcript grandit ; à long terme il faut limiter / scroller. À voir si `Tui::setScrollOffset()` suffit.
- **Quit par commande tapée** (`:q`, `exit`, etc.). Le Ctrl+C par défaut de `symfony/tui` devrait aussi arrêter proprement.

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
