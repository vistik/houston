<?php

use App\Metrics\Benchmarks;
use App\Metrics\MetricIcons;

it('has an icon for every metric', function () {
    expect(array_keys(MetricIcons::Glyphs))->toEqualCanonicalizing(Benchmarks::Metrics);
});

it('draws the same icons as the dashboard', function (string $metric) {
    $tsx = (string) file_get_contents(dirname(__DIR__, 2).'/resources/js/components/metric-icons.tsx');
    // Compare without whitespace, so the formatter's line breaks and the space before "/>" don't count.
    $normalize = fn (string $markup): string => preg_replace('/\s+/', '', $markup);

    // Each glyph sits in GLYPHS under its metric, as JSX up to the next metric's comment.
    preg_match('/^    '.$metric.': (.*?)\n(?:    \/\/|\};)/ms', $tsx, $match);

    expect($match)->not->toBeEmpty();

    $jsx = str_replace(['<>', '</>'], '', $normalize($match[1]));

    expect(trim($jsx, '(),'))->toBe($normalize(MetricIcons::Glyphs[$metric]));
})->with(array_keys(MetricIcons::Glyphs));
