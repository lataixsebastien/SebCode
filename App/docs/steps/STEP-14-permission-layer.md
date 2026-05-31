# STEP-14 — Couche `Permission` (fidèle à opencode), avant les outils mutants

**Statut :** ✅ terminé (`composer qa` vert)
**Branche :** `feat/sebcode-foundation`
**ADR lié :** [ADR-0007](../adr/0007-permission-layer.md)

## But

Préparer l'arrivée des outils **mutants** (`write`/`edit` au STEP-15, `shell` au STEP-16) en bâtissant
d'abord la couche d'**autorisation** qu'ils utiliseront tous. Décision produit : « Permission d'abord,
fidèle » — on réplique le sous-système `permission/` d'opencode comme une brique propre, **avant** le
premier outil qui l'appelle.

Au STEP-14 la couche est **dormante** : aucun outil ne la consomme encore (les read-only `glob`/`read`
n'en ont pas besoin, exactement comme dans opencode). La validation est donc 100 % unitaire.

## Décisions clés

| Décision | Choix | Raison |
|---|---|---|
| Où vit la couche | Dans le contexte `Tool` | L'autorisation est intrinsèque à l'exécution d'outils (cf. ADR-0007) |
| Évaluation ruleset | **Dernière règle qui matche gagne**, défaut `Ask` | Mirror exact de `core/permission.ts::evaluate` (`findLast`) |
| Matching wildcard | `*`→`.*`, `?`→`.`, `\`→`/`, `" *"` final → `( .*)?` | Port fidèle de `core/util/wildcard.ts::match` |
| Injection dans les outils | Par **constructeur** (STEP-15+), pas via `ToolExecutionContext` | Garde le VO de contexte pur et l'interface `Tool` inchangée |
| Prompter livré | `ConfigDefaultPermissionPrompter` non-interactif (défaut `deny`) | Pas d'UI dans la pile d'appel encore ; sûr en one-shot |
| `PermissionDenied` | Hard failure (remonte, abort la boucle) | Cohérent avec `ToolExecutionFailed` (non catché dans `ExecuteToolHandler`) ; miroir de `DeniedError`/`RejectedError` |

## Layout produit

### Nouveau — Domain (`App/src/Tool/Domain/`)

```
Model/ValueObject/PermissionType.php       (enum edit | bash | external_directory)
Model/ValueObject/PermissionAction.php     (enum allow | deny | ask)
Model/ValueObject/PermissionRequest.php    (type + list<string> patterns + metadata)
Model/PermissionRule.php                    (typePattern, subjectPattern, action)
Model/PermissionRuleset.php                 (evaluate(): last-match-wins, défaut Ask)
Service/WildcardMatcher.php                 (port fidèle de Wildcard.match)
Port/PermissionGate.php                     (ensure(PermissionRequest): void)
Port/PermissionPrompter.php                 (prompt(request, subject): PermissionAction)
Exception/PermissionDenied.php             (hard failure)
```

### Nouveau — Infrastructure (`App/src/Tool/Infrastructure/Permission/`)

```
RulesetPermissionGate.php                   (Allow passe / Deny jette / Ask → prompter)
ConfigDefaultPermissionPrompter.php         (défaut non-interactif via TOOL_PERMISSION_DEFAULT)
RulesetFactory.php                          (fromConfig: rows → PermissionRuleset)
```

### Nouveau — Tests (`App/tests/`)

```
Unit/Tool/Domain/Service/WildcardMatcherTest.php           (13 cas : *, ?, slash, trailing, backslash)
Unit/Tool/Domain/Model/PermissionRulesetTest.php           (allow/deny/défaut ask/last-wins/type-match/wildcard)
Unit/Tool/Domain/Model/ValueObject/PermissionRequestTest.php (validation patterns)
Unit/Tool/Infrastructure/Permission/RulesetPermissionGateTest.php  (allow/deny/ask→prompter/tous patterns)
Unit/Tool/Infrastructure/Permission/ConfigDefaultPermissionPrompterTest.php (deny/allow/fallback/ask rejeté)
Support/Tool/Doubles/FakePermissionPrompter.php            (double scriptable)
```

### Modifié

| Fichier | Changement |
|---|---|
| `config/services.yaml` | Param `tool.permission.rules: []` ; aliases `PermissionGate`/`PermissionPrompter` ; factory `PermissionRuleset` + prompter via `fromString('%env(TOOL_PERMISSION_DEFAULT)%')` |
| `.env` | Bloc `sebcode/tool-permission` avec `TOOL_PERMISSION_DEFAULT=deny` |

### NON modifié (volontaire)

`ToolExecutionContext.php`, `Tool.php` (interface), `GlobTool.php`, `ReadTool.php`,
`ExecuteToolHandler.php` — la couche reste dormante jusqu'au STEP-15.

## Fidélité opencode

| opencode | SebCode |
|---|---|
| `ctx.ask({ permission, patterns, metadata })` | `PermissionGate::ensure(PermissionRequest)` |
| `core/permission.ts::evaluate` (findLast, défaut ask) | `PermissionRuleset::evaluate()` |
| `core/util/wildcard.ts::match` | `WildcardMatcher::matches()` |
| actions `allow`/`deny`/`ask` | `PermissionAction` |
| `DeniedError` / `RejectedError` | `PermissionDenied` |
| permissions `edit` / `bash` / `external_directory` | `PermissionType` |
| `fromConfig()` (expansion du bloc `permission`) | `RulesetFactory::fromConfig()` |

## Comment vérifier

```bash
docker compose exec php composer qa
# attendu : cs 0 / phpstan max 0 / deptrac 0 violation / phpunit vert (137 tests, 327 assertions)

# Le câblage DI résout bien :
docker compose exec php bin/console debug:container "App\Tool\Domain\Port\PermissionGate"
# → App\Tool\Infrastructure\Permission\RulesetPermissionGate
```

Pas de smoke LLM : aucun outil ne consomme encore la couche. Le premier smoke réel viendra au STEP-15
quand `write`/`edit` appelleront `PermissionGate::ensure()`.

## Step suivante

**STEP-15 — `WriteTool` + `EditTool`** : premiers consommateurs de `PermissionGate` (permission `edit`),
confinement `realpath`, politique read-before-write, et portage de la *replacer-chain* de
`edit.ts` (au moins Simple / LineTrimmed / BlockAnchor au départ). Ajout d'un prompter **interactif**
CLI/TUI à ce moment-là (l'UI sera accessible dans la pile d'appel).
