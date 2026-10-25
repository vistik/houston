<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install Houston · Mission control for your engineering team, on your own server</title>
<meta name="description" content="Install Houston on your laptop or server: requirements, composer setup, a GitHub token, the queue worker and scheduler, MCP for AI assistants, and every HOUSTON_* setting. Your data never leaves your server.">
<link rel="canonical" href="{{ route('landing.install') }}">
<meta name="theme-color" content="#0a0c11">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<meta property="og:type" content="article">
<meta property="og:site_name" content="Houston">
<meta property="og:title" content="Install Houston · Mission control for your engineering team">
<meta property="og:description" content="Install guide for Houston, free and open-source DORA and PR metrics for your GitHub repos: requirements, a GitHub token, the queue worker and MCP. Your data never leaves your server.">
<meta property="og:url" content="{{ route('landing.install') }}">
<meta property="og:image" content="{{ asset('landing/og.png') }}">
<meta property="og:image:alt" content="Houston logo with the headline Mission control for your engineering team, next to a repository report showing time to merge, pickup time and where the time goes">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Install Houston · Mission control for your engineering team">
<meta name="twitter:description" content="Install guide for Houston, free and open-source DORA and PR metrics for your GitHub repos: requirements, a GitHub token, the queue worker and MCP. Your data never leaves your server.">
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
      "codeRepository": "https://github.com/vistik/Houston",
      "license": "https://opensource.org/licenses/MIT",
      "programmingLanguage": ["PHP", "TypeScript"]
    },
    {
      "@type": "SoftwareApplication",
      "name": "Houston",
      "applicationCategory": "DeveloperApplication",
      "operatingSystem": "Linux, macOS, Windows (self-hosted, PHP 8.5.12+)",
      "license": "https://opensource.org/licenses/MIT",
      "url": "https://github.com/vistik/Houston"
    }
  ]
}
</script>
@endverbatim
</head>
<body class="min-h-screen overflow-x-hidden bg-bg text-text">

<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-primary focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-on-primary">Skip to content</a>

@include('landing.partials.header', ['awaitsLogin' => $awaitsLogin])

