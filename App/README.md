# SebCode

SebCode is a console-first local coding agent prototype built with PHP 8.4 and Symfony Console 8.1.

The MVP intentionally avoids an HTTP application surface. Product entry points are Symfony Console commands and, later, Symfony UI/TUI screens. Ollama local HTTP remains the only tolerated transport when the LLM backend requires it.

## Bootstrap Commands

```bash
docker compose run --rm php composer install
docker compose run --rm php php bin/sebcode --version
docker compose run --rm php composer qa
```
