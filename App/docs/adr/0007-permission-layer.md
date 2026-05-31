# ADR-0007 — Couche `Permission` dans le contexte `Tool`

**Date :** 2026-05-31
**Statut :** Accepté
**Step lié :** STEP-14 (création), STEP-15/16 (consommation par write/edit/shell)

## Contexte

À partir du STEP-15 on introduit des outils **mutants** (`write`, `edit`, puis `shell`). Jusqu'ici le
contexte `Tool` ne contenait que `glob` et `read`, read-only, confinés par `realpath()` au project root.
Un outil qui écrit sur le disque ou lance une commande a besoin d'un garde-fou d'**autorisation**, pas
seulement de confinement de chemin.

opencode (notre référence — c'est un fork) fait passer **tous** les outils dangereux par une couche
d'autorisation centrale avant d'agir : chaque outil appelle
`ctx.ask({ permission, patterns, metadata })`
(cf. `packages/opencode/src/tool/write.ts:54`, `edit.ts:98 & 141`, `shell.ts:266-287`). Le module
`permission/` évalue une **ruleset** wildcard :

- `evaluate(permission, pattern, ...rulesets)` garde la **dernière** règle qui matche (`findLast`)
  type ET pattern, et retourne `ask` par défaut si aucune ne matche
  (`packages/core/src/permission.ts:21`).
- Actions : `allow` / `deny` / `ask`.
- Le matching wildcard (`packages/core/src/util/wildcard.ts`) convertit `*` → `.*`, `?` → `.`,
  normalise les `\` en `/`, et optionalise un `" *"` final (`ls *` matche `ls`).
- Réponses utilisateur : `once` / `always` / `reject`.

## Décision

On réplique cette couche **dans le contexte `Tool`**, en respectant l'hexagone, **avant** d'écrire le
moindre outil mutant (décision produit : « Permission d'abord, fidèle »).

### Domain (pur PHP)

- `PermissionType` (enum : `edit`, `bash`, `external_directory`) — valeurs identiques à opencode.
- `PermissionAction` (enum : `allow`, `deny`, `ask`).
- `PermissionRequest` (VO : type + `list<string> $patterns` + `array $metadata`).
- `PermissionRule` (`typePattern`, `subjectPattern`, `action` — patterns wildcard).
- `PermissionRuleset` — `evaluate(type, subject): PermissionAction`, **last-match-wins**, défaut `Ask`.
- `WildcardMatcher` (service Domain) — port fidèle de `Wildcard.match` (regex, pur stdlib).
- `Port\PermissionGate` — `ensure(PermissionRequest): void` (jette `PermissionDenied`).
- `Port\PermissionPrompter` — `prompt(request, subject): PermissionAction` (terminal : allow|deny).
- `Exception\PermissionDenied` — **hard failure** (remonte, abort la boucle ; pas un soft `ToolResult`).

### Infrastructure

- `RulesetPermissionGate` — pour chaque pattern : `Allow` passe, `Deny` jette, `Ask` délègue au prompter.
- `ConfigDefaultPermissionPrompter` — prompter **non-interactif** (défaut `deny`, pilotable via
  `TOOL_PERMISSION_DEFAULT`). Seul prompter livré au STEP-14 : il n'y a pas encore d'UI dans la pile
  d'appel (les outils tournent profond sous `SendMessageHandler`).
- `RulesetFactory::fromConfig()` — construit la ruleset depuis le paramètre `tool.permission.rules`.

### Injection

Le `PermissionGate` sera injecté **par constructeur** dans les outils mutants (STEP-15/16), **pas** via
`ToolExecutionContext` : ce dernier reste un VO de données pur, et l'interface `Tool` ainsi que les
outils read-only existants ne changent pas. La couche est **dormante** au STEP-14 (aucun appelant).

## Conséquences

### Positives

- Frontière hexa nette : tout le Domain Permission est pur PHP (prouvé par Deptrac : `Domain: ~`).
- Extensible : un prompter interactif CLI/TUI implémentera simplement `PermissionPrompter` (STEP-15/16).
- Fidèle à opencode : mêmes types, même sémantique d'évaluation, même grammaire wildcard.

### Négatives

- Couche introduite avant son premier consommateur (dette de « code non encore utilisé »), assumée car
  elle isole une préoccupation transverse aux trois outils mutants à venir.
- La réponse `always` (persistance d'une règle approuvée en session, façon `scan.always` opencode) n'est
  pas encore implémentée — stretch goal une fois le prompter interactif en place.

## Alternatives écartées

- **Garde-fous filesystem en dur dans chaque outil** (sans couche commune) — rejetée : dupliquerait la
  logique d'autorisation dans `write`/`edit`/`shell` et divergerait de la structure opencode.
- **Permission portée par `ToolExecutionContext`** — rejetée : mélange données de sandbox et service de
  décision, et forcerait à changer la signature passée à tous les outils (y compris read-only).
- **Couche dans un nouveau contexte `Permission/`** — rejetée (pour l'instant) : l'autorisation est
  intrinsèque à l'exécution d'outils ; un contexte séparé serait prématuré (YAGNI).
