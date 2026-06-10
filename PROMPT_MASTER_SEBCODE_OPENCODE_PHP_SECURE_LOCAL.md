# PROMPT MAÎTRE CODEX — Créer un clone OpenCode complet en PHP, local-only et sécurisé

## 0. Rôle de Codex

Tu es Codex, utilisé comme agent de développement pour créer un nouveau projet PHP from scratch.

Tu dois construire un outil de type OpenCode / Claude Code / Codex CLI, mais écrit en PHP, fonctionnant uniquement avec des LLM locaux, sans accès réseau externe, avec une sécurité maximale.

pour cela tu as accés au code d'opencode pur faire ce clone

Le projet doit être conçu comme un produit sérieux, extensible, testable, sécurisé et maintenable.

Tu ne dois pas créer un simple chatbot CLI avec quelques outils. Tu dois construire un vrai runtime agentique de code.

---

# 1. Objectif produit

Créer un outil nommé provisoirement :

```text
SebCode
```

SebCode est un agent de développement local qui permet à un utilisateur de travailler sur un dépôt de code depuis le terminal.

Il doit pouvoir :

- lire un dépôt ;
- comprendre sa structure ;
- discuter avec l’utilisateur ;
- construire un contexte repo intelligent ;
- appeler des outils ;
- lire, chercher et modifier des fichiers ;
- proposer des patchs ;
- lancer des commandes locales autorisées ;
- lancer tests/lint/typecheck ;
- corriger en boucle ;
- gérer des todos ;
- gérer des sous-tâches ;
- conserver une mémoire locale ;
- journaliser toutes les actions ;
- fonctionner sans cloud ;
- utiliser uniquement des modèles locaux ;
- rester strictement dans le workspace autorisé ;
- refuser les actions dangereuses ;
- empêcher l’exfiltration de secrets.

Le produit doit être comparable dans l’esprit à OpenCode, mais :

```text
- écrit en PHP ;
- local-only ;
- offline-first ;
- LLM locaux uniquement ;
- sécurité renforcée ;
- pas de provider cloud ;
- pas de websearch ;
- pas de télémétrie ;
- pas d’accès externe.
```

---

# 2. Contraintes absolues

## 2.1 Local-only

Le projet ne doit utiliser que des LLM locaux :

- Ollama ;
- llama.cpp local ;
- vLLM local éventuellement plus tard.

Provider principal MVP :

```text
Ollama local sur http://127.0.0.1:11434
```

Interdiction :

- OpenAI ;
- Anthropic ;
- Google Gemini ;
- Mistral Cloud ;
- OpenRouter ;
- GitHub Copilot ;
- Azure OpenAI ;
- AWS Bedrock ;
- tout provider externe.

## 2.2 Réseau fermé

Par défaut, l’agent ne doit faire aucun appel réseau externe.

Autorisé :

```text
localhost
127.0.0.1
::1
port Ollama local configuré
```

Interdit :

```text
HTTP externe
HTTPS externe
DNS externe
SSH
SCP
FTP
WebSearch
WebFetch
curl
wget
Invoke-WebRequest
irm
nc
ncat
telnet
```

Toute tentative d’accès réseau externe doit être bloquée et journalisée.

## 2.3 Sécurité workspace

L’agent travaille uniquement dans le workspace courant.

Interdiction :

```text
../
~/
C:\Users\
/home/user
/etc
/tmp hors workspace
.ssh
.aws
.gnupg
```

Les chemins doivent être normalisés avant toute lecture ou écriture.

Les symlinks doivent être contrôlés pour éviter une sortie du workspace.

## 2.4 Secrets

Ne jamais lire, injecter au modèle, afficher ou journaliser :

```text
.env
.env.*
*.pem
*.key
*.p12
*.pfx
id_rsa
id_ed25519
credentials
secrets.*
.auth.json
.npmrc si token
composer auth.json
docker-compose.override.yml si secrets visibles
```

Toute donnée suspecte doit être redacted :

```text
password=xxx
token=xxx
api_key=xxx
DATABASE_URL=xxx
JWT_SECRET=xxx
PRIVATE_KEY=xxx
```

devient :

```text
[REDACTED]
```

## 2.5 Écritures contrôlées

Aucune écriture directe non contrôlée.

