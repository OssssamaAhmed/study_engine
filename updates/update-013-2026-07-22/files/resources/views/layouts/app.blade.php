<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Study Engine' }}</title>
    <link rel="icon" href='data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" rx="7" fill="%23101828"/><text x="16" y="22" font-family="monospace" font-size="15" font-weight="bold" fill="%2322d3ee" text-anchor="middle">»</text></svg>'>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-paper font-sans text-ink antialiased">
    @php
        $unreadCount = \App\Models\Notification::where('is_read', false)->count();
        $name = \App\Support\LearnerName::get();
        $nav = [
            '/' => ['label' => 'Dashboard', 'icon' => '📊'],
            '/ingest' => ['label' => 'Ingest', 'icon' => '📥'],
            '/chat' => ['label' => 'Tutor', 'icon' => '💬'],
            '/phases' => ['label' => 'Phases & Projects', 'icon' => '🗺️'],
            '/exam-prep' => ['label' => 'Exam Prep', 'icon' => '🎯'],
            '/concepts' => ['label' => 'Concepts', 'icon' => '🧠'],
            '/history' => ['label' => 'History', 'icon' => '📜'],
            '/notifications' => ['label' => 'Notifications', 'icon' => '🔔', 'badge' => $unreadCount],
            '/settings' => ['label' => 'Settings', 'icon' => '⚙️'],
        ];
    @endphp

    <div class="min-h-screen flex flex-col md:flex-row">
        {{-- Mobile Sticky Top Bar --}}
        <header class="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-white/10 bg-ink px-4 md:hidden">
            <a href="/" class="font-mono text-sm font-semibold tracking-[0.2em] text-white">
                STUDY<span class="text-cyan-400">//</span>ENGINE
            </a>
            <button type="button" onclick="document.getElementById('mobile-menu').classList.toggle('hidden')"
                class="rounded-md p-2 text-white/70 hover:bg-white/10 hover:text-white" aria-label="Toggle Navigation">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </header>

        {{-- Mobile Dropdown Menu --}}
        <div id="mobile-menu" class="hidden border-b border-white/10 bg-ink p-4 md:hidden z-30">
            <nav class="space-y-1">
                @foreach ($nav as $href => $item)
                    @php $active = $href === '/' ? request()->is('/') : request()->is(ltrim($href, '/').'*'); @endphp
                    <a href="{{ $href }}"
                        class="flex items-center justify-between rounded-lg px-3 py-2 text-sm transition-colors {{ $active ? 'bg-white/15 font-medium text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                        <span class="flex items-center gap-3">
                            <span>{{ $item['icon'] }}</span>
                            <span>{{ $item['label'] }}</span>
                        </span>
                        @if (!empty($item['badge']))
                            <span class="rounded-full bg-cyan-500 px-2 py-0.5 font-mono text-[10px] font-bold text-ink">
                                {{ $item['badge'] }}
                            </span>
                        @endif
                    </a>
                @endforeach
            </nav>
        </div>

        {{-- Desktop Left Sidebar Navigation --}}
        <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col justify-between border-r border-white/10 bg-ink text-white md:flex">
            <div>
                {{-- Brand Header --}}
                <div class="flex h-16 items-center justify-between border-b border-white/10 px-6">
                    <a href="/" class="font-mono text-sm font-semibold tracking-[0.2em] text-white">
                        STUDY<span class="text-cyan-400">//</span>ENGINE
                    </a>
                    <span class="rounded bg-cyan-950 px-2 py-0.5 font-mono text-[10px] font-semibold text-cyan-300 border border-cyan-800/40">
                        v3.1
                    </span>
                </div>

                {{-- Navigation Links --}}
                <nav class="mt-4 px-3 space-y-1">
                    @foreach ($nav as $href => $item)
                        @php $active = $href === '/' ? request()->is('/') : request()->is(ltrim($href, '/').'*'); @endphp
                        <a href="{{ $href }}"
                            class="group flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-medium transition-all {{ $active ? 'bg-white/12 text-white border-l-2 border-cyan-400 pl-3.5 shadow-xs' : 'text-white/65 hover:bg-white/5 hover:text-white' }}">
                            <span class="flex items-center gap-3">
                                <span class="text-base transition-transform group-hover:scale-110">{{ $item['icon'] }}</span>
                                <span>{{ $item['label'] }}</span>
                            </span>
                            @if (!empty($item['badge']))
                                <span class="rounded-full bg-cyan-400 px-2 py-0.5 font-mono text-[10px] font-bold text-ink">
                                    {{ $item['badge'] }}
                                </span>
                            @endif
                        </a>
                    @endforeach
                </nav>
            </div>

            {{-- Sidebar Footer / Profile --}}
            <div class="border-t border-white/10 p-4">
                <div class="rounded-lg bg-white/5 p-3 flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-cyan-950 font-mono text-sm font-bold text-cyan-300 border border-cyan-700/50">
                        {{ strtoupper(substr($name ?: 'O', 0, 1)) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-semibold text-white">{{ $name ?: 'Learner' }}</p>
                        <p class="truncate font-mono text-[10px] text-cyan-300/80">AI Security Track</p>
                    </div>
                </div>
            </div>
        </aside>

        {{-- Main Page Content Area --}}
        <div class="flex-1 min-w-0 md:pl-64">
            <main class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-8 sm:py-10">
                @if (session('status'))
                    <div class="mb-8 flex items-start gap-3 rounded-lg border border-accent/25 bg-accent-wash px-4 py-3 text-sm text-accent-deep">
                        <span class="mt-0.5 font-mono">✓</span>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif
                @if (session('error'))
                    <div class="mb-8 flex items-start gap-3 rounded-lg border border-red-500/25 bg-red-500/10 px-4 py-3 text-sm text-red-700">
                        <span class="mt-0.5 font-mono">✕</span>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>

    <script>
        // LLM-calling forms: show a working state so a slow model never looks broken.
        document.querySelectorAll('form[data-llm]').forEach((form) => {
            form.addEventListener('submit', (e) => {
                const button = e.submitter || form.querySelector('button');
                if (!button) return;
                setTimeout(() => {
                    button.disabled = true;
                    button.classList.add('animate-pulse');
                    button.textContent = 'Working — the model can take a minute or two…';
                }, 0);
            });
        });

        // Mission outline (Google-Docs style): clickable section list built
        // from the mission's headings; the section in view stays highlighted.
        const outline = document.querySelector('article.mission[data-outline]');
        const tocBox = document.getElementById('mission-toc');
        if (outline && tocBox) {
            const heads = [...outline.querySelectorAll('h2, h3')];
            if (heads.length >= 2) {
                tocBox.insertAdjacentHTML('afterbegin', '<p class="eyebrow mb-2">On this page</p>');
                const links = heads.map((h, i) => {
                    h.id = 'sec-' + i;
                    const a = document.createElement('a');
                    a.href = '#' + h.id;
                    a.textContent = h.textContent;
                    a.className = 'toc-link' + (h.tagName === 'H3' ? ' toc-sub' : '');
                    tocBox.appendChild(a);
                    return a;
                });
                const spy = new IntersectionObserver((entries) => {
                    entries.forEach((e) => {
                        if (!e.isIntersecting) return;
                        links.forEach((a) => a.classList.toggle('active', a.hash === '#' + e.target.id));
                    });
                }, { rootMargin: '-15% 0px -75% 0px' });
                heads.forEach((h) => spy.observe(h));
            }
        }
    </script>
</body>
</html>
