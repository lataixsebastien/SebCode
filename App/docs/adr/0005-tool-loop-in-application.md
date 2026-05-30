# ADR-0005 — La boucle agentique vit dans `Assistant/Application`

**Date :** 2026-05-30
**Statut :** Accepté
**Step lié :** STEP-12

## Contexte

À partir de STEP-12, le `SendMessageHandler` doit gérer une **boucle multi-tour** :
appel LLM → si tool_calls → exécuter les outils → ré-appeler le LLM → … → réponse textuelle finale.

Où vit cette boucle ?

## Décision

Dans **`Assistant/Application/Command/SendMessageHandler`** lui-même, refactoré.

**On ne crée PAS** :
- un bounded context `Agent/`
- un orchestrateur séparé `RunAgentLoopHandler` qui appellerait `SendMessageHandler` itérativement
- une intégration de `Symfony\AI\Agent\Agent` (la classe Agent du bundle), même si elle implémente déjà cette logique

La boucle est encapsulée dans le `__invoke()` du handler, bornée par deux constantes (`MAX_TURNS = 10`, `DOOM_LOOP_THRESHOLD = 3`).

## Conséquences

### Positives

- **Frontière hexa intacte.** `Assistant\Application` ne touche jamais `Symfony\AI\Agent` — ça vivrait dans Infrastructure, ce qui n'est pas où la logique métier doit aller. La boucle est un détail d'orchestration applicatif, pas une dépendance technique.
- **Un seul use case.** L'opération utilisateur "envoyer un message" englobe naturellement "et tous les tool calls qui en découlent". Séparer le tour user du tour agent forcerait l'UI à orchestrer ce qui devrait être atomique côté Application.
- **YAGNI.** Pas de bounded context `Agent/` tant qu'on n'a pas de besoins multi-agent (sub-agents, planning multi-niveau, coordination), qui sont pour l'instant hypothétiques. Quand ces besoins arriveront, `Agent/` aura sa propre raison d'être au-delà de "où mettre la boucle".
- **Tests directs.** `SendMessageHandlerTest` couvre tous les cas en pur PHPUnit avec des doubles in-memory (cf. `RecordingToolGateway`, `ScriptedLlm`). Aucun besoin de booter un AI bundle.

### Négatives

- **`SendMessageHandler` grossit.** ~200 lignes vs ~60 avant STEP-12. C'est acceptable parce que la complexité est intrinsèque (boucle + doom-loop guard + persistance des Messages intermédiaires), pas accidentelle.
- **Pas de réutilisation hors Assistant.** Si un jour `Tool/` veut tester un outil "en agent" (sandbox tour LLM), il devra dupliquer la boucle. Acceptable — Tool ne devrait pas avoir besoin de ça.

## Bornes et garde-fous

- **`MAX_TURNS = 10`** : nombre maximal de tours LLM avant d'abandonner avec `AgentLoopExceeded`. Override via env `LLM_MAX_TURNS` envisageable plus tard.
- **`DOOM_LOOP_THRESHOLD = 3`** : si le LLM répète **3 fois de suite le même tool call** (même signature `name + json(arguments)`), on :
  1. Drop la liste des tools annoncés au prochain tour (le LLM ne peut plus en appeler).
  2. Inject un `system` message "stop repeating, finalize".
  Effet typique observé : le LLM commit à une réponse textuelle au tour suivant.

  Différent de MAX_TURNS qui vise les boucles "intelligentes" (différents tool calls successifs).
- **Hard failure du `ToolGateway`** (registry corrompue, exception non-soft) : abort la boucle, propagation au caller. User message + intermediates déjà persistés → inspection possible via `assistant:sessions`.
- **`LlmUnavailable`** au milieu de la boucle : propagation. Même garantie : tout ce qui était déjà persisté reste.

## Pipeline complet

```
SendMessageCommand(sessionId, userText)
        │
        ├─ trim(userText) == "" → throw InvalidArgument
        ├─ session = sessions.findById() → null ? throw SessionNotFound
        ├─ persist user Message
        │
        ├─ advertisements = toolGateway.availableTools()
        ├─ for turn in 1..MAX_TURNS:
        │     history = messages.forSession()
        │     reply   = llm.complete(model, history, advertisements)
        │
        │     if reply.toolCalls == []:
        │         persist assistant text → return SendMessageResult
        │
        │     persist assistant turn  (role=Assistant, payload=ToolCall list)
        │     for each call:
        │         signature = hash(name + args)
        │         track in recentToolSignatures
        │         result = toolGateway.execute(call)   ── may throw (abort)
        │         persist tool message  (role=Tool, payload=ToolResult)
        │
        │     if isDoomLoop(recentToolSignatures):
        │         advertisements = []                        # force textual reply
        │         persist system nudge
        │
        └─ throw AgentLoopExceeded(MAX_TURNS)
```

## Alternatives écartées

### A1 — Bounded context `Agent/`

❌ Reportée. Pas de besoin réel aujourd'hui — la boucle est mono-Agent, mono-session, sans coordination. Sera ré-évaluée si on attaque sub-agents (cf. opencode `agent/`), mode planning, ou orchestration multi-LLM.

### A2 — Utiliser `Symfony\AI\Agent\Agent`

❌ Rejetée. Importerait `symfony/ai-agent` dans Application via le Port `LlmPort` — viol direct de ADR-0001 / CLAUDE.md §3.2. Le wrapper Agent du bundle est conçu pour un usage différent (config-driven, attribute-driven tools `#[AsTool]`) qui ne s'intègre pas avec notre approche Port/Adapter.

### A3 — Bus Messenger / event-sourcing tour-par-tour

❌ Rejetée. Pas de besoin async court terme (cf. [ADR-0002](0002-no-messenger-wiring.md)). Reconsidérer si un outil bloque > 30s typiquement.

## Notes d'application

- Voir [`steps/STEP-12-agent-loop.md`](../steps/STEP-12-agent-loop.md) pour le journal.
- Voir [`contexts/assistant.md`](../contexts/assistant.md) §2 pour le pipeline détaillé documenté côté contexte.
- Pseudo-code dans le PHPDoc de `App/src/Assistant/Application/Command/SendMessageHandler.php`.
