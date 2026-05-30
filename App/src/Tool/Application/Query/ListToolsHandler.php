<?php

declare(strict_types=1);

namespace App\Tool\Application\Query;

use App\Tool\Domain\Model\ToolDescriptor;
use App\Tool\Domain\Port\ToolRegistry;

final readonly class ListToolsHandler
{
    public function __construct(private ToolRegistry $registry)
    {
    }

    /**
     * @return list<ToolDescriptor>
     */
    public function __invoke(ListToolsQuery $query): array
    {
        return $this->registry->list();
    }
}
