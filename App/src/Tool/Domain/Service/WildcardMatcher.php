<?php

declare(strict_types=1);

namespace App\Tool\Domain\Service;

/**
 * Glob-style matching of a concrete value against a wildcard pattern.
 *
 * Faithful PHP port of opencode's `Wildcard.match(str, pattern)`
 * (packages/core/src/util/wildcard.ts). Semantics:
 *
 *   - backslashes in both value and pattern are normalised to `/`;
 *   - regex specials are escaped, EXCEPT `*` and `?`;
 *   - `*` becomes `.*` (matches anything, path separators included);
 *   - `?` becomes `.` (any single character);
 *   - a trailing `" *"` becomes `"( .*)?"` so a command prefix like `ls *`
 *     matches both `ls` and `ls -la`;
 *   - the whole string is anchored and matched with the DOTALL flag.
 *
 * Matching is case-sensitive (the Linux container behaviour; opencode only
 * adds the `i` flag on win32). Pure stdlib (regex), so it stays Domain-clean.
 *
 * @see _opencode_ref/opencode-dev/packages/core/src/util/wildcard.ts
 */
final class WildcardMatcher
{
    public static function matches(string $value, string $pattern): bool
    {
        $value = str_replace('\\', '/', $value);
        $pattern = str_replace('\\', '/', $pattern);

        $regex = '';
        $length = \strlen($pattern);
        for ($i = 0; $i < $length; ++$i) {
            $char = $pattern[$i];
            $regex .= match ($char) {
                '*' => '.*',
                '?' => '.',
                default => preg_quote($char, '#'),
            };
        }

        // Trailing " *" → "( .*)?": let "ls *" match the bare "ls" too.
        if (str_ends_with($regex, ' .*')) {
            $regex = substr($regex, 0, -3).'( .*)?';
        }

        return 1 === preg_match('#^'.$regex.'$#s', $value);
    }
}
