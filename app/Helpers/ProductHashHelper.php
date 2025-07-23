<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Utility for generating a stable hash for product deduplication.
 * Only hashes the product name (case-insensitive, trimmed).
 */
class ProductHashHelper
{
    /**
     * Generate a hash for the product name, matching frontend logic.
     */
    public static function getProductHash(string $name): string
    {
        if ($name === '') {
            return '';
        }

        $normalized = mb_strtolower(trim($name));
        $hash       = 0;
        $length     = mb_strlen($normalized);
        for ($i = 0; $i < $length; ++$i) {
            $charCode = mb_ord(mb_substr($normalized, $i, 1));
            $hash     = (($hash << 5) - $hash + $charCode) & 0xFFFFFFFF;
            if (($hash & 0x80000000) !== 0) {
                $hash -= 0x100000000;
            }
        }

        return 'name_' . abs($hash);
    }
}
