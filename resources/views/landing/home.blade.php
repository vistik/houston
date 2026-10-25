<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Houston · Mission control for your engineering team</title>
<meta name="description" content="Free, open-source DORA and PR metrics for your GitHub repos. Run it yourself with one token; your data never leaves your server.">
<link rel="canonical" href="{{ route('landing') }}">
<meta name="theme-color" content="#0a0c11">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<meta property="og:type" content="website">
<meta property="og:site_name" content="Houston">
<meta property="og:title" content="Houston · Mission control for your engineering team">
<meta property="og:description" content="Free, open-source DORA and PR metrics for your GitHub repos. Run it yourself with one token; your data never leaves your server.">
<meta property="og:url" content="{{ route('landing') }}">
<meta property="og:image" content="{{ asset('landing/og.png') }}">
<meta property="og:image:alt" content="Houston logo with the headline Mission control for your engineering team, next to a repository report showing time to merge, pickup time and where the time goes">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Houston · Mission control for your engineering team">
<meta name="twitter:description" content="Free, open-source DORA and PR metrics for your GitHub repos. Run it yourself with one token; your data never leaves your server.">
<meta name="twitter:image" content="{{ asset('landing/og.png') }}">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=geist:400,500,600,700|geist-mono:400,500&display=swap" rel="stylesheet">
@vite('resources/css/landing.css')

@verbatim
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "SoftwareSourceCode",
      "name": "Houston",
      "description": "Houston measures engineering velocity for GitHub repositories from a local cache of pull requests, reviews, CI workflow runs and deployments.",
      "codeRepository": "https://github.com/vistik/Houston",
      "license": "https://opensource.org/licenses/MIT",
      "programmingLanguage": ["PHP", "TypeScript"],
      "author": { "@type": "Person", "name": "Visti Kløft" }
    },
    {
      "@type": "SoftwareApplication",
      "name": "Houston",
      "description": "Free, open-source DORA and PR metrics for your GitHub repos. Run it yourself with one token; your data never leaves your server.",
      "applicationCategory": "DeveloperApplication",
      "operatingSystem": "Linux, macOS, Windows (self-hosted, PHP 8.5.12+)",
      "license": "https://opensource.org/licenses/MIT",
      "url": "https://github.com/vistik/Houston"
    }
  ]
}
</script>
@endverbatim
@verbatim
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "Is it really free?",
      "acceptedAnswer": { "@type": "Answer", "text": "Yes. Houston is MIT licensed. There is no paid tier, no hosted version and no seat count. No launch fees either." }
    },
    {
      "@type": "Question",
      "name": "Where does my data live?",
      "acceptedAnswer": { "@type": "Answer", "text": "On your server. Houston keeps a local cache of pull requests, reviews, workflow runs and deployments in SQLite by default, or in any database Laravel supports. Nothing is sent anywhere but GitHub's API." }
    },
    {
      "@type": "Question",
      "name": "Who can see the dashboards?",
      "acceptedAnswer": { "@type": "Answer", "text": "Authorized personnel only: whoever has the login. Houston has one login, on by default: php artisan houston:install creates it, and you can turn it off on your own machine. There are no per-user accounts. The /mcp endpoint has its own token." }
    },
    {
      "@type": "Question",
      "name": "Does it rank developers?",
      "acceptedAnswer": { "@type": "Answer", "text": "No. Houston reports on teams and repositories, never on individual people." }
    },
    {
      "@type": "Question",
      "name": "Does it work with GitLab or Bitbucket?",
      "acceptedAnswer": { "@type": "Answer", "text": "No. Houston reads GitHub only: pull requests, reviews, Actions workflow runs, and Deployments or Releases." }
    },
    {
      "@type": "Question",
      "name": "How is change failure rate measured?",
      "acceptedAnswer": { "@type": "Answer", "text": "A deployment counts as a failed change when the next deployment ships a revert, hotfix or rollback pull request, matched on its title, branch or labels. The pattern is configurable. It is inferred from pull requests, not from incident data." }
    },
    {
      "@type": "Question",
      "name": "Will it hit GitHub's rate limits?",
      "acceptedAnswer": { "@type": "Answer", "text": "It syncs every 15 minutes and pauses when fewer than 50 requests are left in the current window, leaving room for anything else that shares the token. Paused syncs resume when the window resets." }
    }
  ]
}
</script>
@endverbatim
</head>
<body class="min-h-screen overflow-x-hidden bg-bg text-text">

<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-primary focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-on-primary">Skip to content</a>

@include('landing.partials.header')

