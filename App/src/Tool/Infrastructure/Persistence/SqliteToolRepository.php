<?php

declare(strict_types=1);

namespace SebCode\Tool\Infrastructure\Persistence;

use SebCode\Tool\Domain\Exception\ToolNotFound;
use SebCode\Tool\Domain\Model\ToolDescriptor;
use SebCode\Tool\Domain\Port\Tool;
use SebCode\Tool\Domain\Port\ToolRepository;

final class SqliteToolRepository implements ToolRepository
{
    /**
     * @var array<string, Tool>
     */
    private array $runtimeTools = [];

    public function __construct(private readonly \PDO $pdo)
    {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS tool_catalog (
                name TEXT PRIMARY KEY,
                description TEXT NOT NULL,
                category TEXT NOT NULL,
                safe INTEGER NOT NULL,
                cost INTEGER NOT NULL,
                allowed_modes TEXT NOT NULL,
                timeout_seconds INTEGER NOT NULL,
                requires_review INTEGER NOT NULL,
                input_schema TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )
        SQL);
    }

    public function save(Tool $tool): void
    {
        $descriptor = $tool->descriptor();
        if (isset($this->runtimeTools[$descriptor->name])) {
            throw new \InvalidArgumentException(sprintf('Tool "%s" is already registered.', $descriptor->name));
        }

        $this->runtimeTools[$descriptor->name] = $tool;

        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO tool_catalog (name, description, category, safe, cost, allowed_modes, timeout_seconds, requires_review, input_schema, updated_at)
            VALUES (:name, :description, :category, :safe, :cost, :allowed_modes, :timeout_seconds, :requires_review, :input_schema, :updated_at)
            ON CONFLICT(name) DO UPDATE SET
                description = excluded.description,
                category = excluded.category,
                safe = excluded.safe,
                cost = excluded.cost,
                allowed_modes = excluded.allowed_modes,
                timeout_seconds = excluded.timeout_seconds,
                requires_review = excluded.requires_review,
                input_schema = excluded.input_schema,
                updated_at = excluded.updated_at
        SQL);

        $statement->execute([
            'name' => $descriptor->name,
            'description' => $descriptor->description,
            'category' => $descriptor->category,
            'safe' => $descriptor->safe ? 1 : 0,
            'cost' => $descriptor->cost,
            'allowed_modes' => json_encode($descriptor->allowedModes, JSON_THROW_ON_ERROR),
            'timeout_seconds' => $descriptor->timeoutSeconds,
            'requires_review' => $descriptor->requiresReview ? 1 : 0,
            'input_schema' => json_encode($descriptor->inputSchema, JSON_THROW_ON_ERROR),
            'updated_at' => gmdate(DATE_ATOM),
        ]);
    }

    public function get(string $name): Tool
    {
        return $this->runtimeTools[$name] ?? throw ToolNotFound::named($name);
    }

    public function descriptors(): array
    {
        $statement = $this->pdo->query('SELECT name, description, category, safe, cost, allowed_modes, timeout_seconds, requires_review, input_schema FROM tool_catalog ORDER BY name ASC');
        if (false === $statement) {
            return [];
        }

        $descriptors = [];
        while (false !== $row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }

            /** @var array<string, mixed> $row */
            $name = $row['name'] ?? null;
            $description = $row['description'] ?? null;
            $category = $row['category'] ?? null;
            $safe = $row['safe'] ?? null;
            $cost = $row['cost'] ?? null;
            $allowedModes = $row['allowed_modes'] ?? null;
            $timeoutSeconds = $row['timeout_seconds'] ?? null;
            $requiresReview = $row['requires_review'] ?? null;
            $inputSchema = $row['input_schema'] ?? null;

            if (!is_string($name) || !is_string($description) || !is_string($category) || !is_string($allowedModes) || !is_string($inputSchema)) {
                continue;
            }

            if (!is_numeric($safe) || !is_numeric($cost) || !is_numeric($timeoutSeconds) || !is_numeric($requiresReview)) {
                continue;
            }

            $decodedModes = json_decode($allowedModes, true, flags: JSON_THROW_ON_ERROR);
            $decodedSchema = json_decode($inputSchema, true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($decodedModes)) {
                $decodedModes = [];
            }

            if (!is_array($decodedSchema)) {
                $decodedSchema = [];
            }

            /** @var list<string> $modes */
            $modes = array_values(array_filter($decodedModes, 'is_string'));
            /** @var array<string, mixed> $schema */
            $schema = $decodedSchema;
            $descriptors[] = new ToolDescriptor(
                $name,
                $description,
                $category,
                (bool) $safe,
                (int) $cost,
                $modes,
                (int) $timeoutSeconds,
                (bool) $requiresReview,
                $schema,
            );
        }

        return $descriptors;
    }

    public function availableDescriptors(string $mode): array
    {
        return array_values(array_filter(
            $this->descriptors(),
            static fn (ToolDescriptor $descriptor): bool => $descriptor->isAvailableForMode($mode),
        ));
    }
}
