# ADR-0002 — Pas de wiring Symfony Messenger sur les Commands/Handlers Application

**Date :** 2026-05-30
**Statut :** Accepté
**Step lié :** STEP-03

## Contexte

L'Application layer (STEP-03) introduit des classes CQRS :

- `Application/Command/<X>Command.php` — DTO immutable d'intention de mutation.
- `Application/Command/<X>Handler.php` — classe invokable (`__invoke`) qui exécute la mutation.
- `Application/Query/<X>Query.php` + `<X>Handler.php` — équivalent côté lecture.

Symfony fournit un bundle Messenger avec attributs `#[AsMessage]`, `#[AsMessageHandler]`, etc. pour router automatiquement les Commands vers leurs Handlers via un bus.

Question : on les colle dès maintenant pour "se préparer" à l'async, ou on s'en passe ?

## Décision

**On ne colle pas** d'attribut Messenger sur les `Command`/`Handler` de l'Application layer. Les Handlers sont des classes Symfony standard, instanciées par autowire (`App\:` resource dans `services.yaml`) et appelées **directement** par la couche UI :

```php
// UI/Cli/AskCommand.php (Symfony Console)
public function __construct(
    private readonly StartSessionHandler $startSession,
    private readonly SendMessageHandler $sendMessage,
) {}

protected function execute(InputInterface $input, OutputInterface $output): int
{
    $sessionId = ($this->startSession)(new StartSessionCommand(...));
    $result = ($this->sendMessage)(new SendMessageCommand($sessionId, ...));
    // ...
}
```

## Conséquences

### Positives

- **Application reste indépendante de Messenger.** Aucun import `Symfony\Component\Messenger\…` dans `Application/` ; les tests unitaires n'ont rien à booter.
- **Moins de magie.** Pas de "qui handle quoi" caché derrière le bus — l'UI sait exactement quel Handler elle appelle.
- **Stack trace lisible.** Pas de middleware Messenger entre UI et logique.
- **Pas de configuration de transport** (sync/async) à maintenir pour un cas où on n'en a pas besoin.

### Négatives

- **Re-câblage à prévoir** si on décide d'ajouter de l'async (ex. : un appel LLM long déporté sur un worker). À ce moment-là, on introduira Messenger sur les Handlers concernés, individuellement.
- **Pas de middleware unifié** (logging, transaction, retry) — il faudra le faire à la main ou décorer les Handlers via DI quand le besoin émergera.

## Quand reconsidérer ?

Bascule Messenger envisagée si **l'une** de ces conditions est vraie :

- Un Handler dépasse 10 s d'exécution typique (LLM long, outil shell long).
- On veut un retry/backoff strict sur certains use cases (rate limit upstream).
- On veut une queue durable / job workers.
- L'API HTTP a besoin de répondre 202 + traiter en arrière-plan.

À ce moment-là on ajoutera **par Handler** (pas globalement) `#[AsMessageHandler]` + une transport routing dédié, et on documentera dans une nouvelle ADR.

## Alternatives considérées

### A1 — Coller Messenger partout dès le départ

❌ Rejetée. YAGNI. Aucun use case Application ne dépasse quelques ms hors LLM (qui sera streamé, voir step ultérieur). Pas besoin d'orchestrateur.

### A2 — Faire un Bus maison (PSR-14 ou interface custom)

❌ Rejetée. Couche d'indirection sans valeur tant qu'on appelle un seul Handler par use case. Les Handlers invokables font déjà le job.

## Notes d'application

- Voir [`feedback-no-messenger-wiring`](../../../../C--Users-latai-Documents-Professionnel-SebCode/memory/feedback_no_messenger_wiring.md) (mémoire Claude) — la décision est rappelée aux sessions futures.
- Voir [`contexts/assistant.md`](../contexts/assistant.md) §2 pour les use cases concrets.
