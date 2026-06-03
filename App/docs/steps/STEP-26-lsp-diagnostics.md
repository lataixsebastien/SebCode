# STEP-26 — Outil `lsp` (diagnostics)

**Statut :** ✅ terminé (`composer qa` vert)
**Branche :** `feat/sebcode-foundation`

## But

Donner à l'agent de quoi **vérifier les erreurs d'un fichier** (syntaxe + analyse statique) avant de
conclure une tâche — la capacité « lsp » d'opencode. **Local**, read-only.

## Décision d'implémentation (assumée, signalée à l'utilisateur)

Plutôt qu'un **client LSP-protocole vers phpactor** (fragile + rebuild de l'image Docker requis), backend
**PHP-natif robuste** : `php -l` (erreurs de syntaxe) + **phpstan** (analyse statique, déjà installé),
exécutés via le `CommandRunner` existant. Avantages : pas de modif Docker, fiable, **unit-testable**. Le
port `DiagnosticsProvider` permet de brancher un vrai backend language-server plus tard **sans toucher
l'outil**.

| Décision | Choix | Raison |
|---|---|---|
| Backend | `php -l` + `phpstan` via `CommandRunner` | Robuste, local, zéro dépendance/Docker en plus |
| Abstraction | Port `DiagnosticsProvider` | Le `LspTool` ignore le backend ; swap futur possible |
| Court-circuit | Si `php -l` échoue (fichier non parsable) → on n'appelle pas phpstan | phpstan ne peut rien analyser sur un fichier cassé |
| Permission | Aucune (read-only, confiné realpath comme read/grep) | Pas d'effet de bord |
| phpstan | `-c phpstan.dist.neon --error-format=json --memory-limit=512M` sur le fichier | Réutilise la config projet (level max) |

## Layout produit

### Nouveau

```
src/Tool/Domain/Model/ValueObject/DiagnosticSeverity.php   (enum error | warning)
src/Tool/Domain/Model/Diagnostic.php                       (severity, line, message, source)
src/Tool/Domain/Port/DiagnosticsProvider.php               (diagnostics(absolutePath): list<Diagnostic>)
src/Tool/Infrastructure/Diagnostics/PhpDiagnosticsProvider.php (php -l + phpstan, parse)
src/Tool/Infrastructure/Tool/LspTool.php                   (id "lsp" ; confiné ; rend la liste)
tests/Support/Tool/Doubles/FakeDiagnosticsProvider.php
tests/Unit/Tool/Infrastructure/Tool/LspToolTest.php
tests/Unit/Tool/Infrastructure/Diagnostics/PhpDiagnosticsProviderTest.php
```

### Modifié

| Fichier | Changement |
|---|---|
| `config/services.yaml` | `lsp` au service-locator ; binding `DiagnosticsProvider` → `PhpDiagnosticsProvider` (`$projectRoot`) |

## Sortie

```
Found 2 problem(s) in src/Foo.php:
  [error] line 12: Undefined variable $x [phpstan]
  [error] line 0: PHP Parse error: ... [php]
```
ou `No problems found in src/Foo.php.`

## Comment vérifier

```bash
docker compose exec php composer qa     # cs / phpstan max / deptrac / phpunit Unit (vert)

# Smoke LLM (créer un fichier avec une faute puis lui demander de le vérifier) :
docker compose exec php bin/console assistant:ask -m qwen2.5:7b \
  "Crée var/tmp/bad.php avec '<?php $x =;' puis utilise lsp pour vérifier var/tmp/bad.php."
```

## Step suivante

STEP-27 — boucle agentique **async** (la limite n°1 ; validation TUI interactive requise). Backend
phpactor-LSP réel = évolution possible (modif Dockerfile) si souhaité.