Toute modification doit passer par :

```text
PatchManager
→ PermissionPolicy
→ Diff
→ Validation selon mode
→ Application
→ Historique
→ Rollback possible
```

## 2.6 Commandes shell contrôlées

Toute commande shell passe par :

```text
SecureShellExecutor
→ PermissionPolicy
→ timeout
→ capture stdout/stderr
→ redaction
→ journalisation
```

Commandes dangereuses bloquées :

```text
rm -rf
sudo
chmod 777
curl
wget
ssh
scp
ftp
powershell Invoke-WebRequest
irm
nc
ncat
format
del /s
rmdir /s
```

---

# 3. Décision importante : repartir de zéro

Tu dois créer un nouveau projet propre from scratch.

Tu ne dois pas modifier un ancien projet existant.

Tu dois produire une architecture propre dès le départ.

Si du code ancien est disponible, tu peux t’en inspirer uniquement pour les idées, mais ne copie pas de dette technique.

---

# 4. Stack technique

Langage :

```text
PHP 8.3 ou PHP 8.4
```

Framework :

```text
Symfony Console
Symfony DependencyInjection
Symfony Filesystem
Symfony Process
Symfony Finder
Symfony YAML
```

Tests :

```text
PHPUnit
```

Qualité :

```text
PHPStan
PHP-CS-Fixer optionnel
```

Stockage local :

```text
SQLite
JSONL
fichiers cache locaux
```

Dépendances à éviter :

```text
dépendances SaaS
SDK cloud
clients HTTP externes inutiles
services distants
```

HTTP client :

Autorisé uniquement pour appeler Ollama local.

---

# 5. Architecture cible

Créer la structure :

```text
sebcode/
├── bin/
│   └── sebcode
├── config/
│   ├── services.php
│   └── sebcode.yaml
├── src/
│   ├── Core/
│   │   ├── Agent/
│   │   ├── Session/
│   │   ├── Message/
│   │   ├── Model/
│   │   ├── Tool/
│   │   ├── Permission/
│   │   ├── Patch/
│   │   ├── Context/
│   │   ├── Memory/
│   │   ├── Audit/
│   │   ├── Security/
│   │   ├── Workspace/
│   │   └── Mode/
│   │
│   ├── Provider/
│   │   ├── Ollama/
│   │   └── LocalLlm/
│   │
│   ├── Tool/
│   │   ├── File/
│   │   ├── Search/
│   │   ├── Shell/
│   │   ├── Git/
│   │   ├── Task/
│   │   ├── Todo/
│   │   ├── Context/
│   │   └── Security/
│   │
│   ├── UI/
│   │   ├── Console/
│   │   └── Tui/
│   │
│   └── Extension/
│       ├── Symfony/
│       └── React/
│
├── tests/
│   ├── Unit/
│   ├── Integration/
│   └── Security/
│
├── var/
│   ├── cache/
│   ├── audit/
│   ├── patches/
│   └── memory/
│
├── .sebcode/
│   ├── runtime.db
│   ├── repo.db
│   ├── memory.db
│   ├── audit/
│   ├── patches/
│   ├── context/
│   └── index/
│
├── composer.json
├── phpunit.xml.dist
├── phpstan.neon
├── README.md
└── AGENTS.md
```

---

# 6. Core domain

## 6.1 AgentRunner

Classe centrale du runtime.

Responsabilités :

- recevoir une tâche utilisateur ;
- charger la session ;
- déterminer le mode ;
- construire le contexte ;
- appeler le modèle ;
- interpréter les tool calls ;
- exécuter les outils ;
- collecter les observations ;
- produire les étapes ;
- gérer les boucles ;
- lancer tests/corrections si mode adapté ;
- produire un rapport final.

Objets :

```text
AgentRunner
AgentRun
AgentRunId
AgentRunContext
AgentRunResult
AgentStep
AgentObservation
AgentLoopController
```

Règles :

- max iterations configurable ;
- max tool calls configurable ;
- arrêt si même tool call répété ;
- arrêt si même erreur répétée ;
- rapport honnête en cas d’échec.

---

## 6.2 SessionManager

Responsabilités :

- créer session ;
- reprendre session ;
- lister sessions ;
- archiver session ;
- stocker l’historique conversationnel ;
- stocker le mode courant ;
- stocker le workspace courant.

