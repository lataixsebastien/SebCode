# ADR-0003 — Séparer les Aggregates Domain des Entities Doctrine

**Date :** 2026-05-30
**Statut :** Accepté
**Step lié :** STEP-04

## Contexte

Doctrine ORM permet de mapper directement des classes PHP via attributs (`#[ORM\Entity]`). La tentation est de mettre ces attributs sur les Aggregates `Domain/Model/Session` et `Domain/Model/Message` — moins de code, moins de plomberie.

Mais ça violerait [ADR-0001](0001-hexa-ddd-layout.md) §règle de dépendance : le Domain ne doit dépendre d'AUCUN framework, Doctrine inclus.

Question : comment persister en Doctrine sans polluer le Domain ?

## Décision

On **dédouble** la classe persistante :

- `App\Assistant\Domain\Model\Session` — Aggregate, pur PHP, invariants métier, méthodes (`start()`, `rename()`, `archive()`, `touch()`).
- `App\Assistant\Infrastructure\Persistence\Doctrine\Entity\SessionEntity` — POPO Doctrine, propriétés publiques, **uniquement** des attributs `#[ORM\…]`.
- `App\Assistant\Infrastructure\Persistence\Doctrine\Mapper\SessionMapper` — traduit `Session ↔ SessionEntity`.
- `App\Assistant\Infrastructure\Persistence\Doctrine\Repository\DoctrineSessionRepository` — implémente le Port `Domain\Port\SessionRepository`, utilise le Mapper en interne.

Idem pour `Message` / `MessageEntity` / `MessageMapper` / `DoctrineMessageRepository`.

## Conséquences

### Positives

- **Domain reste 100 % pur.** Zéro import Doctrine dans `Domain/`. Les tests unitaires du Domain (22 tests STEP-02) tournent en quelques ms, sans booter Symfony ni Postgres.
- **Aggregate peut évoluer indépendamment du schéma DB.** Ajout d'une méthode `archive()` sans toucher au schéma. Renommage d'une colonne sans toucher au Domain (juste le Mapper).
- **Plusieurs représentations possibles.** Si on veut un cache mémoire ou un store filesystem en parallèle, on ajoute juste un nouveau Repository qui implémente le même Port, sans toucher au Domain. Cf. opencode qui mélange Postgres et fichiers.
- **Invariants métier impossibles à contourner via le repo.** Un `SessionEntity` est un POPO modifiable, mais le `DoctrineSessionRepository` n'expose jamais l'Entity à l'extérieur — l'Application ne voit que le `Session` Domain qui force les invariants.

### Négatives

- **Double déclaration des champs.** Ajouter une colonne implique : 1) propriété dans l'Aggregate, 2) propriété dans l'Entity, 3) update du Mapper, 4) migration. ~4 endroits. Coût d'une dizaine de lignes par champ.
- **Mapper testable mais à maintenir.** Couvert par des tests unitaires round-trip (`MapperTest::testRoundTripPreservesAllFields`).
- **Pas de "rich entity" Doctrine.** Pas d'`InheritanceMapping`, pas d'`Embeddable` Doctrine du côté Domain. Si besoin, on les met sur l'Entity et on les déplie côté Mapper.

## Alternatives considérées

### A1 — Coller les attributs `#[ORM\…]` sur les Aggregates Domain

❌ Rejetée. Violation directe d'ADR-0001 / CLAUDE.md §3.2. Le Domain deviendrait inutilisable sans Doctrine (impossible d'instancier `new Session(…)` dans un test unitaire sans charger les metadata Doctrine). Couplage fort à un ORM concret.

### A2 — Mapping XML/YAML séparé (l'Aggregate reste pur)

🟡 Considérée mais rejetée. Évite la pollution du Domain par les attributs MAIS :
- L'Aggregate doit exposer des setters / propriétés publiques pour Doctrine → casse l'encapsulation et les invariants (un caller peut bypasser `archive()` et set directement `$session->archived = true`).
- Doctrine reflection peut hydrater des propriétés privées, mais sans appeler de constructeur métier (problème pour les invariants à la construction).

L'option Entity séparée résout les deux problèmes proprement.

### A3 — Event Sourcing (persister les events plutôt que l'état)

❌ Reportée. Trop d'overhead pour le besoin actuel (un chat avec un LLM). Reconsidérable plus tard si on veut un audit log complet d'opencode (snapshot, revert, sync, etc.).

## Implémentation

```php
// Pattern de save (upsert) dans DoctrineSessionRepository
public function save(Session $session): void {
    $existing = $this->em->find(SessionEntity::class, $session->id->value);
    $entity = $this->mapper->toEntity($session, $existing);  // mute existing or create new
    if (null === $existing) {
        $this->em->persist($entity);
    }
    $this->em->flush();
}

// Pattern de load (hydratation Aggregate depuis Entity)
public function findById(SessionId $id): ?Session {
    $entity = $this->em->find(SessionEntity::class, $id->value);
    return null === $entity ? null : $this->mapper->toAggregate($entity);
}
```

## Notes d'application

- Voir [`contexts/assistant.md`](../contexts/assistant.md) §3 pour le détail des Entity Doctrine et des Repositories.
- Tests Mapper : `App/tests/Unit/Assistant/Infrastructure/Persistence/Doctrine/Mapper/*Test.php` — round-trip Aggregate ↔ Entity.
