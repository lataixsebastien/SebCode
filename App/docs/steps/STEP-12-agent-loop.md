# STEP-12 — Boucle agentique + migration `payload_json`

**Statut :** ✅ terminé (`composer qa` vert + smoke end-to-end Ollama validé)
**Branche :** `feat/sebcode-foundation`

## But

C'est le step qui rend l'assistant **vraiment utile** : `SendMessageHandler` devient une boucle multi-tour qui annonce les outils au LLM, exécute ce qu'il demande, persiste tout l'aller-retour, et finit sur une réponse textuelle.

Smoke validé en réel (qwen2.5:7b via Ollama local) :

```
$ docker compose exec php bin/console assistant:ask \
    "List the PHP files under src/Assistant/Domain (use a glob pattern)." \
    --model=qwen2.5:7b
Started new session ses_…
Assistant: Below are the PHP files under src/Assistant/Domain:
  - /var/www/App/src/Assistant/Domain/Exception/LlmUnavailable.php
  - /var/www/App/src/Assistant/Domain/Exception/SessionNotFound.php
  - /var/www/App/src/Assistant/Domain/Model/Message.php
  ...  (21 files listed)
session=ses_… prompt_tokens=787 completion_tokens=455
```

## Décisions clés

| Décision | Choix | Raison |
|---|---|---|
| Où vit la boucle | Dans `SendMessageHandler` directement | [ADR-0005](../adr/0005-tool-loop-in-application.md) — YAGNI sur context `Agent/` séparé tant qu'on n'a pas sub-agents |
| Représentation Message multi-part | `Message + ?MessagePayload` + colonne `payload_json TEXT NULL` | [ADR-0006](../adr/0006-message-payload-json-column.md) — évite un gros refactor vers Parts |
| `MAX_TURNS` | `10` (constante) | Borne large mais finie ; 10 outils en une commande = déjà beaucoup |
| Doom-loop guard | Si 3 tool_calls identiques consécutifs (hash `name+json(args)`) → drop tools + system nudge | Observé en pratique avec qwen2.5:3b (cf. premier smoke STEP-12 qui a déclenché le guard) |
| Frontière inter-context | Un **seul** fichier importe `App\Tool\…` : `Assistant\Infrastructure\Tool\AssistantToolGatewayAdapter` | Respect strict [ADR-0004](../adr/0004-tool-as-separate-context.md) ; les DTO miroir (`ToolAdvertisement`, `ToolCallRequest`, `ToolResultDto` côté Assistant) évitent toute fuite |
| Normalisation des `ToolCallId` | L'adapter wrap l'id Ollama (qui est un entier `"0"`) avec le préfixe `tcl_` avant de le passer au context Tool | LLM providers utilisent leurs propres formats d'id ; on tolère ça à la frontière |
| Erreur de tool (soft) | `ToolResultDto(isError=true, output=reason)` → persistée comme `Message(role=Tool, payload.isError=true)` → renvoyée au LLM pour qu'il corrige | Cohérent avec opencode |
| Erreur de tool (hard) | Exception propagée → abort de la boucle → user message + intermediates déjà persistés | Permet inspection via `assistant:sessions` |

## Layout produit / modifié

### Nouveau

```
App/src/Assistant/Domain/
├── Port/ToolGateway.php
├── Model/ValueObject/MessagePayload.php
├── Model/ValueObject/MessagePayloadKind.php          (enum tool_call | tool_result)
└── Exception/AgentLoopExceeded.php

App/src/Assistant/Infrastructure/Tool/
└── AssistantToolGatewayAdapter.php                    (Tool ↔ Assistant bridge — sole import of App\Tool\*)

App/tests/Support/Assistant/Doubles/
└── RecordingToolGateway.php                           (scriptResult/scriptException + executions log)

App/migrations/
└── Version20260530163804.php                          (ALTER TABLE assistant_messages ADD payload_json)

App/docs/adr/
├── 0005-tool-loop-in-application.md
└── 0006-message-payload-json-column.md
```

### Modifié

| Fichier | Changement |
|---|---|
| `Assistant/Domain/Model/Message.php` | +`?MessagePayload $payload = null` (optionnel, rétrocompat) |
| `Assistant/Application/Command/SendMessageHandler.php` | Refactor complet en boucle multi-tour + doom-loop guard + persistance des Messages intermédiaires |
| `Assistant/Application/Dto/SendMessageResult.php` | +`list<Message> $intermediateMessages` |
| `Assistant/Infrastructure/Llm/SymfonyAiOllamaAdapter.php` | Supporte `MessageRole::Tool` (lit `payload->toolCallId/toolName/toolOutput`, émet `ToolCallMessage`) |
| `Assistant/Infrastructure/Persistence/Doctrine/Entity/MessageEntity.php` | +`?string $payloadJson` (colonne nullable) |
| `Assistant/Infrastructure/Persistence/Doctrine/Mapper/MessageMapper.php` | Encode/decode `MessagePayload` ↔ JSON (clés `kind`, `tool_calls`, `tool_call_id`, `tool_name`, `output`, `is_error`) |
| `config/services.yaml` | Bind `Assistant\Domain\Port\ToolGateway` → `AssistantToolGatewayAdapter` + `$projectRoot: '%kernel.project_dir%'` |
| `tests/Support/Assistant/Doubles/ScriptedLlm.php` | (déjà mis à jour STEP-11) supporte `scriptToolCallTurn()` et enregistre les `tools` annoncés par tour |

