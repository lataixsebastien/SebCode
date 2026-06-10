<?php

declare(strict_types=1);

namespace SebCode\Provider\Domain;

use SebCode\Provider\Domain\Exception\ProviderNotFound;

final class ProviderRegistry
{
    /**
     * @var array<string, ModelProviderInterface>
     */
    private array $providers = [];

    public function register(ModelProviderInterface $provider): void
    {
        $name = $provider->name();
        if (isset($this->providers[$name])) {
            throw new \InvalidArgumentException(sprintf('Provider "%s" is already registered.', $name));
        }

        $this->providers[$name] = $provider;
        ksort($this->providers);
    }

    public function get(string $name): ModelProviderInterface
    {
        return $this->providers[$name] ?? throw ProviderNotFound::named($name);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->providers);
    }
}
