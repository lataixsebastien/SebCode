# AGENTS.md

SebCode agents must stay local-only and console-first.

- Use PHP 8.4 and Symfony 8.1 components.
- Prefer Symfony AI, Symfony Agent and Symfony Ollama integrations before writing custom LLM orchestration.
- Add AI/TUI dependencies only in the dedicated implementation step, not in the bootstrap.
- Do not add HTTP controllers or an API server for MVP features.
- Route user-facing interactions through Symfony Console and Symfony UI/TUI.
- Keep network access restricted to local Ollama transports.
