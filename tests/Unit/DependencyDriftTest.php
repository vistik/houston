<?php

use App\Enums\DependencyDrift;

it('rates how far an installed version is behind the latest release', function (?string $installed, ?string $latest, DependencyDrift $drift) {
    expect(DependencyDrift::between($installed, $latest))->toBe($drift);
})->with([
    'the latest release' => ['v2.3.4', '2.3.4', DependencyDrift::Current],
    'ahead of the registry' => ['2.4.0', '2.3.4', DependencyDrift::Current],
    'a patch behind' => ['2.3.3', '2.3.4', DependencyDrift::Patch],
    'a minor behind' => ['2.2.9', '2.3.0', DependencyDrift::Minor],
    'a major behind' => ['1.9.0', '2.0.0', DependencyDrift::Major],
    'a minor behind on 0.x' => ['0.3.2', '0.4.0', DependencyDrift::Minor],
    'a major behind from 0.x' => ['0.9.0', '1.0.0', DependencyDrift::Major],
    'a patch behind on 0.x' => ['0.3.1', '0.3.2', DependencyDrift::Patch],
    'a short version' => ['2.3', '2.3.1', DependencyDrift::Patch],
    'a normalized version' => ['2.3.4.0', '2.3.4', DependencyDrift::Current],
    'a branch' => ['dev-main', '2.0.0', DependencyDrift::Unknown],
    'a pre-release' => ['2.0.0-beta.1', '2.0.0', DependencyDrift::Unknown],
    'nothing installed' => [null, '2.0.0', DependencyDrift::Unknown],
    'no release known' => ['2.0.0', null, DependencyDrift::Unknown],
]);

it('rates whether a version constraint allows the latest release', function (string $constraint, ?string $latest, DependencyDrift $drift) {
    expect(DependencyDrift::allowedBy($constraint, $latest))->toBe($drift);
})->with([
    'a caret on the latest major' => ['^2.0.5', '2.1.0', DependencyDrift::Current],
    'a caret a major behind' => ['^2.0', '3.1.0', DependencyDrift::Major],
    'alternatives reaching the latest' => ['^0.14.2 || ^1.0', '1.0.0', DependencyDrift::Current],
    'alternatives with one bar' => ['^6.4|^7.0', '7.3.0', DependencyDrift::Current],
    'a caret on 0.x' => ['^0.3', '0.3.9', DependencyDrift::Current],
    'a caret on 0.x a minor behind' => ['^0.3', '0.4.0', DependencyDrift::Minor],
    'a caret on 0.x a major behind' => ['^0.3', '1.0.0', DependencyDrift::Major],
    'a wildcard version' => ['2.x', '2.9.0', DependencyDrift::Current],
    'any version' => ['*', '5.0.0', DependencyDrift::Current],
    'a lower bound only' => ['>=1.0', '5.0.0', DependencyDrift::Current],
    'an upper bound below the latest' => ['>=1.0 <3.0', '3.0.0', DependencyDrift::Major],
    'an upper bound above the latest' => ['>=1.0,<4.0', '3.2.0', DependencyDrift::Current],
    'a branch' => ['dev-main', '2.0.0', DependencyDrift::Unknown],
    'no release known' => ['^2.0', null, DependencyDrift::Unknown],
]);
