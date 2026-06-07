# STEP-28 — Console chat REPL (`assistant:chat`)

## But

Avoir une **console interactive qui marche vraiment**, type opencode, dans
l'environnement réel de l'utilisateur (**Windows + `docker compose exec`**).

Le TUI (`assistant:tui`, STEP-07/27) passe le terminal en **mode raw**
(`stty raw -echo` + watcher STDIN Revolt). Or `docker compose exec -it` lancé
depuis **PowerShell** ne transmet pas les frappes en mode raw : le TUI s'affiche
mais ne reçoit jamais l'input → « rien ne part, aucune réponse ». C'est une
limite d'environnement, pas un bug du code.

## Décisions clés

- **Nouvelle commande `assistant:chat`** = boucle de chat **ligne-par-ligne**
  (`fgets(\STDIN)`, mode *cooked*). Pas de mode raw → fonctionne dans **tout**
  terminal, y compris docker-exec sous PowerShell.
- **Réutilisation totale** de la plomberie existante : `StartSessionHandler`,
  `SendMessageHandler` (qui streame déjà via `AgentOutputStreamRegistry`),
  `ConsolePermissionConsole`, `CliAgentOutputStream`. La commande n'ajoute que la
  boucle read-eval.
- Le TUI **reste** en place (cible quand l'env le permet : vrai terminal / Linux,
  ou plus tard piste #2 du ROADMAP « TUI async »). `assistant:chat` est la voie
  robuste par défaut côté Windows/Docker.
- **Sortie de boucle** : `/exit`, `/quit`, `:q`, `exit`, `quit`, ou EOF
  (Ctrl+D / Ctrl+Z+Entrée). Erreurs `LlmUnavailable` / `AgentLoopExceeded`
  signalées sans casser la boucle ; `SessionNotFound` arrête proprement.

## Fichiers touchés

- **Ajouté** : `App/src/Assistant/UI/Cli/ChatCommand.php`
- **Modifié** : `App/config/services.yaml` (binding `$defaultModel` pour `ChatCommand`)
- **Ajouté** : `App/docs/steps/STEP-28-console-chat.md` (ce fichier)

## Comment vérifier

```bash
# Lancer la console (interactif). Depuis Windows : utiliser Windows Terminal.
docker compose exec -it php bin/console assistant:chat

# Reprendre une session existante
docker compose exec -it php bin/console assistant:chat --session=ses_xxx

# Modèle au choix (qwen2.5:7b = bien plus propre que 3b sur le tool-calling)
docker compose exec -it php bin/console assistant:chat -m qwen2.5:7b

# Smoke-test non-interactif (mécanique de la boucle)
printf 'dis bonjour\n/exit\n' | docker compose exec -T php bin/console assistant:chat

# QA
docker compose exec -T php vendor/bin/php-cs-fixer fix --dry-run src/Assistant/UI/Cli/ChatCommand.php
docker compose exec -T php vendor/bin/phpstan analyse src/Assistant/UI/Cli/ChatCommand.php -c phpstan.dist.neon
```

Validé : boucle OK (enchaîne les tours, streame la réponse, `/exit` propre),
php-cs-fixer 0 changement, PHPStan max 0 erreur.

## Notes

- **Modèle** : `qwen2.5:3b` (défaut actuel) **hallucine des tool calls** et peut
  donc écrire/éditer des fichiers **dans le workspace** (la règle
  `edit:* → allow` les auto-autorise, sandboxées par `WorkspacePath`, sémantique
  opencode). Pour un usage propre, préférer `qwen2.5:7b`. Le « gros » modèle
  viendra sur une machine plus puissante.
- **Blocage rendu** : pendant la génération LLM, la boucle est synchrone (figement
  de quelques secondes). Async = piste #2 du `ROADMAP.md`.

## Correctif annexe — chemins absolus (WorkspacePath)

Découvert en testant `assistant:chat` avec `qwen2.5:3b` : le modèle émet souvent
un **chemin absolu** (`/var/www/App/resume.md`) au lieu du relatif attendu.
`WorkspacePath::resolveForWrite()` traitait toute entrée comme relative → le `/`
initial était ignoré et les segments **re-collés à la racine** :
`/var/www/App/resume.md` → `/var/www/App/var/www/App/resume.md` (fichier introuvable
« à la racine »).

Fix (indépendant du modèle, parité opencode qui accepte l'absolu dans le cwd) :
- `resolveForWrite()` réduit un chemin absolu **dans** le workspace à son reste ;
  un absolu **hors** workspace est rejeté (`PathNotAllowed::outside`).
- `relativePattern()` devient **root-aware** → affichage/permission cohérents
  (`resume.md`, plus `var/www/App/resume.md`).
- Appelants mis à jour : `WriteTool`, `EditTool`, `ApplyPatchTool` (×2).
- Test : `App/tests/Unit/Tool/Domain/Service/WorkspacePathTest.php` (relatif,
  absolu-dans-workspace **non doublé**, absolu-hors-workspace rejeté, traversal).

Fichiers : `App/src/Tool/Domain/Service/WorkspacePath.php`,
`App/src/Tool/Infrastructure/Tool/{WriteTool,EditTool,ApplyPatchTool}.php`,
`App/tests/Unit/Tool/Domain/Service/WorkspacePathTest.php`.

## Correctif annexe — tokens en streaming (`prompt=? completion=?`)

Le chat affichait `tokens: prompt=? completion=?`. Cause : la plateforme
`symfony/ai` possède un `TokenUsage\StreamListener` qui **sort** le delta
`TokenUsage` du flux (`skipDelta()`) pour le ranger dans les **métadonnées** du
résultat. Notre `SymfonyAiOllamaAdapter::completeStreaming()` ne matchait donc
jamais un delta `TokenUsage`.

Fix : après avoir drainé le stream, lire `token_usage` via
`$deferred->getMetadata()->get('token_usage')` (comme le fait déjà le chemin
non-streaming). Résultat : `tokens: prompt=1777 completion=12` (le prompt est
gros car il inclut le system prompt + les 9 descriptions d'outils).

Fichier : `App/src/Assistant/Infrastructure/Llm/SymfonyAiOllamaAdapter.php`.

## Ajout — interrompre une réponse (Entrée)

Besoin : stopper une génération qui part mal. La **touche Échap** n'est pas
retenue : elle exige le mode raw (le piège Windows/docker qu'on évite justement).
Mécanisme robuste en mode *cooked* : pendant la génération, **appuyer sur Entrée**
→ la réponse en cours s'arrête, on revient au prompt. Actif **uniquement sur un
vrai TTY** (`stream_isatty(\STDIN)`) — un run piped/`ask` ne s'auto-interrompt
jamais.

Câblage (pattern existant `AgentOutputStream` + callable `onText`) :
- `AgentOutputStream::isInterrupted(): bool` (Null/Tui/Fake → `false`).
- `CliAgentOutputStream` : flag `interruptible` + sondage non-bloquant de STDIN
  via `stream_select(..., 0)` ; draine la ligne pour ne pas la relire en prompt.
- `LlmPort::completeStreaming(..., ?callable $shouldStop = null)` ; l'adapter
  rompt le `foreach` du stream dès que `$shouldStop()` est vrai.
- `SendMessageHandler` : passe `fn() => $stream->isInterrupted()` ; si interrompu,
  persiste le texte partiel, saute les tool calls, termine la boucle.
- `SendMessageResult::$interrupted` ; `ChatCommand` affiche `⏹ interrupted` et
  active l'interruption selon `stream_isatty(\STDIN)`.
- Test : `SendMessageHandlerTest::testUserInterruptStopsTheLoopBeforeRunningToolCalls`.

Fichiers : `AgentOutputStream.php`, `NullAgentOutputStream.php`,
`TuiAgentOutputStream.php`, `CliAgentOutputStream.php`, `LlmPort.php`,
`SymfonyAiOllamaAdapter.php`, `SendMessageHandler.php`, `SendMessageResult.php`,
`ChatCommand.php`, doubles de test (`FakeAgentOutputStream`, `ScriptedLlm`).

## Ajout — rendu console stylé (look opencode)

Le REPL était trop brut (« on ne voit rien / aucun style »). Restyling de
`CliAgentOutputStream` + `ChatCommand`, sans mode raw (toujours robuste) :
- Bannière `╭─ SebCode · <model> ─╮` + ligne d'aide.
- Libellés colorés `● You` (cyan) / `● Assistant` (vert).
- Étapes d'outils encadrées et lisibles : `│ ⚙ <tool>  <arg utile>` puis
  `│   ✓ <tool> → <résumé>` (✓ vert / ⚠ rouge). L'argument affiché est le champ
  porteur de sens (`filePath`/`path`/`pattern`/`command`…) plutôt que du JSON brut.
- Indicateur d'activité `⏳ réflexion…` pendant le chargement/inférence du modèle
  (sinon ça semblait figé), effacé en place dès le 1er token.
- Ligne d'usage `⏱ <in> tok in · <out> tok out`.

Couleurs via le formatter Symfony (strippées si sortie non décorée) ; le seul ANSI
écrit à la main (effacement de l'indicateur) est gardé par `isDecorated()`, donc
les runs piped/`ask` restent propres.

> Le rendu **plein écran** fidèle opencode reste `assistant:tui` (bloqué par la
> saisie clavier sous docker-exec/PowerShell — cf. début de ce step). La console
> stylée est le meilleur compromis robuste dans cet environnement.

Fichiers : `App/src/Assistant/UI/Cli/CliAgentOutputStream.php`,
`App/src/Assistant/UI/Cli/ChatCommand.php`.

## Step suivante

Au choix dans `App/docs/ROADMAP.md` — candidat naturel : rendre l'I/O async
(piste #2) pour fluidifier aussi bien `assistant:chat` que le TUI.
