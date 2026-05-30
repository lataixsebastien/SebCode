# ADR-0001 — Architecture hexagonale + DDD avec dossiers par Bounded Context

**Date :** 2026-05-30
**Statut :** Accepté
**Step lié :** STEP-01, STEP-02

## Contexte

Le projet SebCode réimplémente opencode (agent IA de coding, ~25 modules TS) sur Symfony 8.1. C'est un projet à durée de vie longue (plusieurs années espérées), qui va accumuler de la complexité (LLM, tools, agents, MCP, sub-agents, snapshot, …). On veut éviter le "fat controller" Symfony classique où la logique métier finit noyée dans `App\Controller\` et `App\Entity\`.

## Décision

On adopte une **architecture hexagonale** (ports & adapters) avec **DDD tactique** :

- Un dossier par **Bounded Context** dans `App/src/<Context>/`.
- Quatre layers fixes par contexte : `Domain/`, `Application/`, `Infrastructure/`, `UI/`.
- Le **Domain** est en **pur PHP** — aucune dépendance Symfony / Doctrine / lib externe.
- L'**Application** ne dépend que du Domain (du même contexte).
- L'**Infrastructure** implémente les Ports déclarés dans `Domain/Port/` et peut utiliser n'importe quelle lib.
- L'**UI** appelle l'Application (via Handlers autowired) et ne touche jamais directement à l'Infrastructure.

## Conséquences

### Positives

- **Testabilité** : le Domain et l'Application se testent en pur PHPUnit, sans Postgres ni Ollama. Tests rapides, isolés.
- **Portabilité** : on peut changer Doctrine → autre ORM, Ollama → OpenAI, sans toucher au Domain.
- **Clarté** : un nouveau contributeur sait immédiatement où trouver le code métier (`Domain/`) versus l'infra (`Infrastructure/`).
- **Évolutivité** : ajouter un contexte (`Tool/`, `Agent/`, `Mcp/`) sans risque de pollution croisée.

### Négatives

- **Plus de fichiers** que pour une approche "all-in-one Symfony". Chaque use case = un Command + un Handler. Chaque entity Doctrine est dédoublée par un Aggregate Domain.
- **Mapping Aggregate ↔ Entity Doctrine** à écrire à la main dans le Repository (pas d'auto-mapping). Coût de maintenance modéré.
- **Discipline requise** : facile d'oublier la règle "pas de Symfony dans Domain". Un `grep -RE "^use (Symfony|Doctrine)" src/*/Domain/` doit toujours retourner vide ; à terme un Deptrac le fera en CI.

## Alternatives considérées

### A1 — Layout Symfony standard (Controller/Entity/Service)

❌ Rejetée. La logique métier finit éclatée dans Controllers (validation), Entity (calculs), Service (orchestration), Repository (filtrage). Pas de barrière claire. Difficile à tester sans booter Symfony.

### A2 — Mono-contexte (un seul `src/Domain`, `src/Application`, etc.)

❌ Rejetée. On sait qu'on aura plusieurs contextes (Assistant, Tool, Agent…). Les regrouper par layer dilue la cohérence métier et complique l'orientation dans le code. Le découpage par contexte d'abord, layer ensuite, garde chaque feature regroupée.

### A3 — Hexa stricte avec un package Composer par contexte

❌ Rejetée pour l'instant. Sur-engineering : on n'a pas besoin de versionner les contextes indépendamment. À reconsidérer si un contexte devient une lib réutilisée par un autre projet.

## Notes d'application

- Voir [`architecture.md`](../architecture.md) pour le layout détaillé.
- Voir [`CLAUDE.md`](../../../CLAUDE.md) §3 pour les règles que Claude Code doit respecter.
