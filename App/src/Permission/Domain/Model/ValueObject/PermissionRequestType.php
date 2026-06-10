<?php

declare(strict_types=1);

namespace SebCode\Permission\Domain\Model\ValueObject;

enum PermissionRequestType: string
{
    case ReadFile = 'read_file';
    case WriteFile = 'write_file';
    case RunCommand = 'run_command';
    case Network = 'network';
}
