# STEP-05 — Refactor hexagonal DDD

## But

Corriger le socle initial pour respecter l'architecture demandée : un bounded context par domaine, avec `Domain`, `Application`, `Infrastructure` et `UI` au lieu d'un dossier transversal `Core`.

## Décisions clés

- Supprimer `App/src/Core/*` : le dossier était trop horizontal et ne respectait pas le découpage DDD.
- Créer les bounded contexts `Workspace`, `Security` et `Tool`.
- Mettre les règles pures dans `Domain` : value objects, exceptions, ports et services métier.
- Mettre les use cases dans `Application` : commands/queries handlers, managers applicatifs.
- Mettre les adapters et factories techniques dans `Infrastructure` : filesystem probe, adapter workspace pour la permission, repositories in-memory, factories de wiring.
- Garder le runtime Composer minimal : aucune nouvelle dépendance.

## Fichiers touchés

- Ajoutés : `App/src/Workspace/Domain/*`, `App/src/Workspace/Application/*`, `App/src/Workspace/Infrastructure/*`.
- Ajoutés : `App/src/Security/Domain/*`, `App/src/Security/Application/*`, `App/src/Security/Infrastructure/*`.
- Ajoutés : `App/src/Tool/Domain/*`, `App/src/Tool/Application/*`, `App/src/Tool/Infrastructure/*`.
- Ajouté : `App/docs/architecture.md`.
- Supprimés : `App/src/Core/*` et `App/tests/Unit/Core/*`.
- Mis à jour : tests unitaires/sécurité pour viser les bounded contexts.

## Comment vérifier

```bash
docker compose run --rm php composer qa
```

## Résultat

- `Workspace` expose maintenant `Domain`, `Application` et `Infrastructure`, avec `ResolveWorkspacePathHandler`, `WorkspaceGuard`, `FilesystemProbe` et `NativeFilesystemProbe`.
- `Permission` expose maintenant `Domain`, `Application` et `Infrastructure`, avec `PermissionPolicy`, `EvaluatePermissionHandler`, `WorkspaceAccessPolicy` et `WorkspaceAccessPolicyAdapter`.
- `Tool` expose maintenant `Domain`, `Application` et `Infrastructure`, avec `Tool`, `ToolRepository`, `ExecuteToolHandler`, `ListToolsHandler` et `InMemoryToolRepository`.
- L'ancien dossier horizontal `Core` a été supprimé.
- `docker compose run --rm php composer qa` : OK, 30 tests, 59 assertions.

## Risques restants

- `InMemoryToolRepository` est suffisant pour le bootstrap ; une persistance durable arrivera avec les tools/session/memory.
- Les tools concrets ne sont pas encore implémentés après ce refactor.

## Step suivante

`STEP-06-file-search-git-tools.md` : implémenter `read_file`, `list_files`, `grep`, `glob`, `git_status`, `git_diff` dans le bounded context `Tool`.
