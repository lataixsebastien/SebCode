# CORRECTIONS ARCHITECTURE — SEBCODE
# À appliquer avant toute nouvelle fonctionnalité

Objectif :
Corriger l’architecture actuelle avant de continuer le développement.

IMPORTANT :
STOP toute nouvelle feature.
STOP toute nouvelle tool.
STOP toute nouvelle UI.

Objectif immédiat :
réduire la dette créée.

---

# Correction 1 — Supprimer le God Object SendMessageHandler

Problème :

SendMessageHandler concentre :

- historique
- construction conversation
- appel modèle
- streaming
- interruption
- tool loop
- persistance
- session

C’est déjà un anti-pattern.

Action :

Extraire :

Core/Application/

AgentRunner
ConversationBuilder
ToolLoopExecutor
TurnExecutor
LoopController

Responsabilités :

AgentRunner
→ pilote run complet

ConversationBuilder
→ construit contexte conversation

ToolLoopExecutor
→ exécute boucle outils

TurnExecutor
→ exécute un tour

LoopController
→ contrôle itérations

Critères :

SendMessageHandler :
< 100 lignes

---

# Correction 2 — Remplacer faux DDD

Problème :

Managers fourre-tout :

ToolManager
PermissionManager
WorkspaceManager

Action :

Remplacer :

ExecuteToolUseCase
EvaluatePermissionUseCase
ResolveWorkspaceUseCase

Critères :

Application :
uniquement UseCase + DTO + Handler

Infrastructure :
implémentations

---

# Correction 3 — Découpler Provider

Problème :

Adapter Ollama trop couplé.

Action :

Créer :

Provider/

ModelProviderInterface
OllamaProvider
ProviderRegistry
MessageTranslator
ToolCallTranslator

Critères :

Changer provider sans toucher AgentRunner.

---

# Correction 4 — Ajouter mémoire persistante

Problème :

Mémoire actuelle = chat.

Action :

Créer :

.sebcode/

runtime.db
repo.db
memory.db

runtime.db :

sessions
runs
tool_calls

repo.db :

repo
framework
index
tests

memory.db :

rules
failures
success

Critères :

Reprise session sans rescanner repo.

---

# Correction 5 — ToolRegistry riche

Problème :

ToolRegistry trop faible.

Action :

Ajouter :

ToolDescriptor

Champs :

name
category
safe
cost
allowed_modes
timeout
requires_review

Exemple :

read_file
safe=true

run_shell
requires_review=true

Critères :

ToolRegistry décide disponibilité.

---

# Correction 6 — Ajouter RepoContext

Problème :

absence contexte repo.

Créer :

Context/

RepoAnalyzer
ContextBuilder
ContextPack
RepoIndex
ContextBudget

Critères :

Aucun scan complet répété.

---

# Correction 7 — Extraire PermissionPolicy

Problème :

permissions liées UI.

Créer :

Permission/

PermissionPolicy
DecisionEngine
ApprovalStore
PermissionContext

Critères :

Console ne décide rien.

---

# Correction 8 — Réduire DDD

Problème :

sur‑architecture.

Règles :

pas plus de :

Entity
DTO
UseCase
Handler
Repository

Interdits :

Service
Manager
ManagerFactory
AbstractFactory

sauf besoin démontré.

---

# Correction 9 — Mesurer le runtime

Créer :

Metrics/

RunMetrics
TokenMetrics
ToolMetrics

Mesures :

temps run
temps tool
tokens
patchs

---

# Correction 10 — Geler le périmètre

Interdit jusqu’au MVP :

TUI
React
Symfony extension
Plugins
MCP
Multi-provider
Vector DB

---

# Prompt Codex

STOP.

Ne crée plus aucune fonctionnalité.

Applique uniquement ce document.

Pour chaque correction :

1 analyse impact
2 plan
3 modification
4 tests
5 rapport

Ne fais pas correction suivante sans validation.