<main id="main" tabindex="-1" class="outline-none">

  <!-- 2 · Hero ================================================================ -->
  <section aria-labelledby="hero-title" class="relative isolate overflow-hidden">
    <div aria-hidden="true" class="hero-grid pointer-events-none absolute inset-0 -z-10"></div>
    <div aria-hidden="true" class="starfield pointer-events-none absolute inset-0 -z-10"></div>
    <div aria-hidden="true" class="comets pointer-events-none absolute inset-0 -z-10 overflow-hidden">
      <span style="top: 8%; left: 4%; --angle: 18deg; --duration: 13s; --delay: 2s"></span>
      <span style="top: 4%; left: 52%; --angle: 24deg; --duration: 19s; --delay: 9s; --length: 90px; --travel: 35vw"></span>
      <span style="top: 30%; left: 22%; --angle: 14deg; --duration: 23s; --delay: 15s; --length: 150px"></span>
    </div>

    <div class="mx-auto max-w-6xl px-4 pt-16 text-center sm:px-6 sm:pt-24">
      <p class="mx-auto mb-6 flex w-fit items-center gap-2 rounded-md border border-border-strong bg-surface/60 px-3 py-1 font-mono text-xs tracking-widest text-muted uppercase">
        <span class="size-1.5 rounded-md bg-accent motion-safe:animate-pulse" aria-hidden="true"></span>
        Open source · Self-hosted · All systems go
        <span class="size-1.5 rounded-md bg-accent motion-safe:animate-pulse" aria-hidden="true"></span>
      </p>
      <h1 id="hero-title" class="mx-auto max-w-3xl font-display text-4xl font-semibold leading-tight tracking-tighter text-balance text-text sm:text-6xl sm:leading-none">
        Mission control for your engineering team.
      </h1>
      <p class="mx-auto mt-6 max-w-2xl text-base leading-relaxed text-pretty text-muted sm:text-lg">
        Free, open-source DORA and PR metrics for your GitHub repos. See which pull request is stuck on the pad, which pipeline is red and how long a fix takes to reach orbit. Run it yourself with one token; your data never leaves your server.
      </p>

      <div class="mx-auto mt-8 flex max-w-sm flex-col gap-3 sm:max-w-none sm:flex-row sm:justify-center">
        <a href="https://github.com/vistik/Houston" class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-6 py-3 text-sm font-semibold text-on-primary transition hover:bg-primary/90">
          <svg aria-hidden="true" viewBox="0 0 16 16" class="size-4" fill="currentColor"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg>
          Get it on GitHub
        </a>
        <a href="{{ route('home') }}" class="inline-flex items-center justify-center rounded-lg border border-border-strong bg-surface/60 px-6 py-3 text-sm font-semibold text-text transition hover:border-muted">
          See the demo
        </a>
        <a href="{{ route('landing.install') }}" class="inline-flex items-center justify-center rounded-lg border border-border-strong bg-surface/60 px-6 py-3 text-sm font-semibold text-text transition hover:border-muted">
          Install guide
        </a>
      </div>

      <!-- Quickstart -->
      <div class="mx-auto mt-8 max-w-4xl">
        <div class="mx-auto flex w-fit max-w-full items-center gap-2 rounded-xl border border-border bg-surface-2 p-2 text-left">
          <div class="min-w-0 flex-1 overflow-x-auto px-3 py-2">
            <code class="font-mono text-sm whitespace-nowrap text-text"><span aria-hidden="true" class="select-none text-muted">$ </span>git clone https://github.com/vistik/houston.git &amp;&amp; cd houston &amp;&amp; composer setup</code>
          </div>
          <button type="button" data-copy="git clone https://github.com/vistik/houston.git &amp;&amp; cd houston &amp;&amp; composer setup" aria-label="Copy the quickstart command" class="grid size-10 shrink-0 place-items-center rounded-lg border border-border bg-surface text-muted transition hover:border-border-strong hover:text-text">
            <svg data-icon="copy" aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/></svg>
            <svg data-icon="check" aria-hidden="true" viewBox="0 0 24 24" class="hidden size-4 text-accent" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
          </button>
        </div>
        <p class="mt-4 font-mono text-xs text-muted">MIT licensed · Laravel + SQLite · launches from your laptop</p>
      </div>
    </div>

    <!-- Product visual -->
    <div class="relative mx-auto mt-14 max-w-6xl px-4 sm:mt-16 sm:px-6">
      <div aria-hidden="true" class="hero-glow pointer-events-none absolute -top-16 left-1/2 -z-10 h-96 w-full max-w-4xl -translate-x-1/2"></div>

      <figure class="window-shadow relative overflow-hidden rounded-2xl border border-border-strong bg-surface motion-safe:animate-rise">
        <div class="flex items-center gap-3 border-b border-border bg-surface-2 px-4 py-3">
          <div class="flex gap-2" aria-hidden="true">
            <span class="size-3 rounded-md bg-skeleton-hi"></span>
            <span class="size-3 rounded-md bg-skeleton-hi"></span>
            <span class="size-3 rounded-md bg-skeleton-hi"></span>
          </div>
          <div class="mx-auto hidden rounded-md border border-border bg-bg/60 px-4 py-1 font-mono text-xs text-muted sm:block">localhost:8000/repos/laravel/framework</div>
          <div class="w-12"></div>
        </div>

        <div class="relative aspect-16/10">
            <img src="/landing/screenshots/repo-report-dark.png" width="2880" height="1800" alt="Houston repository report for laravel/framework: time to merge, pickup and review time with ratings, where the time goes, and DORA delivery metrics" class="absolute inset-0 size-full object-cover object-top" fetchpriority="high">
          </div>
      </figure>
      <div aria-hidden="true" class="fade-bottom pointer-events-none absolute inset-x-0 bottom-0 h-24"></div>
    </div>
  </section>

  <!-- 3 · Badge band ========================================================== -->
  <section aria-label="Project facts" class="border-y border-border bg-surface-2">
    <ul class="mx-auto flex max-w-6xl flex-wrap items-center justify-center gap-x-6 gap-y-3 px-4 py-6 font-mono text-xs text-muted sm:px-6 sm:text-sm">
      <li class="flex items-center gap-2"><span class="size-1.5 rounded-md bg-accent" aria-hidden="true"></span>MIT</li>
      <li>Laravel 13 / PHP 8.5</li>
      <li>React 19</li>
      <li>SQLite</li>
      <li class="rounded-md border border-dashed border-border-strong px-2 py-0.5">[version]</li>
    </ul>
  </section>

  <!-- 4 · Metrics at a glance ================================================= -->
  <section id="features" aria-labelledby="metrics-title" class="relative isolate">
    <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28">
      <div class="grid grid-cols-1 gap-10 lg:grid-cols-12 lg:items-end lg:gap-16">
        <div class="lg:col-span-7">
          @include('landing.partials.feature-eyebrow', ['feature' => App\Enums\LandingFeature::Metrics, 'text' => 'Telemetry'])
          <h2 id="metrics-title" class="mt-4 font-display text-3xl font-semibold leading-tight tracking-tight text-balance text-text sm:text-4xl">
            43 readouts from the pull requests, reviews and CI runs you already have.
          </h2>
          <p class="mt-5 max-w-2xl text-base leading-relaxed text-muted">
            Grouped the way the report reads, from how long a PR waits on the pad to how often production needs an abort. Every metric carries a plain-words note on how it is calculated.
          </p>
        </div>
        <dl class="grid grid-cols-3 gap-4 border-t border-border pt-6 lg:col-span-5 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-10">
          <div><dt class="sr-only">Metrics</dt><dd class="font-display text-3xl font-semibold tracking-tight text-text">43</dd><dd class="mt-1 text-xs text-muted">metrics</dd></div>
          <div><dt class="sr-only">Metric groups</dt><dd class="font-display text-3xl font-semibold tracking-tight text-text">5</dd><dd class="mt-1 text-xs text-muted">groups</dd></div>
          <div><dt class="sr-only">Built-in benchmark standards</dt><dd class="font-display text-3xl font-semibold tracking-tight text-text">31</dd><dd class="mt-1 text-xs text-muted">benchmark standards</dd></div>
        </dl>
      </div>

      <ul class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <li class="flex flex-col rounded-2xl border border-border bg-surface p-6 sm:col-span-2">
          <div class="flex items-baseline justify-between gap-4">
            <h3 class="font-display text-lg font-semibold tracking-tight text-text">Speed</h3>
            <span class="font-mono text-xs text-muted">13 metrics</span>
          </div>
          <ul class="mt-4 flex flex-wrap gap-2 font-mono text-xs text-text">
            @include('landing.partials.metric-chip', ['metric' => 'cycleTime', 'label' => 'Time to merge'])
            @include('landing.partials.metric-chip', ['metric' => 'pickupTime', 'label' => 'Pickup time'])
            @include('landing.partials.metric-chip', ['metric' => 'reviewTime', 'label' => 'Review time'])
            @include('landing.partials.metric-chip', ['metric' => 'cycleTimeP90', 'label' => 'Time to merge (p90)'])
            @include('landing.partials.metric-chip', ['metric' => 'leadTime', 'label' => 'Lead time'])
            @include('landing.partials.metric-chip', ['metric' => 'codingTime', 'label' => 'Coding time'])
            @include('landing.partials.metric-chip', ['metric' => 'timeToApproval', 'label' => 'Time to approval'])
            @include('landing.partials.metric-chip', ['metric' => 'mergeDelay', 'label' => 'Merge delay'])
            @include('landing.partials.metric-chip', ['metric' => 'waitingOnReviewer', 'label' => 'Waiting on reviewers'])
            @include('landing.partials.metric-chip', ['metric' => 'waitingOnAuthor', 'label' => 'Waiting on the author'])
            @include('landing.partials.metric-chip', ['metric' => 'mergeFrequency', 'label' => 'Merge frequency'])
            @include('landing.partials.metric-chip', ['metric' => 'throughput', 'label' => 'Merged PRs'])
            @include('landing.partials.metric-chip', ['metric' => 'opened', 'label' => 'PRs opened'])
          </ul>
        </li>
        <li class="flex flex-col rounded-2xl border border-border bg-surface p-6">
          <div class="flex items-baseline justify-between gap-4">
            <h3 class="font-display text-lg font-semibold tracking-tight text-text">Review quality</h3>
            <span class="font-mono text-xs text-muted">9 metrics</span>
          </div>
          <ul class="mt-4 flex flex-wrap gap-2 font-mono text-xs text-text">
            @include('landing.partials.metric-chip', ['metric' => 'pullRequestSize', 'label' => 'PR size'])
            @include('landing.partials.metric-chip', ['metric' => 'reviewRounds', 'label' => 'Review rounds'])
            @include('landing.partials.metric-chip', ['metric' => 'unreviewedMergeRate', 'label' => 'Merged without review'])
            @include('landing.partials.metric-chip', ['metric' => 'changesRequestedRate', 'label' => 'Changes requested'])
            @include('landing.partials.metric-chip', ['metric' => 'reviewDepth', 'label' => 'Review depth'])
            @include('landing.partials.metric-chip', ['metric' => 'reworkRate', 'label' => 'Rework after review'])
            @include('landing.partials.metric-chip', ['metric' => 'reviewConcentration', 'label' => 'Review concentration'])
            @include('landing.partials.metric-chip', ['metric' => 'remediations', 'label' => 'Reverts & hotfixes'])
            @include('landing.partials.metric-chip', ['metric' => 'mergeRate', 'label' => 'Merge rate'])
          </ul>
        </li>
        <li class="flex flex-col rounded-2xl border border-border bg-surface p-6">
          <div class="flex items-baseline justify-between gap-4">
            <h3 class="font-display text-lg font-semibold tracking-tight text-text">CI health</h3>
            <span class="font-mono text-xs text-muted">12 metrics</span>
          </div>
          <ul class="mt-4 flex flex-wrap gap-2 font-mono text-xs text-text">
            @include('landing.partials.metric-chip', ['metric' => 'ciFailureRate', 'label' => 'CI failure rate'])
            @include('landing.partials.metric-chip', ['metric' => 'ciDefaultBranchFailureRate', 'label' => 'Default branch failure rate'])
            @include('landing.partials.metric-chip', ['metric' => 'ciPullRequestFailureRate', 'label' => 'PR build failure rate'])
            @include('landing.partials.metric-chip', ['metric' => 'defaultBranchRedShare', 'label' => 'Time the default branch was red'])
            @include('landing.partials.metric-chip', ['metric' => 'ciDuration', 'label' => 'CI duration'])
            @include('landing.partials.metric-chip', ['metric' => 'ciDurationP90', 'label' => 'CI duration (p90)'])
            @include('landing.partials.metric-chip', ['metric' => 'ciRecoveryTime', 'label' => 'Time to recovery'])
            @include('landing.partials.metric-chip', ['metric' => 'ciQueueTime', 'label' => 'Queue time'])
            @include('landing.partials.metric-chip', ['metric' => 'ciFlakyRate', 'label' => 'Flaky commits'])
            @include('landing.partials.metric-chip', ['metric' => 'ciRerunRate', 'label' => 'Re-run rate'])
            @include('landing.partials.metric-chip', ['metric' => 'ciRuns', 'label' => 'Workflow runs'])
            @include('landing.partials.metric-chip', ['metric' => 'ciMinutes', 'label' => 'CI minutes'])
          </ul>
        </li>
        <li class="relative flex flex-col overflow-hidden rounded-2xl border border-primary/40 bg-surface p-6">
          <div aria-hidden="true" class="side-glow pointer-events-none absolute -top-10 -right-10 size-48"></div>
          <div class="relative flex items-baseline justify-between gap-4">
            <h3 class="font-display text-lg font-semibold tracking-tight text-text">Delivery <span class="text-primary">(DORA)</span></h3>
            <span class="font-mono text-xs text-muted">6 metrics</span>
          </div>
          <ul class="relative mt-4 flex flex-wrap gap-2 font-mono text-xs text-text">
            @include('landing.partials.metric-chip', ['metric' => 'deployFrequency', 'label' => 'Deploy frequency'])
            @include('landing.partials.metric-chip', ['metric' => 'leadTimeToProduction', 'label' => 'Lead time to production'])
            @include('landing.partials.metric-chip', ['metric' => 'changeFailureRate', 'label' => 'Change failure rate'])
            @include('landing.partials.metric-chip', ['metric' => 'timeToRestore', 'label' => 'Time to restore'])
            @include('landing.partials.metric-chip', ['metric' => 'deploymentFailureRate', 'label' => 'Deployment failure rate'])
            @include('landing.partials.metric-chip', ['metric' => 'deployments', 'label' => 'Deploys'])
          </ul>
        </li>
        <li class="flex flex-col rounded-2xl border border-border bg-surface p-6">
          <div class="flex items-baseline justify-between gap-4">
            <h3 class="font-display text-lg font-semibold tracking-tight text-text">Team &amp; work in progress</h3>
            <span class="font-mono text-xs text-muted">3 metrics</span>
          </div>
          <ul class="mt-4 flex flex-wrap gap-2 font-mono text-xs text-text">
            @include('landing.partials.metric-chip', ['metric' => 'openPullRequests', 'label' => 'Open PRs'])
            @include('landing.partials.metric-chip', ['metric' => 'stalePullRequests', 'label' => 'Stale PRs'])
            @include('landing.partials.metric-chip', ['metric' => 'activeDevelopers', 'label' => 'Active developers'])
          </ul>
          <p class="mt-auto pt-5 text-sm leading-relaxed text-muted">A PR goes stale after 7 days without activity, and the threshold is a setting. Active developers is a headcount, not a ranking: there are no per-person tables.</p>
        </li>
      </ul>
    </div>
  </section>

  <!-- 5 · Deep-dive 1: stats per repo ======================================== -->
  <section id="repo" aria-labelledby="repo-title" class="relative isolate overflow-hidden border-t border-border/60">
    <div aria-hidden="true" class="side-glow pointer-events-none absolute top-1/4 right-0 -z-10 h-96 w-2/3"></div>
    <div class="mx-auto grid grid-cols-1 max-w-6xl items-center gap-12 px-4 py-20 sm:px-6 sm:py-28 lg:grid-cols-12 lg:gap-16">
      <div class="lg:col-span-5">
        @include('landing.partials.feature-eyebrow', ['feature' => App\Enums\LandingFeature::Repository, 'text' => 'Flight recorder · per repository'])
        <h2 id="repo-title" class="mt-4 font-display text-3xl font-semibold leading-tight tracking-tight text-balance text-text sm:text-4xl">
          See where every pull request spends its time.
        </h2>
        <p class="mt-5 text-base leading-relaxed text-muted">
          Houston reads a repo's pull requests, reviews, Actions runs and deployments, and splits each PR's flight into coding, pickup, review and merge delay. In working hours, so a PR opened on Friday evening doesn't look two days slower.
        </p>
        <ul class="mt-8 space-y-4 text-sm leading-relaxed text-text sm:text-base">
          <li class="flex gap-3">
            <svg aria-hidden="true" viewBox="0 0 20 20" class="mt-0.5 size-5 shrink-0 text-accent" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 10.5l3.5 3.5 7.5-8"/></svg>
            DORA metrics compared with the previous period
          </li>
          <li class="flex gap-3">
            <svg aria-hidden="true" viewBox="0 0 20 20" class="mt-0.5 size-5 shrink-0 text-accent" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 10.5l3.5 3.5 7.5-8"/></svg>
            Time to merge, pickup and review in working hours, bots excluded
          </li>
          <li class="flex gap-3">
            <svg aria-hidden="true" viewBox="0 0 20 20" class="mt-0.5 size-5 shrink-0 text-accent" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 10.5l3.5 3.5 7.5-8"/></svg>
            The slowest merges listed, so you can open the PR that dragged the tail out
          </li>
        </ul>
        <p class="mt-8 border-t border-border pt-6 text-sm leading-relaxed text-muted">
          Also on the page: trends, the time-to-merge distribution, CI health and work in progress, for the last 7 days up to 12 months, or a custom range.
        </p>
      </div>

      <div class="lg:col-span-7">
        <div class="relative">
          <figure class="window-shadow relative overflow-hidden rounded-2xl border border-border-strong bg-surface motion-safe:animate-rise">
            <div class="flex items-center gap-2 border-b border-border bg-surface-2 px-4 py-3" aria-hidden="true">
              <span class="size-2.5 rounded-md bg-skeleton-hi"></span>
              <span class="size-2.5 rounded-md bg-skeleton-hi"></span>
              <span class="size-2.5 rounded-md bg-skeleton-hi"></span>
            </div>
            <div class="relative aspect-4/3">
            <img src="/landing/screenshots/repo-where-time-goes.png" width="2528" height="1896" alt="Where the time goes for laravel/framework, the time-to-merge distribution, and the four DORA metrics compared with the previous period, and releases with the merged PRs not shipped yet" class="absolute inset-0 size-full object-cover object-top" loading="lazy">
          </div>
          </figure>
        </div>
      </div>
    </div>
  </section>

  <!-- 6 · Deep-dive 2: before and after an event ============================ -->
  <section id="events" aria-labelledby="events-title" class="relative isolate overflow-hidden border-t border-border/60">
    <div aria-hidden="true" class="accent-glow pointer-events-none absolute top-1/4 left-0 -z-10 h-96 w-2/3"></div>
    <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28">
      <div class="grid grid-cols-1 gap-10 lg:grid-cols-12 lg:items-end lg:gap-16">
        <div class="lg:col-span-7">
          @include('landing.partials.feature-eyebrow', ['feature' => App\Enums\LandingFeature::Events, 'text' => 'Mission log · before and after'])
          <h2 id="events-title" class="mt-4 font-display text-3xl font-semibold leading-tight tracking-tight text-balance text-text sm:text-4xl">
            Log the change. Then see if it helped.
          </h2>
          <p class="mt-5 text-base leading-relaxed text-muted">
            Introduced AI code reviews, switched tools, set up a Slack bot that nudges reviewers: add it as an event and it shows as a line on every timeline. Click the line to compare the time since with the equally long stretch before it.
          </p>
        </div>
        <ul class="space-y-4 border-t border-border pt-6 text-sm leading-relaxed text-text lg:col-span-5 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-10">
          <li class="flex gap-3">
            <svg aria-hidden="true" viewBox="0 0 20 20" class="mt-0.5 size-5 shrink-0 text-accent" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 10.5l3.5 3.5 7.5-8"/></svg>
            On every trend chart, for every repository and team
          </li>
          <li class="flex gap-3">
            <svg aria-hidden="true" viewBox="0 0 20 20" class="mt-0.5 size-5 shrink-0 text-accent" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 10.5l3.5 3.5 7.5-8"/></svg>
            The days since against the same number of days before, so the comparison is like for like
          </li>
          <li class="flex gap-3">
            <svg aria-hidden="true" viewBox="0 0 20 20" class="mt-0.5 size-5 shrink-0 text-accent" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 10.5l3.5 3.5 7.5-8"/></svg>
            Headline metrics before and after, marked better or worse, with the charts split at the event
          </li>
        </ul>
      </div>

      <figure class="window-shadow relative mt-12 overflow-hidden rounded-2xl border border-border-strong bg-surface motion-safe:animate-rise">
        <div class="flex items-center gap-3 border-b border-border bg-surface-2 px-4 py-3">
          <div class="flex gap-2" aria-hidden="true">
            <span class="size-2.5 rounded-md bg-skeleton-hi"></span>
            <span class="size-2.5 rounded-md bg-skeleton-hi"></span>
            <span class="size-2.5 rounded-md bg-skeleton-hi"></span>
          </div>
          <div class="mx-auto hidden rounded-md border border-border bg-bg/60 px-4 py-1 font-mono text-xs text-muted sm:block">localhost:8000/repos/acme/api?event=2</div>
          <div class="w-10"></div>
        </div>
        <img src="/landing/screenshots/timeline-events.png" width="2560" height="1300" alt="Comparing the 61 days since introducing AI code reviews with the 61 days before: time to merge 1.3 days to 10 hours, review time 12 hours to 2 hours 54 minutes, merged pull requests 163 to 238, and throughput and time to merge charts split at the event" class="block h-auto w-full" loading="lazy">
      </figure>
      <p class="mt-6 text-sm leading-relaxed text-muted">
        Add events under <span class="text-text">Settings → Events</span>. The screenshot uses the demo data that ships with Houston.
      </p>
    </div>
  </section>

  <!-- 7 · Deep-dive 3: stats per team ======================================== -->
  <section id="teams" aria-labelledby="team-title" class="relative isolate overflow-hidden border-t border-border/60">
    <div aria-hidden="true" class="accent-glow pointer-events-none absolute top-1/3 left-0 -z-10 h-96 w-2/3"></div>
    <div class="mx-auto grid grid-cols-1 max-w-6xl items-center gap-12 px-4 py-20 sm:px-6 sm:py-28 lg:grid-cols-12 lg:gap-16">
      <div class="lg:order-2 lg:col-span-5">
        @include('landing.partials.feature-eyebrow', ['feature' => App\Enums\LandingFeature::Teams, 'text' => 'Per team'])
        <h2 id="team-title" class="mt-4 font-display text-3xl font-semibold leading-tight tracking-tight text-balance text-text sm:text-4xl">
          Report on a team, not a repo.
        </h2>
        <p class="mt-5 text-base leading-relaxed text-muted">
          A team is a group of people whose pull requests are reported on together, across every watched repository. Memberships have join and leave dates, so work counts for the team someone was on when they did it.
        </p>
        <ul class="mt-8 space-y-4 text-sm leading-relaxed text-text sm:text-base">
          <li class="flex gap-3">
            <svg aria-hidden="true" viewBox="0 0 20 20" class="mt-0.5 size-5 shrink-0 text-accent" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 10.5l3.5 3.5 7.5-8"/></svg>
            Across every watched repository, not one at a time
          </li>
          <li class="flex gap-3">
            <svg aria-hidden="true" viewBox="0 0 20 20" class="mt-0.5 size-5 shrink-0 text-accent" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 10.5l3.5 3.5 7.5-8"/></svg>
            History follows who was on the team, so a reshuffle doesn't rewrite last quarter
          </li>
          <li class="flex gap-3">
            <svg aria-hidden="true" viewBox="0 0 20 20" class="mt-0.5 size-5 shrink-0 text-accent" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 10.5l3.5 3.5 7.5-8"/></svg>
            The same pull request sections as a repo report: where the time goes, trends, flow and review quality, work in progress, slowest merges
          </li>
        </ul>
        <p class="mt-8 border-t border-border pt-6 text-sm leading-relaxed text-muted">
          Set teams up under <span class="text-text">Settings → Teams</span> and add people by their GitHub login.
        </p>
      </div>

      <div class="lg:order-1 lg:col-span-7">
        <figure class="window-shadow relative overflow-hidden rounded-2xl border border-border-strong bg-surface motion-safe:animate-rise">
          <div class="flex items-center gap-3 border-b border-border bg-surface-2 px-4 py-3">
            <div class="flex gap-2" aria-hidden="true">
              <span class="size-2.5 rounded-md bg-skeleton-hi"></span>
              <span class="size-2.5 rounded-md bg-skeleton-hi"></span>
              <span class="size-2.5 rounded-md bg-skeleton-hi"></span>
            </div>
            <div class="mx-auto hidden rounded-md border border-border bg-bg/60 px-4 py-1 font-mono text-xs text-muted sm:block">localhost:8000/teams/space-engineers</div>
            <div class="w-10"></div>
          </div>
          <div class="relative aspect-16/10">
            <img src="/landing/screenshots/team-report.png" width="2880" height="1800" alt="The Space Engineers team report in Houston: time to merge, pickup and review time with ratings, and where the time goes, across every repository the team works in" class="absolute inset-0 size-full object-cover object-top" loading="lazy">
          </div>
        </figure>

        <!-- How memberships work: a diagram, not product data -->
        <div class="mt-5 rounded-2xl border border-border bg-surface-2 p-5 sm:p-6">
          <p class="font-mono text-xs tracking-widest text-muted uppercase">How memberships count</p>
          <div aria-hidden="true" class="mt-4 space-y-3">
            <div class="grid grid-cols-4 items-center gap-3 sm:grid-cols-6">
              <span class="font-mono text-xs text-muted">member A</span>
              <div class="col-span-3 h-2 rounded-md bg-primary/60 sm:col-span-5"></div>
            </div>
            <div class="grid grid-cols-4 items-center gap-3 sm:grid-cols-6">
              <span class="font-mono text-xs text-muted">member B</span>
              <div class="col-span-3 flex sm:col-span-5"><div class="h-2 w-3/5 rounded-md bg-primary/60"></div><div class="ml-1 h-2 flex-1 rounded-md border border-dashed border-border-strong"></div></div>
            </div>
            <div class="grid grid-cols-4 items-center gap-3 sm:grid-cols-6">
              <span class="font-mono text-xs text-muted">member C</span>
              <div class="col-span-3 flex sm:col-span-5"><div class="h-2 w-2/5 rounded-md border border-dashed border-border-strong"></div><div class="ml-1 h-2 flex-1 rounded-md bg-accent/60"></div></div>
            </div>
          </div>
          <p class="mt-4 text-sm leading-relaxed text-muted">B left halfway through, C joined later. Each person's PRs count for the team only while they were on it.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- 8 · Deep-dive 4: MCP ==================================================== -->
  <section id="mcp" aria-labelledby="mcp-title" class="relative isolate overflow-hidden border-y border-border bg-surface-2">
    <div aria-hidden="true" class="side-glow pointer-events-none absolute top-0 right-0 -z-10 h-96 w-1/2"></div>
    <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28">
      <div class="grid grid-cols-1 items-start gap-12 lg:grid-cols-12 lg:gap-16">
        <div class="lg:col-span-5">
          @include('landing.partials.feature-eyebrow', ['feature' => App\Enums\LandingFeature::Mcp, 'text' => 'CAPCOM · MCP'])
          <h2 id="mcp-title" class="mt-4 font-display text-3xl font-semibold leading-tight tracking-tight text-balance text-text sm:text-4xl">
            Give your AI assistant a line to mission control.
          </h2>
          <p class="mt-5 text-base leading-relaxed text-muted">
            Houston ships a read-only MCP server. Connect Claude Code or any MCP client and it becomes your CAPCOM: ask about cycle time, review load or a bad week in CI. The assistant reads the same reports you do, including the previous period to compare against.
          </p>
          <ul class="mt-8 space-y-4 text-sm leading-relaxed text-text sm:text-base">
            <li class="flex gap-3">
              <svg aria-hidden="true" viewBox="0 0 20 20" class="mt-0.5 size-5 shrink-0 text-accent" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 10.5l3.5 3.5 7.5-8"/></svg>
              3 read-only tools and a prompt that walks through a full review
            </li>
            <li class="flex gap-3">
              <svg aria-hidden="true" viewBox="0 0 20 20" class="mt-0.5 size-5 shrink-0 text-accent" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 10.5l3.5 3.5 7.5-8"/></svg>
              <span>Locally over stdio with <code class="inline-code whitespace-nowrap">php artisan mcp:start houston</code></span>
            </li>
            <li class="flex gap-3">
              <svg aria-hidden="true" viewBox="0 0 20 20" class="mt-0.5 size-5 shrink-0 text-accent" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 10.5l3.5 3.5 7.5-8"/></svg>
              <span>Over HTTP at <code class="inline-code">/mcp</code> with a bearer token; the endpoint stays off until you set one</span>
            </li>
          </ul>
          <div class="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm font-medium">
            <a href="{{ route('landing.install') }}#mcp" class="rounded-md text-primary transition-colors hover:text-text">Install guide: MCP →</a>
            <a href="https://github.com/vistik/Houston" class="rounded-md text-muted transition-colors hover:text-text">View on GitHub →</a>
          </div>
        </div>

        <!-- Split terminal: connect your client -->
        <div class="lg:sticky lg:top-24 lg:col-span-7">
          <div class="window-shadow overflow-hidden rounded-2xl border border-border-strong bg-bg">
            <div class="flex items-center gap-3 border-b border-border bg-surface px-3 sm:px-4">
              <div class="hidden gap-2 sm:flex" aria-hidden="true">
                <span class="size-2.5 rounded-md bg-skeleton-hi"></span>
                <span class="size-2.5 rounded-md bg-skeleton-hi"></span>
                <span class="size-2.5 rounded-md bg-skeleton-hi"></span>
              </div>
              <div role="tablist" aria-label="MCP client" data-tabs="mcp-client" class="flex min-w-0 flex-1 gap-1 overflow-x-auto py-2 sm:ml-2">
                <button type="button" role="tab" id="tab-claude" aria-controls="panel-claude" aria-selected="true" class="shrink-0 rounded-md px-3 py-1.5 font-mono text-xs whitespace-nowrap text-muted transition-colors hover:text-text aria-selected:bg-surface-3 aria-selected:text-text">Claude Code</button>
                <button type="button" role="tab" id="tab-json" aria-controls="panel-json" aria-selected="false" tabindex="-1" class="shrink-0 rounded-md px-3 py-1.5 font-mono text-xs whitespace-nowrap text-muted transition-colors hover:text-text aria-selected:bg-surface-3 aria-selected:text-text">Any MCP client (JSON)</button>
                <button type="button" role="tab" id="tab-http" aria-controls="panel-http" aria-selected="false" tabindex="-1" class="shrink-0 rounded-md px-3 py-1.5 font-mono text-xs whitespace-nowrap text-muted transition-colors hover:text-text aria-selected:bg-surface-3 aria-selected:text-text">Over HTTP</button>
              </div>
            </div>

            <div id="panel-claude" role="tabpanel" aria-labelledby="tab-claude" tabindex="0" class="p-4 sm:p-6">
              <p class="text-sm text-muted">Register the local server with Claude Code. Use the absolute path to your checkout.</p>
              <div class="mt-4 flex items-start gap-2 rounded-xl border border-border bg-surface-2 p-2">
                <pre class="min-w-0 flex-1 px-3 py-2 font-mono text-sm leading-relaxed whitespace-pre-wrap break-words text-text"><code><span aria-hidden="true" class="select-none text-muted">$ </span>claude mcp add houston -- php /path/to/Houston/artisan mcp:start houston</code></pre>
                <button type="button" data-copy="claude mcp add houston -- php /path/to/Houston/artisan mcp:start houston" aria-label="Copy the Claude Code command" class="grid size-10 shrink-0 place-items-center rounded-lg border border-border bg-surface text-muted transition hover:border-border-strong hover:text-text">
                  <svg data-icon="copy" aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/></svg>
                  <svg data-icon="check" aria-hidden="true" viewBox="0 0 24 24" class="hidden size-4 text-accent" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                </button>
              </div>
              <p class="mt-4 font-mono text-xs text-muted">stdio · read-only · no token needed locally</p>
            </div>

            <div id="panel-json" role="tabpanel" aria-labelledby="tab-json" tabindex="0" hidden class="p-4 sm:p-6">
              <p class="text-sm text-muted">Add this to your client's MCP config (stdio).</p>
              <div class="mt-4 flex items-start gap-2 rounded-xl border border-border bg-surface-2 p-2">
                <pre class="min-w-0 flex-1 overflow-x-auto px-3 py-2 font-mono text-sm leading-relaxed text-text"><code id="mcp-json">{
  "mcpServers": {
    "houston": {
      "command": "php",
      "args": ["/path/to/Houston/artisan", "mcp:start", "houston"]
    }
  }
}</code></pre>
                <button type="button" data-copy-target="mcp-json" aria-label="Copy the JSON config" class="grid size-10 shrink-0 place-items-center rounded-lg border border-border bg-surface text-muted transition hover:border-border-strong hover:text-text">
                  <svg data-icon="copy" aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/></svg>
                  <svg data-icon="check" aria-hidden="true" viewBox="0 0 24 24" class="hidden size-4 text-accent" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                </button>
              </div>
            </div>

            <div id="panel-http" role="tabpanel" aria-labelledby="tab-http" tabindex="0" hidden class="p-4 sm:p-6">
              <p class="text-sm text-muted">1. On the server, set a token in <code class="inline-code">.env</code>. Any long random string works.</p>
              <div class="mt-3 rounded-xl border border-border bg-surface-2 p-2">
                <pre class="overflow-x-auto px-3 py-2 font-mono text-sm leading-relaxed text-text"><code>HOUSTON_MCP_TOKEN=<span class="text-muted">[a long random string]</span></code></pre>
              </div>
              <p class="mt-5 text-sm text-muted">2. Point your client at <code class="inline-code">/mcp</code> and send it as a bearer token.</p>
              <div class="mt-3 flex items-start gap-2 rounded-xl border border-border bg-surface-2 p-2">
                <pre class="min-w-0 flex-1 overflow-x-auto px-3 py-2 font-mono text-sm leading-relaxed text-text"><code><span aria-hidden="true" class="select-none text-muted">$ </span>claude mcp add --transport http houston http://localhost:8000/mcp \
    --header "Authorization: Bearer $HOUSTON_MCP_TOKEN"</code></pre>
                <button type="button" data-copy="claude mcp add --transport http houston http://localhost:8000/mcp --header &quot;Authorization: Bearer $HOUSTON_MCP_TOKEN&quot;" aria-label="Copy the HTTP command" class="grid size-10 shrink-0 place-items-center rounded-lg border border-border bg-surface text-muted transition hover:border-border-strong hover:text-text">
                  <svg data-icon="copy" aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/></svg>
                  <svg data-icon="check" aria-hidden="true" viewBox="0 0 24 24" class="hidden size-4 text-accent" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                </button>
              </div>
              <p class="mt-4 font-mono text-xs text-muted">No token set: /mcp answers 404 · wrong token: 401</p>
            </div>
          </div>

          <!-- SCREENSHOT (optional): screenshots/mcp-conversation.png could replace this mock exchange -->
          <figure aria-labelledby="mcp-example-caption" class="mt-6 rounded-2xl border border-border bg-bg p-4 sm:p-6">
            <figcaption id="mcp-example-caption" class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
              <span class="font-display text-lg font-semibold tracking-tight whitespace-nowrap text-text">An example exchange</span>
              <span class="rounded-md border border-dashed border-border-strong px-2 py-0.5 font-mono text-xs text-muted">Example output · values are placeholders</span>
            </figcaption>
            <ol class="mt-5 space-y-4 text-sm leading-relaxed">
              <li class="ml-auto max-w-md rounded-xl rounded-br-md bg-primary-soft px-4 py-3 text-text">
                <span class="sr-only">You: </span>Houston, how did review time on laravel/framework change over the last 30 days?
              </li>
              <li class="rounded-xl border border-border bg-surface-2 px-4 py-3">
                <p class="flex flex-wrap items-center gap-2 font-mono text-xs text-muted">
                  <span class="text-accent">● calls</span>
                  <code class="text-text">get-velocity-report</code>
                  <span>read-only</span>
                </p>
                <pre class="mt-2 overflow-x-auto font-mono text-xs text-muted"><code>{ "repository": "laravel/framework", "days": 30 }</code></pre>
              </li>
              <li class="max-w-lg rounded-xl rounded-bl-md border border-border bg-surface px-4 py-3 text-text">
                <span class="sr-only">Assistant: </span>Median review time was <span class="font-mono text-primary">[x h]</span> over the last 30 days, against <span class="font-mono text-primary">[y h]</span> in the 30 days before, counted in working hours. Pickup time moved from <span class="font-mono text-primary">[a h]</span> to <span class="font-mono text-primary">[b h]</span>. The slowest merge, <span class="font-mono text-primary">#[number]</span>, waited <span class="font-mono text-primary">[n days]</span> for its first review. Want me to list the PRs that waited longest?
              </li>
            </ol>
          </figure>
        </div>
      </div>

      <!-- The server's tools and prompt, as a row under both columns so they end level -->
      <div class="mt-16">
        <h3 class="font-display text-lg font-semibold tracking-tight text-text">What the server offers</h3>
        <dl class="mt-5 grid gap-x-8 sm:grid-cols-2 lg:grid-cols-4">
          <div class="border-t border-border py-4">
            <dt><code class="font-mono text-sm text-primary">list-repositories</code> <span class="ml-2 font-mono text-xs text-muted">tool</span></dt>
            <dd class="mt-1.5 text-sm leading-relaxed text-muted">Every watched repository with headline velocity KPIs, a daily merge trend and default branch CI status.</dd>
          </div>
          <div class="border-t border-border py-4">
            <dt><code class="font-mono text-sm text-primary">get-velocity-report</code> <span class="ml-2 font-mono text-xs text-muted">tool</span></dt>
            <dd class="mt-1.5 text-sm leading-relaxed text-muted">The full report for one repository: every KPI with its previous-period value, the cycle time breakdown, CI workflows, slowest merges and DORA delivery metrics.</dd>
          </div>
          <div class="border-t border-border py-4">
            <dt><code class="font-mono text-sm text-primary">list-pull-requests</code> <span class="ml-2 font-mono text-xs text-muted">tool</span></dt>
            <dd class="mt-1.5 text-sm leading-relaxed text-muted">Cached pull requests with lifecycle timestamps, size, discussion counts, labels and reviews, to drill into the examples behind a metric.</dd>
          </div>
          <div class="border-t border-border py-4">
            <dt><code class="font-mono text-sm text-accent">give-feedback</code> <span class="ml-2 font-mono text-xs text-muted">prompt</span></dt>
            <dd class="mt-1.5 text-sm leading-relaxed text-muted">Review a repository's engineering velocity and give the team concrete, evidence-backed feedback.</dd>
          </div>
        </dl>
      </div>
    </div>
  </section>

  <!-- 9 · Wall display ======================================================== -->
  <section id="wall" aria-labelledby="wall-title" class="relative isolate overflow-hidden">
    <div aria-hidden="true" class="hero-glow pointer-events-none absolute top-1/3 left-1/2 -z-10 h-96 w-full max-w-3xl -translate-x-1/2 opacity-60"></div>
    <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28">
      <div class="mx-auto max-w-2xl text-center">
        @include('landing.partials.feature-eyebrow', ['feature' => App\Enums\LandingFeature::Wall, 'text' => 'The big board'])
        <h2 id="wall-title" class="mt-4 font-display text-3xl font-semibold leading-tight tracking-tight text-balance text-text sm:text-4xl">
          Put it on the big board.
        </h2>
        <p class="mt-5 text-base leading-relaxed text-muted">
          A hands-off, auto-rotating view meant for a screen on the wall, like the front screens in mission control. It is always dark, whatever theme the app uses, and shows one repository at a time.
        </p>
      </div>

      <!-- TV -->
      <div class="mx-auto mt-14 max-w-4xl">
        <div class="window-shadow rounded-2xl border border-border-strong bg-surface p-2 sm:p-3">
          <figure class="relative aspect-video overflow-hidden rounded-lg border border-border bg-bg">
            <img src="/landing/screenshots/wall-dark.png" width="3200" height="1800" alt="The Houston wall display for laravel/framework over the last 14 days: time to merge, pickup time, merged PRs and CI failure rate with their trends and benchmark ratings, where the time goes and the PRs waiting for review" class="absolute inset-0 size-full object-cover object-top" loading="lazy">
          </figure>
        </div>
        <div aria-hidden="true" class="tv-stand mx-auto h-6 w-40 bg-border-strong sm:h-8 sm:w-56"></div>
        <div aria-hidden="true" class="mx-auto h-1.5 w-56 rounded-md bg-border-strong sm:w-72"></div>
      </div>

      <dl class="mx-auto mt-14 grid max-w-4xl gap-px overflow-hidden rounded-2xl border border-border bg-border sm:grid-cols-2">
        <div class="bg-surface p-5">
          <dt class="text-sm font-semibold text-text">Rotates every 20 s</dt>
          <dd class="mt-2 text-sm leading-relaxed text-muted">Anywhere from 5 to 600 s with <code class="inline-code">?rotate=</code></dd>
        </div>
        <div class="bg-surface p-5">
          <dt class="text-sm font-semibold text-text">Step through it</dt>
          <dd class="mt-2 text-sm leading-relaxed text-muted"><kbd class="kbd">←</kbd> <kbd class="kbd">→</kbd> previous and next</dd>
        </div>
        <div class="bg-surface p-5">
          <dt class="text-sm font-semibold text-text">Pause</dt>
          <dd class="mt-2 text-sm leading-relaxed text-muted"><kbd class="kbd">Space</kbd> holds the current slide</dd>
        </div>
        <div class="bg-surface p-5">
          <dt class="text-sm font-semibold text-text">All repos or one</dt>
          <dd class="mt-2 text-sm leading-relaxed text-muted"><code class="inline-code">/wall</code> for every repo, <code class="inline-code">/repos/{owner}/{name}/wall</code> for one</dd>
        </div>
      </dl>
    </div>
  </section>

  <!-- 10 · Custom benchmarks =================================================== -->
  <section id="benchmarks" aria-labelledby="benchmarks-title" class="relative isolate overflow-hidden border-t border-border/60">
    <div class="mx-auto grid grid-cols-1 max-w-6xl items-start gap-12 px-4 py-20 sm:px-6 sm:py-28 lg:grid-cols-12 lg:gap-16">
      <div class="lg:order-2 lg:col-span-5 lg:self-center">
        @include('landing.partials.feature-eyebrow', ['feature' => App\Enums\LandingFeature::Benchmarks, 'text' => 'Go / no-go'])
        <h2 id="benchmarks-title" class="mt-4 font-display text-3xl font-semibold leading-tight tracking-tight text-balance text-text sm:text-4xl">
          Set your own go / no-go criteria.
        </h2>
        <p class="mt-5 text-base leading-relaxed text-muted">
          Set targets for any metric and Houston rates it as elite, good, medium or low wherever it appears. Counts of work are benchmarked per week, so one target holds for every reporting period.
        </p>
        <ul aria-label="Rating tiers" class="mt-6 flex flex-wrap gap-2 text-xs font-medium">
          <li class="rounded-full bg-accent px-2.5 py-1 text-bg">Elite</li>
          <li class="rounded-full bg-accent-soft px-2.5 py-1 text-accent">Good</li>
          <li class="rounded-full bg-warn-soft px-2.5 py-1 text-warn">Medium</li>
          <li class="rounded-full bg-bad-soft px-2.5 py-1 text-bad">Low</li>
        </ul>
        <p class="mt-6 text-base leading-relaxed text-muted">
          It is opt-in: nothing is rated until you set targets. When you want a starting point, 31 standards are one click away: <span class="text-text">4 from DORA, 4 from LinearB and 23 suggested targets</span>.
        </p>
      </div>

      <div class="lg:order-1 lg:col-span-7">
        <figure class="window-shadow relative overflow-hidden rounded-2xl border border-border-strong bg-surface">
          <div class="flex items-center gap-3 border-b border-border bg-surface-2 px-4 py-3">
            <div class="flex gap-2" aria-hidden="true">
              <span class="size-2.5 rounded-md bg-skeleton-hi"></span>
              <span class="size-2.5 rounded-md bg-skeleton-hi"></span>
              <span class="size-2.5 rounded-md bg-skeleton-hi"></span>
            </div>
            <div class="mx-auto hidden rounded-md border border-border bg-bg/60 px-4 py-1 font-mono text-xs text-muted sm:block">localhost:8000/settings/benchmarks</div>
            <div class="w-10"></div>
          </div>
          <div class="relative aspect-16/10">
            <img src="/landing/screenshots/benchmarks-settings.png" width="2880" height="1800" alt="Houston benchmark settings: elite, good and medium thresholds per metric, with LinearB and suggested standards" class="absolute inset-0 size-full object-cover object-top" loading="lazy">
          </div>
        </figure>

        <div class="mt-5 overflow-hidden rounded-2xl border border-border bg-surface">
          <table class="w-full text-left text-xs sm:text-sm">
            <caption class="border-b border-border px-4 py-3 text-left font-mono text-xs tracking-widest text-muted uppercase sm:px-5">The 4 DORA standards</caption>
            <thead class="font-mono text-xs text-muted">
              <tr class="border-b border-border">
                <th scope="col" class="px-4 py-3 font-medium sm:px-5">Metric</th>
                <th scope="col" class="px-2 py-3 font-medium text-accent sm:px-3">Elite</th>
                <th scope="col" class="px-2 py-3 font-medium text-accent/70 sm:px-3">Good</th>
                <th scope="col" class="px-2 py-3 font-medium text-warn sm:px-3">Medium</th>
                <th scope="col" class="px-2 py-3 font-medium text-bad sm:px-3">Low</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border font-mono text-text">
              <tr><th scope="row" class="px-4 py-3 font-sans font-normal text-muted sm:px-5">Deploy frequency</th><td class="px-2 py-3 sm:px-3">1/day</td><td class="px-2 py-3 sm:px-3">1/week</td><td class="px-2 py-3 sm:px-3">1/month</td><td class="px-2 py-3 sm:px-3">&lt; 1/month</td></tr>
              <tr><th scope="row" class="px-4 py-3 font-sans font-normal text-muted sm:px-5">Lead time to production</th><td class="px-2 py-3 sm:px-3">1 d</td><td class="px-2 py-3 sm:px-3">7 d</td><td class="px-2 py-3 sm:px-3">30 d</td><td class="px-2 py-3 sm:px-3">&gt; 30 d</td></tr>
              <tr><th scope="row" class="px-4 py-3 font-sans font-normal text-muted sm:px-5">Change failure rate</th><td class="px-2 py-3 sm:px-3">5%</td><td class="px-2 py-3 sm:px-3">10%</td><td class="px-2 py-3 sm:px-3">15%</td><td class="px-2 py-3 sm:px-3">&gt; 15%</td></tr>
              <tr><th scope="row" class="px-4 py-3 font-sans font-normal text-muted sm:px-5">Time to restore</th><td class="px-2 py-3 sm:px-3">1 h</td><td class="px-2 py-3 sm:px-3">1 d</td><td class="px-2 py-3 sm:px-3">7 d</td><td class="px-2 py-3 sm:px-3">&gt; 7 d</td></tr>
            </tbody>
          </table>
          <p class="border-t border-border px-4 py-3 text-xs leading-relaxed text-muted sm:px-5">Thresholds a value must meet for each tier; anything worse than medium rates low.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- 11 · How it measures ===================================================== -->
  <section id="how-it-measures" aria-labelledby="method-title" class="border-y border-border bg-surface-2">
    <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28">
      <div class="max-w-2xl">
        @include('landing.partials.feature-eyebrow', ['feature' => App\Enums\LandingFeature::Method, 'text' => 'Flight rules'])
        <h2 id="method-title" class="mt-4 font-display text-3xl font-semibold leading-tight tracking-tight text-balance text-text sm:text-4xl">
          How it measures, in plain words.
        </h2>
        <p class="mt-5 text-base leading-relaxed text-muted">
          A metric is only useful if the team believes it. These are the flight rules, and most of them are settings.
        </p>
      </div>

      <div class="mt-12 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        <!-- Working hours, with a week strip -->
        <article class="rounded-2xl border border-border bg-surface p-6 md:col-span-2">
          <div class="grid gap-6 lg:grid-cols-2 lg:items-center">
            <div>
              <h3 class="font-display text-lg font-semibold tracking-tight text-text">Working time only</h3>
              <p class="mt-3 text-sm leading-relaxed text-muted">
                Durations that measure people (pickup, review, time to merge, recovery) count working time, so a PR opened on Friday evening doesn't look two days slower than one opened on Monday. Working days and hours are settings, and they count in the timezone you pick for your reports; the default is Monday to Friday, UTC. CI durations stay wall-clock.
              </p>
            </div>
            <figure aria-labelledby="week-caption">
              <div aria-hidden="true" class="grid grid-cols-7 gap-1.5 font-mono text-xs">
                <div class="text-center text-muted">Mon</div><div class="text-center text-muted">Tue</div><div class="text-center text-muted">Wed</div><div class="text-center text-muted">Thu</div><div class="text-center text-muted">Fri</div><div class="text-center text-muted">Sat</div><div class="text-center text-muted">Sun</div>
                <div class="h-10 rounded-md bg-primary/50"></div>
                <div class="h-10 rounded-md border border-border bg-surface-2"></div>
                <div class="h-10 rounded-md border border-border bg-surface-2"></div>
                <div class="h-10 rounded-md border border-border bg-surface-2"></div>
                <div class="relative h-10 overflow-hidden rounded-md border border-border bg-surface-2"><div class="absolute inset-y-0 right-0 w-1/4 bg-primary/50"></div></div>
                <div class="h-10 rounded-md border border-dashed border-border-strong"></div>
                <div class="h-10 rounded-md border border-dashed border-border-strong"></div>
              </div>
              <figcaption id="week-caption" class="mt-3 text-xs leading-relaxed text-muted">A PR opened late Friday and reviewed Monday morning: only the blue part counts. The weekend doesn't.</figcaption>
            </figure>
          </div>
        </article>

        <article class="rounded-2xl border border-border bg-surface p-6">
          <h3 class="font-display text-lg font-semibold tracking-tight text-text">Bots excluded</h3>
          <p class="mt-3 text-sm leading-relaxed text-muted">Pull requests from Dependabot, Renovate and other bots are left out of the metrics, so they don't skew people numbers. The bot backlog is reported on its own.</p>
        </article>

        <article class="rounded-2xl border border-border bg-surface p-6">
          <h3 class="font-display text-lg font-semibold tracking-tight text-text">Every KPI against the previous period</h3>
          <p class="mt-3 text-sm leading-relaxed text-muted">Each number sits next to the same metric over the equally long period before it. "Last 30 days" is today and the 29 days before it, from midnight in your timezone, so every bar on a chart is a whole day and the bars add up to the number. Metrics that sum up one value per PR show the median and the average, so a skewed spread shows.</p>
        </article>

        <article class="rounded-2xl border border-border bg-surface p-6">
          <h3 class="font-display text-lg font-semibold tracking-tight text-text">Nobody ranked</h3>
          <p class="mt-3 text-sm leading-relaxed text-muted">There are no author or reviewer tables. Houston reports on teams and repositories, never on individual people.</p>
        </article>

        <article class="rounded-2xl border border-border bg-surface p-6">
          <h3 class="font-display text-lg font-semibold tracking-tight text-text">Change failure rate, inferred</h3>
          <p class="mt-3 text-sm leading-relaxed text-muted">A deployment counts as failed when the next one ships a revert, hotfix or rollback PR, matched on title, branch or labels. The pattern is configurable. There is no incident data behind it.</p>
        </article>

        <article class="rounded-2xl border border-border bg-surface p-6 md:col-span-2 lg:col-span-3 lg:flex lg:items-baseline lg:gap-10">
          <h3 class="font-display text-lg font-semibold tracking-tight text-text lg:w-1/4 lg:shrink-0">What counts as a deploy</h3>
          <p class="mt-3 text-sm leading-relaxed text-muted lg:mt-0">GitHub Deployments to an environment named like <code class="inline-code">prod</code>, <code class="inline-code">production</code> or <code class="inline-code">live</code>. Repos without them fall back to published GitHub Releases.</p>
        </article>
      </div>
    </div>
  </section>

  <!-- 13 · Run it yourself ===================================================== -->
  <section id="install" aria-labelledby="run-title" class="relative isolate overflow-hidden border-t border-border/60">
    <div aria-hidden="true" class="hero-grid pointer-events-none absolute inset-0 -z-10 opacity-60"></div>
    <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28">
      <div class="max-w-2xl">
        <p class="font-mono text-xs font-medium tracking-widest text-primary uppercase">Launch sequence</p>
        <h2 id="run-title" class="mt-4 font-display text-3xl font-semibold leading-tight tracking-tight text-balance text-text sm:text-4xl">
          T-minus three steps to your first report.
        </h2>
      </div>

      <div class="mt-12 grid grid-cols-1 gap-10 lg:grid-cols-12 lg:gap-16">
        <ol class="space-y-8 lg:col-span-7">
          <li class="flex gap-4 sm:gap-5">
            <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-primary font-mono text-sm font-medium text-on-primary">1</span>
            <div class="min-w-0 flex-1">
              <h3 class="font-display text-lg font-semibold tracking-tight text-text">Clone and set up</h3>
              <div class="mt-3 flex items-start gap-2 rounded-xl border border-border bg-surface-2 p-2">
                <pre class="min-w-0 flex-1 overflow-x-auto px-3 py-2 font-mono text-sm leading-relaxed text-text"><code>git clone https://github.com/vistik/houston.git