Objets :

```text
Session
SessionId
SessionRepository
SQLiteSessionRepository
MessageHistory
```

---

## 6.3 Message model

Créer un modèle clair :

```text
Message
SystemMessage
UserMessage
AssistantMessage
ToolMessage
ToolCall
ToolResult
```

Chaque message doit pouvoir être sérialisé en JSON.

---

## 6.4 ModelProvider

Contrat :

```php
interface ModelProviderInterface
{
    public function complete(ModelRequest $request): ModelResponse;
    public function stream(ModelRequest $request): iterable;
}
```

Provider initial :

```text
OllamaProvider
```

Il doit appeler uniquement :

```text
http://127.0.0.1:11434
```

Le host doit être configurable mais validé par NetworkPolicy.

Objets :

```text
ModelRequest
ModelResponse
ModelMessage
ModelToolDefinition
ModelToolCall
ModelUsage
```

---

## 6.5 ToolRegistry

Responsabilités :

- enregistrer les tools ;
- exposer leur schéma ;
- retrouver un tool par nom ;
- fournir la liste des tools disponibles selon mode ;
- fournir la liste des tools disponibles selon permissions.

Objets :

```text
ToolInterface
ToolRegistry
ToolDefinition
ToolInputSchema
ToolResult
ToolExecutionContext
```

Contrat :

```php
interface ToolInterface
{
    public function name(): string;
    public function description(): string;
    public function inputSchema(): array;
    public function execute(array $input, ToolExecutionContext $context): ToolResult;
}
```

---

## 6.6 ToolExecutor

Responsabilités :

- recevoir ToolCall ;
- valider tool existant ;
- passer par PermissionPolicy ;
- exécuter tool ;
- appliquer timeout ;
- tronquer sortie ;
- redacter secrets ;
- journaliser ;
- retourner ToolResult.

---

## 6.7 PermissionPolicy

Cœur sécurité.

Niveaux :

```text
ALLOW
REQUIRE_APPROVAL
DENY
```

Raisons :

```text
READ_SAFE
WRITE_REVIEW_REQUIRED
SHELL_REVIEW_REQUIRED
NETWORK_BLOCKED
SECRET_BLOCKED
OUTSIDE_WORKSPACE_BLOCKED
DANGEROUS_COMMAND_BLOCKED
MODE_BLOCKED
```

Objets :

```text
PermissionPolicy
PermissionDecision
PermissionReason
PermissionRequest
PermissionContext
```

Toute action sensible doit passer par là :

- read file ;
- write file ;
- patch ;
- shell ;
- network ;
- git ;
- memory write ;
- config write.

---

## 6.8 PatchManager

Responsabilités :

- créer patch ;
- calculer diff ;
- valider fichiers modifiés ;
- demander approval selon mode ;
- appliquer patch ;
- créer snapshot ;
- rollback ;
- stocker historique.

Objets :

```text
PatchManager
PatchRequest
Patch
PatchId
PatchResult
PatchHistoryRepository
FileSnapshot
DiffRenderer
```

Règles :

- aucune écriture directe ;
- fichiers interdits bloqués ;
- diff obligatoire ;
- rollback disponible.

---

## 6.9 ContextManager

Responsabilités :

- construire le contexte repo ;
- respecter budget tokens approximatif ;
- inclure fichiers utiles ;
- exclure secrets ;
- produire ContextPack ;
- stocker les context packs utiles ;
- compresser/résumer si nécessaire.

Objets :

```text
ContextManager
ContextBuilder
ContextPack
ContextFile
ContextBudget
ContextReport
ContextCompressor
```

---

## 6.10 MemoryStore

Mémoire locale, pas cloud.

Stockages :

```text
.sebcode/runtime.db
.sebcode/repo.db
.sebcode/memory.db
```

Mémoire runtime :

- runs ;
- steps ;
- tool executions ;
- sessions.

Mémoire repo :

- projet détecté ;
- fichiers ;
- symboles ;
- frameworks ;
- commandes tests ;
- règles projet.

Mémoire long terme :

- préférences ;
- conventions acceptées ;
- erreurs connues ;
- patchs réussis ;
- patchs refusés ;
- règles spécifiques au repo.

---

## 6.11 AuditLogger

