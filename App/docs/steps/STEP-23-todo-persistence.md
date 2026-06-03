# STEP-23 — Persistance des todos en Postgres + plomberie `SessionId`

**Statut :** ✅ terminé (`composer qa` vert + test d'intégration vert sur Postgres)
**Branche :** `feat/sebcode-foundation`

## But

Rendre les todos **durables et scopés par session** (au lieu d'en mémoire process-lifetime), comme
opencode. Au passage, **câbler le `SessionId` jusqu'aux outils** — plomberie réutilisable par tous les
futurs outils session-stateful. Premier **test d'intégration** réel du projet (Postgres `app_test`).

## Décisions clés

| Décision | Choix | Raison |
|---|---|---|
| `SessionId` aux outils | Ajout d'un `string $sessionId` à `ToolExecutionContext` (défaut `''`), passé via `ToolGateway::execute(request, sessionId)` → adaptateur → contexte | Plomberie minimale, ne casse pas les tests d'outils existants (param défauté) |
| `TodoStore` | Keyé par session : `replace(sessionId, items)` / `all(sessionId)` | Sépare les sessions ; prêt pour un backend persistant |
| Backend prod | `DoctrineTodoStore` (table `tool_todos`) ; `InMemoryTodoStore` gardé pour les tests | Durabilité ; le in-memory reste simple pour l'unitaire |
| Couplage inter-contexte | **Pas de FK** vers `assistant_sessions` ; `session_id` = simple string indexée | Le contexte Tool reste découplé du schéma Assistant (DDD) |
| Clé | PK composite `(session_id, position)` | Fidèle opencode (pas d'id surrogate ; position = ordre) |
| `replace()` | **load + remove + flush, puis insert + flush**, en transaction | Un DELETE DQL en masse laisse les entités dans l'identity map → `EntityIdentityCollisionException` au 2ᵉ `replace()` du même process (bug attrapé par le test d'intégration) |

## Layout produit

### Nouveau

```
src/Tool/Infrastructure/Persistence/Doctrine/Entity/TodoEntity.php          (table tool_todos, PK composite)
src/Tool/Infrastructure/Persistence/Doctrine/Repository/DoctrineTodoStore.php
migrations/Version20260603120000.php                                         (CREATE TABLE tool_todos)
tests/Integration/Tool/Persistence/DoctrineTodoStoreTest.php                 (KernelTestCase vs Postgres app_test)
```

### Modifié

| Fichier | Changement |
|---|---|
| `Tool/Domain/Port/ToolExecutionContext.php` | + `string $sessionId = ''` |
| `Tool/Domain/Port/TodoStore.php` | `replace`/`all` prennent `sessionId` |
| `Tool/Infrastructure/Todo/InMemoryTodoStore.php` | map `sessionId → list` |
| `Tool/Infrastructure/Tool/TodoWriteTool.php` | `replace($context->sessionId, $items)` |
| `Assistant/Domain/Port/ToolGateway.php` | `execute(request, string $sessionId)` |
| `Assistant/Infrastructure/Tool/AssistantToolGatewayAdapter.php` | passe `$sessionId` au contexte |
| `Assistant/Application/Command/SendMessageHandler.php` | `execute($call, $command->sessionId->value)` |
| `config/packages/doctrine.yaml` | mapping `Tool` (Entity dir) |
| `config/services.yaml` | `TodoStore` → `DoctrineTodoStore` |
| tests | `RecordingToolGateway` (signature + capture sessionId) ; `InMemoryTodoStoreTest`/`TodoWriteToolTest` keyés |

## Harness d'intégration (nouveau pour le projet)

- Base de test : `app_test` (suffixe `_test` via `when@test`).
- Setup : `doctrine:database:create --env=test` + `doctrine:migrations:migrate --env=test`.
- Les tests étendent `KernelTestCase`, bootent en `APP_ENV=test` (forcé dans `phpunit.dist.xml`),
  construisent le store avec le vrai `EntityManager`, nettoient `tool_todos` en setUp/tearDown.
- `composer qa` ne lance que la suite **Unit** ; l'intégration se lance via
  `vendor/bin/phpunit --testsuite Integration`.

## Comment vérifier

```bash
docker compose exec php composer qa     # cs / phpstan max / deptrac / phpunit Unit (vert)
docker compose exec php sh -lc 'php bin/console doctrine:database:create --env=test --if-not-exists \
  && php bin/console doctrine:migrations:migrate --env=test --no-interaction \
  && APP_ENV=test vendor/bin/phpunit --testsuite Integration'   # 3 tests verts
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction  # dev DB (tool_todos)
```

## Step suivante

Plomberie `SessionId` désormais disponible pour les outils session-stateful. Pistes restantes :
`task` (sous-agent), `lsp`, persistance des permissions, ou la boucle agentique **async**.
