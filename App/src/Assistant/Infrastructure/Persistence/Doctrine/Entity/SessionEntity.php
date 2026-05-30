<?php

declare(strict_types=1);

namespace App\Assistant\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'assistant_sessions')]
class SessionEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 64)]
    public string $id;

    #[ORM\Column(name: 'model_name', type: 'string', length: 128)]
    public string $model;

    #[ORM\Column(type: 'string', length: 255)]
    public string $title;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetimetz_immutable')]
    public \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    public bool $archived = false;
}
