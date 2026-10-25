{{--
  The footer shared by the landing page and the install guide. Like the header, the feature links point
  at the landing page and only show when it is on, with the same icons and colours as the Features menu.
--}}
@php
  $onInstall = request()->routeIs('landing.install');
@endphp
<footer class="border-t border-border bg-surface-2">
  <div class="mx-auto grid grid-cols-1 max-w-6xl gap-10 px-4 py-12 sm:px-6 md:grid-cols-12">
    <div class="md:col-span-5">
      <a href="{{ route('landing') }}" class="inline-flex items-center gap-2 rounded-md font-display text-base font-semibold tracking-tight text-text">
        <img src="/brand/houston-logo-dark.svg" width="28" height="28" alt="">
        Houston
      </a>
      <p class="mt-4 max-w-sm text-sm leading-relaxed text-muted">Mission control for your GitHub repos. DORA and PR metrics, free, open source, self-hosted.</p>
      <p class="mt-4 font-mono text-xs text-muted">Failure is not an option. Change failure rate is a metric.</p>
      <p class="mt-2 font-mono text-xs text-muted">MIT licence · Made by Visti Kløft</p>
    </div>
    <nav aria-label="Footer" class="grid grid-cols-2 gap-8 text-sm md:col-span-7 md:grid-cols-3">
      @if (config('houston.landing'))
      <div class="col-span-2 md:col-span-1">
        <h2 class="font-mono text-xs tracking-widest text-muted uppercase">Features</h2>
        <ul class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2.5 md:grid-cols-1">
          @foreach (App\Enums\LandingFeature::cases() as $feature)
          <li>
            <a href="{{ route('landing') }}#{{ $feature->value }}" class="inline-flex items-center gap-2.5 rounded-md text-text transition-colors hover:text-primary">
              @include('landing.partials.feature-icon', ['feature' => $feature, 'small' => true])
              {{ $feature->label() }}
            </a>
          </li>
          @endforeach
        </ul>
      </div>
      @endif
      <div>
        <h2 class="font-mono text-xs tracking-widest text-muted uppercase">Get it</h2>
        <ul class="mt-4 space-y-3">
          <li><a href="https://github.com/vistik/Houston" class="rounded-md text-text transition-colors hover:text-primary">GitHub</a></li>
          <li><a href="{{ route('landing.install') }}" @if ($onInstall) aria-current="page" @endif class="rounded-md text-text transition-colors hover:text-primary">Install guide</a></li>
          @if (config('houston.landing'))
          <li><a href="{{ route('home') }}" class="rounded-md text-text transition-colors hover:text-primary">See the demo</a></li>
          <li><a href="{{ route('landing') }}#faq" class="rounded-md text-text transition-colors hover:text-primary">FAQ</a></li>
          @endif
          <li><a href="https://github.com/vistik/Houston/blob/main/LICENSE" class="rounded-md text-text transition-colors hover:text-primary">MIT licence</a></li>
        </ul>
      </div>
      <div>
        <h2 class="font-mono text-xs tracking-widest text-muted uppercase">Project</h2>
        <ul class="mt-4 space-y-3">
          <li><a href="https://github.com/vistik/Houston/blob/main/CONTRIBUTING.md" class="rounded-md text-text transition-colors hover:text-primary">Contributing</a></li>
          <li><a href="https://github.com/vistik/Houston/issues/new/choose" class="rounded-md text-text transition-colors hover:text-primary">Report an issue</a></li>
          <li><a href="https://github.com/vistik/Houston/blob/main/CHANGELOG.md" class="rounded-md text-text transition-colors hover:text-primary">Changelog</a></li>
          <li><a href="https://github.com/vistik/Houston/blob/main/SECURITY.md" class="rounded-md text-text transition-colors hover:text-primary">Security policy</a></li>
          <li><a href="https://github.com/vistik/Houston/blob/main/CODE_OF_CONDUCT.md" class="rounded-md text-text transition-colors hover:text-primary">Code of conduct</a></li>
        </ul>
      </div>
    </nav>
  </div>
</footer>