<main id="main" tabindex="-1" class="outline-none">

  <!-- Sub-page hero -->
  <section aria-labelledby="page-title" class="relative isolate overflow-hidden border-b border-border">
    <div aria-hidden="true" class="hero-grid pointer-events-none absolute inset-0 -z-10"></div>
    <div aria-hidden="true" class="hero-glow pointer-events-none absolute -top-24 left-1/2 -z-10 h-72 w-full max-w-3xl -translate-x-1/2 opacity-60"></div>
    <div class="mx-auto max-w-6xl px-4 pt-14 pb-12 sm:px-6 sm:pt-20 sm:pb-16">
      <p class="font-mono text-xs font-medium tracking-widest text-primary uppercase">Pre-flight manual</p>
      <h1 id="page-title" class="mt-4 font-display text-4xl font-semibold leading-tight tracking-tighter text-balance text-text sm:text-5xl">Install Houston</h1>
      <p class="mt-5 max-w-2xl text-base leading-relaxed text-pretty text-muted sm:text-lg">
        Houston is a standard Laravel app. Clone it, run <code class="inline-code">composer setup</code>, give it a GitHub token, and keep a queue worker and the scheduler running. On a laptop that takes a few commands.
      </p>
      @if ($awaitsLogin)
        <p role="status" class="mt-6 max-w-2xl rounded-xl border border-primary/40 bg-primary-soft px-4 py-3 text-sm leading-relaxed text-text">
          Houston, we have a problem: the app is running, but it has no login yet. Run <code class="inline-code">php artisan houston:install</code> on the server to create one, then <a href="{{ route('login') }}" class="font-semibold text-primary underline-offset-4 hover:underline">log in</a>.
        </p>
      @endif
      <ul class="mt-8 flex flex-wrap gap-x-6 gap-y-3 font-mono text-xs text-muted sm:text-sm">
        <li class="flex items-center gap-2"><span class="size-1.5 rounded-md bg-accent" aria-hidden="true"></span>MIT</li>
        <li>PHP 8.5.12+</li>
        <li>Node 22</li>
        <li>SQLite by default</li>
        <li>No Docker needed</li>
      </ul>
    </div>
  </section>

  <div class="mx-auto grid grid-cols-1 max-w-6xl gap-10 px-4 py-12 sm:px-6 sm:py-16 lg:grid-cols-12 lg:gap-16">

    <!-- Table of contents -->
    <aside class="lg:col-span-3">
      <nav aria-labelledby="toc-title" class="rounded-2xl border border-border bg-surface-2 p-5 lg:sticky lg:top-24 lg:border-0 lg:bg-transparent lg:p-0">
        <h2 id="toc-title" class="font-mono text-xs tracking-widest text-muted uppercase">On this page</h2>
        <ol class="mt-4 grid gap-1 text-sm sm:grid-cols-2 lg:grid-cols-1 lg:border-l lg:border-border">
          <li><a href="#requirements" class="block rounded-md py-1.5 text-muted transition-colors hover:text-text lg:-ml-px lg:border-l lg:border-transparent lg:pl-4 lg:hover:border-muted">Requirements</a></li>
          <li><a href="#setup" class="block rounded-md py-1.5 text-muted transition-colors hover:text-text lg:-ml-px lg:border-l lg:border-transparent lg:pl-4 lg:hover:border-muted"><span class="font-mono text-xs">1</span> Clone and set up</a></li>
          <li><a href="#github" class="block rounded-md py-1.5 text-muted transition-colors hover:text-text lg:-ml-px lg:border-l lg:border-transparent lg:pl-4 lg:hover:border-muted"><span class="font-mono text-xs">2</span> Connect GitHub</a></li>
          <li><a href="#run" class="block rounded-md py-1.5 text-muted transition-colors hover:text-text lg:-ml-px lg:border-l lg:border-transparent lg:pl-4 lg:hover:border-muted"><span class="font-mono text-xs">3</span> Run it</a></li>
          <li><a href="#repositories" class="block rounded-md py-1.5 text-muted transition-colors hover:text-text lg:-ml-px lg:border-l lg:border-transparent lg:pl-4 lg:hover:border-muted"><span class="font-mono text-xs">4</span> Add repositories</a></li>
          <li><a href="#teams" class="block rounded-md py-1.5 text-muted transition-colors hover:text-text lg:-ml-px lg:border-l lg:border-transparent lg:pl-4 lg:hover:border-muted"><span class="font-mono text-xs">5</span> Teams and benchmarks</a></li>
          <li><a href="#mcp" class="block rounded-md py-1.5 text-muted transition-colors hover:text-text lg:-ml-px lg:border-l lg:border-transparent lg:pl-4 lg:hover:border-muted"><span class="font-mono text-xs">6</span> Connect an AI assistant</a></li>
          <li><a href="#configuration" class="block rounded-md py-1.5 text-muted transition-colors hover:text-text lg:-ml-px lg:border-l lg:border-transparent lg:pl-4 lg:hover:border-muted"><span class="font-mono text-xs">7</span> Configuration</a></li>
          <li><a href="#security" class="block rounded-md py-1.5 text-muted transition-colors hover:text-text lg:-ml-px lg:border-l lg:border-transparent lg:pl-4 lg:hover:border-muted">Security</a></li>
        </ol>
      </nav>
    </aside>

    <div class="min-w-0 space-y-16 lg:col-span-9 sm:space-y-20">

      <!-- Requirements -->
      <section id="requirements" aria-labelledby="requirements-title">
        <h2 id="requirements-title" class="font-display text-2xl font-semibold tracking-tight text-text sm:text-3xl">Requirements</h2>
        <div class="mt-6 overflow-hidden rounded-2xl border border-border bg-surface">
          <table class="w-full text-left text-sm">
            <caption class="sr-only">What Houston needs</caption>
            <tbody class="divide-y divide-border">
              <tr><th scope="row" class="w-2/5 px-4 py-3 font-medium text-text sm:px-5">PHP</th><td class="px-4 py-3 text-muted sm:px-5">8.5.12 or newer</td></tr>
              <tr><th scope="row" class="px-4 py-3 font-medium text-text sm:px-5">Composer</th><td class="px-4 py-3 text-muted sm:px-5">2</td></tr>
              <tr><th scope="row" class="px-4 py-3 font-medium text-text sm:px-5">Node and npm</th><td class="px-4 py-3 text-muted sm:px-5">Node 22, to build the frontend</td></tr>
              <tr><th scope="row" class="px-4 py-3 font-medium text-text sm:px-5">Database</th><td class="px-4 py-3 text-muted sm:px-5">SQLite by default; any database Laravel supports works</td></tr>
              <tr><th scope="row" class="px-4 py-3 font-medium text-text sm:px-5">GitHub</th><td class="px-4 py-3 text-muted sm:px-5">The GitHub CLI, or a token that can read the repositories you want to measure</td></tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- 1 Clone and set up -->
      <section id="setup" aria-labelledby="setup-title">
        <p class="font-mono text-xs font-medium tracking-widest text-primary uppercase">Step 1</p>
        <h2 id="setup-title" class="mt-2 font-display text-2xl font-semibold tracking-tight text-text sm:text-3xl">Clone and set up</h2>
        <div class="mt-6 flex items-start gap-2 rounded-xl border border-border bg-surface-2 p-2">
          <pre class="min-w-0 flex-1 overflow-x-auto px-3 py-2 font-mono text-sm leading-relaxed text-text"><code>git clone https://github.com/vistik/houston.git
