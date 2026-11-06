<?php

declare(strict_types=1);

namespace NksHub\NetteRuian\Support;

/**
 * Czech alphabetical comparison: uses ext-intl Collator when available, otherwise a built-in approximation.
 */
final class CzechCollator
{
    /** Primary letters in Czech order; "ch" sorts as a letter after "h". */
    private const ORDER = ['a', 'b', 'c', 'č', 'd', 'e', 'f', 'g', 'h', 'ch', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 'ř', 's', 'š', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'ž'];

    /** Accented variants sort with their base letter. */
    private const BASE = ['á' => 'a', 'ď' => 'd', 'é' => 'e', 'ě' => 'e', 'í' => 'i', 'ň' => 'n', 'ó' => 'o', 'ť' => 't', 'ú' => 'u', 'ů' => 'u', 'ý' => 'y'];

    public static function compare(string $a, string $b): int
    {
        static $collator = false;
        if ($collator === false) {
            $collator = class_exists(\Collator::class) ? new \Collator('cs_CZ') : null;
        }

        if ($collator !== null) {
            $result = $collator->compare($a, $b);
            if ($result !== false) {
                return $result;
            }
        }

        return self::compareFallback($a, $b);
    }

    /**
     * Fallback without ext-intl.
     * shortcut: accents are ignored on the first pass and only break ties by byte order, which matches CLDR for names; upgrade to intl for full rules.
     */
    public static function compareFallback(string $a, string $b): int
    {
        return strcmp(self::key($a), self::key($b)) ?: strcmp(mb_strtolower($a), mb_strtolower($b));
    }

    private static function key(string $s): string
    {
        static $rank = null;
        $rank ??= array_flip(self::ORDER);

        $s = strtr(mb_strtolower($s), self::BASE);
        $key = '';
        foreach (preg_split('/(ch)|/u', $s, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE) ?: [] as $char) {
            // letters sort after every other character
            $key .= isset($rank[$char]) ? chr(100 + $rank[$char]) : chr(min(mb_ord($char), 99));
        }

        return $key;
    }
}
