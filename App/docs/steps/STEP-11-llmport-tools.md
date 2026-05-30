# STEP-11 — `LlmPort` enrichi pour le tool calling

**Statut :** ✅ terminé (`composer qa` vert)
**Branche :** `feat/sebcode-foundation`

## But

Étendre le contrat `LlmPort` pour qu'il puisse :
1. **Annoncer une liste d'outils** au LLM lors de l'appel.
2. **Récupérer les invocations d'outils** demandées par le LLM dans la réponse.

À cette étape, **`SendMessageHandler` reste inchangé** — il n'utilise pas encore le nouveau paramètre `$tools` ni la nouvelle propriété `$toolCalls`. C'est STEP-12 qui branche la boucle agentique qui exploitera les deux.

## Décisions clés

| Décision | Choix | Raison |
|---|---|---|
| Signature `complete(...)` | Ajout d'un 3ème paramètre `array $tools = []` (valeur par défaut) | Rétrocompat totale : tous les appels existants continuent de fonctionner |
| `LlmReply` | Ajout d'une 4ème propriété `array $toolCalls = []` | Idem ; un reply sans tool reste possible |
| VOs miroir côté Assistant | `ToolAdvertisement`, `ToolCallRequest`, `ToolResultDto` dans `App/src/Assistant/Domain/Model/ValueObject/` | Aucune dépendance `Assistant\Domain` → `Tool\Domain` (cf. [ADR-0004](../adr/0004-tool-as-separate-context.md)). La traduction se fera dans `AssistantToolGatewayAdapter` (Infrastructure, STEP-12). |
| Format des tools envoyés à Ollama | OpenAI-style : `[{type:'function', function:{name, description, parameters}}]`, passé en `$options['tools']` à `Platform::invoke()` | `OllamaClient::CHAT_TOP_LEVEL_KEYS` accepte `tools` directement (`vendor/symfony/ai-ollama-platform/OllamaClient.php:32`) et le pousse tel quel dans le body JSON `/api/chat` |
| Parsing du résultat | Détecte `ToolCallResult` (du namespace `Symfony\AI\Platform\Result`) en plus de `TextResult` | `OllamaResultConverter::doConvertCompletion()` retourne déjà `ToolCallResult` quand le message contient `tool_calls` |
| Role `Message::Tool` dans le bag | **Toujours rejeté avec `LlmUnavailable`** | La traduction "tool result → ToolCallMessage Symfony AI" requiert `MessagePayload` (le payload structuré, ID du call, output) qui n'arrive qu'en STEP-12 |

## Layout produit

```
App/src/Assistant/Domain/Model/ValueObject/
├── ToolAdvertisement.php       (name, description, parameters: JsonSchemaArray)
├── ToolCallRequest.php         (id, name, arguments[])
└── ToolResultDto.php           (toolCallId, output, isError ; factories success/error)
```

Modifs aux fichiers existants :

| Fichier | Modif |
|---|---|
| `Assistant/Domain/Port/LlmPort.php` | `complete(ModelName, list<Message>, list<ToolAdvertisement> $tools = []): LlmReply` |
| `Assistant/Domain/Port/LlmReply.php` | +`readonly array $toolCalls = []` (list<ToolCallRequest>) |
| `Assistant/Infrastructure/Llm/SymfonyAiOllamaAdapter.php` | Émet les tools dans `$options['tools']` ; détecte `ToolCallResult` et le traduit en `LlmReply.toolCalls` ; refus explicite de `MessageRole::Tool` avec message renvoyant vers STEP-12 |
| `tests/Support/Assistant/Doubles/ScriptedLlm.php` | Signature mise à jour ; nouveau `scriptToolCallTurn(array $toolCalls, ...)` ; `calls()` enregistre aussi les `tools` annoncés |
| `tests/Support/Assistant/Doubles/RecordingPlatform.php` | +`public array $lastOptions = []` pour inspection des tools envoyés |

## Pipeline `complete` avec tool calling

```
SymfonyAiOllamaAdapter::complete(model, conversation, tools)
        │
        ├─ toMessageBag(conversation)
        ├─ options['tools'] = [{type:'function', function:{name, description, parameters}}, ...]   (si tools non vide)
        ├─ deferred = platform.invoke(model.value, bag, options)   ── peut throw
        ├─ result = deferred.getResult()                           ── peut throw
        │
        ├─ if (result instanceof ToolCallResult):
        │       toolCalls = array_map(ToolCall → ToolCallRequest, result.getContent())
        │       return LlmReply(content='', tokens..., toolCalls=...)
        │
        ├─ if (result instanceof TextResult):
        │       return LlmReply(content=result.getContent(), tokens...)
        │
        └─ else throw LlmUnavailable("unexpected result type")
```

## Comment vérifier

```bash
docker compose exec php composer qa
# attendu :
#   cs        0
#   stan      0 erreur (level max)
#   deptrac   0 violation, 240 deps allowed (vs 234 après STEP-10, +6)
#   phpunit   110 tests, 256 assertions OK   (+3 nouveaux : tools advertisés, ToolCallResult parsé, TextResult fallback)
```

## Step suivante

**STEP-12 — Boucle agentique + migration `payload_json`.** La grosse step :

- `Assistant/Domain/Port/ToolGateway.php` (Port + DTOs `ToolExecutionContextDto`).
- `Assistant/Domain/Model/Message.php` + `ValueObject/MessagePayload.php` (nouveau champ optionnel pour persister tool_call/tool_result structurés).
- `Assistant/Infrastructure/Tool/AssistantToolGatewayAdapter.php` (pont Assistant ↔ Tool).
- `Assistant/Infrastructure/Persistence/Doctrine/Entity/MessageEntity.php` + migration `ALTER TABLE assistant_messages ADD COLUMN payload_json TEXT NULL`.
- `Assistant/Application/Command/SendMessageHandler.php` refactoré en boucle bornée (`MAX_TURNS=10`) avec doom-loop guard.
- Mise à jour `Assistant/Infrastructure/Llm/SymfonyAiOllamaAdapter.php` pour supporter `MessageRole::Tool` en traduisant `Message{role:Tool, payload:tool_result}` → `ToolCallMessage`.
- ADR-0005 (tool loop in Application), ADR-0006 (payload_json column).
- +6 tests Application.

C'est l'étape qui rend `assistant:ask "Liste les fichiers de App/src"` enfin fonctionnel.
