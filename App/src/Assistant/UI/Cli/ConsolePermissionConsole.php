<?php

declare(strict_types=1);

namespace App\Assistant\UI\Cli;

use App\Tool\Domain\Model\ValueObject\PermissionChoice;
use App\Tool\Domain\Model\ValueObject\PermissionRequest;
use App\Tool\Domain\Port\PermissionConsole;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * CLI adapter of {@see PermissionConsole}: renders a permission request and
 * blocks on a Symfony Console choice (once / always / reject).
 *
 * Implements a Tool Domain port from the Assistant UI — the same cross-context,
 * Domain-only seam the assistant already uses to reach the Tool context. It is
 * only ever active when the input is interactive; piped/`--no-interaction` runs
 * report inactive so the prompter falls back to its safe default.
 */
final readonly class ConsolePermissionConsole implements PermissionConsole
{
    public function __construct(
        private SymfonyStyle $io,
        private bool $interactive,
    ) {
    }

    public function isActive(): bool
    {
        return $this->interactive;
    }

    public function confirm(PermissionRequest $request, string $subject): PermissionChoice
    {
        $this->io->newLine();
        $this->io->writeln(\sprintf('<comment>⚠ Permission requested (%s)</comment>', $request->type->value));

        $description = $request->metadata['description'] ?? null;
        if (\is_string($description) && '' !== $description) {
            $this->io->writeln('  '.$description);
        }
        $this->io->writeln(\sprintf('  <info>%s</info>', $subject));

        $answer = $this->io->choice('Allow?', [
            PermissionChoice::AllowOnce->value => 'Allow once',
            PermissionChoice::AllowAlways->value => 'Allow always (this session)',
            PermissionChoice::Reject->value => 'Reject',
        ], PermissionChoice::Reject->value);

        return \is_string($answer) ? PermissionChoice::from($answer) : PermissionChoice::Reject;
    }
}
