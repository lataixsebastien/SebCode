# Bounded Context — `Tool`

> Catalogue d'outils que le LLM peut invoquer via tool calling. Indépendant du contexte `Assistant` — la communication inter-contextes passe par un Port côté Assistant (`ToolGateway`, voir [ADR-0004](../adr/0004-tool-as-separate-context.md)).

**Statut d'avancement :**

| Layer | Statut |
|---|---|
| Domain | ✅ STEP-09 |
| Application | ✅ STEP-09 |
| Infrastructure (registry + tools concrets) | ⏳ STEP-10 |
| UI (debug `tool:run` éventuel) | optionnel STEP-10 |

---

## 1. Domain (`App/src/Tool/Domain/`)

### 1.1 Aggregates / VOs

| Élément | Type | Rôle |
|---|---|---|
| `ToolDescriptor` | `final readonly` | Métadonnées publiques d'un outil : `name: ToolName`, `description: string`, `parameters: JsonSchema`. C'est ce que le LLM voit. |
| `ToolCall` | `final readonly` | Intention d'invocation décodée : `id: ToolCallId`, `name: ToolName`, `arguments: array<string,mixed>`. |
| `ToolResult` | `final readonly` | Résultat d'exécution : `callId: ToolCallId`, `output: string`, `isError: bool`, `metadata?: array`. Factories `::success(...)` / `::failure(...)`. |
| `ToolName` (VO) | `final readonly` | String wrappé, regex `[a-z_][a-z0-9_]{0,63}` (compat OpenAI/Anthropic). |
| `ToolCallId` (VO) | `final readonly` | Préfixe `tcl_` (cohérent avec `ses_`/`msg_`/`prt_` d'opencode). |
| `JsonSchema` (VO) | `final readonly` | Wrap d'un array, valide la forme `type=object` (+ `properties`/`required` cohérents). Factory `::of(array)` et `::empty()`. |

### 1.2 Ports

| Port | Méthodes |
|---|---|
| `Tool` | `descriptor(): ToolDescriptor` ; `execute(ToolCall, ToolExecutionContext): ToolResult` |
| `ToolRegistry` | `list(): list<ToolDescriptor>` (ordre stable) ; `find(ToolName): ?Tool` |
| `ToolExecutionContext` (DTO) | `projectRoot: string`, `maxOutputBytes: int`, `now: \DateTimeImmutable` |

### 1.3 Exceptions

| Exception | Levée quand |
|---|---|
| `ToolNotFound` | `ToolRegistry::find()` renvoie `null` pour un nom demandé. Hard failure — abort. |
| `ToolExecutionFailed` | Infrastructure cassée pendant `execute()`. Hard failure — abort. |
| `InvalidToolArguments` | Arguments invalides au regard du schéma de l'outil. **Soft failure** — `ExecuteToolHandler` la convertit en `ToolResult::failure(...)` pour que le LLM puisse corriger. |

### 1.4 Distinction soft vs hard failure

C'est l'invariant central du Domain :

- **Soft** (`InvalidToolArguments`, fichier inexistant, sandbox refusé, etc.) → toujours emballée dans `ToolResult(isError=true)`. La boucle Assistant continue.
- **Hard** (`ToolExecutionFailed`, registry corrompue, etc.) → propagée. La boucle Assistant s'arrête, la commande remonte une erreur.

---

## 2. Application (`App/src/Tool/Application/`)

### 2.1 `ExecuteTool`

```php
final readonly class ExecuteToolCommand {
    public ToolCall $call;
    public ToolExecutionContext $context;
}

(new ExecuteToolHandler($registry))($command): ToolResult
```

Pipeline :
1. `registry.find($call->name)` → si `null`, throw `ToolNotFound`.
2. `tool.execute($call, $context)` :
   - retourne `ToolResult` (success ou failure) → renvoyé tel quel ;
   - throw `InvalidToolArguments` → converti en `ToolResult::failure($call->id, $e->getMessage())` ;
   - throw autre (`ToolExecutionFailed`, `\RuntimeException`, …) → propagé.

### 2.2 `ListTools`

```php
final readonly class ListToolsQuery {}

(new ListToolsHandler($registry))(new ListToolsQuery()): list<ToolDescriptor>
```

Lecture pure, retourne la liste dans l'ordre du registry (qui s'engage à être stable).

---

## 3. Infrastructure

*À venir — STEP-10 :*

- `ServiceLocatorToolRegistry` — backed par un `!tagged_locator { tag: 'app.tool' }` Symfony.
- `GlobTool`, `ReadTool` — premiers outils concrets en lecture seule, sandboxés par `realpath()` contre le `projectRoot` du context.

---

## 4. Tests

```
App/tests/Unit/Tool/
├── Domain/
│   ├── Model/
│   │   ├── ToolDescriptorTest.php
│   │   ├── ToolCallTest.php
│   │   ├── ToolResultTest.php
│   │   └── ValueObject/
│   │       ├── ToolNameTest.php       (data provider valid/invalid)
│   │       ├── ToolCallIdTest.php
│   │       └── JsonSchemaTest.php
│   └── Port/
│       └── ToolExecutionContextTest.php
└── Application/
    ├── Command/ExecuteToolHandlerTest.php   (success, ToolNotFound, soft→failure, hard→bubble)
    └── Query/ListToolsHandlerTest.php
```

Doubles réutilisables sous `App/tests/Support/Tool/Doubles/` :

- `InMemoryToolRegistry` — map name → Tool.
- `FakeTool` — scénarisable via `scriptOutput($text)` / `scriptException($e)`, enregistre tous les `calls`.

---

## 5. Garanties

- Aucun `use Symfony\…` ni `use Doctrine\…` dans `App/src/Tool/Domain/` ni `App/src/Tool/Application/` (vérifié par Deptrac via les layers génériques `Domain` / `Application` du `deptrac.yaml`).
- Pas de dépendance directe `Tool ↔ Assistant` ni `Assistant ↔ Tool` au niveau Domain/Application — le pont sera fait via le Port `Assistant\Domain\Port\ToolGateway` + adapter `Assistant\Infrastructure\Tool\AssistantToolGatewayAdapter` en STEP-12. ADR-0004 documente la décision.
