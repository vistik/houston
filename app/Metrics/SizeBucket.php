<?php

namespace App\Metrics;

/**
 * T-shirt sizes for pull requests by changed lines, smallest first.
 */
enum SizeBucket: string
{
    case XS = 'XS';

    case S = 'S';

    case M = 'M';

    case L = 'L';

    case XL = 'XL';

    public static function for(int $changedLines): self
    {
        foreach (self::cases() as $bucket) {
            if ($bucket->maxLines() === null || $changedLines <= $bucket->maxLines()) {
                return $bucket;
            }
        }

        return self::XL;
    }

    /**
     * The most changed lines a pull request of this size has, or null for no limit.
     */
    public function maxLines(): ?int
    {
        return match ($this) {
            self::XS => 10,
            self::S => 100,
            self::M => 400,
            self::L => 1000,
            self::XL => null,
        };
    }
}
