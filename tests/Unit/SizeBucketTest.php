<?php

use App\Metrics\SizeBucket;

it('sizes a pull request by its changed lines, with inclusive upper bounds', function (int $changedLines, SizeBucket $bucket) {
    expect(SizeBucket::for($changedLines))->toBe($bucket);
})->with([
    [0, SizeBucket::XS],
    [10, SizeBucket::XS],
    [11, SizeBucket::S],
    [100, SizeBucket::S],
    [400, SizeBucket::M],
    [1000, SizeBucket::L],
    [1001, SizeBucket::XL],
    [PHP_INT_MAX, SizeBucket::XL],
]);