Journaliser localement :

- run id ;
- session id ;
- date ;
- modèle ;
- mode ;
- prompt système ;
- contexte injecté ;
- fichiers lus ;
- tool calls ;
- shell commands ;
- patchs ;
- tests ;
- erreurs ;
- décisions permission ;
- rapport final.

Format recommandé :

```text
JSONL
```

Chemin :

```text
.sebcode/audit/YYYY-MM-DD.jsonl
```

Secrets toujours redacted.

---

## 6.12 WorkspaceGuard

Responsabilités :

- déterminer workspace root ;
- normaliser chemins ;
- empêcher sortie workspace ;
- contrôler symlinks ;
- appliquer exclusions.

Objets :

```text
Workspace
WorkspaceRoot
PathNormalizer
WorkspaceGuard
IgnoreMatcher
```

---

## 6.13 Mode system

Modes initiaux :

```text
READ_ONLY
PATCH
EXECUTE
AUTO
REVIEW
```

Plus tard :

```text
LEGACY_FEATURE
BUGFIX
REFACTOR
SYMFONY
REACT
```

Pour le MVP OpenCode-like, ne pas encore spécialiser Symfony/React. Prévoir l’extension, mais rester généraliste.

---

# 7. Tools obligatoires MVP

Créer les tools suivants.

## 7.1 File tools

### read_file

Lit un fichier autorisé.

Input :

```json
{
  "path": "src/Foo.php"
}
```

Règles :

- workspace only ;
- secrets blocked ;
- taille max ;
- redaction ;
- audit.

### list_files

Liste fichiers selon glob.

### write_file

Ne doit pas écrire directement.

Doit produire un patch via PatchManager.

### edit_file

Édition ciblée via patch.

### apply_patch

Applique patch si autorisé.

---

## 7.2 Search tools

### grep

Recherche texte via PHP ou ripgrep si disponible.

### glob

Recherche fichiers.

### symbol_search

MVP simple :

- classes PHP ;
- fonctions ;
- méthodes ;
- fichiers.

Pas besoin d’AST complet au début.

### find_references

MVP texte/regex.

---

## 7.3 Shell tools

### run_command

Passe par SecureShellExecutor.

- timeout ;
- allowlist/denylist ;
- stdout/stderr ;
- redaction ;
- audit.

---

## 7.4 Git tools

### git_status

### git_diff

### git_show

### git_branch

Interdiction :

- git push ;
- git pull ;
- git clone ;
- git fetch ;
- git remote réseau.

---

## 7.5 Task tools

### todo_read

### todo_write

Gérer todo list locale de la session.

### task_create

Créer une sous-tâche.

### task_status

Lire état sous-tâche.

---

## 7.6 Context tools

### repo_overview

Produit une carte du repo :

- langage ;
- framework ;
- dossiers ;
- tests ;
- commandes ;
- patterns.

### context_pack

Construit un contexte utile pour une tâche.

---

## 7.7 Security tools

### security_check_path

### security_scan_patch

### security_scan_output

---

# 8. Console CLI

Créer une CLI `sebcode`.

Commandes MVP :

```bash
sebcode chat
sebcode run "task"
sebcode session list
sebcode session resume <id>
sebcode mode show
sebcode mode set <mode>
sebcode tools list
sebcode repo overview
sebcode memory inspect
sebcode audit list
sebcode audit show <run-id>
```

Exemples :

```bash
sebcode chat
sebcode run "explique ce repo"
sebcode run "corrige cette erreur PHPUnit"
sebcode mode set PATCH
sebcode repo overview
```

---

# 9. TUI

Prévoir une TUI, mais ne pas la faire avant le core.

Priorité TUI plus tard :

- panneau conversation ;
- panneau tools ;
- panneau diff ;
- panneau logs ;
- validation patch.

Pour le MVP, une CLI propre suffit.

---

# 10. Configuration

Fichier :

```text
.sebcode/config.yaml
```

Exemple :

```yaml
model:
  provider: ollama
  name: qwen2.5-coder:14b
  host: http://127.0.0.1:11434

security:
  network: deny_external
  workspace_only: true
  block_secrets: true
  require_patch_review: true

agent:
  max_iterations: 10
  max_tool_calls: 30
  default_mode: PATCH

shell:
  timeout_seconds: 30
  max_output_chars: 20000
```

