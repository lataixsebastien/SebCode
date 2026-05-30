<?php

declare(strict_types=1);

namespace App\Tool\Domain\Model\ValueObject;

/**
 * Minimal wrapper for a JSON Schema describing a tool's input parameters.
 *
 * Only enforces the LLM-side invariants (root object with a `properties` map);
 * leaves deeper validation to consumers. The wire-format is just an array
 * that will be JSON-encoded into the LLM contract.
 *
 * @phpstan-type SchemaArray array{type: string, properties?: array<string, mixed>, required?: list<string>, ...}
 */
final readonly class JsonSchema
{
    /** @var SchemaArray */
    public array $value;

    /**
     * @param array<string, mixed> $value
     */
    private function __construct(array $value)
    {
        if (($value['type'] ?? null) !== 'object') {
            throw new \InvalidArgumentException('Tool JSON schema root must have type=object.');
        }
        if (isset($value['properties']) && !\is_array($value['properties'])) {
            throw new \InvalidArgumentException('Tool JSON schema "properties" must be an array.');
        }
        if (isset($value['required']) && (!\is_array($value['required']) || !array_is_list($value['required']))) {
            throw new \InvalidArgumentException('Tool JSON schema "required" must be a list of strings.');
        }
        /** @var SchemaArray $value */
        $this->value = $value;
    }

    /**
     * @param array<string, mixed> $value
     */
    public static function of(array $value): self
    {
        return new self($value);
    }

    /**
     * Empty schema for tools that accept no parameters.
     *
     * Encoded on the wire as `{"type":"object","properties":{}}` once the
     * adapter forces JSON_FORCE_OBJECT on the empty `properties` map.
     */
    public static function empty(): self
    {
        return new self(['type' => 'object', 'properties' => []]);
    }
}
