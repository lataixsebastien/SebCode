# STEP-08 — QA : PHPStan max, php-cs-fixer, Deptrac

**Statut :** ✅ terminé (`composer qa` passe en vert end-to-end)
**Branche :** `feat/sebcode-foundation`

## But

Mettre en place le harness qualité qui va garantir, à chaque commit, que :

1. **Le typage est strict et propre** (PHPStan level max).
2. **Le style est cohérent** (php-cs-fixer PSR-12 + @Symfony + risky).
3. **Les frontières d'architecture hexa/DDD sont respectées** (Deptrac) — c'est ça le vrai filet de sécurité du projet sur le long terme : impossible (sans casser la CI) qu'un `use Symfony\…` se glisse dans `Domain/`.

## Décisions clés

| Décision | Choix | Raison |
|---|---|---|
| PHPStan level | `max` (=10) sur `src/` + `tests/` | Strictest type-checking. Pour un projet à durée de vie longue, c'est l'investissement initial qui paie. |
| cs-fixer ruleset | `@PSR12` + `@PHP83Migration` + `@Symfony` + `@Symfony:risky` | Standard de la communauté Symfony. **PHP83** (pas PHP84) car `@PHP84Migration` rewrite `(new Foo())->bar()` en `new Foo()->bar()` que la version courante de Deptrac (parser bundlé) ne supporte pas encore. |
| Deptrac layers | 4 layers hexa par contexte (Domain/Application/Infrastructure/UI) + 3 layers vendors (Symfony/Doctrine/AiPlatform) | Encode directement les règles de [CLAUDE.md §3.2](../../../CLAUDE.md). Domain = 0 dep ; Application = Domain seul ; Infrastructure = libre ; UI = Application+Domain+Symfony, **jamais Infrastructure**. |
| Scripts composer | `composer qa` chaîne cs → stan → deptrac → test:unit | Une commande unique pour tout valider. Compatible CI. |
| Tests dans la suite QA | Unit seulement (`test:unit`) | L'intégration (Postgres + Ollama) demande un environnement runtime, à brancher sur la CI plus tard. |

## Configurations produites

### `App/phpstan.dist.neon`

```neon
parameters:
    level: max
    paths: [src, tests]
    bootstrapFiles: [vendor/autoload.php]
    excludePaths: [var/cache/*, vendor/*]
```

### `App/.php-cs-fixer.dist.php`

PSR-12 + PHP83 + Symfony + risky. Exclusions notables :
- Pas de `phpdoc_to_comment` (on garde les `/** @var */` pour PHPStan).
- `phpdoc_align: left`.
- `native_function_invocation` n'inclut que les fonctions optimisées par opcache (`@compiler_optimized`).

### `App/deptrac.yaml`

```yaml
ruleset:
    Domain: ~                                    # rien
    Application:    [Domain]
    Infrastructure: [Domain, Application, Symfony, Doctrine, AiPlatform]
    UI:             [Domain, Application, Symfony, AiPlatform]   # PAS Infrastructure
```

Layers définis par regex sur le FQN :
- `^App\\[^\\]+\\Domain\\.*$` (etc.) — match `App\Assistant\Domain\…`, `App\Tool\Domain\…`, etc. Le futur contexte `Tool/` tombera automatiquement sous les bonnes règles.

⚠️ **Ordre des layers vendors important** : `AiPlatform` (regex `^Symfony\\AI\\…`) doit être déclaré AVANT `Symfony` (regex `^Symfony\\…`) car Deptrac assigne au premier match. Sinon `Symfony\AI\…` serait classé en `Symfony` au lieu d'`AiPlatform` et la rule UI (qui interdit Infrastructure mais autorise AiPlatform pour les types AI) serait fausse.

### `App/composer.json` (scripts)

```json
"scripts": {
    "test":     "vendor/bin/phpunit",
    "test:unit":"vendor/bin/phpunit --testsuite Unit",
    "stan":     "vendor/bin/phpstan analyse -c phpstan.dist.neon --memory-limit=512M",
    "cs":       "vendor/bin/php-cs-fixer fix --dry-run --diff",
    "cs:fix":   "vendor/bin/php-cs-fixer fix",
    "deptrac":  "vendor/bin/deptrac analyse",
    "qa":       ["@cs","@stan","@deptrac","@test:unit"]
}
```

