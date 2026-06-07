# ROADMAP — Ce qui reste à faire

> État au **2026-06-06** — après **STEP-27** (streaming output).
> Le roadmap local initial est **bouclé** : architecture hexa/DDD, 2 bounded contexts
> (`Assistant`, `Tool`), 10 outils, permissions persistées, CLI + TUI, streaming live.
> Ce fichier liste les pistes **restantes**, toutes **optionnelles** et **hors scope initial**.

## Contrainte projet

⚠️ **Local-only.** On ne porte **aucun** outil réseau externe : pas de `webfetch`,
pas de `websearch`, pas de `repo_clone`. Toute piste ci-dessous respecte cette règle.

---

## Pistes restantes

### 1. Contexte `Workspace` (gros morceau)
Nouveau bounded context, prévu dès le départ dans `CLAUDE.md` mais jamais créé.
- Intégration **git** (status, diff, branches) comme port Domain + adapter Infra.
- Gestion **projet / racine de travail** (déjà esquissée par `WorkspacePath` côté Tool).
- **Snapshots / checkpoints** : sauvegarde + revert d'un état de session (équivalent
  du snapshot opencode).
- Réf opencode : `_opencode_ref/opencode-dev/packages/opencode/src/` (git, snapshot).

### 2. TUI pleinement asynchrone (Revolt / Amp)
Aujourd'hui l'appel LLM **bloque** la boucle TUI pendant la génération.
- HTTP non-bloquant (`amphp/http-client` est déjà une dépendance).
- Spinner réactif + possibilité d'**annuler** une génération en cours (Ctrl-C doux).
- Gros gain d'UX sur l'existant, sans nouvelle feature fonctionnelle.

### 3. Contexte `Agent` dédié (agents nommés / background)
Extraire la boucle agent de `Assistant` vers un contexte `Agent` propre.
- **Agents nommés** avec config par agent (modèle, outils autorisés, system prompt).
- Tâches en **arrière-plan** (au-delà du `task` éphémère actuel).
- Réf opencode : `_opencode_ref/opencode-dev/packages/opencode/src/agent/`.

### 4. LSP réel (phpactor)
Remplacer le backend diagnostics actuel (`php -l` + phpstan) par un **vrai serveur LSP**.
- Hover, go-to-definition, diagnostics riches via `phpactor`.
- Garde l'outil `lsp` en read-only ; on change juste le `DiagnosticsProvider`.

### 5. Consolidation / polish
Pas de nouvelle feature, durcissement de l'existant.
- Tests d'**intégration LLM** (Ollama réel ou double de plateforme).
- Outil `multiedit` (plusieurs remplacements en un appel) — réf opencode `tool/`.
- Gestion de la **troncature** des gros outputs d'outils.
- Compléter la doc `contexts/` et les ADR pour les steps récents (24–27).

---

## Suggestion d'ordre

1. **TUI async** (#2) — gros gain UX, dépendance déjà présente, périmètre maîtrisé.
2. **Workspace + git/snapshot** (#1) — la brique structurante qui manque vraiment.
3. **Agent dédié** (#3) puis **LSP réel** (#4) — enrichissements.
4. **Polish** (#5) — en continu.

> Quand on démarre une de ces pistes : créer `App/docs/steps/STEP-28-<slug>.md`
> et suivre le workflow habituel (voir `CLAUDE.md` §2).
