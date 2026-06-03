# STEP-19 — Outil `todowrite` (liste de tâches de l'agent)

**Statut :** ✅ terminé (`composer qa` vert)
**Branche :** `feat/sebcode-foundation`

## But

Donner à l'agent une **liste de tâches** qu'il maintient pendant la session (pending / in_progress /
completed / cancelled + priorité), pour planifier le travail multi-étapes et rendre sa progression
visible en CLI/TUI. Port **local** et fidèle d'opencode (`todowrite`).

## Décisions clés

| Décision | Choix | Raison |
|---|---|---|
| Un seul outil | `todowrite` (pas de `todoread`) | Fidèle opencode : la liste est ré-affichée à chaque écriture et reste dans l'historique de conversation |
| Sémantique | **Remplacement total** de la liste à chaque appel | Fidèle opencode (`todowrite` envoie la liste complète) |
| Modèle d'item | `content` + `status` + `priority`, pas d'id (ordre = position) | Fidèle opencode |
| **Stockage** | **Store en mémoire, process-lifetime, NON keyé** (mirror `InMemoryPermissionGrants`) | Un process = une session aujourd'hui → le `SessionId` n'apporte rien à un store mémoire ; évite de plomber `ToolExecutionContext`/`ToolGateway` → **zéro modif du contexte Assistant** |
| Permission | Aucune | Effet de bord limité à la liste en mémoire (pas de disque) |
| Validation | `status`/`priority` via `tryFrom` → **soft failure** si invalide ; `InvalidToolArguments` si `todos` n'est pas un array | Le LLM peut corriger ; schéma dur = vraie erreur |
| Règle « un seul in_progress » | Comportementale (guidée par la description), non contrainte | Fidèle opencode |
| Rendu | Aucune modif d'UI | Le TUI affiche déjà la sortie d'outil multi-lignes ; la CLI la collapse (ok pour listes courtes) |

## Layout produit

### Nouveau — Domain (`App/src/Tool/Domain/`)

```
Model/ValueObject/TodoStatus.php     (enum pending|in_progress|completed|cancelled)
Model/ValueObject/TodoPriority.php   (enum high|medium|low)
Model/TodoItem.php                   (VO : content non vide + status + priority)
Port/TodoStore.php                   (replace(list<TodoItem>) / all(): list<TodoItem>)
```

### Nouveau — Infrastructure (`App/src/Tool/Infrastructure/`)

```
Todo/InMemoryTodoStore.php   (liste mutable en mémoire ; mirror InMemoryPermissionGrants)
Tool/TodoWriteTool.php       (id "todowrite" ; injecte TodoStore ; rend une checklist)
```

### Nouveau — Tests

```
Unit/Tool/Domain/Model/TodoItemTest.php                    (content vide rejeté)
Unit/Tool/Infrastructure/Todo/InMemoryTodoStoreTest.php    (replace/all ; 2ᵉ replace écrase)
Unit/Tool/Infrastructure/Tool/TodoWriteToolTest.php        (écrit & persiste ; checklist + metadata ; status/priority/content invalides → soft fail ; non-array → InvalidToolArguments ; liste vide efface)
```

### Modifié — Config

| Fichier | Changement |
|---|---|
| `config/services.yaml` | `todowrite` ajouté au service-locator ; binding `TodoStore` → `InMemoryTodoStore` (près de `PermissionGrants`) |

## Format de sortie

```
3 todos · 2 open
  [✓] read the file
  [•] wire the CLI
  [ ] add tests
```
Marqueurs : ✓ completed · • in_progress · (espace) pending · ✗ cancelled. `metadata.todos` porte la
structure complète (content/status/priority).

## Comment vérifier

```bash
docker compose exec php composer qa     # cs 0 / phpstan max 0 / deptrac 0 / phpunit vert (232 tests, 482 assertions)
docker compose exec php bin/console debug:container "App\Tool\Domain\Port\TodoStore"
# → App\Tool\Infrastructure\Todo\InMemoryTodoStore

docker compose exec php bin/console assistant:ask -m qwen2.5:7b \
  "Utilise todowrite : 1) lire src/Kernel.php (in_progress, high) 2) résumer (pending, medium) 3) test (pending, low)."
# attendu : 🔧 todowrite(...) puis ✓ todowrite → la checklist (… open)
```

## Limites assumées

- Todos **non persistés** entre 2 process (mémoire) → perdus si on relance `assistant:ask --session …`.
  Upgrade futur : `TodoStore` Doctrine/Postgres keyé par session (nécessitera de plomber le `SessionId`
  jusqu'au `ToolExecutionContext`).

## Step suivante

Outils : `glob`, `read`, `grep`, `write`, `edit`, `bash`, `todowrite`. Pistes locales restantes :
`apply_patch`, `task` (sous-agent), `lsp`, persistance (permissions/todos), ou la boucle agentique async.
