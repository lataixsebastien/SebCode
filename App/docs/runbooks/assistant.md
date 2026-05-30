# Assistant Runbook (LLM/Ollama)

## Demarrage fiable

1. Lancer la stack Docker:
   - `docker compose up -d`
2. Verifier la sante assistant:
   - `docker compose exec php php bin/console assistant:health --no-debug`

## Variables importantes

- `OLLAMA_ENDPOINT` (default: `http://host.docker.internal:11434`)
- `OLLAMA_HTTP_TIMEOUT` (default: `600`)
- `LLM_PLATFORM` (default: `ollama`)
- `LLM_MODEL` (default: `qwen2.5:3b`)

## Test API end-to-end

Endpoints:

- `GET /api/assistant/health`
- `POST /api/assistant/session`
- `POST /api/assistant/message`
- `POST /api/assistant/run-loop`
- `POST /api/assistant/run-stream` (SSE)
- `GET /api/assistant/session/{sessionId}/messages`

Recommandation UI:

- Demarrer avec `maxSteps=2` pour limiter la latence.
- Utiliser `run-stream` pour afficher la reponse en direct au lieu d'attendre la fin du loop.

## Symptomes frequents

- `ModelNotFoundException`: modele absent dans Ollama, adapter `LLM_MODEL`.
- `Invalid URL: scheme is missing`: endpoint Ollama invalide, corriger `OLLAMA_ENDPOINT`.
- `TimeoutException`: modele trop lent/charge, augmenter `OLLAMA_HTTP_TIMEOUT` ou choisir un modele plus leger.
