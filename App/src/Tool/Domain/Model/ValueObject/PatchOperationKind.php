<?php

declare(strict_types=1);

namespace App\Tool\Domain\Model\ValueObject;

/**
 * The action a {@see \App\Tool\Domain\Model\FilePatch} performs on one file.
 *
 * Mirrors opencode's apply_patch headers (Add / Update / Delete File). A move
 * is an Update carrying a non-null movePath.
 */
enum PatchOperationKind: string
{
    case Add = 'add';
    case Update = 'update';
    case Delete = 'delete';
}
