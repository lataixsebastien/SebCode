# ADR-0006 — Représentation des messages multi-parts via une colonne `payload_json` annexe

**Date :** 2026-05-30
**Statut :** Accepté
**Step lié :** STEP-12

## Contexte

À partir de STEP-12 (tool calling), un `Message` peut transporter **plus que du texte brut** :

- Un tour assistant peut émettre une liste de **`ToolCallRequest`** (intentions d'appel d'outils).
- Un tour `Tool` (résultat d'outil) doit retenir `toolCallId`, `toolName`, `output`, `isError`.

L'approche "Parts" d'opencode (`PartID`, `TextPart`, `ToolCallPart`, `ToolResultPart`, `SnapshotPart`...) est tentante mais demanderait :

- Refactor de `Message` (Domain) en `Message + body: list<Part>`.
- Migration Doctrine majeure (nouvelle table `assistant_message_parts`, FK).
- Refactor de `MessageContent`, `MessageMapper`, `MessageRepository`, tous les tests existants.
- Recompréhension du flux de persistance.

## Décision

**On NE migre PAS** vers le modèle Parts.

À la place :

1. `Message` (Domain) reste mono-string (`content: MessageContent`).
2. On ajoute **un seul champ optionnel** `payload: ?MessagePayload` qui transporte la structure typée pour les messages tool-related.
3. On ajoute **une seule colonne** `payload_json TEXT NULL` à la table `assistant_messages`. JSON-encodée par `MessageMapper`.
4. Le `content.text` des messages tool-related contient une **représentation lisible** (`tool_call glob({...})` côté assistant, `✓ glob → 23 paths` côté tool result). Le payload JSON est la source structurée.

### Format JSON

Pour un tour Assistant avec tool calls :
```json
{
  "kind": "tool_call",
  "tool_calls": [
    { "id": "tcl_abc", "name": "glob", "arguments": { "pattern": "**/*.php" } },
    { "id": "tcl_def", "name": "read", "arguments": { "filePath": "README.md" } }
  ]
}
```

Pour un message Tool result :
```json
{
  "kind": "tool_result",
  "tool_call_id": "tcl_abc",
  "tool_name": "glob",
  "output": "(23 matched, showing first 23)\n…",
  "is_error": false
}
```

## Conséquences

### Positives

- **Migration triviale.** Une seule colonne nullable, ALTER TABLE en place, pas de backfill.
- **Rétrocompat totale.** Tous les messages existants (user, system, plain assistant) ont `payload = null`. Aucun test existant ne casse hors changements liés à la boucle.
- **Domain peu impacté.** `Message` gagne un champ readonly optionnel. `MessageRole::Tool` (déjà dans l'enum depuis STEP-02) trouve enfin un usage.
- **Lecture humaine préservée.** `assistant:sessions <id>` reste lisible (les content.text donnent un résumé), et un `SELECT id, role, LEFT(content,60), LEFT(payload_json,80) ...` en SQL marche pour debug.

### Négatives

- **Dette technique consciente.** Le jour où un outil retourne du binaire (image, audio, PDF), ou qu'on veut représenter des `Citation`/`Reasoning` parts, on devra refactorer vers Parts. Reportée à un futur ADR-00XX, déclenché par le premier outil avec ce besoin.
- **Pas de query indexée sur le contenu du payload.** Si on veut "tous les tool_calls vers glob", il faut un scan ou un GIN index Postgres sur `payload_json`. Non prévu.
- **Encodage à maintenir.** `MessageMapper::encodePayload()` / `::decodePayload()` doit rester cohérent avec `MessagePayload`. Couvert par les tests unitaires du Mapper (round-trip).

## Alternatives écartées

### A1 — `MessageBody = list<Part>` immédiatement (opencode-style)

❌ Rejetée pour STEP-12. Coût × bénéfice trop faible :
- Coût : refactor Aggregate + entity Doctrine + mapper + migrations + ~50 tests.
- Bénéfice : type-safety au compile time pour les Parts. Mais on n'a que 2 kinds aujourd'hui (`tool_call`, `tool_result`), l'enum + champs typés du `MessagePayload` couvrent.

### A2 — Tout stringifier dans `content`

❌ Rejetée. Perd la structure typée. Un `MessageMapper` qui doit parser du Markdown ou du JSON pour reconstituer les tool_calls = recipe à bugs. Le payload JSON séparé est plus propre.

### A3 — Table dédiée `assistant_message_payloads(message_id, kind, data_json)`

❌ Surdimensionné pour l'usage : 1-to-1 avec Message, jamais querié séparément, double les jointures pour `forSession()` qui devrait rester rapide.

## Quand re-déclencher la migration vers Parts ?

Quand l'**un** de ces besoins arrive :
- Un outil retourne du binaire / image / fichier (≠ string).
- Un fournisseur LLM impose la structure Parts (Anthropic citations, Vertex thinking blocks…) et qu'on veut la préserver dans l'historique.
- On veut indexer/querier les Parts (analytics, search dans les outputs de tools).
- Le payload_json moyen dépasse 4 KiB régulièrement (jointure DB devient le bottleneck).

À ce moment-là, ADR-00XX (à écrire) actera la décision et la migration sera planifiée comme un STEP entier.

## Notes d'application

- Voir [`steps/STEP-12-agent-loop.md`](../steps/STEP-12-agent-loop.md) pour le contexte.
- Voir `App/src/Assistant/Domain/Model/ValueObject/MessagePayload.php` pour le VO.
- Voir `App/src/Assistant/Infrastructure/Persistence/Doctrine/Mapper/MessageMapper.php` pour le round-trip JSON.
- Pour debug SQL :
  ```sql
  SELECT id, role, LEFT(content, 60), LEFT(payload_json, 120)
  FROM assistant_messages
  WHERE session_id = 'ses_…' ORDER BY created_at;
  ```
