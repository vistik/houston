{{-- A metric in the "Metrics at a glance" lists, with its icon. Pass $metric (a key such as 'cycleTime') and $label. Links to $firstRepository's report when there is one. --}}
@php($chip = 'inline-flex items-center gap-1.5 rounded-md border border-border bg-surface-2 px-2 py-1')
@php($icon = '<svg aria-hidden="true" viewBox="0 0 24 24" class="size-3.5 shrink-0 text-muted" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">'.App\Metrics\MetricIcons::Glyphs[$metric].'</svg>')
<li>
  @if ($firstRepository)
    <a href="{{ route('repositories.show', ['owner' => $firstRepository->owner, 'name' => $firstRepository->name]) }}" class="{{ $chip }} transition-colors hover:border-muted hover:text-primary focus-visible:outline-2 focus-visible:outline-primary">{!! $icon !!}{{ $label }}</a>
  @else
    <span class="{{ $chip }}">{!! $icon !!}{{ $label }}</span>
  @endif
</li>
