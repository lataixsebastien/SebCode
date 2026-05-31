# STEP-13 — Rendu des tool calls / résultats dans l'UI (CLI + TUI)

## But

Rendre visibles les **étapes intermédiaires de l'agent loop** dans les deux interfaces : quand l'assistant appelle un outil, l'utilisateur voit l'appel (`🔧 nom(args)`) puis son résultat (`✓` / `⚠` + sortie tronquée), avant la réponse finale.

## Contexte de départ

À l'issue du STEP-12, l'agent loop tournait et persistait les messages intermédiaires (`ToolCall` / `ToolResult` via `MessagePayload`), mais l'UI ne les affichait pas : seul le message final de l'assistant était rendu. La boucle était donc une boîte noire côté utilisateur.

## Décisions clés

- **Rendu piloté par le payload**, pas par le rôle : on lit `Message::$payload` et on branche sur `MessagePayloadKind` (`ToolCall` → liste des appels ; `ToolResult` → marqueur + sortie). Un message sans payload garde le rendu texte classique.
- **CLI (`AskCommand`)** — `SendMessageResult::$intermediateMessages` est parcouru avant la section « Assistant ». Trois helpers privés : `renderIntermediate()`, `renderArguments()` (args en JSON compact), `oneLine()` (sortie aplatie + tronquée à 140 caractères pour rester lisible en terminal).
- **TUI (`TuiCommand`)** — `appendMessageWidget()` gère les deux kinds de payload en amont du `match` sur le rôle, en réutilisant `appendLine()` avec les classes de style existantes (`thinking` pour les tools, `error` si `isError`).
- **`AgentLoopExceeded` catchée dans la CLI** — message d'erreur clair + invitation à inspecter via `assistant:sessions <id>`.
- Marqueurs cohérents entre CLI et TUI : `🔧` (appel), `✓` (succès), `⚠` (erreur tool).

## Fichiers touchés

### Modifiés
- `App/src/Assistant/UI/Cli/AskCommand.php` — rendu des intermédiaires + catch `AgentLoopExceeded` (+ helpers `renderIntermediate` / `renderArguments` / `oneLine`).
- `App/src/Assistant/UI/Tui/TuiCommand.php` — branche `ToolCall` / `ToolResult` dans `appendMessageWidget()`.

## Comment vérifier

```bash
docker compose exec php composer qa
docker compose exec php bin/console assistant:ask \
  "List the PHP files under src/Assistant/Domain/Port using glob, then briefly describe what you found." \
  --model=qwen2.5:7b --no-interaction
```

Sortie attendue (extrait) :

```
🔧 glob({"pattern":"src/Assistant/Domain/Port/**/*.php","limit":100})
   ✓ glob → (7 matched, showing first 7) .../Clock.php .../IdGenerator.php ...
```

Validé : QA verte (php-cs-fixer 0, PHPStan/Deptrac 0 violation, 115 tests / 281 assertions OK) et smoke test affichant bien l'appel d'outil + son résultat avant la réponse finale.

> Note : `qwen2.5:3b` produit parfois un pattern glob invalide (`{*.php}`) → 0 résultat. C'est une limite du petit modèle, pas du rendu. `qwen2.5:7b` est fiable pour ce smoke.

## Step suivante

STEP-14 — (à définir) : outils supplémentaires (write/edit/grep) ou streaming de la réponse finale.
