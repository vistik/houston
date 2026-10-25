<?php

namespace App\Enums;

/**
 * Where a repository's deployments come from.
 */
enum DeploymentSource: string
{
    /** GitHub Deployments to a production environment. */
    case Deployments = 'deployments';

    /** Published GitHub releases, for repositories that don't deploy, such as libraries. */
    case Releases = 'releases';
}
