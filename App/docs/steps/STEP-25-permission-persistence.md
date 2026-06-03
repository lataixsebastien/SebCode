# STEP-25 — Persistance des permissions (« always » par projet)

**Statut :** ✅ terminé (`composer qa` vert + intégration verte)
**Branche :** `feat/sebcode-foundation`

## But

Rendre les décisions **« always »** durables : un choix « autoriser toujours » survit désormais **entre
les runs**, scopé **par projet** (faithful opencode, qui persiste ses permissions). Réutilise le harness
d'intégration de STEP-23.

> ⚠️ Implication sécurité assumée (validée) : un « always » accordé reste actif pour les runs futurs du
> projet. Seuls les choix **explicites** de l'utilisateur sont persistés.

## Décisions clés

| Décision | Choix | Raison |
|---|---|---|
| Backend | `DoctrinePermissionGrants` (table `tool_permission_grants`) ; `InMemoryPermissionGrants` gardé pour l'unitaire | Durabilité ; le in-memory reste simple pour les tests du gate/prompter |
| Clé | **par `project_root`** (PK composite `project_root, type, pattern`) | Permissions par projet ; dédup naturelle |
| Couplage | Pas de FK ; le contexte Tool possède la table | Découplé du contexte Assistant (DDD) |
| Cache | Grants du projet chargés une fois (lazy) puis cachés en mémoire ; `grant()` écrit DB + cache | `isGranted()` est appelé souvent → évite une requête par appel |
| Libellés | « (this session) » → « **(this project)** » en CLI et TUI | Reflète la portée réelle |

## Layout produit

### Nouveau

```
src/Tool/Infrastructure/Persistence/Doctrine/Entity/PermissionGrantEntity.php
src/Tool/Infrastructure/Persistence/Doctrine/Repository/DoctrinePermissionGrants.php
migrations/Version20260603130000.php                       (CREATE TABLE tool_permission_grants)
tests/Integration/Tool/Persistence/DoctrinePermissionGrantsTest.php
```

### Modifié

| Fichier | Changement |
|---|---|
| `config/services.yaml` | `PermissionGrants` → `DoctrinePermissionGrants` (`$projectRoot = %kernel.project_dir%`) |
| `Assistant/UI/Cli/ConsolePermissionConsole.php` | libellé « (this project) » |
| `Assistant/UI/Tui/TuiPermissionConsole.php` | libellés « (project) » |

## Comment vérifier

```bash
docker compose exec php composer qa     # cs / phpstan max / deptrac / phpunit Unit (vert)
docker compose exec php sh -lc 'php bin/console doctrine:migrations:migrate --env=test --no-interaction \
  && APP_ENV=test vendor/bin/phpunit --testsuite Integration'   # 6 tests verts (todos + permissions)
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction  # dev DB
```

## Step suivante

STEP-26 — outil `lsp` (diagnostics via language server local ; nécessite une modif du `Dockerfile`).
Puis STEP-27 — boucle agentique **async**.
