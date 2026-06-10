# STEP-04 — Tool system

## But

Créer le système minimal de tools : contrat d'exécution, descriptor, contexte, résultat, registry et executor.

## Décisions clés

- Ne pas implémenter encore les tools fichier/search/git/shell : ce step ne pose que le runtime commun.
- Garder les schemas d'entrée sous forme `array<string, mixed>` pour éviter une dépendance JSON Schema prématurée.
- `ToolExecutor` transforme les erreurs d'exécution en `ToolResult::failure()` afin que la future boucle agent puisse continuer proprement.
- `ToolRegistry` reste déterministe et refuse les doublons.

## Fichiers touchés

- Ajoutés : `App/src/Core/Tool/ToolInterface.php`, `App/src/Core/Tool/ToolDescriptor.php`, `App/src/Core/Tool/ToolExecutionContext.php`, `App/src/Core/Tool/ToolResult.php`.
- Ajoutés : `App/src/Core/Tool/ToolRegistry.php`, `App/src/Core/Tool/ToolExecutor.php`, `App/src/Core/Tool/ToolNotFound.php`.
- Ajouté : `App/tests/Unit/Core/Tool/ToolRegistryTest.php`.

## Comment vérifier

```bash
docker compose run --rm php composer qa
```

## Résultat

- `ToolInterface` expose `descriptor()` et `execute()` avec un contexte typé.
- `ToolDescriptor` porte nom, description et schema d'entrée minimal.
- `ToolExecutionContext` transporte le workspace root et `PermissionPolicy`.
- `ToolRegistry` enregistre les tools, refuse les doublons et liste les descriptors dans un ordre déterministe.
- `ToolExecutor` exécute un tool par nom et retourne `ToolResult::failure()` en cas d'exception.
- `docker compose run --rm php composer qa` : OK, 30 tests, 58 assertions.

## Risques restants

- Aucun tool concret n'est encore implémenté ; le prochain step branche file/search/git sur cette base.
- Les schemas d'entrée sont de simples tableaux ; une validation stricte arrivera avec les tools concrets.
- L'executor capture toutes les exceptions pour stabiliser la boucle agent, mais l'audit détaillé n'est pas encore implémenté.

## Step suivante

`STEP-05-hexa-ddd-refactor.md` : refactorer le socle vers des bounded contexts hexagonaux avant d'ajouter les tools concrets.
