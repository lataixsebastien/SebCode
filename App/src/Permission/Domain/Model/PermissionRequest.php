<?php

declare(strict_types=1);

namespace SebCode\Permission\Domain\Model;

use SebCode\Permission\Domain\Model\ValueObject\PermissionRequestType;

final readonly class PermissionRequest
{
    private function __construct(
        public PermissionRequestType $type,
        private ?string $path = null,
        private ?string $command = null,
        private ?string $url = null,
    ) {
    }

    public static function readFile(string $path): self
    {
        return new self(PermissionRequestType::ReadFile, path: $path);
    }

    public static function writeFile(string $path): self
    {
        return new self(PermissionRequestType::WriteFile, path: $path);
    }

    public static function runCommand(string $command): self
    {
        return new self(PermissionRequestType::RunCommand, command: $command);
    }

    public static function network(string $url): self
    {
        return new self(PermissionRequestType::Network, url: $url);
    }

    public function path(): string
    {
        if (null === $this->path) {
            throw new \LogicException('Permission request has no path.');
        }

        return $this->path;
    }

    public function command(): string
    {
        if (null === $this->command) {
            throw new \LogicException('Permission request has no command.');
        }

        return $this->command;
    }

    public function url(): string
    {
        if (null === $this->url) {
            throw new \LogicException('Permission request has no URL.');
        }

        return $this->url;
    }
}
