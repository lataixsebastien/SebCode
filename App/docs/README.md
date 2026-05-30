# Documentation SebCode

> Réimplémentation d'**opencode** (agent IA de coding terminal) sur **Symfony 8.1 + Symfony AI + Symfony TUI**, en architecture **hexagonale + DDD**.

## Plan de la doc

| Document | À quoi ça sert |
|---|---|
| [`architecture.md`](architecture.md) | Vue d'ensemble : layers hexa, règles de dépendance, layout par bounded context |
| [`contexts/`](contexts/) | Une fiche par bounded context (Assistant, Tool, Agent, …) — aggregates, VOs, ports, use cases, adapters |
| [`adr/`](adr/) | Architecture Decision Records — pourquoi tel choix d'archi a été retenu |
| [`runbooks/`](runbooks/) | Procédures opérationnelles (démarrage stack, troubleshoot Ollama, etc.) |
| [`steps/`](steps/) | Journal chronologique des steps d'implémentation (STEP-01, STEP-02, …) |

## Quickstart

```bash
# 1. Stack Docker (php 8.4, nginx, postgres 16, redis 7)
docker compose up -d

# 2. Deps + DB
docker compose exec php composer install
docker compose exec php bin/console doctrine:migrations:migrate -n   # à partir de STEP-04

# 3. Tests
docker compose exec php vendor/bin/phpunit --testsuite Unit

# 4. CLI / TUI (à partir de STEP-05)
docker compose exec php bin/console assistant:ask "Explique-moi la stack"
docker compose exec php bin/console assistant:tui
```

## Pour Claude Code

Lire en priorité `../../CLAUDE.md` à la racine du repo — il contient les règles projet, les invariants d'archi et le workflow attendu.
