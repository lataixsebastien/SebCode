# STEP-15 — `WriteTool` + `EditTool` : premiers outils mutants

**Statut :** ✅ terminé (`composer qa` vert)
**Branche :** `feat/sebcode-foundation`
**ADR lié :** [ADR-0007](../adr/0007-permission-layer.md) (couche permission consommée ici)

## But

Donner à l'assistant le pouvoir d'**écrire** dans le workspace : `write` (crée/écrase un fichier) et
`edit` (remplace une chaîne dans un fichier existant). Ce sont les **premiers consommateurs** de la
couche `PermissionGate` bâtie au STEP-14 (jusque-là dormante) et les premiers outils à franchir la
frontière read-only.

L'`edit` porte fidèlement la *replacer-chain* d'opencode (`edit.ts`) : neuf stratégies de la plus
exacte à la plus floue, pour tolérer les petits écarts entre le `oldString` produit par le LLM et les
octets réels du fichier.

## Décisions clés

| Décision | Choix | Raison |
|---|---|---|
| Confinement chemin | `WorkspacePath` : rejet lexical de `..`, validation realpath des ancêtres, refus de sortir du projet | Sécurité : un outil mutant ne doit jamais écrire hors du workspace (miroir du sandbox opencode) |
| Replacer-chain | Port **complet des 9 stratégies** (`Simple` → `MultiOccurrence`), pas seulement 3 | Fidélité à `edit.ts` ; les tests couvrent exact / indentation / block-anchor |
| `replaceAll` | `str_replace` byte-level (toutes occurrences, première règle qui matche gagne) | Mirror exact de `content.replaceAll(search, …)` d'opencode (`edit.ts:697`) |
| `oldString` vide | Crée/écrase le fichier avec `newString` | Comportement d'opencode `edit.ts` |
| Échecs « match » | **Soft failures** (`ToolResult` d'erreur renvoyé au LLM) | Le LLM peut corriger et re-essayer ; pas d'abort de la boucle |
| Permission refusée | **Hard failure** (`PermissionDenied` remonte, abort la boucle) | Cohérent avec STEP-14 / `ToolExecutionFailed` |
| Moment du gate sur `edit` | On demande la permission **après** que le match a réussi | On ne sollicite l'utilisateur que pour une mutation réellement applicable |
| Prompter interactif | **Différé au STEP-16** | L'UI n'est pas encore confortablement dans la pile d'appel ; le défaut `deny` + règle `edit:allow` suffit pour ce step |
| Règle de permission | `{ type: edit, pattern: '*', action: allow }` | `write`/`edit` sont déjà confinés au workspace par `WorkspacePath`/realpath ; l'autorisation fine arrivera avec le prompter interactif |

## Layout produit

### Nouveau — Domain (`App/src/Tool/Domain/`)

```
Service/EditReplacer.php          (port fidèle des 9 replacers de edit.ts ; similarité Levenshtein)
Service/WorkspacePath.php         (resolveForWrite : rejet '..', realpath, confinement projet — pur stdlib)
Exception/EditConflict.php        (no-change / not-found / multiple-matches, messages miroir d'opencode)
Exception/PathNotAllowed.php      (chemin vide, traversal, sortie de la racine projet)
```

### Nouveau — Infrastructure (`App/src/Tool/Infrastructure/Tool/`)

```
WriteTool.php   (tool `write` : valide, gate `edit`, crée les dossiers parents, renvoie le nb d'octets)
EditTool.php    (tool `edit` : EditReplacer, détecte CRLF/LF, gate après match, conflits en soft failure)
```

### Nouveau — Tests (`App/tests/`)

```
Unit/Tool/Domain/Service/EditReplacerTest.php          (exact, replaceAll byte-level, no-change/not-found/ambigu, indentation, block-anchor)
Unit/Tool/Infrastructure/Tool/EditToolTest.php          (10 : edit exact, replaceAll, création, fichier absent, string absente, gate allow/deny, validation)
Unit/Tool/Infrastructure/Tool/WriteToolTest.php         (10 : création, écrasement, dossiers imbriqués, gate allow/deny, rejet traversal, validation)
Support/Tool/Doubles/FakePermissionGate.php             (double : enregistre les requêtes, peut refuser)
```

### Modifié

| Fichier | Changement |
|---|---|
| `config/services.yaml` | Service-locator du registry : ajout `write` / `edit` ; param `tool.permission.rules` : règle `{ type: edit, pattern: '*', action: allow }` |

### NON modifié (volontaire)

`ConfigDefaultPermissionPrompter` reste le seul prompter (non-interactif) — le prompter interactif
CLI/TUI est décalé au STEP-16. `Tool.php` (interface) et `ToolExecutionContext.php` inchangés :
`PermissionGate` est injecté par **constructeur** dans `WriteTool`/`EditTool` (décision STEP-14).

## Fidélité opencode

| opencode (`tool/edit.ts`, `tool/write.ts`) | SebCode |
|---|---|
| `replace()` : boucle de replacers, première règle qui matche | `EditReplacer::replace()` |
| 9 replacers `Simple`…`MultiOccurrence` | méthodes privées de `EditReplacer` |
| `content.replaceAll(search, …)` si `replaceAll` | `str_replace($search, …)` |
| `oldString` vide ⇒ create/overwrite | idem dans `EditTool` |
| `ctx.ask({ permission: 'edit', … })` | `PermissionGate::ensure(PermissionRequest::edit(...))` |
| confinement au workspace | `WorkspacePath::resolveForWrite()` |

## Comment vérifier

```bash
docker compose exec php composer qa
# attendu : cs 0 / phpstan max 0 / deptrac 0 violation / phpunit vert (170 tests, 364 assertions)

# Smoke LLM réel — premier outil mutant de bout en bout :
docker compose exec php bin/console assistant:ask -m qwen2.5:7b \
  "Utilise l'outil write pour créer var/tmp/smoke_step15.txt avec le contenu: salut"
# attendu : 🔧 write({...})  puis  ✓ write → Wrote var/tmp/smoke_step15.txt (5 bytes).
docker compose exec php sh -lc 'cat var/tmp/smoke_step15.txt && rm var/tmp/smoke_step15.txt'
```

Smoke validé le 2026-06-02 : le LLM a appelé `write`, le fichier a été créé (5 octets), le rendu CLI
du tool call + résultat s'affiche, la couche permission a laissé passer via la règle `edit:allow`.

## Step suivante

**STEP-16 — Prompter interactif + `shell`/bash** : implémenter le prompter interactif CLI/TUI
(confirmation y/n dans la pile d'appel) puis l'outil `shell` (nouveau type de permission `bash`,
qui en a réellement besoin). Ensuite : `grep` (recherche de contenu, toujours absent).
