# STEP-07 — UI TUI : `assistant:tui` (symfony/tui)

**Statut :** ✅ terminé (code en place, instanciation DI OK, `--help` OK) — **validation interactive à faire dans un vrai terminal** (non TTY-testable depuis `docker compose exec -T`)
**Branche :** `feat/sebcode-foundation`

## But

Donner à SebCode son interface terminal interactive style opencode :

- Chat en cours dans le terminal — pas de scroll en arrière entre commandes.
- Multi-tour fluide (taper, Entrée, voir la réponse, retaper, etc.).
- Reprise d'une session existante (`--session=ses_…`).
- Quit propre (taper `:q` / `exit` / Ctrl+C).

Le tout en respectant l'archi : zéro logique métier dans le TUI — il consomme les `Application/*Handler` exactement comme la CLI le fait.

## Décisions clés

| Décision | Choix | Raison |
|---|---|---|
| Lib TUI | `symfony/tui ^8.1@beta` (déjà installé) | Aligné stack Symfony, déjà en composer.json sur main. API experimental mais riche (`Tui`, `ContainerWidget`, `InputWidget`, `TextWidget`, `Style`, `StyleSheet`, focus manager, event dispatcher). |
| Layout | Vertical : header / transcript / input | Le plus simple qui marche. Une seule session à la fois ; le **sidebar sessions** (à la opencode) est reporté — on a déjà `assistant:sessions` en CLI pour ça. |
| Wiring | Tout autowired sauf `$defaultModel` (env `LLM_MODEL`) | Cohérent avec `AskCommand`. |
| Threading LLM | Synchrone, déféré au **tick suivant** via `Revolt\EventLoop::queue()` | `Tui::run()` tourne sur Revolt — `queue()` permet d'afficher "(thinking…)" AVANT le call LLM bloquant. Sans ça l'utilisateur ne verrait aucun feedback pendant l'appel. |
| Streaming | **Reporté** | Le LLM est appelé via `LlmPort::complete()` qui retourne en bloc. Pour streamer il faut un nouveau Port `LlmStreamPort` qui yield des deltas + un adapter Ollama qui consomme le SSE — non trivial et hors-scope du foundation. À ajouter en STEP ultérieur. |
| Quit | Commandes tapées (`:q`, `:quit`, `exit`, `quit`) + le default de la lib (Ctrl+C) | Pas de keybinding custom — la lib s'en occupe pour le SIGINT. |
| Tests | **Aucun** (TUI pur visuel) | Pas faisable raisonnablement sans harness TTY simulé. La logique métier est déjà couverte par les tests Application/Domain. |

## Layout produit

```
App/src/Assistant/UI/Tui/
└── TuiCommand.php          (Symfony Console command qui orchestre le Tui::run())
```

## Pipeline d'un tour (détaillé)

```
[User tape "Hello" + Entrée]
        │
        ▼
SubmitEvent reçu sur InputWidget
        │
        ├─ input.getValue() trim → ""? → return (rien à faire)
        ├─ ∈ {":q",":quit","exit","quit"}? → tui.stop()
        ├─ input.setValue("")  (vide le champ)
        │
        ├─ transcript.add(TextWidget "▶ You\nHello")
        ├─ transcript.add(TextWidget "◀ Assistant — (thinking…)")
        ├─ tui.requestRender()      ← l'utilisateur voit "thinking"
        │
        └─ EventLoop::queue(function () {       // déféré au tick suivant
              try {
                  result = sendMessage(SendMessageCommand(sessionId, "Hello"))
                  transcript.remove(thinking_widget)
                  transcript.add(TextWidget "◀ Assistant\n<reply>")
              } catch (LlmUnavailable e) {
                  transcript.remove(thinking_widget)
                  transcript.add(TextWidget "⚠ Error\nLLM unavailable: ...")
              }
              tui.requestRender()
           })
```

## Comment vérifier

### Check non-interactif (boot + DI + --help)

```bash
docker compose exec php bin/console lint:container
# → [OK]

docker compose exec php bin/console assistant:tui --help
# → affiche l'usage + options --session/--model/--title
```

### Check interactif (à toi de tester dans ton terminal)

⚠️ Nécessite un **vrai TTY**. Depuis Windows, faire :

```bash
docker compose exec php bin/console assistant:tui
```

Sans `-T`. Si tu fais ça via Cursor / Claude Code, le rendu peut être bizarre — préférer un `cmd.exe` / Terminal Windows / WSL natif.

**Tour attendu :**

1. La commande dit "Started new session ses_…" et bascule en mode TUI.
2. Tu vois : header bleu en haut, zone vide au milieu, prompt `›` en bas.
3. Tape "Bonjour" + Entrée.
4. `▶ You / Bonjour` apparaît, puis `◀ Assistant — (thinking…)` en jaune.
5. ~quelques secondes plus tard : le "thinking" est remplacé par la réponse.
6. Re-tape "Continue", la session se poursuit.
7. Tape `:q` ou Ctrl+C pour quitter — terminal restauré proprement.

### Reprendre une session

```bash
docker compose exec php bin/console assistant:tui --session=ses_1CbYnCB6PGiNFRa5Kry2Zc
# La transcript se charge avec tout l'historique avant d'attendre la prochaine saisie.
```

## Limites connues (cf. doc contexte §4.2)

- Pas de streaming token-par-token.
- Pas de sidebar de sessions.
- Pas de scroll explicite si l'historique dépasse la fenêtre.
- Pas de rendu markdown des réponses (le `MarkdownWidget` existe dans `symfony/tui` mais nécessite plus de travail).

## Step suivante

**STEP-08** — QA tooling : `phpstan.neon.dist` (level max sur `src/`), `.php-cs-fixer.dist.php` (PSR-12 + Symfony), `deptrac.yaml` qui enforce les règles hexa en CI (Domain n'importe rien, Application n'importe pas Symfony, etc.). Compose script `composer qa`.

Plus tard :

- Streaming Ollama (SSE) + `LlmStreamPort` + adapter qui consomme le stream + update incrémental du `TextWidget` côté TUI.
- Sidebar de sessions (un `SelectListWidget` à gauche pour switcher).
- Rendu markdown via `MarkdownWidget`.
- Tools (`Tool/` context) : read, write, edit, shell, glob, grep — c'est le gros morceau d'opencode.
