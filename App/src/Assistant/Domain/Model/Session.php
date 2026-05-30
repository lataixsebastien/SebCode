<?php

declare(strict_types=1);

namespace App\Assistant\Domain\Model;

use App\Assistant\Domain\Model\ValueObject\ModelName;
use App\Assistant\Domain\Model\ValueObject\SessionId;

final class Session
{
    public function __construct(
        public readonly SessionId $id,
        public readonly ModelName $model,
        private string $title,
        public readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
        private bool $archived = false,
    ) {
    }

    public static function start(
        SessionId $id,
        ModelName $model,
        string $title,
        \DateTimeImmutable $now,
    ): self {
        return new self($id, $model, $title, $now, $now);
    }

    public function rename(string $title, \DateTimeImmutable $now): void
    {
        $this->title = $title;
        $this->updatedAt = $now;
    }

    public function touch(\DateTimeImmutable $now): void
    {
        $this->updatedAt = $now;
    }

    public function archive(\DateTimeImmutable $now): void
    {
        $this->archived = true;
        $this->updatedAt = $now;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }
}
