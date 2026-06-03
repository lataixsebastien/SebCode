# STEP-24 — Outil `task` (sous-agent imbriqué)

**Statut :** ✅ terminé (`composer qa` vert)
**Branche :** `feat/sebcode-foundation`

## But

Donner à l'agent la capacité de **déléguer une sous-tâche** à un agent imbriqué (faithful opencode
`task`) : l'agent passe une instruction auto-suffisante, un sous-agent **borné** la traite avec la même
panoplie d'outils (sauf `task`) et renvoie un **texte** final. **Local**, pas de persistance, pas de
récursion. Réutilise la plomberie `SessionId` de STEP-23.

## Décisions clés

| Décision | Choix | Raison |
|---|---|---|
| Placement | Port Domain `SubAgentRunner` (Tool) + impl `AssistantSubAgentRunner` (Assistant Infra) | L'outil n'importe pas le contexte Assistant ; **2ᵉ bridge** Assistant↔Tool (après `ToolGateway`, cf. ADR-0004) |
| Boucle | **Éphémère, en mémoire, bornée** (`MAX_TURNS=8`) ; rien n'est persisté | Un sous-agent ne pollue pas l'historique de la session |
| Outils du sous-agent | Tous **sauf `task` et `todowrite`** (filtrés des advertisements) | Anti-récursion + ne touche pas l'état todo du parent (fidèle opencode) |
| Garde anti-récursion | Si un appel `task` revient malgré tout, il n'est **pas exécuté** (résultat d'erreur injecté) | Double sécurité au-delà du filtrage |
| Scope des tool calls | `sessionId` du parent (via `ToolExecutionContext`) | Les outils mutants restent gatés et scopés comme d'habitude |
| Permission de `task` | Aucune propre | Les outils que le sous-agent appelle sont gatés individuellement |
| Modèle | `%env(LLM_MODEL)%` par défaut | Le runner n'a pas de session ; modèle par défaut comme la CLI |
| Retour | Texte final du sous-agent (ou message « turn limit ») | Fidèle opencode (`<task_result>`) |

## Layout produit

### Nouveau

```
src/Tool/Domain/Port/SubAgentRunner.php                         (run(instruction, sessionId): string)
src/Tool/Infrastructure/Tool/TaskTool.php                       (id "task" ; délègue au runner)
src/Assistant/Infrastructure/SubAgent/AssistantSubAgentRunner.php (boucle éphémère bornée ; filtre task/todowrite)
tests/Support/Tool/Doubles/FakeSubAgentRunner.php
tests/Unit/Tool/Infrastructure/Tool/TaskToolTest.php
tests/Unit/Assistant/Infrastructure/SubAgent/AssistantSubAgentRunnerTest.php
```

### Modifié

| Fichier | Changement |
|---|---|
| `config/services.yaml` | `task` au service-locator ; binding `SubAgentRunner` → `AssistantSubAgentRunner` (`$defaultModel`) |

## Fidélité opencode (simplifiée)

| opencode (`tool/task.ts`) | SebCode |
|---|---|
| params `description` + `prompt` (+ `subagent_type`) | identiques (`subagent_type` accepté, agent unique général) |
| sous-session enfant + boucle d'agent | boucle **éphémère** en mémoire (pas de session enfant persistée) |
| `task: false` / `todowrite: false` pour le sous-agent | filtrés des advertisements + garde d'exécution |
| retour = texte final | identique |
| background / task_id / agents nommés | **non portés** (hors scope) |

## Comment vérifier

```bash
docker compose exec php composer qa     # cs / phpstan max / deptrac / phpunit Unit (vert)
docker compose exec php bin/console lint:container

# Smoke LLM :
docker compose exec php bin/console assistant:ask -m qwen2.5:7b \
  "Utilise l'outil task pour déléguer: trouve où la classe Kernel est définie et résume en une phrase."
```

## Step suivante

Outils : glob, read, grep, write, edit, bash, todowrite, apply_patch, **task**. Restantes (local) :
`lsp` (diagnostics, lourd), persistance des permissions, ou la boucle agentique **async** (la limite n°1).
