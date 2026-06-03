# STEP-20 — Outil `apply_patch` (patch multi-fichiers)

**Statut :** ✅ terminé (`composer qa` vert)
**Branche :** `feat/sebcode-foundation`

## But

Compléter l'édition : appliquer un **patch multi-fichiers** en un seul appel (add / update / delete /
move), via l'enveloppe **apply_patch** d'opencode/OpenAI. Outil **mutant**, **local**, gated `edit`,
confiné au project root, et **all-or-nothing**.

## Format (fidèle opencode)

```
*** Begin Patch
*** Add File: path           (chaque ligne de contenu préfixée par +)
*** Update File: path        (option "*** Move to: newpath", puis hunks @@)
@@
 contexte
-ligne supprimée
+ligne ajoutée
*** Delete File: path
*** End Patch
```

## Décisions clés

| Décision | Choix | Raison |
|---|---|---|
| Découpage | `PatchParser` (parse) + `PatchApplier` (apply) **purs** dans le Domain ; `ApplyPatchTool` orchestre | Logique testable sans I/O ; outil = orchestration + I/O (comme edit/EditReplacer) |
| Application | **Two-phase** : parse + calcul de tous les changements en mémoire, **puis** écriture | **All-or-nothing** : un hunk qui échoue ⇒ aucun fichier touché |
| Matching des hunks | 4 passes : exact → rtrim → trim → unicode-normalisé ; ancre EOF ; retry sans ligne vide finale | Port fidèle de `seekSequence` (tolère la dérive de whitespace/quotes) |
| Permission | `edit`, **un seul** ask pour tout le patch, `always: ['*']` | Fidèle opencode ; cohérent avec write/edit (règle `edit:*` allow) |
| Confinement | `WorkspacePath::resolveForWrite` par fichier (rejette `..`/évasion) | Réutilise le sandbox de write/edit |
| Échecs | parse / chemin / hunk introuvable / fichier absent = **soft failures** ; permission refusée = **hard** | Le LLM peut corriger ; refus abort la boucle |
| heredoc | `PatchParser` déballe `cat <<'EOF' … EOF` | Robustesse (le LLM enveloppe parfois) |

## Layout produit

### Nouveau — Domain (`App/src/Tool/Domain/`)

```
Model/ValueObject/PatchOperationKind.php   (enum add|update|delete)
Model/PatchHunk.php                        (oldLines, newLines, isEndOfFile)
Model/FilePatch.php                        (kind, path, movePath?, content?, hunks)
Service/PatchParser.php                    (enveloppe → list<FilePatch>)
Service/PatchApplier.php                   (hunks → contenu ; seekSequence 4 passes)
Exception/InvalidPatch.php                 (parse : soft)
Exception/PatchApplyFailed.php             (contexte introuvable / fichier absent : soft)
```

### Nouveau — Infrastructure

```
Tool/ApplyPatchTool.php   (id "apply_patch" ; two-phase ; gate edit ; résumé A/M/D)
```

### Nouveau — Tests

```
Unit/Tool/Domain/Service/PatchParserTest.php          (add, delete, update+hunk, move, multi, heredoc, marqueurs manquants, header inconnu)
Unit/Tool/Domain/Service/PatchApplierTest.php         (hunk contextuel, insertion, fuzzy trim, ancre EOF, introuvable, multi-hunks)
Unit/Tool/Infrastructure/Tool/ApplyPatchToolTest.php  (add/update/delete/move ; all-or-nothing ; parse/traversal/fichier absent soft ; permission edit 1×/tous chemins ; refus n'écrit rien ; patchText vide → InvalidToolArguments)
```

### Modifié — Config

| Fichier | Changement |
|---|---|
| `config/services.yaml` | `apply_patch` ajouté au service-locator (`PatchParser`/`PatchApplier` autowirés comme les Domain/Service) |

## Sortie

```
Applied patch:
A sub/new.txt
M app.php
D gone.txt
M from.php -> to.php
```

## Comment vérifier

```bash
docker compose exec php composer qa     # cs 0 / phpstan max 0 / deptrac 0 / phpunit vert (256 tests, 532 assertions)
docker compose exec php bin/console lint:container

# Smoke LLM (le modèle doit produire l'enveloppe exacte) :
docker compose exec php bin/console assistant:ask -m qwen2.5:7b \
  "Avec apply_patch, crée le fichier demo/hello.txt contenant la ligne: bonjour"
```

## Step suivante

Outils : `glob`, `read`, `grep`, `write`, `edit`, `bash`, `todowrite`, `apply_patch`. Pistes locales
restantes : `task` (sous-agent), `lsp`, persistance (permissions/todos en Postgres), ou la boucle
agentique **async**.
