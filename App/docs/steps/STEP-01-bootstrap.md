# STEP-01 — Bootstrap projet

## But

Créer un nouveau socle SebCode from scratch dans `App/`, en PHP 8.4 et Symfony 8.1, sans réintroduire l'ancien code supprimé.

Le résultat attendu de ce step est limité au bootstrap : Composer minimal, autoload PSR-4, application Symfony Console versionnée, configuration locale, PHPUnit, PHPStan et PHP-CS-Fixer.

## Décisions clés

- Repartir de zéro comme demandé dans `PROMPT_MASTER_SEBCODE_OPENCODE_PHP_SECURE_LOCAL.md`.
- Cibler `PHP ^8.4` et `Symfony 8.1.*`.
- Suivre la documentation Symfony 8.1 : pour une application console/microservice, démarrer sans `--webapp` et n'ajouter que `symfony/console` au runtime de ce step.
- Garder Symfony AI / Agent / Ollama et Symfony UI/TUI comme cible documentée, mais ne pas les installer avant le step qui les utilise.
- Garder le MVP console-first : pas de contrôleur HTTP, pas d'API locale et pas de serveur applicatif obligatoire.
- Ne pas installer `symfony/http-client` au bootstrap ; il sera ajouté uniquement avec le provider Ollama si nécessaire.

## Fichiers touchés

- Ajoutés : `App/composer.json`, `App/bin/sebcode`, `App/config/sebcode.yaml`.
- Ajoutés : `App/src/ConsoleApplicationBuilder.php`, `App/src/UI/Console/AboutCommand.php`.
- Ajoutés : `App/tests/Unit/ConsoleApplicationBuilderTest.php`, `App/tests/bootstrap.php`, `App/phpunit.dist.xml`, `App/phpstan.dist.neon`, `App/.php-cs-fixer.dist.php`.
- Ajoutés : `App/README.md`, `App/AGENTS.md`, `App/.gitignore`.
- Ajouté : `App/docs/steps/STEP-01-bootstrap.md`.
- Ajouté : `App/composer.lock` généré dans le container PHP 8.4.
- Modifiés : `CLAUDE.md`, `PROMPT_MASTER_SEBCODE_OPENCODE_PHP_SECURE_LOCAL.md` pour préciser PHP 8.4, Symfony 8.1, Symfony AI/UI/TUI, le mode console-first et l'installation progressive des dépendances.
- Modifié : `docker-compose.yml` pour retirer le service `nginx` et garder un runtime console-first.

## Comment vérifier

```bash
cd App
composer validate --strict
composer install
php bin/sebcode --version
composer test:unit
composer stan
composer cs
```

Avec Docker PHP 8.4 :

```bash
docker compose run --rm php composer install
docker compose run --rm php php bin/sebcode --version
docker compose run --rm php composer test:unit
docker compose run --rm php composer stan
docker compose run --rm php composer cs
```

## Résultat

- Documentation Symfony consultée : Symfony 8.1 demande PHP 8.4+ et recommande `symfony new ... --version="8.1.x-dev"` sans `--webapp` pour une application console/microservice/API.
- Documentation Symfony Console consultée : le composant autonome s'installe avec `composer require symfony/console` et l'application enregistre les commandes avec `addCommand()`.
- `docker compose run --rm php composer validate --strict` : OK.
- `docker compose run --rm php composer install` : OK, lock minimal généré.
- `docker compose run --rm php php bin/sebcode --version` : OK, affiche `SebCode 0.1.0-dev`.
- `docker compose run --rm php composer qa` : OK, CS Fixer, PHPStan et PHPUnit passent.
- Ancien container `sebcode_nginx` supprimé via `docker compose up -d --remove-orphans postgres redis` après retrait du service HTTP.

## Risques restants

- Le bootstrap ne contient pas encore de kernel Symfony complet, de DI applicative, de Symfony AI ni de TUI : ces dépendances seront ajoutées au moment du step fonctionnel correspondant.
- Les tests couvrent seulement le démarrage console minimal ; la sécurité workspace commence au step suivant.

## Step suivante

`STEP-02-security-core.md` : implémenter `WorkspaceGuard`, `PathNormalizer`, `IgnoreMatcher`, `SecretRedactor`, `NetworkPolicy` et les tests sécurité.
