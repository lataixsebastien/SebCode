# STEP-10 — Outils MVP : `glob` + `read` + ServiceLocatorToolRegistry

**Statut :** ✅ terminé (`composer qa` vert end-to-end)
**Branche :** `feat/sebcode-foundation`

## But

Premier vrai code utilisable dans le contexte `Tool/` : deux outils en lecture seule (`glob`, `read`) découverts via une registry alimentée par le DI Symfony.

À l'issue de STEP-10, on a tout ce qu'il faut côté Tool pour répondre à des questions comme "schéma du dossier App/src" — il reste à câbler l'Assistant (STEP-11 + STEP-12).

## Décisions clés

| Décision | Choix | Raison |
|---|---|---|
| `glob` recursive `**` | Implémentation manuelle (PHP `glob()` ne supporte pas `**`) — split sur `**`, walk `RecursiveDirectoryIterator`, `fnmatch()` sur le suffixe sans `FNM_PATHNAME` | Plus simple qu'embarquer Symfony Finder ; comportement opencode-compatible |
| `GLOB_BRACE` | Fallback à 0 si la constante n'est pas définie (Alpine/musl notamment) | Évite un crash sur Docker minimal |
| Sandbox | Double check : (1) `realpath()` sur chaque match — rejette si `false` ou hors `projectRoot` ; (2) `realpath()` sur le filePath de `read` avant ouverture | `..` traversal qui boucle vers le root via symlink/`/../sebcode_glob_xxx` est accepté (résolu DANS le root) — c'est techniquement safe |
| Troncature | `glob` : par nombre de paths (`limit`). `read` : par lignes (`limit`) ET par octets (`maxOutputBytes` du context, défaut 64 KiB en test, 256 KiB envisagé en prod via `services.yaml`) | Cap à plusieurs niveaux ; protège contre les gros fichiers binaires |
| Pas de debug `tool:run` CLI | Reporté/skippé | La vraie validation viendra via `assistant:ask` en STEP-12 — un CLI ad-hoc serait du code à jeter |
| ServiceLocator pattern | Construction `iterable<Tool> + Psr\Container\ContainerInterface` ; map name→descriptor pré-calculée au boot, locator lazy à l'instanciation | Permet d'ajouter de futurs outils via 1 ligne YAML (locator) + 1 fichier PHP ; pas de scan runtime |

## Layout produit

```
App/src/Tool/Infrastructure/
├── Registry/
│   └── ServiceLocatorToolRegistry.php   (implements ToolRegistry, PSR-11)
└── Tool/
    ├── GlobTool.php                      (Tool, name="glob")
    └── ReadTool.php                      (Tool, name="read")
```

```
App/tests/Unit/Tool/Infrastructure/
├── Registry/ServiceLocatorToolRegistryTest.php   (list ordering, duplicate name, locator lookup, miss)
└── Tool/
    ├── GlobToolTest.php                          (shallow, **, truncation, no-hit, validation, sandbox)
    └── ReadToolTest.php                          (line numbers, offset, limit, not-found, traversal, empty, negative-offset, byte-truncate)
```

## Modifications config (`config/services.yaml`)

- Section `_instanceof` qui auto-tag toute classe implémentant `App\Tool\Domain\Port\Tool` avec `app.tool`.
- `ServiceLocatorToolRegistry` reçoit `!tagged_iterator 'app.tool'` (pour l'introspection des descriptors) ET un `!service_locator` explicite mappant `glob` → `GlobTool::class`, `read` → `ReadTool::class` (pour l'instanciation lazy).
- Alias `App\Tool\Domain\Port\ToolRegistry` → `ServiceLocatorToolRegistry`.

Note : le mapping `service_locator` est explicite (pas auto-indexed) parce que le nom du tool vit dans le code (`descriptor()->name`) et pas dans le service id. Pour ajouter un futur outil il faut donc 1 ligne YAML supplémentaire dans le locator.

## Pipeline d'un `glob`

```
ToolCall { name:"glob", arguments:{pattern:"App/src/**/*.php", limit:100} }
        │
        ▼
[1] Valide arguments  (pattern non-vide string, limit in [1,500]) — sinon InvalidToolArguments
        │
        ▼
[2] $root = realpath(context.projectRoot) — sinon ToolResult::failure("project root does not exist")
        │
        ▼
[3] scan($root, $pattern)
        ├─ pas de "**" → glob($root . "/" . $pattern, GLOB_BRACE) raw
        └─ avec "**"   → split, RecursiveDirectoryIterator + fnmatch sur suffixe
        │
        ▼
[4] Filter : pour chaque path, realpath() ; reject si hors $root
        │
        ▼
[5] header = "(N matched, showing first M)" + slice + "--- truncated ---" si N>limit
        │
        ▼
ToolResult::success(callId, body, metadata={matched: N, returned: M})
```

## Pipeline d'un `read`

```
ToolCall { name:"read", arguments:{filePath:"App/src/Kernel.php", offset:0, limit:20} }
        │
        ▼
[1] Valide arguments  (filePath string non-vide, offset≥0, limit in [1,2000])
        │
        ▼
[2] $absolute = realpath($root . "/" . $filePath) — sinon ToolResult::failure
        │
        ▼
[3] Vérifie inside-root + is_file($absolute) — sinon ToolResult::failure
        │
        ▼
[4] Stream fgets : skip $offset lignes, prend max $limit lignes,
    coupe à context.maxOutputBytes
        │
        ▼
ToolResult::success(callId, body_with_line_numbers,
                    metadata={lines_returned, truncated})
```

## Comment vérifier

```bash
docker compose exec php composer qa
# attendu :
#   cs        0 fichier
#   stan      0 erreur (level max)
#   deptrac   0 violation, 234 deps allowed (vs 228 après STEP-09, +6)
#   phpunit   107 tests, 240 assertions OK   (+20 nouveaux : 9 glob, 8 read, 4 registry, -1 doublon)
```

## Garde-fou hexa

```bash
grep -RnE "^use (Symfony|Doctrine)" App/src/Tool/Domain App/src/Tool/Application
# → vide (l'Infrastructure peut importer Psr\Container — pas une violation)
```

## Step suivante

**STEP-11** — `LlmPort` enrichi pour tool calling :

1. VOs miroir côté Assistant : `Assistant\Domain\Model\ValueObject\{ToolAdvertisement, ToolCallRequest, ToolResultDto}` (DTOs minimaux du Port, ne dépendent pas de `Tool\Domain`).
2. Signature `LlmPort::complete(ModelName, list<Message>, list<ToolAdvertisement> $tools = []): LlmReply`.
3. `LlmReply` enrichie : `+ readonly list<ToolCallRequest> $toolCalls = []`.
4. `SymfonyAiOllamaAdapter` :
   - Émet les tools dans `$options['tools']` lors de `platform->invoke()`.
   - Supporte `MessageRole::Tool` dans la traduction du `MessageBag`.
   - Parse `ToolCallResult` → populer `LlmReply->toolCalls`.
5. **`SendMessageHandler` reste inchangé** (n'annonce pas encore les tools).

À l'issue de STEP-11, l'adapter Ollama saura faire l'aller-retour tool calling, mais aucune commande utilisateur ne déclenche le mécanisme. C'est STEP-12 qui branche la boucle.
