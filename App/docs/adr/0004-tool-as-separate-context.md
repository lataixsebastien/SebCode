# ADR-0004 — `Tool/` est un Bounded Context séparé, consommé par Assistant via un Port

**Date :** 2026-05-30
**Statut :** Accepté
**Step lié :** STEP-09 (création), STEP-12 (consommation par Assistant)

## Contexte

À partir de STEP-09 on introduit la notion d'**outils** que le LLM peut invoquer (read, glob, write, shell, …). Question structurante : où range-t-on ce code ?

Trois options viables :

1. **Tout dans `Assistant/`** : `App/src/Assistant/Infrastructure/Tool/{GlobTool, ReadTool, …}.php` + `Application/Command/ExecuteToolHandler.php`.
2. **Bounded context dédié `Tool/`**, consommé par Assistant via un Port.
3. **Bounded context `Agent/`** qui contient à la fois la boucle agentique ET les outils.

## Décision

**Option 2.** On crée `App/src/Tool/{Domain,Application,Infrastructure,UI}/` en respectant le même layout que `Assistant/`. L'Assistant consommera Tool **en STEP-12** via un Port `Assistant\Domain\Port\ToolGateway`, dont l'unique implémentation `Assistant\Infrastructure\Tool\AssistantToolGatewayAdapter` délègue à `Tool\Application\ExecuteToolHandler` + `Tool\Application\ListToolsHandler` et traduit les DTOs `Tool\Domain` ↔ `Assistant\Domain`.

Les VOs partagés entre les deux contextes (`ToolAdvertisement`, `ToolCallRequest`, `ToolResultDto`) sont **dupliqués** côté `Assistant/Domain/Model/ValueObject/` — DTOs miroir minimaux, pour qu'`Assistant\Domain` ne dépende pas de `Tool\Domain`. La traduction se fait dans l'adapter `AssistantToolGatewayAdapter` (couche Infrastructure).

## Conséquences

### Positives

- **Extensibilité future propre.** Les prochains chantiers (MCP, sub-agents, plugins externes) implémenteront eux aussi `Assistant\Domain\Port\ToolGateway` (ou un autre port équivalent) sans toucher au context Tool. À l'inverse, on pourra ajouter des outils dans Tool sans toucher à Assistant.
- **Pas de coupling Domain↔Domain.** Aucun `use App\Tool\…` dans `App/src/Assistant/Domain/` ou `App/src/Assistant/Application/` — la frontière hexa reste nette, enforçable par Deptrac.
- **Tests isolés.** Le Domain Tool a ses propres unit tests qui ne mockent rien d'Assistant. Idem inverse.
- **Évolutivité du modèle Tool.** Si on veut un jour des outils streaming, des outils avec contexte session, ou typer `JsonSchema` plus strictement, c'est local à `Tool/`.

### Négatives

- **Plus de fichiers.** Trois VOs sont dupliqués (en version DTO) côté Assistant. Coût marginal (~30 lignes).
- **Une couche d'indirection** (`AssistantToolGatewayAdapter`) là où une approche monolithique pourrait appeler directement le registry. La conversion DTO se paie une fois par tool_call (~µs).
- **Discipline requise.** Tentation future de "juste importer `App\Tool\Domain\ToolCall` dans `Assistant\Application`" — Deptrac doit l'interdire, voir STEP-12 pour la règle.

## Alternatives écartées

### A1 — Tout dans Assistant/

❌ Rejetée. Mélange deux préoccupations distinctes : "le système peut exécuter un outil" (Tool) vs "l'assistant orchestre une conversation incluant des outils" (Assistant). Les deux évoluent à des rythmes différents. Dans 6 mois, ajouter MCP (qui expose des outils externes) demanderait un refactor majeur.

### A2 — Bounded context `Agent/` englobant tout

❌ Rejetée (pour l'instant). Anticipée — quand on aura sub-agents, mode planning, multi-coordination, un context `Agent/` aura du sens. Mais aujourd'hui la "boucle agentique" est un détail d'orchestration applicatif dans Assistant — pas un sous-domaine métier à part entière. Voir ADR-0005 (à écrire en STEP-12) qui acte ce choix.

## Notes d'application

- Layers Deptrac : pour STEP-09 et STEP-10, les regex génériques `^App\\[^\\]+\\Domain\\.*$` etc. couvrent Tool sans config supplémentaire — `Tool/Domain` se range automatiquement dans le layer `Domain` (avec règle `Domain: []`).
- L'enforcement spécifique "Assistant ne peut pas importer Tool/Domain directement, sauf depuis `Assistant/Infrastructure/Tool/`" sera ajouté en STEP-12 quand le pont sera créé : layers contexte-spécifiques `AssistantContext` / `ToolContext` + une exception layer `AssistantToolBridge` pour le seul fichier adapter.
- Voir [`contexts/tool.md`](../contexts/tool.md) pour le détail du modèle Tool.
- Voir [`architecture.md`](../architecture.md) pour le tableau des contextes prévus.