cd houston
composer setup</code></pre>
          <button type="button" data-copy="git clone https://github.com/vistik/houston.git &amp;&amp; cd houston &amp;&amp; composer setup" aria-label="Copy the setup commands" class="grid size-10 shrink-0 place-items-center rounded-lg border border-border bg-surface text-muted transition hover:border-border-strong hover:text-text">
            <svg data-icon="copy" aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/></svg>
            <svg data-icon="check" aria-hidden="true" viewBox="0 0 24 24" class="hidden size-4 text-accent" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
          </button>
        </div>
        <p class="mt-6 text-base leading-relaxed text-muted"><code class="inline-code">composer setup</code> runs these, in order:</p>
        <ol class="mt-4 space-y-2 text-sm leading-relaxed text-muted sm:text-base">
          <li class="flex gap-3"><span class="w-5 shrink-0 font-mono text-xs leading-6 text-primary">1</span><span><code class="inline-code">composer install</code> for the PHP dependencies</span></li>
          <li class="flex gap-3"><span class="w-5 shrink-0 font-mono text-xs leading-6 text-primary">2</span><span>copies <code class="inline-code">.env.example</code> to <code class="inline-code">.env</code> if there is no <code class="inline-code">.env</code> yet</span></li>
          <li class="flex gap-3"><span class="w-5 shrink-0 font-mono text-xs leading-6 text-primary">3</span><span><code class="inline-code">php artisan key:generate</code></span></li>
          <li class="flex gap-3"><span class="w-5 shrink-0 font-mono text-xs leading-6 text-primary">4</span><span><code class="inline-code">php artisan migrate --force</code> to create the tables</span></li>
          <li class="flex gap-3"><span class="w-5 shrink-0 font-mono text-xs leading-6 text-primary">5</span><span><code class="inline-code">npm install</code> and <code class="inline-code">npm run build</code> for the frontend</span></li>
        </ol>
      </section>

      <!-- 2 Connect GitHub -->
      <section id="github" aria-labelledby="github-title">
        <p class="font-mono text-xs font-medium tracking-widest text-primary uppercase">Step 2</p>
        <h2 id="github-title" class="mt-2 font-display text-2xl font-semibold tracking-tight text-text sm:text-3xl">Connect GitHub</h2>
        <p class="mt-4 text-base leading-relaxed text-muted">Houston talks to GitHub with one token. Pick one of the two.</p>

        <div class="mt-6 grid gap-4 md:grid-cols-2">
          <div class="min-w-0 rounded-2xl border border-border bg-surface p-5 sm:p-6">
            <h3 class="font-display text-lg font-semibold tracking-tight text-text">On your own machine: the GitHub CLI</h3>
            <div class="mt-4 flex items-start gap-2 rounded-xl border border-border bg-surface-2 p-2">
              <pre class="min-w-0 flex-1 overflow-x-auto px-3 py-2 font-mono text-sm leading-relaxed text-text"><code><span aria-hidden="true" class="select-none text-muted">$ </span>gh auth login</code></pre>
              <button type="button" data-copy="gh auth login" aria-label="Copy gh auth login" class="grid size-10 shrink-0 place-items-center rounded-lg border border-border bg-surface text-muted transition hover:border-border-strong hover:text-text">
                <svg data-icon="copy" aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/></svg>
                <svg data-icon="check" aria-hidden="true" viewBox="0 0 24 24" class="hidden size-4 text-accent" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
              </button>
            </div>
            <p class="mt-4 text-sm leading-relaxed text-muted">When <code class="inline-code">GITHUB_TOKEN</code> is empty, Houston uses the CLI's token (<code class="inline-code">gh auth token</code>).</p>
          </div>
          <div class="min-w-0 rounded-2xl border border-border bg-surface p-5 sm:p-6">
            <h3 class="font-display text-lg font-semibold tracking-tight text-text">On a server: a token in .env</h3>
            <div class="mt-4 rounded-xl border border-border bg-surface-2 p-2">
              <pre class="overflow-x-auto px-3 py-2 font-mono text-sm leading-relaxed text-text"><code>GITHUB_TOKEN=<span class="text-muted">[your token]</span></code></pre>
            </div>
            <p class="mt-4 text-sm leading-relaxed text-muted">A classic token needs no scopes for public repositories and the <code class="inline-code">repo</code> scope for private ones.</p>
          </div>
        </div>

        <div class="mt-4 overflow-hidden rounded-2xl border border-border bg-surface">
          <table class="w-full text-left text-sm">
            <caption class="border-b border-border px-4 py-3 text-left text-sm text-muted sm:px-5">A fine-grained token needs <span class="text-text">read</span> access to these repository permissions:</caption>
            <thead class="font-mono text-xs text-muted">
              <tr class="border-b border-border"><th scope="col" class="px-4 py-3 font-medium sm:px-5">Permission</th><th scope="col" class="px-4 py-3 font-medium sm:px-5">Used for</th></tr>
            </thead>
            <tbody class="divide-y divide-border">
              <tr><th scope="row" class="px-4 py-3 font-medium text-text sm:px-5">Metadata</th><td class="px-4 py-3 text-muted sm:px-5">Repository details</td></tr>
              <tr><th scope="row" class="px-4 py-3 font-medium text-text sm:px-5">Pull requests</th><td class="px-4 py-3 text-muted sm:px-5">Pull requests and reviews</td></tr>
              <tr><th scope="row" class="px-4 py-3 font-medium text-text sm:px-5">Actions</th><td class="px-4 py-3 text-muted sm:px-5">Workflow runs</td></tr>
              <tr><th scope="row" class="px-4 py-3 font-medium text-text sm:px-5">Deployments</th><td class="px-4 py-3 text-muted sm:px-5">Deployments</td></tr>
              <tr><th scope="row" class="px-4 py-3 font-medium text-text sm:px-5">Contents</th><td class="px-4 py-3 text-muted sm:px-5">Releases and commit timestamps</td></tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- 3 Run it -->
      <section id="run" aria-labelledby="run-title">
        <p class="font-mono text-xs font-medium tracking-widest text-primary uppercase">Step 3</p>
        <h2 id="run-title" class="mt-2 font-display text-2xl font-semibold tracking-tight text-text sm:text-3xl">Run it</h2>

        <h3 class="mt-6 font-display text-lg font-semibold tracking-tight text-text">On your laptop</h3>
        <div class="mt-4 flex items-start gap-2 rounded-xl border border-border bg-surface-2 p-2">
          <pre class="min-w-0 flex-1 overflow-x-auto px-3 py-2 font-mono text-sm leading-relaxed text-text"><code><span aria-hidden="true" class="select-none text-muted">$ </span>composer dev</code></pre>
          <button type="button" data-copy="composer dev" aria-label="Copy composer dev" class="grid size-10 shrink-0 place-items-center rounded-lg border border-border bg-surface text-muted transition hover:border-border-strong hover:text-text">
            <svg data-icon="copy" aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/></svg>
            <svg data-icon="check" aria-hidden="true" viewBox="0 0 24 24" class="hidden size-4 text-accent" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
          </button>
        </div>
        <p class="mt-4 text-base leading-relaxed text-muted">This runs <code class="inline-code">php artisan dev</code>, which starts, side by side:</p>
        <ul class="mt-4 grid gap-2 font-mono text-xs sm:grid-cols-2 sm:text-sm">
          <li class="rounded-lg border border-border bg-surface px-3 py-2 text-text">web server <span class="block text-muted">php artisan serve</span></li>
          <li class="rounded-lg border border-border bg-surface px-3 py-2 text-text">scheduler <span class="block text-muted">php artisan schedule:work</span></li>
          <li class="rounded-lg border border-border bg-surface px-3 py-2 text-text">queue worker <span class="block text-muted">php artisan queue:listen</span></li>
          <li class="rounded-lg border border-border bg-surface px-3 py-2 text-text">log tail <span class="block text-muted">php artisan pail</span></li>
          <li class="rounded-lg border border-border bg-surface px-3 py-2 text-text">Vite <span class="block text-muted">npm run dev</span></li>
        </ul>
        <p class="mt-4 text-base leading-relaxed text-muted">Then open <code class="inline-code">http://localhost:8000</code>. If Laravel Herd already serves the app, its <code class="inline-code">.test</code> address works too.</p>

        <h3 class="mt-10 font-display text-lg font-semibold tracking-tight text-text">On a long-running server</h3>
        <p class="mt-3 text-base leading-relaxed text-muted">Serve the app like any Laravel app, then keep two processes running: a queue worker for the syncs, and the scheduler.</p>
        <div class="mt-4 flex items-start gap-2 rounded-xl border border-border bg-surface-2 p-2">
          <pre class="min-w-0 flex-1 overflow-x-auto px-3 py-2 font-mono text-sm leading-relaxed text-text"><code><span aria-hidden="true" class="select-none text-muted">$ </span>php artisan queue:work</code></pre>
          <button type="button" data-copy="php artisan queue:work" aria-label="Copy php artisan queue:work" class="grid size-10 shrink-0 place-items-center rounded-lg border border-border bg-surface text-muted transition hover:border-border-strong hover:text-text">
            <svg data-icon="copy" aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/></svg>
            <svg data-icon="check" aria-hidden="true" viewBox="0 0 24 24" class="hidden size-4 text-accent" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
          </button>
        </div>
        <p class="mt-4 text-sm leading-relaxed text-muted">For the scheduler, either keep <code class="inline-code">php artisan schedule:work</code> running, or add the usual Laravel cron entry:</p>
        <div class="mt-3 flex items-start gap-2 rounded-xl border border-border bg-surface-2 p-2">
          <pre class="min-w-0 flex-1 overflow-x-auto px-3 py-2 font-mono text-sm leading-relaxed text-text"><code>* * * * * cd /path/to/Houston &amp;&amp; php artisan schedule:run &gt;&gt; /dev/null 2&gt;&amp;1</code></pre>
          <button type="button" data-copy="* * * * * cd /path/to/Houston &amp;&amp; php artisan schedule:run &gt;&gt; /dev/null 2&gt;&amp;1" aria-label="Copy the cron entry" class="grid size-10 shrink-0 place-items-center rounded-lg border border-border bg-surface text-muted transition hover:border-border-strong hover:text-text">
            <svg data-icon="copy" aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/></svg>
            <svg data-icon="check" aria-hidden="true" viewBox="0 0 24 24" class="hidden size-4 text-accent" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
          </button>
        </div>
        <div class="mt-6 rounded-2xl border border-border bg-surface p-5 sm:p-6">
          <h4 class="font-mono text-xs tracking-widest text-muted uppercase">What the scheduler runs</h4>
          <dl class="mt-4 space-y-3 text-sm">
            <div class="flex flex-col gap-1 sm:flex-row sm:gap-4"><dt class="font-mono text-text sm:w-56 sm:shrink-0">houston:sync --queue</dt><dd class="text-muted">every 15 minutes, never overlapping: refreshes every watched repository</dd></div>
            <div class="flex flex-col gap-1 sm:flex-row sm:gap-4"><dt class="font-mono text-text sm:w-56 sm:shrink-0">model:prune</dt><dd class="text-muted">daily: prunes old system events</dd></div>
          </dl>
        </div>
      </section>

      <!-- 4 Add repositories -->
      <section id="repositories" aria-labelledby="repositories-title">
        <p class="font-mono text-xs font-medium tracking-widest text-primary uppercase">Step 4</p>
        <h2 id="repositories-title" class="mt-2 font-display text-2xl font-semibold tracking-tight text-text sm:text-3xl">Add repositories</h2>
        <ol class="mt-6 space-y-3 text-base leading-relaxed text-muted">
          <li class="flex gap-3"><span class="w-5 shrink-0 font-mono text-xs leading-7 text-primary">1</span><span>Open Houston and click <span class="text-text">Watch repository</span>.</span></li>
          <li class="flex gap-3"><span class="w-5 shrink-0 font-mono text-xs leading-7 text-primary">2</span><span>Enter <code class="inline-code">owner/name</code>, or paste the repository's GitHub URL.</span></li>
        </ol>
        <p class="mt-6 text-base leading-relaxed text-muted">A sync is queued straight away. The first one pulls the lookback window, 180 days by default, and can take a while on busy repositories. It pauses before it runs into GitHub's rate limits and resumes when they reset. Follow it under <span class="text-text">Settings → Syncs</span>, where you can also change each repository's lookback window.</p>
        <p class="mt-4 text-base leading-relaxed text-muted">Want to see it work before pointing it at your own code? Watch a busy public repository such as <code class="inline-code">laravel/framework</code>.</p>
        <p class="mt-6 text-sm text-muted">From the command line:</p>
        <div class="mt-3 rounded-xl border border-border bg-surface-2 p-2">
          <pre class="overflow-x-auto px-3 py-2 font-mono text-sm leading-relaxed text-text"><code>php artisan houston:sync                    <span class="text-muted"># every watched repository</span>
