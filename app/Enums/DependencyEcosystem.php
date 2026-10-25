<?php

namespace App\Enums;

/**
 * A package manager whose manifest and lock file Houston reads.
 */
enum DependencyEcosystem: string
{
    case Composer = 'composer';

    case Npm = 'npm';
}
