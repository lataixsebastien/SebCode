<?php

declare(strict_types=1);

namespace App\Tool\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine row for one persisted "always allow" permission grant, scoped to a
 * project root. Composite PK (project_root, type, pattern) gives natural dedup.
 *
 * No FK — the Tool context owns this table outright.
 */
#[ORM\Entity]
#[ORM\Table(name: 'tool_permission_grants')]
#[ORM\Index(name: 'idx_grant_project', columns: ['project_root'])]
class PermissionGrantEntity
{
    #[ORM\Id]
    #[ORM\Column(name: 'project_root', type: 'string', length: 512)]
    public string $projectRoot;

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32)]
    public string $type;

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 512)]
    public string $pattern;
}
