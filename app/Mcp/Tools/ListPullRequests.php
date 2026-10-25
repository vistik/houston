<?php

namespace App\Mcp\Tools;

use App\Enums\PullRequestState;
use App\Mcp\PullRequestFilter;
use App\Mcp\PullRequestSort;
use App\Mcp\Tools\Concerns\ReadsRepositories;
use App\Models\PullRequest;
use App\Models\PullRequestReview;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List cached pull requests of one repository with their lifecycle timestamps, size, discussion counts, labels and reviews. Use it to drill into the examples behind a metric, such as which pull requests waited longest for review or what one author shipped.')]
class ListPullRequests extends Tool
{
    use ReadsRepositories;

    public function handle(Request $request): Response
    {
        $request->validate([
            ...$this->periodRules(),
            'state' => ['sometimes', Rule::enum(PullRequestFilter::class)],
            'author' => ['sometimes', 'nullable', 'string'],
            'include_bots' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', Rule::enum(PullRequestSort::class)],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $repository = $this->resolveRepository($request);
        $period = $this->period($request, repository: $repository);
        $filter = PullRequestFilter::from((string) $request->get('state', PullRequestFilter::Merged->value));
        $author = $request->get('author');

        $within = [$period->from, $period->to->subSecond()];

        $pullRequests = $repository->pullRequests()
            ->with('reviews')
            ->where(fn (Builder $query) => match ($filter) {
                PullRequestFilter::Open => $query->where('state', PullRequestState::Open),
                PullRequestFilter::Merged => $query->where('state', PullRequestState::Merged)->whereBetween('merged_at', $within),
                PullRequestFilter::Closed => $query->where('state', PullRequestState::Closed)->whereBetween('closed_at', $within),
                PullRequestFilter::All => $query
                    ->where('state', PullRequestState::Open)
                    ->orWhereBetween('opened_at', $within)
                    ->orWhereBetween('closed_at', $within),
            })
            ->when(filled($author), fn (Builder $query) => $query->where('author', $author))
            ->unless((bool) $request->get('include_bots', false) || filled($author), fn (Builder $query) => $query->where('author_is_bot', false))
            ->get();

        $sorted = match (PullRequestSort::from((string) $request->get('sort', PullRequestSort::Recent->value))) {
            PullRequestSort::Oldest => $pullRequests->sortBy('opened_at'),
            PullRequestSort::Largest => $pullRequests->sortByDesc(fn (PullRequest $pr): int => $pr->size()),
            PullRequestSort::Slowest => $pullRequests->sortByDesc(fn (PullRequest $pr): int => (int) $pr->opened_at->diffInSeconds($pr->merged_at ?? $pr->closed_at ?? now())),
            PullRequestSort::Recent => $pullRequests->sortByDesc(fn (PullRequest $pr): int => ($pr->merged_at ?? $pr->closed_at ?? $pr->opened_at)->getTimestamp()),
        };

        return Response::json([
            'repository' => $repository->fullName(),
            ...$period->filters(),
            'state' => $filter,
            'total' => $pullRequests->count(),
            'pullRequests' => $sorted->take((int) $request->get('limit', 25))->values()->map(fn (PullRequest $pr): array => $this->pullRequestPayload($pr))->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function pullRequestPayload(PullRequest $pr): array
    {
        return [
            'number' => $pr->number,
            'title' => $pr->title,
            'url' => $pr->url,
            'state' => $pr->state,
            'isDraft' => $pr->is_draft,
            'author' => $pr->author,
            'authorIsBot' => $pr->author_is_bot,
            'baseRef' => $pr->base_ref,
            'headRef' => $pr->head_ref,
            'additions' => $pr->additions,
            'deletions' => $pr->deletions,
            'changedFiles' => $pr->changed_files,
            'commits' => $pr->commits_count,
            'comments' => $pr->comments_count,
            'reviewComments' => $pr->review_comments_count,
            'checks' => $pr->checks_state,
            'labels' => collect($pr->labels ?? [])->pluck('name')->all(),
            'isRemediation' => $pr->isRemediation(),
            'firstCommitAt' => $pr->first_commit_at?->toIso8601String(),
            'openedAt' => $pr->opened_at->toIso8601String(),
            'readyAt' => $pr->ready_at?->toIso8601String(),
            'firstResponseAt' => $pr->first_response_at?->toIso8601String(),
            'firstApprovalAt' => $pr->first_approval_at?->toIso8601String(),
            'mergedAt' => $pr->merged_at?->toIso8601String(),
            'closedAt' => $pr->closed_at?->toIso8601String(),
            'lastActivityAt' => $pr->last_activity_at?->toIso8601String(),
            'reviews' => $pr->reviews->sortBy('submitted_at')->values()->map(fn (PullRequestReview $review): array => [
                'reviewer' => $review->reviewer,
                'isBot' => $review->reviewer_is_bot,
                'state' => $review->state,
                'comments' => $review->comments_count,
                'submittedAt' => $review->submitted_at->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'repository' => $this->repositorySchema($schema),
            'state' => $schema->string()
                ->enum(array_column(PullRequestFilter::cases(), 'value'))
                ->description('"open" lists every open pull request regardless of age. "merged" and "closed" (closed without merging) list those that finished within the window. "all" combines open ones with any opened or closed within the window.')
                ->default(PullRequestFilter::Merged->value),
            ...$this->periodSchema($schema),
            'author' => $schema->string()->description('Only pull requests by this GitHub login.'),
            'include_bots' => $schema->boolean()->description('Include pull requests authored by bots.')->default(false),
            'sort' => $schema->string()
                ->enum(array_column(PullRequestSort::cases(), 'value'))
                ->description('"recent" by last state change, "oldest" by opening date, "largest" by changed lines, "slowest" by time from opening to merge, close or now.')
                ->default(PullRequestSort::Recent->value),
            'limit' => $schema->integer()->min(1)->max(100)->default(25),
        ];
    }
}