---

# 11. Tests obligatoires

Créer tests unitaires et sécurité.

## 11.1 WorkspaceGuardTest

- refuse `../secret.txt` ;
- refuse home ;
- accepte `src/Foo.php` ;
- contrôle symlink hors workspace.

## 11.2 SecretRedactorTest

- masque DATABASE_URL ;
- masque API_KEY ;
- masque token ;
- masque private key.

## 11.3 PermissionPolicyTest

- read src allowed ;
- read .env denied ;
- write src requires approval ;
- curl denied ;
- phpunit allowed/review.

## 11.4 PatchManagerTest

- crée diff ;
- refuse fichier secret ;
- applique patch autorisé ;
- rollback possible.

## 11.5 SecureShellExecutorTest

- refuse curl ;
- refuse rm -rf ;
- accepte git status ;
- timeout fonctionne.

## 11.6 ToolRegistryTest

- tools enregistrés ;
- tool introuvable ;
- schema disponible.

---

# 12. Roadmap Codex par étapes

Tu dois travailler par étapes. Ne fais pas tout d’un coup.

Chaque étape doit produire :

```text
- fichiers créés/modifiés
- résumé technique
- tests lancés
- résultat
- risques restants
- prochaine étape
```

## Étape 0 — Bootstrap projet

Créer projet PHP :

- composer.json ;
- bin/sebcode ;
- structure src/tests ;
- PHPUnit ;
- PHPStan ;
- PHP 8.4 ;
- Symfony 8.1.* ;
- Symfony Console ;
- composer.json minimal : `symfony/console` au runtime, dépendances AI/UI/TUI ajoutées seulement aux steps dédiés ;
- autoload PSR-4 ;
- commande `sebcode --version`.

Ne pas implémenter l’agent encore.

## Étape 1 — WorkspaceGuard + Security core

Créer :

- WorkspaceGuard ;
- PathNormalizer ;
- IgnoreMatcher ;
- SecretRedactor ;
- NetworkPolicy ;
- tests sécurité.

## Étape 2 — PermissionPolicy

Créer :

- PermissionPolicy ;
- PermissionRequest ;
- PermissionDecision ;
- PermissionReason ;
- tests.

## Étape 3 — Tool system

Créer :

- ToolInterface ;
- ToolRegistry ;
- ToolExecutor ;
- ToolExecutionContext ;
- ToolResult ;
- tools list ;
- tests.

## Étape 4 — File/Search/Git tools

Créer :

- read_file ;
- list_files ;
- grep ;
- glob ;
- git_status ;
- git_diff.

Tous sécurisés par PermissionPolicy.

## Étape 5 — PatchManager

Créer :

- PatchManager ;
- PatchRequest ;
- Patch ;
- PatchHistory ;
- DiffRenderer ;
- apply_patch ;
- write_file/edit_file via patch.

## Étape 6 — Shell sécurisé

Créer :

- SecureShellExecutor ;
- run_command ;
- allowlist/denylist ;
- timeout ;
- tests.

## Étape 7 — OllamaProvider

Créer :

- ModelProviderInterface ;
- OllamaProvider ;
- ModelRequest ;
- ModelResponse ;
- tool-call handling si supporté ;
- validation host localhost uniquement.

## Étape 8 — AgentRunner

Créer :

- AgentRunner ;
- AgentRun ;
- AgentLoopController ;
- intégration model + tools ;
- commande `sebcode run`.

## Étape 9 — Session + Memory SQLite

Créer :

- SessionManager ;
- SQLite repositories ;
- runtime.db ;
- repo.db ;
- memory.db ;
- commandes session list/resume.

## Étape 10 — ContextManager + repo_overview

Créer :

- RepoOverviewTool ;
- ContextManager ;
- ContextPack ;
- budget ;
- exclusions secrets.

## Étape 11 — Todo/task tools

Créer :

- todo_read ;
- todo_write ;
- task_create ;
- task_status.

## Étape 12 — AuditLogger

Créer :

- AuditLogger JSONL ;
- audit list/show ;
- redaction obligatoire.

## Étape 13 — Modes

Créer :

- READ_ONLY ;
- PATCH ;
- EXECUTE ;
- AUTO ;
- REVIEW ;
- mode show/set ;
- règles par mode.

