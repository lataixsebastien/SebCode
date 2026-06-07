# STEP-29 — TUI « à l'identique » d'opencode

> Démarré le 2026-06-07, sur la branche `feat/sebcode-foundation`.
> Précédent : [STEP-28-console-chat.md](STEP-28-console-chat.md).

## But

Refondre `assistant:tui` pour reproduire l'interface du TUI opencode
(`_opencode_ref/opencode-dev/packages/opencode/src/cli/cmd/run/`) :

- **Layout split-footer** : scrollback immuable (transcript) en haut, footer
  mutable en bas (composer bordé + meta row + status bar).
- **Composer** : input bordé `╭─╮`, placeholder, historique ↑/↓, palette de
  slash commands `/`, Esc pour vider, double Ctrl+C pour quitter.
- **Status bar** : spinner animé pendant la génération, durée écoulée,
  usage tokens, hints contextuels (`esc interrupt · ctrl+c exit`).
- **Meta row** : `agent · modèle` sous le composer (cliquable chez opencode ;
  ici `/models` ouvre le picker).
- **Transcript** : user `> …`, assistant en **markdown rendu**
  (MarkdownWidget : titres, code highlighté, tables), tool calls/résultats
  formatés par outil (bash → commande + sortie ; write/edit → fichier +
  résumé ; read/glob/grep → ligne résumé), erreurs en rouge.
- **Dialogs** : permission (Allow once / Always / Reject naviguée aux
  flèches), picker de modèles (Ollama `/api/tags`), picker de sessions.
- **Splash** : bannière logo + infos session au démarrage (équiv. `splash.ts`).

## Décisions clés

- **symfony/tui v8.1@beta suffit** : MarkdownWidget, SelectListWidget,
  bordures/hidden via Style. Deux dépendances ajoutées pour le rendu
  markdown : `league/commonmark ^2.8` + `tempest/highlight ^2.26`
  (exigées par MarkdownWidget, locales, pas de réseau).
- **Input mono-ligne conservé** (`InputWidget`) : opencode a un éditeur
  multi-ligne (Shift+Enter), mais la détection Shift+Enter exige le protocole
  Kitty — non garanti. Enter = envoi, comme la valeur par défaut opencode.