cd houston
composer setup</code></pre>
                <button type="button" data-copy="git clone https://github.com/vistik/houston.git &amp;&amp; cd houston &amp;&amp; composer setup" aria-label="Copy the setup commands" class="grid size-10 shrink-0 place-items-center rounded-lg border border-border bg-surface text-muted transition hover:border-border-strong hover:text-text">
                  <svg data-icon="copy" aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/></svg>
                  <svg data-icon="check" aria-hidden="true" viewBox="0 0 24 24" class="hidden size-4 text-accent" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                </button>
              </div>
              <p class="mt-3 text-sm leading-relaxed text-muted"><code class="inline-code">composer setup</code> installs the PHP and Node dependencies, creates <code class="inline-code">.env</code>, generates the app key, runs the migrations and builds the frontend.</p>
            </div>
          </li>
          <li class="flex gap-4 sm:gap-5">
            <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-primary font-mono text-sm font-medium text-on-primary">2</span>
            <div class="min-w-0 flex-1">
              <h3 class="font-display text-lg font-semibold tracking-tight text-text">Connect GitHub</h3>
              <div class="mt-3 flex items-start gap-2 rounded-xl border border-border bg-surface-2 p-2">
                <pre class="min-w-0 flex-1 overflow-x-auto px-3 py-2 font-mono text-sm leading-relaxed text-text"><code><span aria-hidden="true" class="select-none text-muted">$ </span>gh auth login</code></pre>
                <button type="button" data-copy="gh auth login" aria-label="Copy gh auth login" class="grid size-10 shrink-0 place-items-center rounded-lg border border-border bg-surface text-muted transition hover:border-border-strong hover:text-text">
                  <svg data-icon="copy" aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/></svg>
                  <svg data-icon="check" aria-hidden="true" viewBox="0 0 24 24" class="hidden size-4 text-accent" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                </button>
              </div>
              <p class="mt-3 text-sm leading-relaxed text-muted">With <code class="inline-code">GITHUB_TOKEN</code> empty, Houston uses the GitHub CLI's token. On a server, set <code class="inline-code">GITHUB_TOKEN</code> in <code class="inline-code">.env</code> instead.</p>
            </div>
          </li>
          <li class="flex gap-4 sm:gap-5">
            <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-primary font-mono text-sm font-medium text-on-primary">3</span>
            <div class="min-w-0 flex-1">
              <h3 class="font-display text-lg font-semibold tracking-tight text-text">Liftoff</h3>
              <div class="mt-3 flex items-start gap-2 rounded-xl border border-border bg-surface-2 p-2">
                <pre class="min-w-0 flex-1 overflow-x-auto px-3 py-2 font-mono text-sm leading-relaxed text-text"><code><span aria-hidden="true" class="select-none text-muted">$ </span>composer dev</code></pre>
                <button type="button" data-copy="composer dev" aria-label="Copy composer dev" class="grid size-10 shrink-0 place-items-center rounded-lg border border-border bg-surface text-muted transition hover:border-border-strong hover:text-text">
                  <svg data-icon="copy" aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/></svg>
                  <svg data-icon="check" aria-hidden="true" viewBox="0 0 24 24" class="hidden size-4 text-accent" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                </button>
              </div>
              <p class="mt-3 text-sm leading-relaxed text-muted">Starts the web server at <code class="inline-code">http://localhost:8000</code>, the scheduler, a queue worker, a log tail and Vite. Open it and click <span class="text-text">Watch repository</span> and enter <code class="inline-code">owner/name</code>.</p>
            </div>
          </li>
        </ol>

        <div class="space-y-4 lg:col-span-5">
          <div class="rounded-2xl border border-border bg-surface p-6">
            <h3 class="font-display text-lg font-semibold tracking-tight text-text">Requirements</h3>
            <ul class="mt-4 space-y-2 font-mono text-sm text-text">
              <li class="flex justify-between gap-4 border-b border-border pb-2"><span>PHP</span><span class="text-muted">8.5.12+</span></li>
              <li class="flex justify-between gap-4 border-b border-border pb-2"><span>Composer</span><span class="text-muted">2</span></li>
              <li class="flex justify-between gap-4 border-b border-border pb-2"><span>Node + npm</span><span class="text-muted">22</span></li>
              <li class="flex justify-between gap-4"><span>Database</span><span class="text-muted">SQLite by default</span></li>
            </ul>
          </div>
          <div class="rounded-2xl border border-border bg-surface p-6">
            <h3 class="font-display text-lg font-semibold tracking-tight text-text">Keeping it running</h3>
            <p class="mt-3 text-sm leading-relaxed text-muted">Syncs run on the queue, and the scheduler refreshes every watched repo every 15 minutes. On a long-running server, run a queue worker and the scheduler.</p>
          </div>
          <div class="rounded-2xl border border-primary/40 bg-primary-soft p-6">
            <h3 class="flex items-center gap-2 font-display text-lg font-semibold tracking-tight text-text">
              <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5 text-primary" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.5-3 8.5-7 10-4-1.5-7-5.5-7-10V6z"/></svg>
              Authorized personnel only
            </h3>
            <p class="mt-3 text-sm leading-relaxed text-text">Houston asks for a login out of the box; <code class="inline-code">php artisan houston:install</code> creates it. Turn it off on your laptop or a private network if you like; keep it on for a public server.</p>
          </div>
          <a href="{{ route('landing.install') }}" class="inline-flex items-center gap-2 rounded-md pt-2 text-sm font-semibold text-primary transition-colors hover:text-text">Full install guide →</a>
        </div>
      </div>
    </div>
  </section>

  <!-- 14 · FAQ ================================================================= -->
  <section id="faq" aria-labelledby="faq-title" class="border-t border-border bg-surface-2">
    <div class="mx-auto grid grid-cols-1 max-w-6xl gap-10 px-4 py-20 sm:px-6 sm:py-28 lg:grid-cols-12 lg:gap-16">
      <div class="lg:col-span-4">
        <p class="font-mono text-xs font-medium tracking-widest text-primary uppercase">FAQ</p>
        <h2 id="faq-title" class="mt-4 font-display text-3xl font-semibold leading-tight tracking-tight text-balance text-text sm:text-4xl">Pre-flight questions</h2>
        <p class="mt-5 text-sm leading-relaxed text-muted">
          Still have a question? <a href="https://github.com/vistik/Houston/discussions" class="rounded-md text-primary underline-offset-4 hover:underline">Ask in GitHub Discussions</a> <span class="font-mono text-xs">[enable Discussions]</span>
        </p>
      </div>
      <div class="divide-y divide-border border-y border-border lg:col-span-8">
        <details id="faq-free" class="group">
          <summary class="flex cursor-pointer items-center justify-between gap-4 rounded-md py-5 font-display text-base font-semibold text-text sm:text-lg">
            Is it really free?
            <svg aria-hidden="true" viewBox="0 0 20 20" class="size-5 shrink-0 text-muted transition-transform group-open:rotate-45" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10 4v12M4 10h12"/></svg>
          </summary>
          <p class="pb-5 text-sm leading-relaxed text-muted sm:text-base">Yes. Houston is MIT licensed. There is no paid tier, no hosted version and no seat count. No launch fees either.</p>
        </details>
        <details id="faq-data" class="group">
          <summary class="flex cursor-pointer items-center justify-between gap-4 rounded-md py-5 font-display text-base font-semibold text-text sm:text-lg">
            Where does my data live?
            <svg aria-hidden="true" viewBox="0 0 20 20" class="size-5 shrink-0 text-muted transition-transform group-open:rotate-45" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10 4v12M4 10h12"/></svg>
          </summary>
          <p class="pb-5 text-sm leading-relaxed text-muted sm:text-base">On your server. Houston keeps a local cache of pull requests, reviews, workflow runs and deployments in SQLite by default, or in any database Laravel supports. Nothing is sent anywhere but GitHub's API.</p>
        </details>
        <details id="faq-access" class="group">
          <summary class="flex cursor-pointer items-center justify-between gap-4 rounded-md py-5 font-display text-base font-semibold text-text sm:text-lg">
            Who can see the dashboards?
            <svg aria-hidden="true" viewBox="0 0 20 20" class="size-5 shrink-0 text-muted transition-transform group-open:rotate-45" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10 4v12M4 10h12"/></svg>
          </summary>
          <p class="pb-5 text-sm leading-relaxed text-muted sm:text-base">Authorized personnel only: whoever has the login. Houston has one login, on by default: php artisan houston:install creates it, and you can turn it off on your own machine. There are no per-user accounts. The /mcp endpoint has its own token.</p>
        </details>
        <details id="faq-ranking" class="group">
          <summary class="flex cursor-pointer items-center justify-between gap-4 rounded-md py-5 font-display text-base font-semibold text-text sm:text-lg">
            Does it rank developers?
            <svg aria-hidden="true" viewBox="0 0 20 20" class="size-5 shrink-0 text-muted transition-transform group-open:rotate-45" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10 4v12M4 10h12"/></svg>
          </summary>
          <p class="pb-5 text-sm leading-relaxed text-muted sm:text-base">No. Houston reports on teams and repositories, never on individual people.</p>
        </details>
        <details id="faq-gitlab" class="group">
          <summary class="flex cursor-pointer items-center justify-between gap-4 rounded-md py-5 font-display text-base font-semibold text-text sm:text-lg">
            Does it work with GitLab or Bitbucket?
            <svg aria-hidden="true" viewBox="0 0 20 20" class="size-5 shrink-0 text-muted transition-transform group-open:rotate-45" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10 4v12M4 10h12"/></svg>
          </summary>
          <p class="pb-5 text-sm leading-relaxed text-muted sm:text-base">No. Houston reads GitHub only: pull requests, reviews, Actions workflow runs, and Deployments or Releases.</p>
        </details>
        <details id="faq-change-failure-rate" class="group">
          <summary class="flex cursor-pointer items-center justify-between gap-4 rounded-md py-5 font-display text-base font-semibold text-text sm:text-lg">
            How is change failure rate measured?
            <svg aria-hidden="true" viewBox="0 0 20 20" class="size-5 shrink-0 text-muted transition-transform group-open:rotate-45" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10 4v12M4 10h12"/></svg>
          </summary>
          <p class="pb-5 text-sm leading-relaxed text-muted sm:text-base">A deployment counts as a failed change when the next deployment ships a revert, hotfix or rollback pull request, matched on its title, branch or labels. The pattern is configurable. It is inferred from pull requests, not from incident data.</p>
        </details>
        <details id="faq-rate-limits" class="group">
          <summary class="flex cursor-pointer items-center justify-between gap-4 rounded-md py-5 font-display text-base font-semibold text-text sm:text-lg">
            Will it hit GitHub's rate limits?
            <svg aria-hidden="true" viewBox="0 0 20 20" class="size-5 shrink-0 text-muted transition-transform group-open:rotate-45" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10 4v12M4 10h12"/></svg>
          </summary>
          <p class="pb-5 text-sm leading-relaxed text-muted sm:text-base">It syncs every 15 minutes and pauses when fewer than 50 requests are left in the current window, leaving room for anything else that shares the token. Paused syncs resume when the window resets.</p>
        </details>
      </div>
    </div>
  </section>

  <!-- 15 · Final CTA =========================================================== -->
  <section aria-labelledby="cta-title" class="relative isolate overflow-hidden">
    <div aria-hidden="true" class="hero-grid pointer-events-none absolute inset-0 -z-10"></div>
    <div aria-hidden="true" class="starfield pointer-events-none absolute inset-0 -z-10"></div>
    <div aria-hidden="true" class="comets pointer-events-none absolute inset-0 -z-10 overflow-hidden">
      <span style="top: 12%; left: 10%; --angle: 16deg; --duration: 17s; --delay: 5s"></span>
      <span style="top: 6%; left: 60%; --angle: 22deg; --duration: 21s; --delay: 12s; --length: 100px; --travel: 30vw"></span>
    </div>
    <div aria-hidden="true" class="hero-glow pointer-events-none absolute top-1/2 left-1/2 -z-10 h-72 w-full max-w-2xl -translate-x-1/2 -translate-y-1/2 opacity-70"></div>
    <div class="mx-auto max-w-3xl px-4 py-24 text-center sm:px-6 sm:py-32">
      <h2 id="cta-title" class="font-display text-3xl font-semibold leading-tight tracking-tight text-balance text-text sm:text-5xl">
        You are go for launch.
      </h2>
      <p class="mx-auto mt-5 max-w-xl text-base leading-relaxed text-muted">Point it at your repos and see your own numbers. Free and MIT licensed. One GitHub token, one SQLite file, nothing to sign up for.</p>
      <div class="mx-auto mt-8 flex max-w-sm flex-col gap-3 sm:max-w-none sm:flex-row sm:justify-center">
        <a href="https://github.com/vistik/Houston" class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-6 py-3 text-sm font-semibold text-on-primary transition hover:bg-primary/90">
          <svg aria-hidden="true" viewBox="0 0 16 16" class="size-4" fill="currentColor"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg>
          Get it on GitHub
        </a>
        <a href="{{ route('home') }}" class="inline-flex items-center justify-center rounded-lg border border-border-strong bg-surface/60 px-6 py-3 text-sm font-semibold text-text transition hover:border-muted">
          See the demo
        </a>
        <a href="{{ route('landing.install') }}" class="inline-flex items-center justify-center rounded-lg border border-border-strong bg-surface/60 px-6 py-3 text-sm font-semibold text-text transition hover:border-muted">
          Install guide
        </a>
      </div>
    </div>
  </section>
</main>

@include('landing.partials.footer')

<p id="copy-status" aria-live="polite" class="sr-only"></p>
@vite('resources/js/landing.js')
</body>
</html>