## Étape 14 — Test loop

Créer :

- TestRunner ;
- CorrectionLoop ;
- max 3 cycles ;
- intégration AgentRunner.

## Étape 15 — Hardening sécurité

Ajouter tests :

- aucun réseau externe ;
- secrets bloqués ;
- shell dangereux bloqué ;
- sortie workspace bloquée ;
- patch obligatoire ;
- audit complet.

---

# 13. Prompt de lancement pour Codex

Utilise ce prompt au démarrage :

```text
Tu dois créer un nouveau projet PHP nommé SebCode.

Objectif :
construire un clone OpenCode-like complet en PHP, local-only, offline-first, sécurisé, utilisant uniquement des LLM locaux.

Contraintes absolues :
- aucun provider cloud ;
- aucun accès réseau externe ;
- Ollama local uniquement pour le MVP ;
- aucune lecture de secrets ;
- workspace only ;
- toutes les écritures passent par PatchManager ;
- toutes les commandes shell passent par SecureShellExecutor ;
- toutes les actions sensibles passent par PermissionPolicy ;
- audit local obligatoire ;
- tests sécurité obligatoires.

Ne spécialise pas encore Symfony/React. Prévois seulement le dossier Extension pour plus tard.

Commence uniquement par l’étape 0 : bootstrap projet.

Ne fais pas les étapes suivantes tant que l’étape 0 n’est pas terminée.

À la fin de l’étape 0, donne :
- fichiers créés ;
- commandes à lancer ;
- tests lancés ;
- résultat ;
- prochaine étape.
```

---

# 14. Prompt de continuation

Après chaque étape :

```text
Continue avec l’étape suivante du fichier PROMPT_MASTER_SEBCODE_OPENCODE_PHP_SECURE_LOCAL.md.

Avant de coder :
1. relis l’objectif de l’étape ;
2. vérifie les contraintes sécurité ;
3. propose un plan court ;
4. implémente uniquement cette étape ;
5. ajoute les tests demandés ;
6. lance les tests possibles ;
7. donne un rapport.

Ne fais pas d’étape suivante sans validation.
```

---

# 15. Definition of Done MVP

Le MVP est validé si :

```text
[ ] sebcode --version fonctionne
[ ] sebcode run "..." fonctionne avec Ollama local
[ ] les tools read/search/git/shell/patch fonctionnent
[ ] aucun accès réseau externe n’est possible
[ ] les secrets sont bloqués
[ ] les chemins hors workspace sont bloqués
[ ] les écritures passent par PatchManager
[ ] les commandes shell passent par SecureShellExecutor
[ ] les sessions sont persistées
[ ] la mémoire SQLite fonctionne
[ ] l’audit JSONL fonctionne
[ ] repo_overview fonctionne
[ ] context_pack fonctionne
[ ] modes READ_ONLY/PATCH/EXECUTE/AUTO existent
[ ] tests sécurité passent
```

---

# 16. Critère de succès produit

SebCode doit devenir :

```text
Un OpenCode-like en PHP :
- local ;
- sécurisé ;
- auditable ;
- sans cloud ;
- extensible ;
- adapté plus tard à Symfony/React.
```

Le premier objectif n’est pas la TUI parfaite.

Le premier objectif est :

```text
Core agentique robuste + tools + sécurité + mémoire + patch + Ollama.
```

---

# 17. Règles de comportement Codex

Tu dois :

- privilégier architecture claire ;
- créer petits commits logiques ;
- ne jamais masquer un test qui échoue ;
- ne jamais ajouter de provider externe ;
- ne jamais ajouter websearch ;
- ne jamais ajouter télémétrie ;
- ne jamais désactiver une sécurité pour faire passer un test ;
- ne jamais mélanger spécialisation Symfony/React dans le core MVP ;
- documenter chaque étape.

Si une décision est ambiguë, choisis l’option la plus sécurisée.

---

# 18. Extensions futures

Après MVP seulement :

```text
Extension/Symfony
Extension/React
Extension/LegacySymfony
Extension/PhpUnit
Extension/TypeScript
```

Ne pas les implémenter maintenant.

Le core doit rester générique, comme OpenCode, puis les spécialisations viendront par extension ou mode.





