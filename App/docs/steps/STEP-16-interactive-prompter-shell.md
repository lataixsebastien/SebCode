# STEP-16 — Prompter interactif (CLI) + outil `shell`

**Statut :** ✅ terminé (`composer qa` vert)
**Branche :** `feat/sebcode-foundation`
**ADR lié :** [ADR-0008](../adr/0008-interactive-prompter-and-shell.md) (complète [ADR-0007](../adr/0007-permission-layer.md))

## But

Rendre la couche permission **vivante** côté CLI et livrer le premier outil qui en a réellement besoin :
`shell` (commande arbitraire → on demande confirmation à l'humain). Concrètement :

1. Un **prompter interactif CLI** : quand un outil mutant tombe sur `Ask`, le terminal affiche la
   demande et propose `once` / `always` / `reject` (fidèle à opencode).
2. La réponse **`always`** persiste une règle en mémoire pour la session (dette laissée par STEP-14).
3. L'outil **`bash`** : exécution shell confinée au workspace, gated `bash`, timeout, troncature.

Périmètre décidé avec l'utilisateur : **CLI maintenant, TUI au STEP-17** (le prompt TUI = widget modal +
lecture clavier dans la boucle Revolt, c'est un mini-projet à part). Extraction de pattern **simplifiée**
(commande complète + premier token `*`). Réponses **once / always / reject**.

## Décisions clés

| Décision | Choix | Raison |
|---|---|---|
| I/O console → prompter | Via **ports Domain** (`PermissionConsole`, `PermissionConsoleRegistry`) | Deptrac interdit UI → Infrastructure ; l'UI n'attache qu'un port Domain |
| Adaptateur console CLI | `ConsolePermissionConsole` dans `Assistant/UI/Cli`, implémente le port `Tool/Domain` | Même couture cross-context Domain-only que le bridge `AssistantToolGatewayAdapter` |
| Prompt bloquant (pas async) | Oui | La pile `AskCommand → … → PermissionGate` est synchrone ; pas besoin du deferred+event-bus d'opencode |
| `always` de session | `PermissionGrants` (port) + `InMemoryPermissionGrants` ; le gate consulte les grants avant la ruleset | Parité avec le tableau `approved` d'opencode |
| Headless / `--no-interaction` | Console inactive → prompter délègue au défaut (`deny`) | Sûr et déterministe hors terminal interactif |
| Exécution process | Isolée derrière `CommandRunner` + `CommandResult` | `ShellTool` testable au fake ; `proc_open` dans un seul adaptateur |
| Pattern `always` du shell | Premier token + ` *` (ex. `git *`) | Port simplifié de tree-sitter + arité d'opencode (décision produit) |
| Exit non-zéro / timeout | **Soft failure** (sortie + code rendus au LLM) ; permission refusée = **hard failure** | Le LLM peut réagir ; un refus abort la boucle |

## Layout produit

### Nouveau — Domain (`App/src/Tool/Domain/`)

```
Model/ValueObject/PermissionChoice.php   (enum once | always | reject)
Model/CommandResult.php                  (stdout, stderr, exitCode?, timedOut)
Port/PermissionConsole.php               (isActive + confirm → PermissionChoice)
Port/PermissionConsoleRegistry.php       (current / attach / detach)
Port/PermissionGrants.php                (grant / isGranted — mémoire "always")
Port/CommandRunner.php                   (run(command, workdir, timeoutMs): CommandResult)
```
+ modif `Model/ValueObject/PermissionRequest.php` : ajout `list<string> $always` (défaut = `$patterns`).

### Nouveau — Infrastructure (`App/src/Tool/Infrastructure/`)

```
Permission/InteractivePermissionPrompter.php   (console active → once/always/reject ; sinon défaut)
Permission/InMemoryPermissionGrants.php         (grants de session, wildcard via WildcardMatcher)
Permission/MutablePermissionConsoleRegistry.php (holder singleton ; reset = NullPermissionConsole)
Permission/NullPermissionConsole.php            (jamais active)
Process/ProcOpenCommandRunner.php               (proc_open, stdout/stderr, timeout SIGTERM→SIGKILL)
Tool/ShellTool.php                              (id "bash" ; gate bash ; troncature ; exit/timeout)
```
+ modif `Permission/RulesetPermissionGate.php` : consulte `PermissionGrants` avant la ruleset.

### Nouveau — UI (`App/src/Assistant/UI/Cli/`)

```
ConsolePermissionConsole.php   (adaptateur SymfonyStyle::choice du port PermissionConsole)
```
+ modif `AskCommand.php` : injecte `PermissionConsoleRegistry`, attache la console avant la boucle et la
détache en `finally`. `isActive()` = `$input->isInteractive()`.

### Nouveau — Tests (`App/tests/`)

```
Unit/Tool/Infrastructure/Permission/InMemoryPermissionGrantsTest.php       (wildcard, type-scope, idempotence)
Unit/Tool/Infrastructure/Permission/InteractivePermissionPrompterTest.php  (fallback / once / always persiste / reject)
Unit/Tool/Infrastructure/Tool/ShellToolTest.php                            (exit, stderr, timeout, gate bash + always, deny, timeout-arg, troncature)
Unit/Tool/Infrastructure/Process/ProcOpenCommandRunnerTest.php             (process réel, Linux-only : stdout/exit/stderr/workdir/timeout)
Support/Tool/Doubles/FakeCommandRunner.php                                 (résultat scripté)
Support/Tool/Doubles/FakePermissionConsole.php                            (active + choix scriptés)
```
+ modif `RulesetPermissionGateTest.php` : 3e arg `PermissionGrants` + test « grant court-circuite le prompt ».

### Modifié — Config

| Fichier | Changement |
|---|---|
| `config/services.yaml` | `bash` ajouté au service-locator ; bindings `PermissionConsole`/`Registry`/`Grants`/`CommandRunner` ; `PermissionPrompter` → `InteractivePermissionPrompter` (fallback = `ConfigDefaultPermissionPrompter`) |

> `bash` n'a **aucune règle** dans `tool.permission.rules` → `Ask` → prompt (CLI) ou `deny` (headless).
> Pas de modif `.env` : `TOOL_PERMISSION_DEFAULT=deny` reste le filet de sécurité.

## Fidélité opencode

| opencode | SebCode |
|---|---|
| `tool/shell.ts` (command, timeout, workdir) | `ShellTool` (command, timeout) |
| `ctx.ask({ permission:'bash', patterns, always })` | `PermissionRequest(Bash, [command], …, always)` |
| réponses `once` / `always` / `reject` | `PermissionChoice` |
| `approved.push(...)` sur `always` | `PermissionGrants::grant()` |
| `BashArity.prefix(tokens)+" *"` | premier token + ` *` (simplifié) |
| kill gracieux puis force | `proc_terminate` SIGTERM → SIGKILL |

## Comment vérifier

```bash
docker compose exec php composer qa
# attendu : cs 0 / phpstan max 0 / deptrac 0 violation / phpunit vert (194 tests, 413 assertions)

# Câblage DI :
docker compose exec php bin/console debug:container "App\Tool\Domain\Port\PermissionPrompter"
# → App\Tool\Infrastructure\Permission\InteractivePermissionPrompter

# Smoke LLM réel — prompt interactif + exécution shell (réponse pipée) :
printf 'once\n' | docker compose exec -T php bin/console assistant:ask -m qwen2.5:7b \
  "Utilise l'outil bash pour exécuter exactement la commande: echo bonjour-depuis-bash"
# attendu : "⚠ Permission requested (bash)" + menu once/always/reject,
#           puis 🔧 bash(...) et ✓ bash → bonjour-depuis-bash [exit code: 0]
```

Smoke validé le 2026-06-02 : le prompt s'est affiché (commande + description), la réponse `once` a
autorisé l'exécution, `bash` a tourné (exit 0), l'assistant a répondu. Le défaut `deny` protège les runs
non-interactifs.

## Step suivante

**STEP-17 — Prompter TUI + scoping session des grants** : widget modal de permission dans la boucle
Revolt (lecture clavier non bloquante), adaptateur `PermissionConsole` côté TUI, et remise à zéro des
`PermissionGrants` par session. Puis **`grep`** (recherche de contenu — dernier outil cœur encore absent).
