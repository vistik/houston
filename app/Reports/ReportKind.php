<?php

namespace App\Reports;

/**
 * The shapes a velocity report is cached in.
 */
enum ReportKind: string
{
    /** Everything, for the repository and team pages. */
    case Full = 'full';

    /** The headline metrics, for overview cards. */
    case Summary = 'summary';

    /** What the wall's slides show. */
    case Wall = 'wall';

    /**
     * @return array<string, mixed>
     */
    public function build(VelocityReport $report): array
    {
        return match ($this) {
            self::Full => $report->toArray(),
            self::Summary => $report->summary(),
            self::Wall => $report->wall(),
        };
    }
}
