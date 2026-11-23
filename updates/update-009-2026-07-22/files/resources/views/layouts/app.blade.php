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
        $nav = [
            '/' => 'Dashboard',
            '/ingest' => 'Ingest',
            '/chat' => 'Tutor',
            '/phases' => 'Phases',
            '/exam-prep' => 'Exam Prep',
            '/concepts' => 'Concepts',
            '/history' => 'History',
            '/notifications' => 'Notifications' . ($unreadCount > 0 ? " ({$unreadCount})" : ''),
            '/settings' => 'Settings',
        ];
    @endphp
    <nav class="sticky top-0 z-10 border-b border-white/10 bg-ink">
        <div class="mx-auto flex h-14 max-w-5xl items-center gap-4 px-4 sm:gap-6 sm:px-6">
            <a href="/" class="whitespace-nowrap font-mono text-sm font-semibold tracking-[0.2em] text-white">
                STUDY<span class="text-cyan-400">//</span>ENGINE
            </a>
            <div class="flex min-w-0 gap-0.5 overflow-x-auto text-sm">
                @foreach ($nav as $href => $label)
                    @php $active = $href === '/' ? request()->is('/') : request()->is(ltrim($href, '/').'*'); @endphp
                    <a href="{{ $href }}"
                        class="whitespace-nowrap rounded-md px-2.5 py-1.5 transition-colors {{ $active ? 'bg-white/10 font-medium text-white' : 'text-white/60 hover:text-white' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>
    </nav>

    <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-10">
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