## Pipeline détaillé

```
[User] "List PHP files under src/Assistant/Domain"
        │
        ▼
SendMessageCommand
        │
        ├─ trim check + session load (throw SessionNotFound)
        ├─ persist user message
        ├─ advertisements = toolGateway.availableTools()    // [glob, read]
        │
        ├─ TURN 1 :
        │     history = [user msg]
        │     reply   = llm.complete(model, history, advertisements)
        │              → toolCalls=[{id:"0", name:"glob", args:{pattern:"src/Assistant/Domain/**/*.php"}}]
        │     persist Message(role=Assistant, payload=tool_call list)
        │     for call in reply.toolCalls:
        │         result = toolGateway.execute(call)
        │              → AssistantToolGatewayAdapter normalize id ("0" → "tcl_0")
        │              → ExecuteToolHandler → GlobTool → 21 paths
        │         persist Message(role=Tool, payload=tool_result, content="✓ glob → ...")
        │
        ├─ TURN 2 :
        │     history = [user, assistant(tool_call), tool(result)]
        │     reply   = llm.complete(...)
        │              → content="Here are the 21 files..." toolCalls=[]
        │     persist Message(role=Assistant, content=reply.content)
        │     session.touch() ; sessions.save()
        │     return SendMessageResult
        │
        ▼
SendMessageResult(userMessage, finalAssistant, intermediateMessages=[tool_call_msg, tool_result_msg], tokens...)
```

## Garanties testées

| Test | Vérifie |
|---|---|
| `testAppendsUserMessageCallsLlmAndAppendsAssistantReply` | Comportement existant intact si pas de tool advertisés |
| `testLlmReceivesFullHistoryIncludingTheNewUserMessage` | Idem |
| `testSecondTurnSendsCompleteHistoryToLlm` | Idem (multi-sessions) |
| `testSessionUpdatedAtIsBumped` | Idem |
| `testThrowsWhenSessionDoesNotExist` | Idem |
| `testThrowsWhenUserTextIsEmpty` | Idem |
| `testPropagatesLlmFailureAndKeepsUserMessage` | Idem |
| **`testSingleToolCallTriggersExecuteThenFinalText`** | Boucle 2-tours : 1 tool exécuté, réponse texte finale ; persistance 4 messages |
| **`testToolGatewayExceptionAbortsLoopWithoutLosingHistory`** | Hard failure → propagation, user message reste |
| **`testMaxTurnsThrowsAgentLoopExceeded`** | Borne `MAX_TURNS` correctement enforcée |
| **`testDoomLoopGuardForcesTextualReplyOnIdenticalRepeatedToolCall`** | 3 tool_calls identiques → tools drop + nudge + LLM commit |
| **`testToolSoftFailureFedBackToLlm`** | Soft failure → `ToolResultDto::error` → payload `is_error=true` |

## Migration

```sql
ALTER TABLE assistant_messages ADD payload_json TEXT DEFAULT NULL;
```

Appliquée via `bin/console doctrine:migrations:migrate`. Pas de backfill. Les rows existantes ont `payload_json IS NULL`.

## Comment vérifier

```bash
docker compose exec php composer qa
# attendu : cs 0 / stan 0 / deptrac 0 / phpunit 115 / 281 assertions

# Migration appliquée
docker compose exec php bin/console doctrine:migrations:migrate -n

# Smoke réel (Ollama qwen2.5:7b — qwen2.5:3b a un taux d'échec plus élevé sur les tools)
docker compose exec php bin/console assistant:ask \
  "List the PHP files under src/Assistant/Domain (use a glob pattern)." \
  --model=qwen2.5:7b

# Inspection détaillée du déroulé multi-tour
docker compose exec php bin/console assistant:sessions <ses_id>
# → User → Assistant(tool_call) → Tool(result) → Assistant(final)

# Debug SQL du payload structuré
docker compose exec postgres psql -U app -d app -c \
  "SELECT id, role, LEFT(content, 60), LEFT(payload_json, 120) \
   FROM assistant_messages WHERE session_id='ses_…' ORDER BY created_at"
```

## Step suivante

**STEP-13 — UI markers tool dans CLI + TUI.** Polish UX :

- `AskCommand` itère `SendMessageResult->intermediateMessages` et affiche chaque tool_call + tool_result avec un préfixe `🔧` ou `[tool]` avant la réponse `Assistant` finale.
- `TuiCommand` crée un `TextWidget` par message intermediate (style `.tool` jaune) entre le user et l'assistant.
- Catch `AgentLoopExceeded` proprement avec hint "session XXX persistée, inspection via assistant:sessions".

Aucun test nouveau ; validation manuelle via runbook.
