# STEP-18 — Prompt de permission interactif en TUI

**Statut :** ✅ terminé (`composer qa` vert ; prompt TUI à valider en vrai terminal)
**Branche :** `feat/sebcode-foundation`
**ADR lié :** [ADR-0009](../adr/0009-tui-permission-prompt.md) (complète [ADR-0008](../adr/0008-interactive-prompter-and-shell.md))

## But

Donner la **parité CLI / TUI** sur la permission : en TUI aussi, un outil mutant (`write`/`edit`/`bash`)
demande confirmation à l'humain (once / always / reject), au lieu du défaut `deny`. Loose end reporté
depuis STEP-16.

## Décision clé (voir ADR-0009)

La boucle agentique tourne dans un `EventLoop::queue()` et l'appel LLM est **bloquant** → la boucle
Revolt est **gelée** pendant tout le tour, donc le système de widgets ne peut pas recevoir de touches.
La voie « widget + suspension de fibre » est donc inopérante. **Choix utilisateur : lecture clavier
bloquante directe** — sûre précisément parce que la boucle est gelée (rien d'autre ne lit STDIN à cet
instant). On force un rendu synchrone pour afficher le prompt, puis on lit la touche.

Point fort : **aucune modif** du gate / prompter / grants / registry — on ajoute juste une 2ᵉ
implémentation du port `PermissionConsole` (après la CLI). Toute la logique once/always/reject + grants
de session est réutilisée telle quelle.

## Layout produit

### Nouveau — UI (`App/src/Assistant/UI/Tui/`)

```
TuiPermissionConsole.php   (implémente PermissionConsole ; rendu synchrone du prompt + stream_select/fread)
```

### Modifié

| Fichier | Changement |
|---|---|
| `TuiCommand.php` | injecte `PermissionConsoleRegistry` ; attache la `TuiPermissionConsole` avant `run()`, détache en `finally` ; ajoute la classe de style `.permission` |

> **Aucune** modif de config DI : le port `PermissionConsoleRegistry` est déjà aliasé (STEP-16) et
> autowiré dans `TuiCommand`. La console TUI est instanciée à la main dans la commande (elle a besoin du
> `Tui` et du `ContainerWidget` construits à l'exécution), pas via le conteneur.

### Nouveau — Tests

```
App/tests/Unit/Assistant/UI/Tui/TuiPermissionConsoleTest.php
  (mapping des touches : o/O/1→once, a/2→always, r/3/Échap/Ctrl-C→reject, inconnu/vide→null, 1ʳᵉ touche reconnue gagne)
```

Seul le mapping pur est testé unitairement ; le rendu + la lecture STDIN sont **TTY-bound** → vérif manuelle.

## Mécanique

1. `TuiCommand` attache une `TuiPermissionConsole(tui, transcript)` au registry, `activate()`, et détache
   en `finally`.
2. Dans le tour d'agent (boucle gelée), `InteractivePermissionPrompter` voit la console **active** et
   appelle `confirm()`.
3. `confirm()` : ajoute un widget de prompt au transcript → `requestRender(true)` + `processRender()`
   (rendu synchrone) → `stream_select` + `fread(STDIN)` jusqu'à une touche valide → retire le prompt →
   journalise la décision → retourne le `PermissionChoice` ; `always` persiste dans `PermissionGrants`
   (durée de la session TUI).
4. `isActive()` = attachée **ET** `stream_isatty(STDIN)` → en non-TTY, inactive → défaut `deny`.

## Comment vérifier

```bash
docker compose exec php composer qa
# attendu : cs 0 / phpstan max 0 / deptrac 0 violation / phpunit vert (220 tests, 453 assertions)

docker compose exec php bin/console lint:container   # le câblage TuiCommand compile

# Vérif manuelle (vrai terminal, TTY requis) :
docker compose exec php bin/console assistant:tui -m qwen2.5:7b
# puis demander : « lance la commande bash: echo coucou »
# → un encadré ⚠ Permission requested (bash) s'affiche ; taper o / a / r ;
#   'o' exécute une fois, 'a' n'redemande plus pour 'echo *' de la session, 'r' refuse.
```

> Le prompt TUI ne peut pas être piloté en headless (pipe → `stream_isatty` faux → console inactive →
> défaut `deny`). Il se valide donc dans un terminal interactif.

## Step suivante

Outils cœur + permission **complets sur CLI et TUI**. Pistes : outils opencode avancés
(`webfetch`/`websearch`, `task`/`todo`, `lsp`, `apply_patch`), ou rendre la boucle agentique **async**
(débloquerait le prompt TUI par widget et le streaming des réponses).