- **Boucle agent toujours synchrone** (le TUI async reste la piste ROADMAP #2) :
  le spinner est avancé **manuellement** à chaque delta streamé
  (`LoaderWidget::tick()` + render forcé), même mécanique que STEP-27.
- **Interruption Esc** pendant la génération : lecture stdin non-bloquante
  (`stream_select`) dans `isInterrupted()` — sûre car la boucle Revolt est
  gelée pendant le tour (même argument que `TuiPermissionConsole`, ADR-0008).
- **Nouveau port `ModelCatalog`** (Domain Assistant) + adapter Infra
  `OllamaModelCatalog` (HTTP local `/api/tags`) pour le picker de modèles.
  Respecte la contrainte local-only (Ollama est local).
- **`/models` démarre une nouvelle session** avec le modèle choisi :
  `Session::model` est readonly (pas de changement de modèle en cours de
  session côté Domain). Alternative écartée : muter l'aggregate + l'entity
  Doctrine — pas justifié pour l'instant.
- **Pas de fonctionnalités réseau** : pas de /share, pas de webfetch (cf.
  contrainte projet).
- **Style PHP 8.4 `new X()->m()` évité dans src/** : le parser de deptrac
  (qossmic, abandonné) ne le supporte pas — arguments nommés du constructeur
  `Style` utilisés à la place.
- **Smoke tests de rendu via `VirtualTerminal`** : les widgets custom
  (`StatusBarWidget`, transcript complet markdown/outils/splash) sont rendus
  par le vrai pipeline en test — le Renderer fait respecter le contrat de
  largeur, donc une ligne trop longue fait échouer le test.

## Fichiers touchés

### Ajoutés
- `src/Assistant/Domain/Port/ModelCatalog.php` — port liste de modèles.
- `src/Assistant/Infrastructure/Llm/OllamaModelCatalog.php` — adapter `/api/tags`.
- `src/Assistant/UI/Tui/TuiTheme.php` — stylesheet opencode (palette, bordures
  arrondies, barre user, sous-éléments status bar).
- `src/Assistant/UI/Tui/Component/TranscriptView.php` — scrollback typé
  (user/markdown/outils/system/error/splash/help + hydratation session).
- `src/Assistant/UI/Tui/Component/ToolEntryFormatter.php` — formatage pur par
  outil (header `⚙ Tool  sujet`, résultat tronqué à 6 lignes).
- `src/Assistant/UI/Tui/Component/StatusBarWidget.php` — meta row
  (agent · modèle | session) + status row (spinner/écoulé/hints | tokens ↑↓).
- `src/Assistant/UI/Tui/Component/SlashCommands.php` — catalogue /help /new
  /sessions /models /clear /exit + alias (:q, quit…).
- `src/Assistant/UI/Tui/Component/PromptHistory.php` — ring d'historique
  (draft sauvegardé, doublons consécutifs fusionnés, cap 200).
- `tests/Unit/Assistant/UI/Tui/Component/` — PromptHistory, ToolEntryFormatter,
  SlashCommands, StatusBarWidget (rendu réel), TranscriptView (rendu réel).
- `tests/Unit/Assistant/Infrastructure/Llm/OllamaModelCatalogTest.php` —
  MockHttpClient (tri, entrées malformées, Ollama down → []).

### Modifiés
- `src/Assistant/UI/Tui/TuiCommand.php` — réécriture complète : layout
  split-footer, palette `/`, pickers, historique, double Ctrl+C, scroll
  PgUp/PgDn, switch de session/modèle.
- `src/Assistant/UI/Tui/TuiAgentOutputStream.php` — markdown live (entry
  MarkdownWidget mise à jour à chaque delta), tick spinner, interruption Esc
  (poll stdin non-bloquant), `beginTurn()`.
- `src/Assistant/UI/Tui/TuiPermissionConsole.php` — dialog footer 3 boutons
  (←/→/Tab + Enter, o/a/r + 1/2/3 directs, Esc reject) + `mapNavigation()`.
- `tests/Unit/Assistant/UI/Tui/TuiPermissionConsoleTest.php` — cas mapNavigation.
- `config/services.yaml` — câblage ModelCatalog (`OLLAMA_ENDPOINT`).
- `composer.json` / `composer.lock` — + league/commonmark, tempest/highlight.

## Comment vérifier

```bash
docker compose exec php composer qa          # cs + stan + tests
docker compose exec -it php bin/console assistant:tui   # terminal Linux/natif
```

⚠️ Rappel STEP-28 : le mode raw ne reçoit pas les frappes via
`docker compose exec` depuis PowerShell Windows ; tester depuis un vrai TTY
(Linux, WSL, ou `docker exec` dans Windows Terminal si TTY OK).
`assistant:chat` reste le fallback Windows.

## Journal

- [x] Inventaire des features opencode (run/footer/scrollback/dialogs).
- [x] Inventaire API symfony/tui (Markdown, SelectList, Loader, Border, Keybindings).
- [x] A — Layout + thème + splash.
- [x] B — Rendu transcript (markdown, tools).
- [x] C — Spinner/durée/tokens/interruption Esc.
- [x] D — Palette `/` + pickers modèles/sessions.
- [x] E — Dialog permission.
- [x] F — Historique + keybindings.
- [x] G — Tests + qa + doc — `composer qa` vert (326 tests, 700 assertions,
      deptrac 0 violation, phpstan max 0 erreur).

### Reste à valider manuellement (TTY réel)
Le rendu et les frappes ne se testent pas via docker-exec/PowerShell
(contrainte STEP-28). À vérifier depuis un vrai TTY (WSL/Linux) :
spinner pendant génération, interruption Esc, dialog permission,
palette + pickers, scroll PgUp/PgDn.

## Step suivante

À déterminer (ROADMAP : TUI async Revolt/Amp ou contexte Workspace).
