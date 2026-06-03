# STEP-21 — Prompt système (rôle d'agent de code + consignes outils)

**Statut :** ✅ terminé (`composer qa` vert)
**Branche :** `feat/sebcode-foundation`

## But

Jusqu'ici le LLM ne recevait **que l'historique persisté** — aucun prompt système. Les petits modèles
locaux (qwen2.5:3b/7b) utilisaient donc les outils « à l'aveugle ». On ajoute un **prompt système
fondateur** : rôle d'agent de code, contexte du workspace, et consignes d'usage des outils. C'est
l'amélioration **locale** la plus rentable et la moins risquée — elle fait mieux fonctionner *tous* les
outils, sans nouvelle surface.

## Décisions clés

| Décision | Choix | Raison |
|---|---|---|
| Où vit le texte | Port Domain `SystemPrompt` + impl Infra `SebCodeSystemPrompt` | La formulation + les faits d'environnement (root, OS) restent en Infra ; la boucle (Application) reste pure |
| Quand l'injecter | **Préfixé à la conversation au moment de l'appel LLM**, à chaque tour | Toujours en tête, sans dépendre de l'historique |
| Persistance | **Jamais persisté** : message System éphémère, id sentinelle `msg_system_prompt`, ne consomme pas le générateur d'ids | Évite la pollution/duplication de l'historique stocké |
| Langue | **Anglais** | Plus fiable pour les modèles que le français (cf. conventions code en anglais) |
| Contenu | Rôle + workspace root + OS + liste concise des outils + règles (read-before-edit, edit/apply_patch plutôt que réécrire, bash pour le terminal seulement, todowrite pour le multi-étapes, ne pas inventer de chemins, vérifier avant de conclure, être concis) | Adapté (condensé) du prompt d'opencode pour petits modèles |

## Layout produit

### Nouveau

```
src/Assistant/Domain/Port/SystemPrompt.php                  (interface : text(): string)
src/Assistant/Infrastructure/Prompt/SebCodeSystemPrompt.php (prompt + root + PHP_OS_FAMILY)
tests/Support/Assistant/Doubles/FixedSystemPrompt.php       (double : texte fixe)
```

### Modifié

| Fichier | Changement |
|---|---|
| `Application/Command/SendMessageHandler.php` | nouvelle dépendance `SystemPrompt` ; préfixe un message System éphémère à `$conversation` avant chaque `llm->complete()` |
| `config/services.yaml` | alias `SystemPrompt` → `SebCodeSystemPrompt` ; `$projectRoot: '%kernel.project_dir%'` |
| `tests/.../SendMessageHandlerTest.php` | setUp câble le double ; 2 tests de conversation décalés (prompt en tête) ; +1 test « préfixé mais jamais persisté » |

## Comment vérifier

```bash
docker compose exec php composer qa     # cs 0 / phpstan max 0 / deptrac 0 / phpunit vert (257 tests, 537 assertions)

# Non-régression e2e (le round-trip Ollama supporte le message system en tête) :
docker compose exec php bin/console assistant:ask -m qwen2.5:3b "Liste les fichiers .md à la racine avec glob."
```

## Step suivante

Pistes locales restantes : `task` (sous-agent), `lsp` (diagnostics), persistance Postgres
(permissions/todos), ou la boucle agentique **async** (streaming + prompt TUI par widget).
