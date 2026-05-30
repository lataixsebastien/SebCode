# STEP-09 — Bounded context `Tool/` : Domain + Application

**Statut :** ✅ terminé (`composer qa` vert end-to-end)
**Branche :** `feat/sebcode-foundation`

## But

Poser le **squelette pur PHP** du nouveau bounded context `Tool/` qui exposera plus tard des outils concrets (glob, read, write, shell, …) au LLM via tool calling.

À ce stade, **aucun outil concret n'existe encore** — c'est uniquement l'ossature : VOs, aggregates, ports, exceptions, use cases CQRS, doubles de test réutilisables. Aucun changement de comportement utilisateur visible.

## Décisions clés

| Décision | Choix | Raison |
|---|---|---|
| Localisation | Bounded context dédié `App/src/Tool/`, **pas** dans `Assistant/` | [ADR-0004](../adr/0004-tool-as-separate-context.md) |
| Naming des préfixes d'ID | `ToolCallId` → préfixe `tcl_` | Cohérence avec `ses_`/`msg_`/`prt_` (cf. opencode `session/schema.ts`) |
| Validation `ToolName` | Regex `[a-z_][a-z0-9_]{0,63}` | Compat directe OpenAI / Anthropic / Ollama function calling spec |
| `JsonSchema` | Wrapper `final readonly` autour d'un `array<string,mixed>` validé en surface | Pas de validation JSON Schema profonde (overkill ; les Tools peuvent valider eux-mêmes au runtime via `InvalidToolArguments`) |
| Soft vs Hard failure | `InvalidToolArguments` (soft) ↔ `ToolResult::failure(...)` ; `ToolNotFound`/`ToolExecutionFailed` (hard) ↔ exception propagée | Invariant central. La boucle Assistant doit pouvoir continuer sur soft, abort sur hard. |
| `ExecuteToolHandler` | Convertit `InvalidToolArguments` en `ToolResult::failure(...)`, propage le reste | Sépare clairement les deux flux côté Application |
| Doubles | `InMemoryToolRegistry` + `FakeTool` scénarisable | Réutilisable par tous les futurs tests Tool + tests Assistant qui mockent le `ToolGateway` (STEP-12) |
| Wiring | **Aucun bind dans `services.yaml` encore** | Aucun service public à enregistrer tant que `ServiceLocatorToolRegistry` n'existe pas (STEP-10) |
| Deptrac | **Aucun changement** | Les layers génériques `Domain` / `Application` du `deptrac.yaml` matchent déjà `^App\\[^\\]+\\Domain\\.*$` — `Tool/Domain` est automatiquement enforced en pur PHP. L'isolation `Assistant ↮ Tool` sera ajoutée en STEP-12 quand le pont sera créé. |

## Layout produit

```
App/src/Tool/Domain/
├── Model/
│   ├── ToolDescriptor.php
│   ├── ToolCall.php
│   ├── ToolResult.php
│   └── ValueObject/
│       ├── ToolName.php       (regex [a-z_][a-z0-9_]{0,63})
│       ├── ToolCallId.php     (préfixe tcl_)
│       └── JsonSchema.php     (wrapper validé : type=object)
├── Port/
│   ├── Tool.php               (interface : descriptor + execute)
│   ├── ToolRegistry.php       (interface : list + find)
│   └── ToolExecutionContext.php   (final readonly DTO : projectRoot, maxOutputBytes, now)
└── Exception/
    ├── ToolNotFound.php
    ├── ToolExecutionFailed.php
    └── InvalidToolArguments.php

App/src/Tool/Application/
├── Command/
│   ├── ExecuteToolCommand.php
│   └── ExecuteToolHandler.php
└── Query/
    ├── ListToolsQuery.php
    └── ListToolsHandler.php
```

```
App/tests/Support/Tool/Doubles/
├── InMemoryToolRegistry.php
└── FakeTool.php

App/tests/Unit/Tool/
├── Domain/
│   ├── Model/
│   │   ├── ToolDescriptorTest.php
│   │   ├── ToolCallTest.php
│   │   ├── ToolResultTest.php
│   │   └── ValueObject/
│   │       ├── ToolNameTest.php          (data provider : 5 valides + 7 invalides)
│   │       ├── ToolCallIdTest.php
│   │       └── JsonSchemaTest.php
│   └── Port/
│       └── ToolExecutionContextTest.php
└── Application/
    ├── Command/ExecuteToolHandlerTest.php   (4 cas : success, ToolNotFound, soft→failure, hard→bubble)
    └── Query/ListToolsHandlerTest.php       (2 cas : avec/sans tools)
```

## Modifications fichiers existants

**Aucune** — STEP-09 est purement additif. La modification de `LlmPort` viendra en STEP-11, celle de `Message`/`SendMessageHandler` en STEP-12.

## Comment vérifier

```bash
docker compose exec php composer qa
# attendu :
#   cs        0 fichier à corriger
#   stan      0 erreur (level max)
#   deptrac   0 violation, 228 deps allowed (vs 219 avant STEP-09, +9 nouvelles)
#   phpunit   87 tests, 199 assertions OK   (vs 49/134 avant, +38)
```

## Garde-fou hexa respecté

```bash
grep -RnE "^use (Symfony|Doctrine)" App/src/Tool/Domain App/src/Tool/Application
# → vide
```

Deptrac le valide automatiquement à chaque `composer qa`.

## Step suivante

**STEP-10** — Outils MVP concrets :

- `Tool/Infrastructure/Registry/ServiceLocatorToolRegistry.php` — backed par `!tagged_locator { tag: 'app.tool' }`.
- `Tool/Infrastructure/Tool/GlobTool.php` — name=`glob`, lecture seule, sandbox par `realpath` contre `projectRoot`.
- `Tool/Infrastructure/Tool/ReadTool.php` — name=`read`, lecture seule, troncature 256 KiB.
- Wiring `services.yaml` avec `_instanceof` pour auto-tagger les implémentations de `Tool\Domain\Port\Tool`.
- ~8 tests Infrastructure (match heureux, sandbox refusé, troncature, offset/limit).

À l'issue de STEP-10, l'Assistant ne sait toujours pas qu'il y a des outils (STEP-11 enrichira `LlmPort`, STEP-12 brachera la boucle). Mais on pourra debug en CLI via `bin/console tool:run` (commande temporaire).
