# Architecture SebCode

SebCode est découpé en bounded contexts. Chaque contexte suit une architecture hexagonale : `Domain`, `Application`, `Infrastructure`, puis `UI` quand le contexte expose une entrée utilisateur.

## Contextes actuels

- `Workspace` : normalisation de chemins, garde workspace, exclusions secrets, contrôle symlink via port filesystem.
- `Security` : redaction de secrets et politique réseau local-only.
- `Permission` : décisions de permission, moteur de décision, contexte et stockage d'approbation.
- `Provider` : abstraction modèle, registry de providers, adapter Ollama local et translators.
- `Tool` : contrat de tools, repository de tools, use cases d'exécution et de listing.
- `UI` : entrée console bootstrap actuelle.

## Layout

```text
src/<Context>/
├── Domain/
│   ├── Model/
│   ├── Exception/
│   └── Port/
├── Application/
│   ├── Dto/
│   │   ├── Input/
│   │   └── Output/
│   ├── UseCase/
│   └── Handler/
└── Infrastructure/
    ├── Repository/
    ├── Persistence/
    └── <Adapter technique>/
```

## Règles

- `Domain` ne dépend pas de Symfony, Doctrine, filesystem natif ou d'un autre contexte concret.
- `Application` contient uniquement DTOs, UseCases et Handlers.
- `Infrastructure` implémente les ports, repositories et adapters techniques.
- Les dépendances inter-contextes passent par des ports et adapters d'infrastructure.
- `UI` reste console-first : pas d'API HTTP ni de contrôleur web dans le MVP.

## Exemples actuels

- `Workspace\Domain\Port\FilesystemProbe` est implémenté par `Workspace\Infrastructure\Filesystem\NativeFilesystemProbe`.
- `Permission\Domain\Port\WorkspaceAccessPolicy` est implémenté par `Permission\Infrastructure\Workspace\WorkspaceAccessPolicyAdapter`, qui appelle `Workspace\Application\Handler\ResolveWorkspacePathHandler`.
- `Permission\Domain\Port\NetworkAccessPolicy` est implémenté par `Permission\Infrastructure\Security\SecurityNetworkAccessPolicyAdapter`.
- `Tool\Domain\Port\ToolRepository` est implémenté par `Tool\Infrastructure\Repository\InMemoryToolRepository` et `Tool\Infrastructure\Persistence\SqliteToolRepository`.
- `Tool\Application\Handler\ExecuteToolHandler` exécute `Tool\Application\UseCase\ExecuteToolUseCase`.
- `Provider\Domain\ModelProviderInterface` est implémenté par `Provider\Infrastructure\Ollama\OllamaProvider`.
- `Provider\Domain\Port\LocalNetworkPolicy` est implémenté par `Provider\Infrastructure\Security\SecurityLocalNetworkPolicyAdapter`.
- `Provider\Application\Handler\GenerateModelReplyHandler` sélectionne un provider via `Provider\Domain\ProviderRegistry`, sans connaître Ollama.
