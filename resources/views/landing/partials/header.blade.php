{{--
  The top bar shared by the landing page and the install guide. The Features menu points at sections of
  the landing page, so it only shows when that is on; on the landing page itself its links are in-page jumps.
--}}
@php
  $awaitsLogin ??= false;
  $onInstall = request()->routeIs('landing.install');
@endphp
<header class="sticky top-0 z-40 border-b border-border/60 bg-bg/80 backdrop-blur">
  <nav aria-label="Main" class="mx-auto max-w-6xl px-4 sm:px-6">
    <div class="flex h-16 items-center justify-between gap-4">
      <a href="{{ route('landing') }}" class="flex items-center gap-2 rounded-md font-display text-base font-semibold tracking-tight text-text">
        <img src="/brand/houston-logo-dark.svg" width="28" height="28" alt="">
        Houston
      </a>
      <ul class="hidden items-center gap-1 text-sm text-muted md:flex">
        @if (config('houston.landing'))
        <li class="relative">
          <details data-menu class="group">
            <summary class="flex cursor-pointer items-center gap-1 rounded-md px-3 py-2 transition-colors group-open:text-text hover:text-text">
              Features
              <svg aria-hidden="true" viewBox="0 0 16 16" class="size-3.5 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6l4 4 4-4"/></svg>
            </summary>
            <ul class="absolute top-full left-0 mt-2 grid w-[40rem] grid-cols-2 gap-1 rounded-xl border border-border bg-surface-2 p-2 shadow-xl">
              @foreach (App\Enums\LandingFeature::cases() as $feature)
              <li>
                <a href="{{ route('landing') }}#{{ $feature->value }}" class="flex items-start gap-3 rounded-lg px-3 py-2.5 transition-colors hover:bg-bg">
                  @include('landing.partials.feature-icon', ['feature' => $feature])
                  <span><span class="block font-medium text-text">{{ $feature->label() }}</span><span class="mt-0.5 block text-xs text-muted">{{ $feature->blurb() }}</span></span>
                </a>
              </li>
              @endforeach
            </ul>
          </details>
        </li>
        @endif
        <li><a href="{{ route('landing.install') }}" @if ($onInstall) aria-current="page" @endif @class(['rounded-md px-3 py-2 transition-colors hover:text-text', 'text-text' => $onInstall])>Install</a></li>
      </ul>
      <div class="flex items-center gap-2">
        <a href="https://github.com/vistik/Houston" class="hidden items-center gap-2 rounded-md px-3 py-2 text-sm text-muted transition-colors hover:text-text sm:flex">
          <svg aria-hidden="true" viewBox="0 0 16 16" class="size-4" fill="currentColor"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg>
          <span>GitHub</span>
          <span class="rounded-md border border-border px-2 py-0.5 font-mono text-xs text-muted">[★ stars]</span>
        </a>
        @if (! $awaitsLogin && config('houston.auth') && auth()->guest())
          <a href="{{ route('login') }}" class="rounded-md px-3 py-2 text-sm text-muted transition-colors hover:text-text">Log in</a>
        @endif
        <a href="https://github.com/vistik/Houston" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-on-primary transition hover:bg-primary/90">Get it on GitHub</a>
      </div>
    </div>
    <!-- Phones and tablets: the same links as a second row -->
    <ul class="-mx-2 flex items-center justify-between border-t border-border/60 py-1 text-sm text-muted md:hidden">
      @if (config('houston.landing'))
      <li>
        <details data-menu class="group">
          <summary class="flex cursor-pointer items-center gap-1 rounded-md px-2 py-2 transition-colors group-open:text-text hover:text-text">
            Features
            <svg aria-hidden="true" viewBox="0 0 16 16" class="size-3.5 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6l4 4 4-4"/></svg>
          </summary>
          <!-- Positioned against the sticky header, so it spans the full width under it -->
          <ul class="absolute inset-x-0 top-full grid gap-1 border-b border-border bg-surface-2 p-2 shadow-xl sm:grid-cols-2">
            @foreach (App\Enums\LandingFeature::cases() as $feature)
            <li>
                <a href="{{ route('landing') }}#{{ $feature->value }}" class="flex items-start gap-3 rounded-lg px-3 py-2.5 transition-colors hover:bg-bg">
                  @include('landing.partials.feature-icon', ['feature' => $feature])
                  <span><span class="block font-medium text-text">{{ $feature->label() }}</span><span class="mt-0.5 block text-xs text-muted">{{ $feature->blurb() }}</span></span>
                </a>
              </li>
            @endforeach
          </ul>
        </details>
      </li>
      @endif
      <li><a href="{{ route('landing.install') }}" @if ($onInstall) aria-current="page" @endif @class(['block rounded-md px-2 py-2 transition-colors hover:text-text', 'text-text' => $onInstall])>Install</a></li>
      <li><a href="https://github.com/vistik/Houston" class="block rounded-md px-2 py-2 transition-colors hover:text-text">GitHub</a></li>
    </ul>
  </nav>
</header>
