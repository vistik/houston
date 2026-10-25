<?php

namespace App\Mcp\Prompts;

use App\Metrics\Period;
use App\Settings\ReportDefaults;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Description('Review a repository\'s engineering velocity and give the team concrete, evidence-backed feedback.')]
class GiveFeedback extends Prompt
{
    public function handle(Request $request): Response
    {
        [$window, $arguments] = $this->window($request);
        $repository = filled($request->get('repository'))
            ? "the repository {$request->get('repository')}"
            : 'every repository Houston watches (start with list-repositories and pick the ones that stand out)';
        $focus = filled($request->get('focus')) ? "\n\nPay particular attention to: {$request->get('focus')}." : '';

        return Response::text(<<<PROMPT
            Act as an experienced engineering lead reviewing how {$repository} has been doing {$window}.

            1. Fetch the data with get-velocity-report ({$arguments}), and pass the same window to list-pull-requests. Check the sync block first; if the data is stale, missing or the sync failed, say so up front.
            2. Compare each KPI with its previous-period value and look at the trend, cycle time breakdown and distributions to find where time actually goes.
            3. Drill into the examples behind anything notable with list-pull-requests: the slowest or largest merges, pull requests stuck waiting for review, changes merged without review.
            4. Write the feedback:
               - Start with a two-sentence summary of overall health.
               - List what is going well, with the numbers that show it.
               - List the top three opportunities, each with the evidence (metric, change versus previous period, and example pull requests by number and link), the likely cause, and one concrete experiment the team could try.
               - Call out risks such as a red default branch, a rising change failure rate, review load concentrated on one person or a growing bot backlog.

            Be specific and constructive. Treat metrics as conversation starters about the system of work, not as a scorecard for individuals: never rank or single out people. Say when a sample is too small to draw conclusions from.{$focus}
            PROMPT);
    }

    /**
     * Describe the reporting window in words and as the tool arguments that select it.
     *
     * @return array{0: string, 1: string}
     */
    private function window(Request $request): array
    {
        $from = $request->get('from');
        $to = $request->get('to');

        if (is_string($from) && is_string($to) && filled($from) && filled($to)) {
            return ["from {$from} through {$to}", "from: {$from}, to: {$to}"];
        }

        $days = Period::lastDays((int) $request->get('days', 30), ReportDefaults::timezone())->days;

        return ["over the last {$days} days", "days: {$days}"];
    }

    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument('repository', 'The repository as "owner/name". Leave empty to review every watched repository.'),
            new Argument('days', 'Reporting window in days: 7, 14, 30, 45, 90, 180 or 365. Defaults to 30.'),
            new Argument('from', 'First day of a custom range as YYYY-MM-DD, such as the start of a sprint. Give it with to; it replaces days.'),
            new Argument('to', 'Last day of a custom range as YYYY-MM-DD, inclusive.'),
            new Argument('focus', 'An area to dig into, such as code review, CI reliability, delivery or pull request size.'),
        ];
    }
}
