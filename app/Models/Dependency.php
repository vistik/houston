<?php

namespace App\Models;

use App\Enums\DependencyDrift;
use App\Enums\DependencyEcosystem;
use Database\Factories\DependencyFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A direct dependency of a repository, as its manifest declares it and its lock file installs it.
 *
 * @property DependencyEcosystem $ecosystem
 * @property string $name
 * @property string|null $constraint
 * @property string|null $version The installed version, or null without a lock file.
 * @property string|null $latest_version The latest stable release, or null when the registry doesn't know the package.
 * @property bool $is_dev
 * @property DependencyDrift $drift
 */
#[Unguarded]
#[WithoutTimestamps]
class Dependency extends Model
{
    /** @use HasFactory<DependencyFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ecosystem' => DependencyEcosystem::class,
            'drift' => DependencyDrift::class,
            'is_dev' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Repository, $this>
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }
}
