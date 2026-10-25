<?php

namespace App\Metrics\Trends;

use App\Metrics\Trend;
use App\Metrics\Unit;

final class DeploymentsTrend implements Trend
{
    public function key(): string
    {
        return 'deployments';
    }

    public function title(): string
    {
        return 'Deploys';
    }

    public function summary(): string
    {
        return 'Production deploys, or published releases';
    }

    public function description(): string
    {
        return 'Count of successful deployments to production in each bucket. Repositories without GitHub Deployments count published releases instead, leaving out drafts and prereleases.';
    }

    public function unit(): Unit
    {
        return Unit::Count;
    }
}
