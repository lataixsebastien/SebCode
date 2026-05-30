# STEP-05 — Infrastructure : Ollama LLM adapter

**Statut :** ✅ terminé
**Branche :** `feat/sebcode-foundation`

## But

Implémenter le Port `LlmPort` côté Infrastructure au-dessus de la stack Symfony AI :

- `symfony/ai-platform` — interface neutre `PlatformInterface` (model-agnostic).
- `symfony/ai-ollama-platform` — bridge concret qui parle à un serveur Ollama HTTP (`/api/chat`, `/api/generate`).
- `symfony/ai-bundle` — expose la Platform Ollama comme service Symfony `ai.platform.ollama` configurable via YAML.

À la fin de la step, on peut résoudre `App\Assistant\Domain\Port\LlmPort` via DI et l'utiliser pour générer une réponse.

## Décisions clés

| Décision | Choix | Raison |
|---|---|---|
| Provider par défaut | Ollama (local) | Gratuit, offline-capable, déjà tournant côté user (`tg_ollama` expose `:11434`). Bundle multi-provider en place — switch trivial (anthropic, openai, etc.) en STEP ultérieur sans toucher au Domain/Application. |
| Modèle par défaut | `qwen2.5:3b` | Petit (1.9 GB), rapide en dev, supporte tool calls. Vérifié présent localement via `/api/tags`. |
| Translation roles | `match` exhaustif sur `MessageRole` | `User`/`Assistant`/`System` mappés sur les classes équivalentes de `Symfony\AI\Platform\Message`. `Tool` lève `LlmUnavailable` jusqu'à ce qu'on ait le contexte `Tool/`. |
| `MessageContent` → `Content` | `new Text($text)` pour User/Assistant ; string brut pour SystemMessage | Conforme aux signatures des constructeurs upstream (`UserMessage(ContentInterface ...$content)`, `SystemMessage(string\|Template $content)`). |
| Extraction tokens | Via `$result->getMetadata()->get('token_usage')` | C'est la convention upstream — `DeferredResult` merge le `TokenUsage` extrait par `OllamaResultConverter::getTokenUsageExtractor()` dans le metadata sous la clé `'token_usage'`. |
| Gestion erreurs | `try { invoke; getResult } catch (\Throwable) -> LlmUnavailable::fromUpstream` | Filet large — n'importe quelle erreur réseau, modèle absent, 5xx serveur, désérialisation, etc. est remontée comme `LlmUnavailable` au Domain. `Application/SendMessageHandler` la propage et **garde le message user persisté** pour permettre le retry. |
| Smoke test end-to-end | **Reporté en STEP-06** | Pour smoker, il faut une UI qui consomme `LlmPort`. La CLI `assistant:ask` (STEP-06) sera l'occasion de valider en réel. Les 4 tests unitaires couvrent la logique de translation. |

## Layout produit

```
App/src/Assistant/Infrastructure/Llm/
└── SymfonyAiOllamaAdapter.php          (final readonly class, implements LlmPort)

App/tests/Unit/Assistant/Infrastructure/Llm/
└── SymfonyAiOllamaAdapterTest.php       (4 tests : translation, tokens, errors, Tool refus)
```

## Modifications config

### `App/.env`

Ajout du bloc Ollama :

```dotenv
###> symfony/ai-ollama-platform ###
OLLAMA_ENDPOINT="http://host.docker.internal:11434"
OLLAMA_HTTP_TIMEOUT=600
LLM_MODEL=qwen2.5:3b
###< symfony/ai-ollama-platform ###
```

### `App/config/packages/ai_ollama_platform.yaml`

```yaml
ai:
    platform:
        ollama:
            endpoint: '%env(OLLAMA_ENDPOINT)%'
```

### `App/config/packages/framework.yaml`

Bloc `http_client.default_options.timeout` ajouté (utilise `OLLAMA_HTTP_TIMEOUT`) — pour que le client HTTP partagé puisse attendre les réponses LLM longues sans abandonner.

### `App/config/services.yaml`

Bind `LlmPort → SymfonyAiOllamaAdapter` et injection explicite du service `@ai.platform.ollama` (créé par le bundle AI à partir de la config YAML ci-dessus).

## Pipeline du complete

```
SymfonyAiOllamaAdapter::complete(ModelName, list<Message>) : LlmReply
    │
    ├─ toMessageBag(conversation)
    │     System → SystemMessage(text)
    │     User → UserMessage(new Text(text))
    │     Assistant → AssistantMessage(new Text(text))
    │     Tool → throw LlmUnavailable
    │
    ├─ try {
    │     deferred = platform.invoke(model.value, bag)
    │     result = deferred.getResult()    ← peut bloquer (HTTP call)
    │  } catch (Throwable e) {
    │     throw LlmUnavailable::fromUpstream(e.message, e)
    │  }
    │
    ├─ if (!result instanceof TextResult) throw LlmUnavailable
    │
    ├─ tokenUsage = result.getMetadata().get('token_usage') ?? null
    │
    └─ return new LlmReply(result.getContent(), $tokenUsage?->getPromptTokens(),
                                                $tokenUsage?->getCompletionTokens())
```

## Comment vérifier

```bash
# Ollama dispo + modèle pull
curl -s http://host.docker.internal:11434/api/tags | jq '.models[].name'
# attendu : doit lister "qwen2.5:3b"

# Cache symfony OK avec la nouvelle config
docker compose exec php rm -rf var/cache/dev
docker compose exec php bin/console lint:container
# → [OK] The container was linted successfully

# Suite unit
docker compose exec php vendor/bin/phpunit --testsuite Unit
# → OK (49 tests, 134 assertions)
```

## Step suivante

**STEP-06** — `Assistant/UI/Cli/AskCommand` + `Assistant/UI/Tui/TuiCommand`. C'est là qu'on va vraiment voir l'agent répondre : une commande console `assistant:ask "question"` qui :
1. Démarre une session (`StartSessionHandler`).
2. Envoie le message user (`SendMessageHandler` → `LlmPort` → Ollama).
3. Imprime la réponse + tokens.

Et `assistant:tui` qui ouvre une interface Symfony TUI interactive.
