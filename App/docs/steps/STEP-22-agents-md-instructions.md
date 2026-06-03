# STEP-22 — Injection des instructions projet (`AGENTS.md`) dans le prompt système

**Statut :** ✅ terminé (`composer qa` vert)
**Branche :** `feat/sebcode-foundation`

## But

Rendre l'agent **conscient des conventions du projet** : si le dépôt contient un `AGENTS.md`, son contenu
est ajouté au prompt système fondateur (STEP-21). Faithful à opencode (qui lit `AGENTS.md`). **Local**,
contenu, et bâti directement sur le step précédent.

## Décisions clés

| Décision | Choix | Raison |
|---|---|---|
| Fichier lu | `<workspace root>/AGENTS.md` | Standard de facto pour les consignes d'agent ; évite de détourner le `CLAUDE.md` (qui est pour Claude Code) |
| Où | Extension de `SebCodeSystemPrompt` (Infra) | Le port `SystemPrompt` est inchangé ; la lecture fichier reste en Infra |
| Taille | Cap à **8 KiB** (`…(truncated)` au-delà) | Évite de saturer le contexte des petits modèles |
| Absence de fichier | Section omise (prompt de base seul) | Pas de bruit quand il n'y a pas d'instructions |
| Section | `## Project-specific instructions (from AGENTS.md)` après le prompt de base | Clair pour le modèle |

## Layout produit

### Modifié

```
src/Assistant/Infrastructure/Prompt/SebCodeSystemPrompt.php
  (+ projectInstructions() : lit AGENTS.md, trim, cap 8 KiB ; appendée si présente)
```

### Nouveau — Tests

```
tests/Unit/Assistant/Infrastructure/Prompt/SebCodeSystemPromptTest.php
  (prompt de base : rôle/workspace/outils ; pas de section sans AGENTS.md ;
   AGENTS.md présent → contenu injecté sous la section ; fichier > 8 KiB → tronqué)
```

> Aucune modif de config DI : `SebCodeSystemPrompt` est déjà câblé avec `$projectRoot`
> (`%kernel.project_dir%`).

## Comment vérifier

```bash
docker compose exec php composer qa     # cs 0 / phpstan max 0 / deptrac 0 / phpunit vert

# Démo : créer un AGENTS.md à la racine du workspace (/var/www/App) puis lancer un ask —
# le prompt système (prompt_tokens) inclura les consignes.
```

## Step suivante

Pistes locales restantes : `task` (sous-agent — couplage inter-contexte à cadrer), `lsp` (diagnostics),
persistance Postgres (permissions/todos), ou la boucle agentique **async**.
