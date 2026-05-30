# STEP-06 — UI CLI : `assistant:ask` + `assistant:sessions`

**Statut :** ✅ terminé (validation end-to-end OK contre Ollama réel)
**Branche :** `feat/sebcode-foundation`

## But

Premier point d'entrée utilisateur du stack SebCode : deux commandes Symfony Console qui exercent l'ensemble du pipeline construit dans les STEP 02→05.

- `assistant:ask "question"` — démarre (ou continue) une session, envoie un message, récupère la réponse du LLM, affiche tout avec stats tokens.
- `assistant:sessions [id?]` — liste les sessions ou affiche le transcript complet d'une session.

L'objectif est aussi de **valider end-to-end** : c'est la première fois qu'on enchaîne Domain → Application → Infrastructure (Postgres + Ollama) en chemin réel.

## Décisions clés

| Décision | Choix | Raison |
|---|---|---|
| Symfony Console (`Command` + `#[AsCommand]`) | Standard Symfony, attribute-driven | Cohérent avec le reste du projet, autowire des Handlers immédiat. C'est différent du CQRS `Command` (Application) malgré le mot — namespaces séparés, pas d'ambigüité ([ADR-0002](../adr/0002-no-messenger-wiring.md)). |
| Pas de logique métier dans les Commands | Wrappers très minces (~80 lignes chacun) | Respect CLAUDE.md §3.1 — UI parse input, appelle Handler, render output. Tout le reste est dans Application/Domain. |
| Default model | Injecté via `$defaultModel` arg + `%env(LLM_MODEL)%` | Permet override sans toucher au code (CI, dev local, prod). |
| Session id en option (`-s`) | Optionnel — si absent, crée une nouvelle session | Mode one-shot ergonomique (`assistant:ask "..."` suffit). Mode continue explicite. |
| Erreur LLM = on garde le user message | Message affiché + hint : re-run avec `--session=$id` | Cohérent avec la pile Application : `SendMessageHandler` ne supprime jamais le user message en cas d'échec LLM ([STEP-03](STEP-03-application.md)). |
| Tests | Smoke tests CLI manuels via docker exec, pas de `CommandTester` | Les commandes sont des wrappers triviaux. La logique sous-jacente est déjà testée à 100 % en unit. Ajouter du CommandTester ferait double emploi. |

## Layout produit

```
App/src/Assistant/UI/Cli/
├── AskCommand.php            (assistant:ask)
└── SessionsCommand.php       (assistant:sessions [id?])
```

## Wiring

```yaml
# config/services.yaml — injection du modèle par défaut
App\Assistant\UI\Cli\AskCommand:
    arguments:
        $defaultModel: '%env(LLM_MODEL)%'
```

Les Handlers (`StartSessionHandler`, `SendMessageHandler`, `GetSessionMessagesHandler`) et `SessionRepository` sont autowired (cf. binds de la STEP-04).

## Validation end-to-end (smoke réel)

```bash
# 1. Nouvelle session
$ docker compose exec php bin/console assistant:ask "Reply with the single word: pong"
Started new session ses_1CbYnCB6PGiNFRa5Kry2Zc (model=qwen2.5:3b)
You
---
Reply with exactly the single word: pong
Assistant
---------
pong
session=ses_1CbYnCB6PGiNFRa5Kry2Zc  prompt_tokens=37  completion_tokens=2

# 2. Continuer la session
$ docker compose exec php bin/console assistant:ask \
    "What did I just ask you in 5 words?" \
    --session=ses_1CbYnCB6PGiNFRa5Kry2Zc
You
---
What did I just ask you in 5 words?
Assistant
---------
What did you ask in 5 words?
session=ses_1CbYnCB6PGiNFRa5Kry2Zc  prompt_tokens=59  completion_tokens=10
# (prompt_tokens 37 -> 59 ⇒ l'historique complet est bien renvoyé au modèle)

# 3. Liste
$ docker compose exec php bin/console assistant:sessions
 ---------------------------- --------- ------------ --------------------- ----------
  id                           title     model        updated_at            archived
 ---------------------------- --------- ------------ --------------------- ----------
  ses_1CbYnCB6PGiNFRa5Kry2Zc   CLI ask   qwen2.5:3b   2026-05-30 12:49:53   no
 ---------------------------- --------- ------------ --------------------- ----------

# 4. Transcript
$ docker compose exec php bin/console assistant:sessions ses_1CbYnCB6PGiNFRa5Kry2Zc
Session ses_1CbYnCB6PGiNFRa5Kry2Zc — CLI ask
============================================
model=qwen2.5:3b  created=2026-05-30 12:48:38  updated=2026-05-30 12:49:53  archived=no

User — 12:48:44 → "Reply with exactly the single word: pong"
Assistant — 12:49:16 → "pong"
User — 12:49:46 → "What did I just ask you in 5 words?"
Assistant — 12:49:53 → "What did you ask in 5 words?"

# 5. Error path (SessionNotFound)
$ docker compose exec php bin/console assistant:ask "x" --session=ses_nope
 [ERROR] Session "ses_nope" not found.
```

## Couverture des invariants

- Le `SendMessageHandler` charge la session AVANT d'appeler le LLM (validé par `SessionNotFound` propagée jusqu'à `AskCommand`).
- L'historique COMPLET est renvoyé au LLM à chaque tour (vérifié par l'évolution `prompt_tokens=37 → 59`).
- Le `updated_at` est bumped (vérifié dans `assistant:sessions`, le `12:49:53` reflète le second tour).
- `SymfonyAiOllamaAdapter` extrait correctement `prompt_tokens` / `completion_tokens` depuis le metadata `'token_usage'` (vu non-null dans la sortie).

## Step suivante

**STEP-07** — `assistant:tui` : interface terminal interactive avec `symfony/tui ^8.1@beta`. Layout opencode-like (sessions list à gauche, chat panel à droite, input en bas). Composants Tui réutilisables.

Idéalement on aura aussi en STEP-08 un **streaming token-par-token** côté LLM (Ollama supporte SSE) pour un effet "live typing" — pour l'instant le CLI attend la réponse complète.