# 19. Contraintes d’architecture — inspiration École Futée (obligatoire)

IMPORTANT :

Le projet SebCode reste un produit généraliste de type OpenCode-like.

MAIS son architecture interne doit suivre des principes DDD / hexagonaux inspirés du projet École Futée.

Objectif :

- séparation claire des responsabilités ;
- testabilité ;
- remplaçabilité des implémentations ;
- faible couplage ;
- code orienté cas d’usage ;
- pas de logique métier dans les commandes console ;
- pas de services fourre‑tout.

Cette contrainte concerne uniquement l’architecture interne de SebCode.

Elle ne doit PAS être imposée aux projets analysés plus tard.

---

## Structure cible

```text
src/

Core/
├── Domain/
│
├── Application/
│
├── Infrastructure/
│
└── UI/

Extension/
├── Symfony/
└── React/
```

---

## Domain

Le domaine ne dépend de rien.

Il contient :

```text
Entity
ValueObject
RepositoryInterface
ManagerInterface
FactoryInterface
DomainService
DomainEvent
Specification
Exception
```

Interdictions :

```text
pas de HTTP
pas de Symfony Console
pas de filesystem
pas de Process
pas de Provider LLM
pas de DTO
```

---

## Application

Couche cas d’usage.

Elle contient :

```text
UseCase
Command
Query
Handler
DTO/Input
DTO/Output
Mapper
Validator
Policy
ApplicationEvent
```

Convention :

```text
1 UseCase = 1 responsabilité
```

Exemple :

```text
RunAgentUseCase
CreatePatchUseCase
ApplyPatchUseCase
RunToolUseCase
BuildContextUseCase
CreateSessionUseCase
LoadSessionUseCase
RunTestsUseCase
```

Convention :

```text
UseCase
↓
ManagerInterface
↓
RepositoryInterface
↓
Infrastructure
```

---

## Infrastructure

Implémentations techniques.

Contient :

```text
SQLiteRepository
OllamaProvider
Filesystem
PatchEngine
ShellExecutor
AuditStorage
MemoryStorage
GitAdapter
ToolAdapter
```

Règles :

```text
jamais appelée directement depuis UI
```

---

## UI

Contient :

```text
Console
Tui
Command
Presenter
InputParser
```

Interdiction :

```text
pas de logique métier
```

---

## DTO obligatoires

Ne jamais faire transiter d’array brute.

Créer :

```text
Input DTO
Output DTO
```

Exemples :

```text
RunAgentInput
RunAgentOutput

ApplyPatchInput
ApplyPatchOutput

CreateSessionInput
CreateSessionOutput
```

---

## Handlers obligatoires

Chaque action importante doit avoir un handler.

Exemples :

```text
RunAgentHandler
RunToolHandler
ApplyPatchHandler
RunTestsHandler
```

---

## Managers obligatoires

Managers = orchestration métier.

Exemples :

```text
AgentManager
SessionManager
PatchManager
ContextManager
SecurityManager
```

---

## Factories obligatoires

Ne jamais instancier directement des objets complexes.

Créer :

```text
AgentFactory
ToolFactory
ProviderFactory
PatchFactory
SessionFactory
```

---

## Interfaces obligatoires

Toute implémentation technique doit avoir son interface.

Exemples :

```text
ModelProviderInterface
PatchRepositoryInterface
MemoryRepositoryInterface
SessionRepositoryInterface
ToolInterface
AuditStorageInterface
```

---

## Events

Prévoir événements internes :

```text
AgentRunStartedEvent
ToolExecutedEvent
PatchAppliedEvent
TestsCompletedEvent
SessionCreatedEvent
```

---

## Cas particulier important

Même si l’architecture interne est DDD/hexa :

Le comportement produit reste :

```text
feature-first
pragmatique
petits patches
```

Interdiction :

```text
sur‑ingénierie
abstraction inutile
20 couches pour lire un fichier
```

---

## Prompt supplémentaire pour Codex

Avant toute création :

```text
Architecture obligatoire :

DDD / hexagonale inspirée École Futée.

Créer :
- DTO
- UseCase
- Handler
- Manager
- Interface
- Factory

mais rester pragmatique.

Ne pas créer de couches inutiles.

Le core doit rester simple malgré la séparation.
```
