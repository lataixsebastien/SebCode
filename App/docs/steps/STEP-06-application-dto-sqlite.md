# STEP-06 — Application DTO et SQLite

## But

Corriger la couche Application pour respecter `CORRECTIONS_SEBCODE_ARCHI.md` : uniquement `UseCase`, `DTO` et `Handler`, sans manager ni factory, avec SQLite comme stockage local.

## Décisions clés

- `Application/UseCase` contient les messages applicatifs.
- `Application/Handler` contient les handlers de use cases.
- `Application/Dto/Input` et `Application/Dto/Output` contiennent les contrats d'entrée/sortie des use cases.
- Aucun `Manager`, `ManagerFactory`, `AbstractFactory`, `Application/Command`, `Application/Query` ou `Domain/Service`.
- Le wiring reste explicite au point de composition, sans factory d'infrastructure.
- SQLite devient le stockage local cible via `pdo_sqlite` ; premier adapter : catalogue de tools persisté en SQLite.
- `Permission` devient un bounded context séparé de `Security` pour porter `PermissionPolicy`, `DecisionEngine`, `ApprovalStore` et `PermissionContext`.
- `ToolDescriptor` est enrichi avec `category`, `safe`, `cost`, `allowed_modes`, `timeout`, `requires_review`.

## Fichiers touchés

- Modifiés : `Workspace/Application`, `Permission/Application`, `Tool/Application`.
- Supprimés : managers, factories, commands/queries applicatifs et dossiers `Domain/Service`.
- Ajoutés : `Permission/Domain/*`, `Permission/Application/*`, `Permission/Infrastructure/*`.
- Ajouté : `Tool/Infrastructure/Persistence/SqliteToolRepository.php`.
- Modifiés : `ToolDescriptor`, `ToolRepository`, repositories Tool, `composer.json`, `docker/php/Dockerfile`, tests et docs.

## Comment vérifier

```bash
docker compose run --rm php composer update --lock
docker compose run --rm php composer qa
```

## Résultat

- Correction 1 analysée : aucun `SendMessageHandler` n'existe dans ce nouveau socle, donc aucun God Object à extraire à ce stade.
- Correction 2 appliquée : les façades `WorkspaceManager`, `PermissionManager`, `ToolManager` ont été supprimées au profit de `ResolveWorkspaceUseCase`, `EvaluatePermissionUseCase`, `ExecuteToolUseCase`, `ListToolsUseCase` et leurs handlers.
- Correction 5 appliquée : `ToolDescriptor` porte les champs riches et les repositories filtrent la disponibilité par mode.
- Correction 7 appliquée : création du bounded context `Permission` avec `PermissionPolicy`, `DecisionEngine`, `ApprovalStore`, `PermissionContext`.
- Correction 8 appliquée sur le code courant : plus de `Manager`, `ManagerFactory`, `AbstractFactory`, `Application/Command`, `Application/Query` ni `Domain/Service`.
- SQLite ajouté via `ext-pdo_sqlite`, `pdo_sqlite` dans Docker et `SqliteToolRepository`.
- `docker compose run --rm php composer update --lock` : OK.
- `docker compose run --rm php composer qa` : OK, 32 tests, 73 assertions.

## Risques restants

- Corrections 3, 4, 6 et 9 concernent des zones non encore présentes ou non branchées dans le socle actuel : Provider/Agent, mémoire runtime complète, RepoContext et Metrics.
- Le markdown demande une validation avant correction suivante ; le prochain step doit traiter la correction suivante explicitement avant toute feature.

## Step suivante

`STEP-07-provider-skeleton.md` ou validation utilisateur : traiter la correction suivante du markdown avant toute feature.
