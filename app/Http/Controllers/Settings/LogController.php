<?php

namespace App\Http\Controllers\Settings;

use App\Enums\SystemEventLevel;
use App\Http\Controllers\Controller;
use App\Models\Repository;
use App\Models\SystemEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A log of what Houston does behind the scenes, to help understand how it works and debug issues.
 */
class LogController extends Controller
{
    private const int PerPage = 50;

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'level' => ['nullable', Rule::enum(SystemEventLevel::class)],
            'category' => ['nullable', 'string', 'alpha_dash'],
            'repository' => ['nullable', 'integer'],
        ]);

        $events = SystemEvent::query()
            ->when($filters['level'] ?? null, fn ($query, string $level) => $query->where('level', $level))
            ->when($filters['category'] ?? null, fn ($query, string $category) => $query->where('type', 'like', "{$category}.%"))
            ->when($filters['repository'] ?? null, fn ($query, int $repository) => $query->where('repository_id', $repository))
            ->latest('id')
            ->paginate(self::PerPage)
            ->withQueryString();

        return Inertia::render('settings/log', [
            'filters' => [
                'level' => $filters['level'] ?? null,
                'category' => $filters['category'] ?? null,
                'repository' => isset($filters['repository']) ? (int) $filters['repository'] : null,
            ],
            'categories' => SystemEvent::query()->distinct()->pluck('type')
                ->map(fn (string $type): string => Str::before($type, '.'))
                ->unique()->sort()->values(),
            'repositories' => Repository::query()->orderBy('owner')->orderBy('name')->get(['id', 'owner', 'name'])
                ->map(fn (Repository $repository): array => ['id' => $repository->id, 'fullName' => $repository->fullName()]),
            'events' => [
                'data' => collect($events->items())->map(fn (SystemEvent $event): array => [
                    'id' => $event->id,
                    'level' => $event->level,
                    'type' => $event->type,
                    'message' => $event->message,
                    'context' => $event->context,
                    'createdAt' => $event->created_at->toIso8601String(),
                ]),
                'currentPage' => $events->currentPage(),
                'lastPage' => $events->lastPage(),
                'total' => $events->total(),
                'previousPageUrl' => $events->previousPageUrl(),
                'nextPageUrl' => $events->nextPageUrl(),
            ],
            'keepDays' => SystemEvent::KeepDays,
        ]);
    }
}
