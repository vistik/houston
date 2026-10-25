{{-- The small label above a feature section's heading, with the feature's icon and in its colour. Pass $feature and $text. --}}
<p style="--feature: {{ $feature->color() }}" class="inline-flex items-center gap-3 font-mono text-xs font-medium tracking-widest text-(--feature) uppercase">
  @include('landing.partials.feature-icon', ['feature' => $feature])
  {{ $text }}
</p>
