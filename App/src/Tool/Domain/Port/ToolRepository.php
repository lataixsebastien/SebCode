<?php

declare(strict_types=1);

namespace SebCode\Tool\Domain\Port;

use SebCode\Tool\Domain\Model\ToolDescriptor;

interface ToolRepository
{
    public function save(Tool $tool): void;

    public function get(string $name): Tool;

    /**
     * @return list<ToolDescriptor>
     */
    public function descriptors(): array;

    /**
     * @return list<ToolDescriptor>
     */
    public function availableDescriptors(string $mode): array;
}