## Corrections appliquées au passage

PHPStan max a flaggé 17 vrais bugs/manques de typage. Tous corrigés :

| Fichier | Problème | Correction |
|---|---|---|
| `Domain/Model/ValueObject/ModelName.php` | Le constructor garantit non-vide mais le PHPDoc ne le reflète pas → PHPStan ne peut pas l'inférer | Ajout `/** @var non-empty-string */` sur la propriété |
| `Kernel.php` | `getAllowedEnvs()` flaggé "unused" alors qu'il est appelé par `MicroKernelTrait` via reflection | `// @phpstan-ignore method.unused` + commentaire explicatif |
| `UI/Cli/AskCommand.php`, `UI/Cli/SessionsCommand.php`, `UI/Tui/TuiCommand.php` | `$input->getArgument()` / `getOption()` retourne `mixed` → cast `(string)` cassait le typage | Remplacé par `assert(is_string($x))` qui informe PHPStan |
| `tests/Support/Assistant/Doubles/RecordingPlatform.php` | Test précédent utilisait une closure-factory anonyme dans le test, PHPStan ne pouvait pas inférer les types | Extracté en `final class RecordingPlatform implements PlatformInterface` réutilisable |
| `tests/Unit/Assistant/Infrastructure/Llm/SymfonyAiOllamaAdapterTest.php` | Conséquence du point précédent + signatures précises pour les anonymous classes restantes | Refactor + ajout types `RawResultInterface`, suppression `?` quand jamais null |
| `tests/bootstrap.php` | Idem CRLF + nouvelle syntaxe PHP 8.4 problématique pour Deptrac | `(new Dotenv())->bootEnv()` (parens explicites) |

## Comment vérifier

```bash
docker compose exec php composer qa
# → cs OK (0 file to fix)
# → stan OK (0 errors)
# → deptrac OK (0 violations / 219 allowed / 1 uncovered = Kernel.php OK)
# → phpunit OK (49 tests, 134 assertions)
```

Le "1 uncovered" Deptrac est `App\Kernel` (ne match aucun regex de contexte car n'a pas de namespace `App\<Context>\…`). C'est attendu — le Kernel est le bootstrap framework, pas un concept métier.

## Garde-fou hexa renforcé

Avant cette step, la règle "Domain ne dépend de rien d'autre que PHP" reposait sur la discipline manuelle + un `grep` ad-hoc. Maintenant Deptrac la **vérifie automatiquement à chaque `composer qa`**. Si quelqu'un ajoute `use Symfony\…` dans `App/Assistant/Domain/…`, Deptrac le bloque.

## Fin du foundation

C'est la dernière step du foundation `feat/sebcode-foundation`. Le projet a maintenant :

- ✅ Bounded context `Assistant` complet (Domain → Application → Infrastructure → UI CLI + TUI).
- ✅ End-to-end fonctionnel (chat avec Ollama via `assistant:ask`).
- ✅ Persistance Postgres via Doctrine.
- ✅ 49 tests unitaires.
- ✅ PHPStan max + cs-fixer + Deptrac en CI-ready.
- ✅ Docs détaillées (architecture, ADR, contexts, runbooks, steps).

Prochains chantiers possibles (à scoper après PR) :

- **Tool/** context — read/write/edit/shell/glob/grep/lsp/webfetch comme dans opencode. C'est le gros morceau.
- **Streaming LLM** — `LlmStreamPort` qui yield des deltas (Ollama SSE).
- **Agent/** context — boucle multi-tool autonome.
- **Sidebar TUI** — multi-session dans le TUI.
- **MCP** — bridge vers les serveurs MCP externes.
- **HTTP API + SSE** — pour drive la TUI web (`public/assistant-ui.html`).
- **Tests d'intégration** — Postgres + Ollama réels dans la CI.
