# STEP-03 — Permission policy

## But

Créer une politique de permission centrale pour les futures actions sensibles : lecture fichier, écriture fichier, commande shell et accès réseau.

## Décisions clés

- Garder la politique en PHP stdlib, sans dépendance supplémentaire.
- Représenter une décision avec trois états pratiques : autorisé immédiatement, refusé, ou approbation requise.
- Réutiliser `WorkspaceGuard` pour les permissions fichier et `NetworkPolicy` pour les permissions réseau.
- Refuser explicitement les commandes réseau externes et commandes destructrices avant d'implémenter le shell sécurisé.

## Fichiers touchés

- Ajoutés : `App/src/Core/Permission/PermissionRequestType.php`, `App/src/Core/Permission/PermissionReason.php`.
- Ajoutés : `App/src/Core/Permission/PermissionRequest.php`, `App/src/Core/Permission/PermissionDecision.php`, `App/src/Core/Permission/PermissionPolicy.php`.
- Ajouté : `App/tests/Security/PermissionPolicyTest.php`.

## Comment vérifier

```bash
docker compose run --rm php composer qa
```

## Résultat

- `PermissionPolicy` autorise immédiatement les lectures de fichiers autorisées par `WorkspaceGuard`.
- Les lectures de secrets ou sorties workspace sont refusées avec `PermissionReason::WorkspaceViolation`.
- Les écritures dans le workspace sont valides côté chemin mais passent en approbation requise.
- Les commandes réseau/dangereuses comme `curl` sont refusées avant le futur shell sécurisé.
- Les commandes de QA comme `vendor/bin/phpunit` passent en approbation requise.
- Les URLs locales autorisées par `NetworkPolicy` sont acceptées ; les URLs externes sont refusées.
- `docker compose run --rm php composer qa` : OK, 24 tests, 46 assertions.

## Risques restants

- La décision `requiresApproval` n'a pas encore de prompter interactif ni de stockage d'audit ; ce sera ajouté avec les steps tools/shell.
- La classification shell reste volontairement minimale avant `SecureShellExecutor`.
- Les modes produit (`READ_ONLY`, `PATCH`, `EXECUTE`, etc.) ne sont pas encore branchés sur la policy.

## Step suivante

`STEP-04-tool-system.md` : implémenter `ToolInterface`, `ToolRegistry`, `ToolExecutor`, `ToolExecutionContext`, `ToolResult` et les tests.
