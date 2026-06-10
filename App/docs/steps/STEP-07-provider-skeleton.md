# STEP-07 — Correction Provider

## But

Appliquer la correction 3 de `CORRECTIONS_SEBCODE_ARCHI.md` : découpler le provider modèle pour qu'un futur `AgentRunner` puisse changer de provider sans modification interne.

## Décisions clés

- Ne pas créer de nouvelle feature utilisateur.
- Ne pas ajouter de provider cloud ni de multi-provider produit : seul un adapter Ollama local est ajouté.
- Respecter l'architecture actuelle : `Domain`, `Application`, `Infrastructure`, avec `Application` limité à `UseCase`, `DTO`, `Handler`.
- Ajouter `ModelProviderInterface`, `ProviderRegistry`, `OllamaProvider`, `MessageTranslator`, `ToolCallTranslator`.
- Faire passer la validation réseau local-only par un port `LocalNetworkPolicy`, implémenté via un adapter vers `Security\Domain\NetworkPolicy`.

## Fichiers touchés

- Ajoutés : `App/src/Provider/Domain/ModelProviderInterface.php`, `ProviderRegistry.php`, modèles `ModelRequest`, `ModelMessage`, `ModelReply`, `ToolCall`.
- Ajoutés : `App/src/Provider/Domain/Port/LocalNetworkPolicy.php`, exceptions provider.
- Ajoutés : `App/src/Provider/Application/Dto/*`, `App/src/Provider/Application/UseCase/GenerateModelReplyUseCase.php`, `App/src/Provider/Application/Handler/GenerateModelReplyHandler.php`.
- Ajoutés : `App/src/Provider/Infrastructure/Ollama/OllamaProvider.php`, `MessageTranslator.php`, `ToolCallTranslator.php`, `OllamaTransport.php`, `NativeOllamaTransport.php`.
- Ajouté : `App/src/Provider/Infrastructure/Security/SecurityLocalNetworkPolicyAdapter.php`.
- Ajouté : `App/tests/Unit/Provider/ProviderTest.php`.
- Modifié : `App/docs/architecture.md`.

## Comment vérifier

```bash
docker compose run --rm php composer qa
```

## Résultat

- Correction 3 appliquée : un futur `AgentRunner` pourra dépendre de `ModelProviderInterface` ou de `GenerateModelReplyHandler`, sans connaître `OllamaProvider`.
- `ProviderRegistry` sélectionne un provider par nom et reste déterministe.
- `OllamaProvider` est local-only : l'URL finale passe par `LocalNetworkPolicy`, implémenté par un adapter vers `Security\Domain\NetworkPolicy`.
- `MessageTranslator` traduit les messages domaine vers le format Ollama.
- `ToolCallTranslator` traduit les tool calls Ollama vers les modèles domaine.
- Aucun provider cloud ajouté.
- Aucun `Manager`, `Factory`, `Application/Command`, `Application/Query` ou `Domain/Service` ajouté.
- `docker compose run --rm php composer qa` : OK, 37 tests, 88 assertions.

## Risques restants

- `NativeOllamaTransport` est un transport minimal par `file_get_contents`; l'intégration Symfony AI/Ollama pourra remplacer ce transport dans un step dédié.
- Le provider n'est pas encore branché à un agent, conformément au gel des features.
- La correction suivante du markdown est la mémoire persistante `.sebcode/*.db`, à faire seulement après validation utilisateur.

## Step suivante

Validation utilisateur requise avant correction suivante, conformément à `CORRECTIONS_SEBCODE_ARCHI.md`.
