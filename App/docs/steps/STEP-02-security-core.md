# STEP-02 — Security core

## But

Implémenter le premier noyau sécurité local-only : normalisation de chemins, garde workspace, exclusions de secrets, redaction et politique réseau localhost-only.

## Décisions clés

- Garder le step sans nouvelle dépendance runtime : tout est en PHP stdlib.
- Séparer `WorkspaceGuard` de `PathNormalizer` pour isoler la normalisation lexicale des règles d'autorisation.
- Utiliser `IgnoreMatcher` pour centraliser les chemins secrets ou interdits avant les futurs tools fichier.
- Autoriser `host.docker.internal` dans `NetworkPolicy`, car SebCode tourne dans Docker et doit pouvoir joindre un Ollama local sur l'hôte.
- Bloquer les sorties workspace par normalisation lexicale et contrôler les symlinks via `realpath()` quand le chemin ou son parent existe.

## Fichiers touchés

- Ajoutés : `App/src/Core/Security/SecurityViolation.php`, `App/src/Core/Security/SecretRedactor.php`, `App/src/Core/Security/NetworkPolicy.php`.
- Ajoutés : `App/src/Core/Workspace/PathNormalizer.php`, `App/src/Core/Workspace/WorkspaceGuard.php`, `App/src/Core/Workspace/IgnoreMatcher.php`.
- Ajoutés : `App/tests/Security/WorkspaceGuardTest.php`, `App/tests/Security/SecretRedactorTest.php`, `App/tests/Security/NetworkPolicyTest.php`.
- Modifié : `App/composer.json` pour ajouter `test:security` et faire exécuter toutes les suites PHPUnit par `composer qa`.

## Comment vérifier

```bash
docker compose run --rm php composer qa
```

## Résultat

- `WorkspaceGuard` accepte les chemins relatifs dans le workspace et refuse `../`, home, chemins secrets et symlinks sortants.
- `IgnoreMatcher` centralise les motifs secrets de base : `.env`, clés privées, credentials, `.ssh`, `.aws`, `.gnupg`, etc.
- `SecretRedactor` masque les valeurs de clés sensibles et les blocs private key.
- `NetworkPolicy` autorise uniquement `localhost`, `127.0.0.1`, `::1` et `host.docker.internal` avec `http` ou `https`.
- `docker compose run --rm php composer validate --strict` : OK.
- `docker compose run --rm php composer qa` : OK, 17 tests, 29 assertions.

## Risques restants

- `WorkspaceGuard` fournit une autorisation générique ; les futurs tools devront encore distinguer lecture, écriture et exécution via `PermissionPolicy`.
- Les patterns `IgnoreMatcher` couvrent le socle secrets, mais devront être enrichis avec les retours des tools fichier et shell.
- `NetworkPolicy` ne résout pas encore les DNS vers IP avant autorisation ; le step shell/provider devra éviter toute résolution externe non nécessaire.

## Step suivante

`STEP-03-permission-policy.md` : implémenter `PermissionPolicy`, `PermissionRequest`, `PermissionDecision`, `PermissionReason` et les tests.