php artisan houston:sync laravel/framework  <span class="text-muted"># one repository</span>
php artisan houston:sync --queue            <span class="text-muted"># dispatch to the queue instead</span></code></pre>
        </div>
      </section>

      <!-- 5 Teams and benchmarks -->
      <section id="teams" aria-labelledby="teams-title">
        <p class="font-mono text-xs font-medium tracking-widest text-primary uppercase">Step 5</p>
        <h2 id="teams-title" class="mt-2 font-display text-2xl font-semibold tracking-tight text-text sm:text-3xl">Teams and benchmarks</h2>
        <div class="mt-6 grid gap-4 md:grid-cols-2">
          <div class="rounded-2xl border border-border bg-surface p-5 sm:p-6">
            <h3 class="font-display text-lg font-semibold tracking-tight text-text">Teams</h3>
            <p class="mt-3 text-sm leading-relaxed text-muted">Create them under <span class="text-text">Settings → Teams</span> and add people by their GitHub login, with the day they joined and, later, the day they left. Work counts for the team someone was on when they did it.</p>
          </div>
          <div class="rounded-2xl border border-border bg-surface p-5 sm:p-6">
            <h3 class="font-display text-lg font-semibold tracking-tight text-text">Benchmarks</h3>
            <p class="mt-3 text-sm leading-relaxed text-muted">Off until you set targets. Under <span class="text-text">Settings → Benchmarks</span>, set your own, or add the 31 built-in standards (DORA, LinearB and suggested targets) with one click.</p>
          </div>
        </div>
      </section>

      <!-- 6 MCP -->
      <section id="mcp" aria-labelledby="mcp-title">
        <p class="font-mono text-xs font-medium tracking-widest text-primary uppercase">Step 6</p>
        <h2 id="mcp-title" class="mt-2 font-display text-2xl font-semibold tracking-tight text-text sm:text-3xl">Connect an AI assistant (MCP)</h2>
        <p class="mt-4 text-base leading-relaxed text-muted">Houston runs a read-only MCP server with three tools (<code class="inline-code">list-repositories</code>, <code class="inline-code">get-velocity-report</code>, <code class="inline-code">list-pull-requests</code>) and a <code class="inline-code">give-feedback</code> prompt.</p>

        <h3 class="mt-8 font-display text-lg font-semibold tracking-tight text-text">Locally, over stdio</h3>
        <p class="mt-3 text-sm leading-relaxed text-muted">Point your client at <code class="inline-code">php artisan mcp:start houston</code>. With Claude Code:</p>
        <div class="mt-3 flex items-start gap-2 rounded-xl border border-border bg-surface-2 p-2">
          <pre class="min-w-0 flex-1 overflow-x-auto px-3 py-2 font-mono text-sm leading-relaxed text-text"><code><span aria-hidden="true" class="select-none text-muted">$ </span>claude mcp add houston -- php /path/to/Houston/artisan mcp:start houston</code></pre>
          <button type="button" data-copy="claude mcp add houston -- php /path/to/Houston/artisan mcp:start houston" aria-label="Copy the Claude Code command" class="grid size-10 shrink-0 place-items-center rounded-lg border border-border bg-surface text-muted transition hover:border-border-strong hover:text-text">
            <svg data-icon="copy" aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/></svg>
            <svg data-icon="check" aria-hidden="true" viewBox="0 0 24 24" class="hidden size-4 text-accent" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
          </button>
        </div>
        <p class="mt-4 text-sm leading-relaxed text-muted">Any other client, in its JSON config:</p>
        <div class="mt-3 flex items-start gap-2 rounded-xl border border-border bg-surface-2 p-2">
          <pre class="min-w-0 flex-1 overflow-x-auto px-3 py-2 font-mono text-sm leading-relaxed text-text"><code id="install-mcp-json">{
  "mcpServers": {
    "houston": {
      "command": "php",
      "args": ["/path/to/Houston/artisan", "mcp:start", "houston"]
    }
  }
}</code></pre>
          <button type="button" data-copy-target="install-mcp-json" aria-label="Copy the JSON config" class="grid size-10 shrink-0 place-items-center rounded-lg border border-border bg-surface text-muted transition hover:border-border-strong hover:text-text">
            <svg data-icon="copy" aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/></svg>
            <svg data-icon="check" aria-hidden="true" viewBox="0 0 24 24" class="hidden size-4 text-accent" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
          </button>
        </div>

        <h3 class="mt-8 font-display text-lg font-semibold tracking-tight text-text">Over HTTP, with a token</h3>
        <p class="mt-3 text-sm leading-relaxed text-muted">Set <code class="inline-code">HOUSTON_MCP_TOKEN</code> in <code class="inline-code">.env</code> to a long random string, then send it as a bearer token to <code class="inline-code">/mcp</code>:</p>
        <div class="mt-3 flex items-start gap-2 rounded-xl border border-border bg-surface-2 p-2">
          <pre class="min-w-0 flex-1 overflow-x-auto px-3 py-2 font-mono text-sm leading-relaxed text-text"><code><span aria-hidden="true" class="select-none text-muted">$ </span>claude mcp add --transport http houston http://localhost:8000/mcp \
    --header "Authorization: Bearer $HOUSTON_MCP_TOKEN"</code></pre>
          <button type="button" data-copy="claude mcp add --transport http houston http://localhost:8000/mcp --header &quot;Authorization: Bearer $HOUSTON_MCP_TOKEN&quot;" aria-label="Copy the HTTP command" class="grid size-10 shrink-0 place-items-center rounded-lg border border-border bg-surface text-muted transition hover:border-border-strong hover:text-text">
            <svg data-icon="copy" aria-hidden="true" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/></svg>
            <svg data-icon="check" aria-hidden="true" viewBox="0 0 24 24" class="hidden size-4 text-accent" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
          </button>
        </div>
        <dl class="mt-4 grid gap-2 font-mono text-xs sm:grid-cols-3 sm:text-sm">
          <div class="rounded-lg border border-border bg-surface px-3 py-2"><dt class="text-muted">No token set</dt><dd class="text-text">404, endpoint off</dd></div>
          <div class="rounded-lg border border-border bg-surface px-3 py-2"><dt class="text-muted">Wrong or missing</dt><dd class="text-text">401</dd></div>
          <div class="rounded-lg border border-border bg-surface px-3 py-2"><dt class="text-muted">Matching token</dt><dd class="text-accent">read-only access</dd></div>
        </dl>
      </section>

      <!-- 7 Configuration -->
      <section id="configuration" aria-labelledby="configuration-title">
        <p class="font-mono text-xs font-medium tracking-widest text-primary uppercase">Step 7</p>
        <h2 id="configuration-title" class="mt-2 font-display text-2xl font-semibold tracking-tight text-text sm:text-3xl">Configuration</h2>
        <p class="mt-4 text-base leading-relaxed text-muted">Everything Houston-specific lives in <code class="inline-code">config/houston.php</code> and can be set from <code class="inline-code">.env</code>. Report defaults (period, grouping and the timezone every report is shown in) and benchmarks are set in the app under <span class="text-text">Settings</span>.</p>
        <div class="mt-6 overflow-hidden rounded-2xl border border-border bg-surface">
          <table class="w-full text-left text-sm">
            <caption class="sr-only">Environment variables, defaults and what they do</caption>
            <thead class="hidden font-mono text-xs text-muted md:table-header-group">
              <tr class="border-b border-border"><th scope="col" class="px-5 py-3 font-medium">Variable</th><th scope="col" class="px-5 py-3 font-medium">Default</th><th scope="col" class="px-5 py-3 font-medium">What it does</th></tr>
            </thead>
            <tbody class="divide-y divide-border">
              <tr class="block px-4 py-4 md:table-row md:p-0"><th scope="row" class="block font-mono text-xs font-medium text-text md:table-cell md:px-5 md:py-4 md:align-top">GITHUB_<wbr>TOKEN</th><td class="mt-1 block font-mono text-xs text-primary md:table-cell md:px-5 md:py-4 md:align-top">gh auth token</td><td class="mt-2 block text-muted md:table-cell md:px-5 md:py-4 md:align-top">The token used to talk to GitHub. Empty falls back to the GitHub CLI's token.</td></tr>
              <tr class="block px-4 py-4 md:table-row md:p-0"><th scope="row" class="block font-mono text-xs font-medium text-text md:table-cell md:px-5 md:py-4 md:align-top">HOUSTON_<wbr>GITHUB_<wbr>RATE_<wbr>LIMIT_<wbr>RESERVE</th><td class="mt-1 block font-mono text-xs text-primary md:table-cell md:px-5 md:py-4 md:align-top">50</td><td class="mt-2 block text-muted md:table-cell md:px-5 md:py-4 md:align-top">Syncs pause when fewer requests are left, leaving room for anything else using the token.</td></tr>
              <tr class="block px-4 py-4 md:table-row md:p-0"><th scope="row" class="block font-mono text-xs font-medium text-text md:table-cell md:px-5 md:py-4 md:align-top">HOUSTON_<wbr>LOOKBACK_<wbr>DAYS</th><td class="mt-1 block font-mono text-xs text-primary md:table-cell md:px-5 md:py-4 md:align-top">180</td><td class="mt-2 block text-muted md:table-cell md:px-5 md:py-4 md:align-top">Days of history a fresh sync pulls. Keep it at least twice the longest period you report on. Can be set per repository.</td></tr>
              <tr class="block px-4 py-4 md:table-row md:p-0"><th scope="row" class="block font-mono text-xs font-medium text-text md:table-cell md:px-5 md:py-4 md:align-top">HOUSTON_<wbr>STALE_<wbr>AFTER_<wbr>MINUTES</th><td class="mt-1 block font-mono text-xs text-primary md:table-cell md:px-5 md:py-4 md:align-top">15</td><td class="mt-2 block text-muted md:table-cell md:px-5 md:py-4 md:align-top">Viewing a repository whose cache is older than this queues a refresh.</td></tr>
              <tr class="block px-4 py-4 md:table-row md:p-0"><th scope="row" class="block font-mono text-xs font-medium text-text md:table-cell md:px-5 md:py-4 md:align-top">HOUSTON_<wbr>STALE_<wbr>PR_<wbr>DAYS</th><td class="mt-1 block font-mono text-xs text-primary md:table-cell md:px-5 md:py-4 md:align-top">7</td><td class="mt-2 block text-muted md:table-cell md:px-5 md:py-4 md:align-top">Open pull requests without activity for this long are flagged as stale.</td></tr>
              <tr class="block px-4 py-4 md:table-row md:p-0"><th scope="row" class="block font-mono text-xs font-medium text-text md:table-cell md:px-5 md:py-4 md:align-top">HOUSTON_<wbr>WORKING_<wbr>DAYS</th><td class="mt-1 block font-mono text-xs text-primary md:table-cell md:px-5 md:py-4 md:align-top">1,2,3,4,5</td><td class="mt-2 block text-muted md:table-cell md:px-5 md:py-4 md:align-top">Working days as ISO day numbers (1 is Monday).</td></tr>
              <tr class="block px-4 py-4 md:table-row md:p-0"><th scope="row" class="block font-mono text-xs font-medium text-text md:table-cell md:px-5 md:py-4 md:align-top">HOUSTON_<wbr>WORKDAY_<wbr>START / _<wbr>END</th><td class="mt-1 block font-mono text-xs text-primary md:table-cell md:px-5 md:py-4 md:align-top">00:00 / 24:00</td><td class="mt-2 block text-muted md:table-cell md:px-5 md:py-4 md:align-top">Working hours. Use all seven days and 00:00 to 24:00 to measure calendar time.</td></tr>
              <tr class="block px-4 py-4 md:table-row md:p-0"><th scope="row" class="block font-mono text-xs font-medium text-text md:table-cell md:px-5 md:py-4 md:align-top">HOUSTON_<wbr>DEPLOY_<wbr>ENVIRONMENT_<wbr>PATTERN</th><td class="mt-1 block font-mono text-xs break-all text-primary md:table-cell md:px-5 md:py-4 md:align-top">/^(prod|production|live)\b/i</td><td class="mt-2 block text-muted md:table-cell md:px-5 md:py-4 md:align-top">Deployments to a matching environment count for delivery metrics. Without them, published releases are used.</td></tr>
              <tr class="block px-4 py-4 md:table-row md:p-0"><th scope="row" class="block font-mono text-xs font-medium text-text md:table-cell md:px-5 md:py-4 md:align-top">HOUSTON_<wbr>REMEDIATION_<wbr>PATTERN</th><td class="mt-1 block font-mono text-xs break-all text-primary md:table-cell md:px-5 md:py-4 md:align-top">/^(revert|hotfix|rollback)\b/i</td><td class="mt-2 block text-muted md:table-cell md:px-5 md:py-4 md:align-top">Pull requests whose title, branch or label matches count as remediations, which drive the change failure rate.</td></tr>
              <tr class="block px-4 py-4 md:table-row md:p-0"><th scope="row" class="block font-mono text-xs font-medium text-text md:table-cell md:px-5 md:py-4 md:align-top">HOUSTON_<wbr>MCP_<wbr>TOKEN</th><td class="mt-1 block font-mono text-xs text-muted md:table-cell md:px-5 md:py-4 md:align-top">empty</td><td class="mt-2 block text-muted md:table-cell md:px-5 md:py-4 md:align-top">Enables the MCP endpoint at /mcp for clients that send this bearer token.</td></tr>
              <tr class="block px-4 py-4 md:table-row md:p-0"><th scope="row" class="block font-mono text-xs font-medium text-text md:table-cell md:px-5 md:py-4 md:align-top">HOUSTON_<wbr>AUTH</th><td class="mt-1 block font-mono text-xs text-muted md:table-cell md:px-5 md:py-4 md:align-top">true</td><td class="mt-2 block text-muted md:table-cell md:px-5 md:py-4 md:align-top">Asks for the single login before showing anything. <code class="inline-code">php artisan houston:install</code> creates the login; until then visitors see this guide.</td></tr>
              <tr class="block px-4 py-4 md:table-row md:p-0"><th scope="row" class="block font-mono text-xs font-medium text-text md:table-cell md:px-5 md:py-4 md:align-top">HOUSTON_<wbr>LANDING</th><td class="mt-1 block font-mono text-xs text-muted md:table-cell md:px-5 md:py-4 md:align-top">false</td><td class="mt-2 block text-muted md:table-cell md:px-5 md:py-4 md:align-top">Shows this website at / and /install. The app is at /dashboard either way; without the website, / goes straight there.</td></tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Security -->
      <section id="security" aria-labelledby="security-title">
        <div class="rounded-2xl border border-primary/40 bg-primary-soft p-6 sm:p-8">
          <h2 id="security-title" class="flex items-center gap-3 font-display text-2xl font-semibold tracking-tight text-text sm:text-3xl">
            <svg aria-hidden="true" viewBox="0 0 24 24" class="size-6 shrink-0 text-primary" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.5-3 8.5-7 10-4-1.5-7-5.5-7-10V6z"/></svg>
            Security: authorized personnel only
          </h2>
          <ul class="mt-5 space-y-3 text-base leading-relaxed text-text">
            <li class="flex gap-3"><span aria-hidden="true" class="mt-2.5 size-1.5 shrink-0 rounded-md bg-primary"></span>Houston asks for its single login by default, and shows this guide until the login exists: <code class="inline-code">php artisan houston:install</code> creates it, or turns the login off with <code class="inline-code">HOUSTON_AUTH=false</code>. There are no per-user accounts.</li>
            <li class="flex gap-3"><span aria-hidden="true" class="mt-2.5 size-1.5 shrink-0 rounded-md bg-primary"></span>Only <code class="inline-code">/mcp</code> is protected, by <code class="inline-code">HOUSTON_MCP_TOKEN</code>, and it is off until you set one.</li>
            <li class="flex gap-3"><span aria-hidden="true" class="mt-2.5 size-1.5 shrink-0 rounded-md bg-primary"></span>Only turn the login off on localhost or a private network or VPN. On a public server, keep it on.</li>
          </ul>
        </div>
      </section>
    </div>
  </div>

  <!-- Final CTA -->
  <section aria-labelledby="cta-title" class="relative isolate overflow-hidden border-t border-border">
    <div aria-hidden="true" class="hero-grid pointer-events-none absolute inset-0 -z-10"></div>
    <div class="mx-auto max-w-3xl px-4 py-20 text-center sm:px-6 sm:py-28">
      <h2 id="cta-title" class="font-display text-3xl font-semibold leading-tight tracking-tight text-balance text-text sm:text-4xl">Houston standing by.</h2>
      <p class="mx-auto mt-5 max-w-xl text-base leading-relaxed text-muted">Star the repo to follow along, or open an issue if a step above didn't work for you.</p>
      <div class="mx-auto mt-8 flex max-w-sm flex-col gap-3 sm:max-w-none sm:flex-row sm:justify-center">
        <a href="https://github.com/vistik/Houston" class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-6 py-3 text-sm font-semibold text-on-primary transition hover:bg-primary/90">
          <svg aria-hidden="true" viewBox="0 0 16 16" class="size-4" fill="currentColor"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg>
          Get it on GitHub
        </a>
        <a href="https://github.com/vistik/Houston/issues" class="inline-flex items-center justify-center rounded-lg border border-border-strong bg-surface/60 px-6 py-3 text-sm font-semibold text-text transition hover:border-muted">
          Open an issue
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
