# ADR-0008 — Prompter interactif (CLI) + outil `shell`

**Date :** 2026-06-02
**Statut :** Accepté
**Step lié :** STEP-16 (CLI) ; le prompter TUI est différé en STEP-17
**Complète :** [ADR-0007](0007-permission-layer.md)

## Contexte

L'ADR-0007 a posé la couche `Permission` avec un seul prompter **non-interactif**
(`ConfigDefaultPermissionPrompter`, défaut `deny`). Au STEP-16 on introduit l'outil **`shell`**
(permission `bash`), qui — contrairement à `edit` (autorisé en masse par une règle) — doit **demander
confirmation à l'humain** avant d'exécuter une commande arbitraire. Il faut donc un prompter qui lit la
console, et la réponse `always` de session laissée en stretch goal par l'ADR-0007.

Contrainte structurelle (vérifiée par Deptrac) : **l'UI ne peut PAS dépendre de l'Infrastructure**
(ruleset UI = `Domain`, `Application`, `Symfony`, `AiPlatform`). Or l'I/O console (`SymfonyStyle`) naît
dans `Assistant/UI/Cli/AskCommand` et doit atteindre un prompter qui vit dans `Tool/Infrastructure`. La
pile d'appel `AskCommand → SendMessageHandler → … → PermissionGate::ensure()` est **synchrone**, donc un
prompt **bloquant** est possible (pas besoin du deferred + event-bus asynchrone d'opencode).

## Décision

### Câblage de l'I/O via des **ports Domain** (jamais l'UI → Infra)

- `Port\PermissionConsole` — `isActive(): bool` + `confirm(request, subject): PermissionChoice`.
  Symfony-free : la CLI fournit un adaptateur Symfony, une future TUI un adaptateur widget, le headless
  laisse la console inactive.
- `Port\PermissionConsoleRegistry` — `current()` / `attach()` / `detach()`. L'UI **attache** sa console
  pour la durée d'un run et la détache en `finally`. L'UI ne voit que ce port Domain.
- `Port\PermissionGrants` — mémoire des « always » de session (`grant` / `isGranted`), équivalent du
  tableau `approved` d'opencode grossi par les réponses `always`.
- `Model\ValueObject\PermissionChoice` (enum `once`/`always`/`reject`) — fidèle aux `Reply` d'opencode.

L'adaptateur CLI `ConsolePermissionConsole` **vit dans `Assistant/UI/Cli`** et implémente le port
`Tool\Domain\Port\PermissionConsole` : c'est la même couture cross-context Domain-only que le bridge
`AssistantToolGatewayAdapter` déjà accepté. Deptrac valide (UI → Domain + Symfony).

### Résolution des réponses

- `InteractivePermissionPrompter` (Infra) : console inactive → délègue au `ConfigDefaultPermissionPrompter`
  (défaut `deny`, sûr en headless/test/`--no-interaction`) ; sinon mappe `once`→`Allow`,
  `always`→persiste les patterns `request.always` dans `PermissionGrants` puis `Allow`, `reject`→`Deny`.
- `RulesetPermissionGate` consulte d'abord `PermissionGrants` (un `always` précédent court-circuite le
  prompt), puis la ruleset statique — parité avec l'évaluation opencode contre `approved`.
- `PermissionRequest` gagne `list<string> $always` (défaut = `$patterns`).

### Outil `shell`

- `ShellTool` (id `bash`) : params `command` (requis), `description`, `timeout` (ms, défaut 120 000,
  max 600 000). Subject de permission = la commande complète ; pattern `always` = **premier token + ` *`**
  (ex. `git *`) — port **simplifié** de l'extraction tree-sitter + dico d'arité d'opencode.
- `Port\CommandRunner` + `Model\CommandResult` : l'exécution process est isolée derrière un port, donc
  `ShellTool` reste testable au fake et la plomberie `proc_open` vit dans un seul adaptateur
  (`ProcOpenCommandRunner` : stdout/stderr séparés, timeout SIGTERM→SIGKILL, `/bin/sh -c`).
- Permission refusée = **hard failure** (abort) ; exit non-zéro ou timeout = **soft failure** (sortie +
  code rendus au LLM).

## Conséquences

### Positives

- L'UI reste hors Infra (Deptrac vert) ; le prompter ne connaît pas Symfony.
- `shell` unit-testable sans spawner de process ; `proc_open` couvert par un test réel (Linux-only).
- Réponse `always` de session livrée (dette ADR-0007 soldée).

### Négatives / limites assumées

- Le registry et les grants sont des **singletons process** : OK pour un one-shot CLI, mais en TUI (1
  process, N messages) les grants vivront toute la session — scoping par session à revoir au STEP-17.
- Extraction de pattern **simplifiée** : `sudo git push` retient `sudo *`, pas `git push *`. Suffisant
  pour un premier jet ; le dico d'arité reste un raffinement possible.
- Prompt **TUI** non livré : en TUI, `bash` → `Ask` → console inactive → défaut `deny` jusqu'au STEP-17.

## Alternatives écartées

- **Passer l'I/O via `ToolExecutionContext`** — rejeté : remélange données de sandbox et canal d'UI, et
  forcerait à reparler Symfony jusque dans le VO de contexte.
- **Deferred + event-bus asynchrone (comme opencode)** — rejeté (pour l'instant) : inutile tant que la
  pile est synchrone ; complexité non justifiée.
- **`ShellTool` appelant `proc_open` en direct** — rejeté : non testable unitairement et mélange logique
  métier (permission, troncature) et plomberie OS.
