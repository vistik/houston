{{-- A feature's icon in a tile tinted with its colour. Pass $feature (an App\Enums\LandingFeature), and 'small' => true for the footer. --}}
<span style="--feature: {{ $feature->color() }}" @class(["grid shrink-0 place-items-center rounded-md border border-(--feature)/30 bg-(--feature)/10 text-(--feature)", "size-6" => $small ?? false, "size-8" => ! ($small ?? false)])>
  <svg aria-hidden="true" viewBox="0 0 24 24" @class(["size-3.5" => $small ?? false, "size-4" => ! ($small ?? false)]) fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $feature->icon() !!}</svg>
</span>
