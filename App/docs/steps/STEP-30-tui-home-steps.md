# STEP-30 — TUI « wahou » : écran d'accueil + panneau Steps

> Démarré le 2026-06-07, sur la branche `feat/sebcode-foundation`.
> Précédent : [STEP-29-tui-opencode.md](STEP-29-tui-opencode.md).

## But

Deux gros manques d'UX par rapport à opencode, demandés explicitement :

1. **À l'ouverture** : pouvoir **reprendre une session existante** (DB) et
   **choisir le modèle** sans copier-coller un `ses_…` — écran d'accueil
   (menu Continue / New chat / Pick a session / Pick a model) + flag
   `--continue`.
2. **Pendant le travail de l'agent** : voir **les étapes effectuées et à
   faire** (la todo-list que l'agent maintient via l'outil `todowrite`),
   affichées en continu **à droite**, mises à jour en live.

## Décisions clés

- **Panneau Steps = boîte alignée à droite dans le footer** (au-dessus du
  composer), pas une vraie colonne : symfony/tui rend un flux de lignes
  scrollées par le bas — une colonne dans le flux serait scrollée hors écran
  avec le transcript. Le footer, lui, est toujours visible (sticky), comme
  le footer mutable d'opencode.
- **Source des étapes = `TodoStore`** (port Tool, déjà persisté par session
  via Doctrine, STEP-23). L'UI Assistant importe ce port comme elle importe
  déjà `PermissionConsoleRegistry` (même couche deptrac).
- **Rafraîchissement live** : le sink (`TuiAgentOutputStream`) détecte le
  résultat de l'outil `todowrite` et recharge le store ; recharge aussi au
  changement/reprise de session.
- **Session paresseuse** : sans `-s`/`--continue`, plus de session créée au
  boot — le menu d'accueil décide. Taper un prompt directement depuis
  l'accueil crée une session avec le modèle par défaut.
- **`--continue` / `-c`** : reprend la session la plus récemment mise à jour
  (`SessionRepository::all()` + tri `updatedAt`, déjà utilisés par /sessions).

## Fichiers touchés

### Ajoutés
- `src/Assistant/UI/Tui/Component/StepsPanelWidget.php` — panneau todos.
- `tests/Unit/Assistant/UI/Tui/Component/StepsPanelWidgetTest.php`.
- `docs/steps/STEP-30-tui-home-steps.md` (ce fichier).

### Modifiés
- `src/Assistant/UI/Tui/TuiCommand.php` — home screen, session nullable,
  `--continue`, câblage TodoStore + StepsPanel.
- `src/Assistant/UI/Tui/TuiAgentOutputStream.php` — hook todowrite.
- `src/Assistant/UI/Tui/Component/TranscriptView.php` — splash bicolore + home.
- `src/Assistant/UI/Tui/TuiTheme.php` — styles StepsPanel + home.

## Comment vérifier

```bash
docker compose exec php composer qa
docker compose exec -it php bin/console assistant:tui              # → home screen
docker compose exec -it php bin/console assistant:tui --continue   # → dernière session
```

## Journal

- [x] A — StepsPanelWidget + refresh live todowrite (overflow intelligent :
      les items ouverts restent visibles, compteur x/y, `… +N more`).
- [x] B — Home screen (Continue last / New chat / Pick a session / Pick a
      model / Quit) + session paresseuse (taper directement crée une session)
      + `--continue` / `-c`.
- [x] C — Polish : logo dégradé 3 tons, dates `Y-m-d H:i` dans les pickers
      session + home, garde « No saved session yet ».
- [x] D — `composer qa` vert (332 tests, 719 assertions), lint:container OK.

### Reste à valider manuellement (TTY réel, WSL/Linux)
Home menu au boot, `--continue`, création paresseuse en tapant direct,
panneau Steps qui se met à jour pendant un tour avec `todowrite`.

## Step suivante

À déterminer.
