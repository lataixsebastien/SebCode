# STEP-17 — Outil `grep` (recherche de contenu)

**Statut :** ✅ terminé (`composer qa` vert)
**Branche :** `feat/sebcode-foundation`

## But

Compléter la panoplie d'outils cœur avec **`grep`** : recherche du contenu des fichiers par expression
régulière, dernier outil fondamental encore absent (on avait `glob`/`read`/`write`/`edit`/`bash`). Outil
**read-only**, donc — comme `glob`/`read` — confiné par `realpath()` au project root et **sans permission**.

## Décisions clés

| Décision | Choix | Raison |
|---|---|---|
| Moteur de recherche | **PHP pur** (parcours récursif + PCRE par ligne) | opencode shelle vers `rg`, mais SebCode réimplémente déjà glob en PHP ; cohérent, testable, zéro dépendance `rg` dans le conteneur |
| Sémantique | Fidèle à opencode (`grep.ts`) | `pattern` regex, `path` optionnel, filtre `include`, group-by-file, tri **mtime desc**, cap 100, troncature ligne 2000, `.git` exclu |
| Délimiteur PCRE | `\1` (SOH) autour du pattern brut | Le pattern utilisateur est une regex sans délimiteur (comme rg) ; un délimiteur control-char évite toute collision. Compile invalide → soft failure |
| Fichiers binaires | Sautés si une ligne contient un octet NUL | Évite de polluer la sortie ; comportement par défaut de rg |
| Borne mémoire | Arrêt du scan à `SCAN_CEILING = 1000` matches | Le parcours PHP charge en mémoire ; on borne et on signale `--- search stopped after 1000 matches ---` |
| `include` avec accolades | Expansion `{a,b}` → `*.a`, `*.b` puis `fnmatch` (casefold) | Supporte `*.{ts,tsx}` sans dépendre de `GLOB_BRACE` |

## Layout produit

### Nouveau — Infrastructure

```
App/src/Tool/Infrastructure/Tool/GrepTool.php   (id "grep" ; read-only ; confiné realpath)
```

### Nouveau — Tests

```
App/tests/Unit/Tool/Infrastructure/Tool/GrepToolTest.php
  (file:line, 0 match, regex \s+\w+, include *.php, braces *.{ts,tsx}, scope path,
   path hors root → soft fail, regex invalide → soft fail, binaire sauté, .git exclu,
   troncature ligne 2000, tri mtime desc, pattern vide → InvalidToolArguments)
```

### Modifié — Config

| Fichier | Changement |
|---|---|
| `config/services.yaml` | `grep` ajouté au service-locator du registry (entre `glob` et `read`) |

## Format de sortie

```
Found 3 matches

src/Tool/Domain/Port/PermissionGate.php:
  Line 24: interface PermissionGate

src/Tool/Domain/Service/EditReplacer.php:
  Line 24: final class EditReplacer
```

En-tête `Found N matches` (+ ` (showing first 100)` si tronqué) ; fichiers groupés, triés du plus
récemment modifié au plus ancien ; chemins **relatifs** au project root ; lignes > 2000 car. tronquées.

## Fidélité opencode

| opencode (`tool/grep.ts`) | SebCode |
|---|---|
| params `pattern` / `path` / `include` | identiques |
| `rg --json` + tri mtime desc + limit 100 | parcours PHP + tri mtime desc + cap 100 |
| `  Line N: text`, group-by-file | identique |
| ligne > `MAX_LINE_LENGTH` (2000) → `…` | `MAX_LINE_LENGTH = 2000` |
| exclusion `.git` | `--glob=!.git/*` → saut des chemins `/.git/` |

## Comment vérifier

```bash
docker compose exec php composer qa
# attendu : cs 0 / phpstan max 0 / deptrac 0 violation / phpunit vert (207 tests, 439 assertions)

# Smoke LLM réel :
docker compose exec php bin/console assistant:ask -m qwen2.5:7b \
  "Utilise l'outil grep pour trouver les lignes contenant 'final readonly class' dans App/src/Tool/Domain."
# attendu : 🔧 grep(...) puis ✓ grep → Found N matches … (chemins + Line X: …)
```

## Step suivante

Outils cœur **complets** (`glob`, `read`, `write`, `edit`, `bash`, `grep`). Restent au choix :
- **Prompter TUI** (widget modal dans la boucle Revolt) + scoping des `PermissionGrants` par session —
  reporté depuis STEP-16.
- Outils opencode plus avancés : `webfetch`/`websearch`, `task`/`todo`, `lsp`, `apply_patch`.
