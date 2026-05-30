# Bounded Context — `Tool`

> Catalogue d'outils que le LLM peut invoquer via tool calling. Indépendant du contexte `Assistant` — la communication inter-contextes passe par un Port côté Assistant (`ToolGateway`, voir [ADR-0004](../adr/0004-tool-as-separate-context.md)).

**Statut d'avancement :**

| Layer | Statut |
|---|---|
| Domain | ✅ STEP-09 |
| Application | ✅ STEP-09 |
| Infrastructure (registry + `glob`/`read`) | ✅ STEP-10 |
| UI (consommé par Assistant) | via `Assistant\UI` après STEP-12 |

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

### 3.1 `Registry/ServiceLocatorToolRegistry`

Implémente `ToolRegistry` au-dessus d'un `PSR-11 Container` (le service locator Symfony) + d'un `iterable<Tool>` tagué.

- Construit la map `descriptor->name->value → ToolDescriptor` une fois au démarrage, triée alphabétiquement (ordre stable pour la cohérence d'un tour à l'autre côté LLM).
- `list()` retourne la liste des descriptors.
- `find(ToolName)` consulte la map puis demande le service au locator (lazy — instancié à la demande). Refuse les noms en double au boot (`LogicException`).

### 3.2 `Tool/GlobTool` — `name="glob"`

Trouve des fichiers correspondant à un pattern, relatif à `projectRoot`.

| Champ | Valeur |
|---|---|
| name | `glob` |
| description (envoyée au LLM) | "Find files matching a glob pattern relative to the project root. Supports `**` for recursive matching and `{a,b}` brace expansion. Returns absolute paths. Use to list directories or explore project structure." |
| paramètres | `pattern: string` (requis), `limit: int [1..500]` (défaut 100) |
| output | `(N matched, showing first M)\n<path1>\n<path2>\n…` + `--- truncated ---` si nécessaire |
| sandbox | chaque path est passé à `realpath()` ; rejeté si ne commence pas par `projectRoot` ou n'est pas égal à `projectRoot` |

Le `**` est implémenté à la main (PHP `glob()` natif ne le supporte pas). Le chemin avant `**` doit exister sinon retourne `[]`. `GLOB_BRACE` est utilisé si disponible (fallback gracieux sur Alpine/musl).

### 3.3 `Tool/ReadTool` — `name="read"`

Lit un fichier et renvoie son contenu en format `cat -n` (lignes numérotées).

| Champ | Valeur |
|---|---|
| name | `read` |
| description | "Read a file from the project root. Returns content in `cat -n` numbered form. Use offset/limit (line numbers, 0-indexed offset) to page through large files." |
| paramètres | `filePath: string` (requis), `offset: int ≥0` (défaut 0), `limit: int [1..2000]` (défaut 2000) |
| output | Lignes formatées `%6d  <content>`, sépare par `\n`. Tronqué avec `--- truncated at N bytes ---` si dépasse `maxOutputBytes` du context. |
| metadata | `{lines_returned: int, truncated: bool}` |
| sandbox | `realpath($projectRoot + filePath)` ; rejet soft (`isError=true`) si fichier inexistant, en dehors du root, ou non-fichier régulier |

### 3.4 Wiring `services.yaml`

```yaml
_instanceof:
    App\Tool\Domain\Port\Tool:
        tags: ['app.tool']

App\Tool\Infrastructure\Registry\ServiceLocatorToolRegistry:
    arguments:
        $tools: !tagged_iterator 'app.tool'
        $locator: !service_locator
            glob: '@App\Tool\Infrastructure\Tool\GlobTool'
            read: '@App\Tool\Infrastructure\Tool\ReadTool'

App\Tool\Domain\Port\ToolRegistry:
    alias: App\Tool\Infrastructure\Registry\ServiceLocatorToolRegistry
```

Pour ajouter un nouveau tool, il suffit de :
1. Créer une classe dans `App/src/Tool/Infrastructure/Tool/` qui implémente `Tool`.
2. Ajouter une ligne dans `service_locator` (le `_instanceof` la tag automatiquement, mais le locator a besoin du mapping explicite).

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
