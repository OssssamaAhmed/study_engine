<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Study Engine' }}</title>
    <link rel="icon" href='data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" rx="7" fill="%23101828"/><text x="16" y="22" font-family="monospace" font-size="15" font-weight="bold" fill="%2322d3ee" text-anchor="middle">»</text></svg>'>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* Sidebar layout shell */
        .app-shell {
            display: flex;
            min-height: 100vh;
        }

        /* ─── Sidebar ─── */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 224px;
            background: #0c1424;
            border-right: 1px solid rgba(255,255,255,0.07);
            display: flex;
            flex-direction: column;
            z-index: 40;
            transition: transform 0.25s cubic-bezier(0.4,0,0.2,1);
            overflow-y: auto;
            overflow-x: hidden;
        }

        /* ─── Brand ─── */
        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 20px 18px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.07);
            flex-shrink: 0;
        }
        .sidebar-brand-icon {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #0e7490 0%, #155e75 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-family: monospace;
            font-size: 14px;
            font-weight: 700;
            color: #22d3ee;
        }
        .sidebar-brand-text {
            font-family: monospace;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.18em;
            color: #fff;
            line-height: 1.2;
        }
        .sidebar-brand-text span {
            color: #22d3ee;
        }

        /* ─── Nav section ─── */
        .sidebar-nav {
            flex: 1;
            padding: 10px 10px;
        }
        .sidebar-section-label {
            font-family: monospace;
            font-size: 9.5px;
            font-weight: 600;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.3);
            padding: 12px 8px 4px;
        }
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 10px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            color: rgba(255,255,255,0.58);
            transition: background 0.15s, color 0.15s;
            margin-bottom: 1px;
            position: relative;
            white-space: nowrap;
        }
        .sidebar-link:hover {
            background: rgba(255,255,255,0.07);
            color: rgba(255,255,255,0.9);
        }
        .sidebar-link.active {
            background: rgba(14,116,144,0.22);
            color: #ffffff;
        }
        .sidebar-link.active::before {
            content: '';
            position: absolute;
            left: -10px;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 22px;
            background: #22d3ee;
            border-radius: 0 3px 3px 0;
        }
        .sidebar-link-icon {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
            opacity: 0.7;
        }
        .sidebar-link.active .sidebar-link-icon {
            opacity: 1;
        }
        .sidebar-badge {
            margin-left: auto;
            background: rgba(34,211,238,0.15);
            color: #22d3ee;
            font-family: monospace;
            font-size: 10px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 20px;
            border: 1px solid rgba(34,211,238,0.25);
        }

        /* ─── Sidebar footer ─── */
        .sidebar-footer {
            padding: 12px 10px 16px;
            border-top: 1px solid rgba(255,255,255,0.07);
            flex-shrink: 0;
        }
        .sidebar-footer-text {
            font-family: monospace;
            font-size: 10px;
            color: rgba(255,255,255,0.22);
            text-align: center;
            letter-spacing: 0.05em;
        }

        /* ─── Main content area ─── */
        .app-main {
            margin-left: 224px;
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
        }

        /* ─── Top bar (mobile hamburger + page context) ─── */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 30;
            background: rgba(245,244,239,0.92);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-bottom: 1px solid #e5e3da;
            height: 52px;
            display: flex;
            align-items: center;
            padding: 0 24px;
            gap: 12px;
        }
        .topbar-hamburger {
            display: none;
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px;
            color: #0c1424;
            border-radius: 6px;
        }
        .topbar-hamburger:hover {
            background: rgba(0,0,0,0.06);
        }

        /* ─── Mobile sidebar overlay ─── */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            z-index: 39;
        }

        /* ─── Page content wrapper ─── */
        .page-content {
            flex: 1;
            padding: 32px 32px;
            max-width: 1100px;
            width: 100%;
            box-sizing: border-box;
        }

        /* ─── Mobile responsive ─── */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.open {
                transform: translateX(0);
                box-shadow: 4px 0 24px rgba(0,0,0,0.35);
            }
            .sidebar-overlay.open {
                display: block;
            }
            .app-main {
                margin-left: 0;
            }
            .topbar-hamburger {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .page-content {
                padding: 20px 16px;
            }
        }
    </style>
