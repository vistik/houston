<?php

namespace App\Enums;

/**
 * Where a deployment comes from. A repository can ship through both, e.g. when it stops deploying and keeps releasing.
 */
enum DeploymentSource: string
{
    /** GitHub Deployments to a production environment. */
    case Deployments = 'deployments';

    /** Published GitHub releases, which is how libraries ship. */
    case Releases = 'releases';
}
