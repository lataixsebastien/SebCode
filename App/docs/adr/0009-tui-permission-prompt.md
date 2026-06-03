# ADR-0009 — Prompt de permission TUI par lecture clavier bloquante

**Date :** 2026-06-02
**Statut :** Accepté
**Step lié :** STEP-18
**Complète :** [ADR-0008](0008-interactive-prompter-and-shell.md)

## Contexte

L'ADR-0008 a livré le prompt de permission interactif **en CLI**. En TUI il restait à faire. Or le TUI
de SebCode tourne sur la boucle **Revolt** (`symfony/tui`), et la boucle agentique est invoquée dans un
`EventLoop::queue()` qui appelle le LLM **de façon bloquante** :

```php
// SymfonyAiOllamaAdapter::complete()
$deferred = $this->platform->invoke(...);
$result = $deferred->getResult();   // HTTP synchrone → bloque la boucle Revolt
```

Pendant tout un tour d'agent (LLM + exécution d'outils), la boucle Revolt est **gelée** : son watcher
`onReadable(STDIN)` ne tourne pas, donc le système de widgets ne peut **pas** recevoir de touches. La
voie « propre » (widget `SelectListWidget` + suspension de fibre) est donc **impossible** ici : la touche
qui résoudrait la suspension ne peut pas être lue tant que la boucle est bloquée → interblocage.

C'est le mur qui pousse opencode à un modèle **serveur async + UI séparée**. SebCode exécute la boucle
agentique en bloquant, dans le process TUI.

## Décision

**Lecture clavier bloquante directe**, pendant le tour d'agent. Puisque la boucle est de toute façon
gelée, lire STDIN en direct y est sûr (rien d'autre ne le consomme à cet instant) :

- `TuiPermissionConsole` (UI) implémente le port Domain `PermissionConsole`. `confirm()` :
  1. ajoute un widget de prompt au transcript et **force un rendu synchrone** (`requestRender(true)` +
     `processRender()`) — le terminal étant en raw mode (`stty raw -echo`) ;
  2. attend la touche via `stream_select` + `fread(STDIN)` (o/a/r, ou 1/2/3, Échap/Ctrl-C = reject) ;
  3. retire le prompt, journalise la décision, retourne le `PermissionChoice`.
- **Aucune** modification du gate / prompter / grants : `InteractivePermissionPrompter`,
  `PermissionGrants` et le registry sont réutilisés tels quels. On n'ajoute qu'une 2ᵉ implémentation du
  port `PermissionConsole` (après celle de la CLI).
- `isActive()` = console attachée **ET** `stream_isatty(STDIN)` : en non-TTY (pipe/test), inactive → le
  prompter retombe sur le défaut `deny`. `TuiCommand` attache la console avant `run()`, détache en
  `finally`.

## Conséquences

### Positives

- Réutilisation totale de la couche permission (un seul nouveau fichier de prod + le câblage).
- `always` persiste pour toute la session TUI — sémantique « always (this session) » naturellement
  correcte (la session = la durée du process TUI).
- Pas de refacto async lourde.

### Négatives / limites assumées

- I/O terminal **non testable unitairement** sans pseudo-TTY : seul le mapping de touches (`mapKeys`)
  est couvert ; le rendu + la lecture se vérifient **manuellement** dans un vrai terminal.
- Touches multi-octets (flèches, séquences ANSI) ignorées sauf Échap : on ne lit qu'une décision simple.
- Lecture STDIN « sous » le watcher Revolt : correct **uniquement** parce que la boucle est gelée
  pendant le tour. Si la boucle devenait async (LLM non bloquant), il faudrait revenir au modèle
  widget + suspension (ADR à réviser).

## Alternatives écartées

- **Widget `SelectListWidget` + suspension de fibre** — la voie propre, mais inopérante tant que la
  boucle est bloquée (la touche de résolution ne peut pas être lue). À reconsidérer si la boucle
  agentique devient non bloquante.
- **Rendre la boucle agentique async** (LLM/outils coopératifs) — gros refacto de
  `SendMessageHandler`/`LlmPort` et de l'invocation TUI ; non justifié à ce stade.
- **Pas de prompt en TUI** (politique par config) — rejeté : on veut la parité fonctionnelle CLI / TUI
  pour les outils mutants.