</head>
<body class="bg-paper font-sans text-ink antialiased">
    @php
        $unreadCount = \App\Models\Notification::where('is_read', false)->count();
        $nav = [
            'main' => [
                '/' => ['label' => 'Dashboard', 'icon' => '<svg class="sidebar-link-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>'],
                '/ingest' => ['label' => 'Ingest', 'icon' => '<svg class="sidebar-link-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>'],
                '/chat' => ['label' => 'Tutor', 'icon' => '<svg class="sidebar-link-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>'],
            ],
            'learn' => [
                '/phases' => ['label' => 'Phases', 'icon' => '<svg class="sidebar-link-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>'],
                '/exam-prep' => ['label' => 'Exam Prep', 'icon' => '<svg class="sidebar-link-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>'],
                '/concepts' => ['label' => 'Concepts', 'icon' => '<svg class="sidebar-link-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>'],
            ],
            'system' => [
                '/history' => ['label' => 'History', 'icon' => '<svg class="sidebar-link-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>'],
                '/notifications' => ['label' => 'Notifications', 'icon' => '<svg class="sidebar-link-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>', 'badge' => $unreadCount],
                '/settings' => ['label' => 'Settings', 'icon' => '<svg class="sidebar-link-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>'],
            ],
        ];
        $sectionLabels = ['main' => 'Main', 'learn' => 'Learning', 'system' => 'System'];
    @endphp

    <div class="app-shell">

        {{-- ─── Sidebar overlay (mobile) ─── --}}
        <div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

        {{-- ─── Sidebar ─── --}}
        <aside class="sidebar" id="sidebar" role="navigation" aria-label="Main navigation">
            {{-- Brand --}}
            <div class="sidebar-brand">
                <div class="sidebar-brand-icon">»</div>
                <div class="sidebar-brand-text">
                    STUDY<span>//</span>ENGINE
                </div>
            </div>

            {{-- Nav --}}
            <nav class="sidebar-nav">
                @foreach ($nav as $section => $items)
                    <div class="sidebar-section-label">{{ $sectionLabels[$section] }}</div>
                    @foreach ($items as $href => $item)
                        @php
                            $active = $href === '/' ? request()->is('/') : request()->is(ltrim($href, '/').'*');
                        @endphp
                        <a href="{{ $href }}"
                           class="sidebar-link {{ $active ? 'active' : '' }}"
                           {{ $active ? 'aria-current="page"' : '' }}>
                            {!! $item['icon'] !!}
                            {{ $item['label'] }}
                            @if (!empty($item['badge']) && $item['badge'] > 0)
                                <span class="sidebar-badge">{{ $item['badge'] }}</span>
                            @endif
                        </a>
                    @endforeach
                @endforeach
            </nav>

            {{-- Footer --}}
            <div class="sidebar-footer">
                <div class="sidebar-footer-text">AI Security Engineer Path</div>
            </div>
        </aside>

        {{-- ─── Main content area ─── --}}
        <div class="app-main">

            {{-- Top bar (mobile) --}}
            <header class="topbar">
                <button class="topbar-hamburger" id="hamburger-btn" onclick="openSidebar()" aria-label="Open navigation" aria-expanded="false" aria-controls="sidebar">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <span style="font-family:monospace;font-size:11px;font-weight:600;letter-spacing:0.15em;color:#64748b;text-transform:uppercase;">
                    {{ $title ?? 'Study Engine' }}
                </span>
            </header>

            {{-- Page content --}}
            <main class="page-content">
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

    </div>{{-- end app-shell --}}

    <script>
        // ─── Mobile sidebar ───
        function openSidebar() {
            document.getElementById('sidebar').classList.add('open');
            document.getElementById('sidebar-overlay').classList.add('open');
            document.getElementById('hamburger-btn').setAttribute('aria-expanded', 'true');
        }
        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('sidebar-overlay').classList.remove('open');
            document.getElementById('hamburger-btn').setAttribute('aria-expanded', 'false');
        }
        // Close sidebar on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeSidebar();
        });

        // ─── LLM-calling forms: show a working state so a slow model never looks broken ───
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

        // ─── Mission outline (Google-Docs style) ───
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
