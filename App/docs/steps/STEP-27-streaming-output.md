# STEP-27 — Sortie en streaming (réponse + tool calls live)

**Statut :** ✅ terminé (`composer qa` vert ; rendu live TUI à valider en TTY)
**Branche :** `feat/sebcode-foundation`

## But

Attaquer la « limite n°1 » (boucle bloquante) par son bénéfice le plus concret et atteignable : **la
réponse de l'assistant s'affiche au fil de l'eau** (token par token), et les tool calls/résultats
apparaissent **en direct** au lieu d'être rendus après coup. Local. CLI vérifiable, TUI à valider à la
main (caveat accepté).

## Périmètre (assumé)

Streaming **événementiel de la sortie**, pas une boucle event-loop entièrement asynchrone. La consommation
HTTP reste synchrone (`EventSourceHttpClient`) ; en TUI on force donc un **rendu synchrone** après chaque
delta (même mécanisme que le prompt de permission) pour que ça s'affiche pendant que la boucle est
bloquée. Une vraie réactivité TUI (HTTP non bloquant intégré à Revolt) reste un chantier ultérieur.

## Décisions clés

| Décision | Choix | Raison |
|---|---|---|
| Streaming LLM | `LlmPort::completeStreaming(..., callable $onText)` ; adapter consomme `$deferred->asStream()` (TextDelta / ToolCallComplete / TokenUsage) | `symfony/ai` + Ollama supportent le streaming nativement |
| Sink de sortie | Port `AgentOutputStream` (assistantText / toolCall / toolResult) + `AgentOutputStreamRegistry` (holder mutable) | La boucle (Application) reste UI-agnostique ; même pattern que le registry de console permission |
| Additif | Sans sink attaché → `NullAgentOutputStream` (no-op) → comportement **identique** à avant | Les tests existants du handler restent intacts |
| CLI | `CliAgentOutputStream` : texte brut live + lignes 🔧/✓ ; le rendu après-coup est supprimé | Affichage progressif réel |
| TUI | `TuiAgentOutputStream` : texte accumulé dans un widget qui grandit (`setText`) + **rendu synchrone forcé** | Visible malgré la boucle bloquée |

## Layout produit

### Nouveau

```
src/Assistant/Domain/Port/AgentOutputStream.php
src/Assistant/Domain/Port/AgentOutputStreamRegistry.php
src/Assistant/Infrastructure/Stream/NullAgentOutputStream.php
src/Assistant/Infrastructure/Stream/MutableAgentOutputStreamRegistry.php
src/Assistant/UI/Cli/CliAgentOutputStream.php
src/Assistant/UI/Tui/TuiAgentOutputStream.php
tests/Support/Assistant/Doubles/FakeAgentOutputStream.php
```

### Modifié

| Fichier | Changement |
|---|---|
| `Domain/Port/LlmPort.php` | + `completeStreaming(model, conversation, tools, callable $onText): LlmReply` |
| `Infrastructure/Llm/SymfonyAiOllamaAdapter.php` | implémente `completeStreaming` (stream Ollama : text/tool/tokens) ; helper `toolOptions()` |
| `Application/Command/SendMessageHandler.php` | utilise `completeStreaming` + émet `toolCall`/`toolResult` live au sink |
| `UI/Cli/AskCommand.php` | attache `CliAgentOutputStream` ; supprime le rendu après-coup (helpers morts retirés) |
| `UI/Tui/TuiCommand.php` | attache `TuiAgentOutputStream` ; le onSubmit ne rend plus le final (live) |
| `config/services.yaml` | binding `AgentOutputStreamRegistry` |
| tests | `ScriptedLlm` implémente `completeStreaming` ; setUp du handler + test « émet text/tool events » |

## Comment vérifier

```bash
docker compose exec php composer qa     # cs / phpstan max / deptrac / phpunit Unit (vert)

# CLI streaming (la réponse apparaît progressivement) :
docker compose exec php bin/console assistant:ask -m qwen2.5:7b "Explique en 3 phrases ce qu'est l'hexagonal."

# TUI (validation manuelle, vrai terminal) :
docker compose exec php bin/console assistant:tui -m qwen2.5:7b
```

## Step suivante

Tous les items de la feuille de route locale sont traités. Réactivité TUI **pleinement async** (HTTP non
bloquant dans Revolt) = chantier ultérieur si souhaité.
