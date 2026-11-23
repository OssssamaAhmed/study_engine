@extends('layouts.app', ['title' => 'Mission — Study Engine'])

@section('content')
    <div class="mb-10 lg:grid lg:grid-cols-[13rem_minmax(0,1fr)] lg:items-start lg:gap-6">
        <aside id="mission-toc" class="hidden lg:sticky lg:top-20 lg:block"></aside>
        <div class="card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-paper/60 px-6 py-4">
                <div>
                    <p class="eyebrow">Mission M{{ $exercise->id }} // {{ $exercise->date->format('d M Y') }}</p>
                    <h1 class="mt-0.5 text-lg font-semibold tracking-tight">{{ $exercise->title() }}</h1>
                </div>
                <span class="badge badge-{{ $exercise->status }}">{{ $exercise->status }}</span>
            </div>
            <article class="mission px-6 py-6 text-sm" data-outline>
                @foreach ($sections as $section)
                    {!! Illuminate\Support\Str::markdown($section['md'], ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                    @if ($section['key'])
                        @include('exercise.partials.drill-tools', ['key' => $section['key'], 'md' => $section['copy']])
                    @endif
                @endforeach
            </article>

            @if ($drillKeys->isNotEmpty())
                @php
                    $scored = $drillAttempts->toBase()->only($drillKeys->all())->whereNotNull('score');
                @endphp
                <div id="drill-total" class="border-t border-line bg-paper/60 px-6 py-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="eyebrow">Inline drills — total</p>
                            <p class="mt-0.5 text-xs text-muted">
                                <span data-total-count>{{ $scored->count() }}</span> of {{ $drillKeys->count() }} drills checked
                                · <span data-total-points>{{ $scored->sum('score') }}</span>/{{ $drillKeys->count() * 100 }} points
                            </p>
                        </div>
                        <span class="rounded-md bg-ink px-3 py-1.5 font-mono text-sm font-semibold text-white"><span data-total-avg>{{ $scored->isEmpty() ? '—' : (int) round($scored->avg('score')) }}</span><span class="text-white/50">/100 avg</span></span>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach ($drillKeys as $key)
                            @php $s = $drillAttempts[$key]->score ?? null; @endphp
                            <a href="#drill-{{ $key }}" class="badge {{ is_null($s) ? 'badge-quiet' : ($s >= 70 ? 'badge-evaluated' : 'badge-pending') }}"
                               data-score-for="{{ $key }}" data-score="{{ $s }}">{{ $key }} · {{ $s ?? '—' }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="mb-10">
        <p class="eyebrow">Debrief</p>
        <h2 class="mb-4 mt-1 text-lg font-semibold tracking-tight">Your result</h2>
        <form method="POST" action="/exercise/{{ $exercise->id }}/submit" data-llm class="card space-y-4 p-6">
            @csrf
            @if ($drillKeys->isNotEmpty())
                <p class="text-xs text-muted">
                    Drills you checked inline above are sent along automatically — paste only the rest here
                    (e.g. your Boss Fight from VS Code), or leave it empty if you did everything inline.
                </p>
            @endif
            <textarea name="pasted_result" rows="30" {{ $drillKeys->isEmpty() ? 'required' : '' }}
                class="input font-mono text-[13px] leading-relaxed"
                style="min-height:20rem; resize:vertical"
                placeholder="Paste your code, answers, terminal output…"
                oninput="this.style.height='auto'; this.style.height=this.scrollHeight+'px'"
                onfocus="this.style.height='auto'; this.style.height=this.scrollHeight+'px'">{{ old('pasted_result', $attempt?->pasted_result) }}</textarea>
            @error('pasted_result') <p class="text-sm text-danger">{{ $message }}</p> @enderror
            <div class="flex flex-wrap items-center gap-4">
                <button class="btn-accent">Submit for evaluation</button>
                <p class="text-xs text-muted">Resubmitting re-evaluates and updates the schedule again.</p>
            </div>
            <p class="border-t border-line pt-3 text-xs text-muted">
                Beginner workflow: write the code yourself → run it → paste it here.
                Use the <a href="/chat" class="text-accent hover:underline">Tutor</a> to explain errors or
                review your finished code — not to write it for you. Writing the first draft is where the learning happens.
                Tip: in chat, say <code class="rounded bg-paper px-1 py-0.5 font-mono">M{{ $exercise->id }} D2</code> to discuss
                drill 2 of this mission — the tutor fetches it itself, no pasting needed.
            </p>
        </form>
    </div>

    @if ($attempt)
        <div class="card mb-8 overflow-hidden">
            <div class="flex items-center justify-between border-b border-line bg-paper/60 px-6 py-4">
                <div>
                    <p class="eyebrow">Evaluation</p>
                    <h2 class="mt-0.5 text-lg font-semibold tracking-tight">Feedback</h2>
                </div>
                <div class="flex items-center gap-3">
                    @if (! is_null($attempt->score))
                        <span class="rounded-md bg-ink px-3 py-1.5 font-mono text-sm font-semibold text-white">{{ $attempt->score }}<span class="text-white/50">/100</span></span>
                    @endif
                    <form method="POST" action="/exercise/{{ $exercise->id }}/reset"
                          onsubmit="return confirm('Clear this evaluation and start fresh?')">
                        @csrf
                        <button class="btn-outline text-xs">Reset Evaluation</button>
                    </form>
                </div>
            </div>
            <article class="mission px-6 py-6 text-sm">
                {!! Illuminate\Support\Str::markdown($attempt->evaluation_md, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
            </article>
        </div>

        <div class="card mb-8 overflow-hidden">
            <div class="border-b border-line bg-paper/60 px-6 py-4">
                <p class="eyebrow">Per-concept schedule</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-line text-left text-xs uppercase tracking-wider text-muted">
                            <th class="px-6 py-3 font-medium">Concept</th>
                            <th class="py-3 font-medium">Role</th>
                            <th class="py-3 font-medium">Interval</th>
                            <th class="py-3 font-medium">Next review</th>
                            <th class="px-6 py-3 font-medium">Lapses</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($concepts as $concept)
                            <tr class="border-b border-line/60 last:border-0">
                                <td class="px-6 py-3 font-medium">{{ $concept->name }}</td>
                                <td class="py-3"><span class="badge badge-quiet">{{ $concept->pivot->role }}</span></td>
                                <td class="py-3 font-mono tabular-nums">{{ $concept->interval_days }}d</td>
                                <td class="py-3 font-mono tabular-nums">{{ $concept->next_review_at?->toDateString() }}</td>
                                <td class="px-6 py-3 font-mono tabular-nums {{ $concept->lapses > 0 ? 'text-danger' : '' }}">{{ $concept->lapses }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card p-6">
            <p class="eyebrow">This was hard for me</p>
            <form method="POST" action="/exercise/{{ $exercise->id }}/note" class="mt-3 space-y-3">
                @csrf
                <textarea name="note" rows="4" required maxlength="1000" class="input leading-relaxed"
                    placeholder="Describe what felt difficult — attaches to all concepts of this mission"></textarea>
                <button class="btn-outline">Add note</button>
            </form>
        </div>
    @endif

    {{-- Follow-up generator --}}
    <div class="card mt-8 p-6">
        <div class="mb-4 flex items-center gap-3">
            <span class="text-xl">⚔️</span>
            <div>
                <p class="eyebrow">Round 2</p>
                <h2 class="mt-0.5 text-base font-semibold tracking-tight">Want more practice on these same concepts?</h2>
            </div>
        </div>
        <p class="mb-5 text-xs text-muted">
            Generate a brand-new follow-up mission using the same concepts as this one.
            Tweak the difficulty and choose how many drills or boss fights you want.
        </p>
        <form method="POST" action="/exercise/{{ $exercise->id }}/add-more" data-llm
              class="flex flex-wrap items-center gap-3">
            @csrf
            @include('partials.generation-options')
            <button class="btn-accent btn-sm whitespace-nowrap">⚡ Generate follow-up mission →</button>
        </form>
    </div>

    <script>
        // ─── Copy drill / Boss Fight (body only — Setup/Task/Output/Concepts, ready for VS Code) ───
        document.querySelectorAll('[data-copy]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const text = btn.dataset.copy;
                try {
                    await navigator.clipboard.writeText(text);
                } catch {
                    const tmp = Object.assign(document.createElement('textarea'), { value: text });
                    document.body.appendChild(tmp);
                    tmp.select();
                    document.execCommand('copy');
                    tmp.remove();
                }
                const label = btn.textContent;
                btn.textContent = '✓ Copied';
                setTimeout(() => (btn.textContent = label), 1500);
            });
        });

        // ─── Inline drill editors: Tab indents, Ctrl+Enter checks, box grows with the code ───
        const grow = (el) => { el.style.height = 'auto'; el.style.height = el.scrollHeight + 'px'; };
        document.querySelectorAll('.drill-editor').forEach((ed) => {
            grow(ed);
            ed.addEventListener('input', () => grow(ed));
            ed.addEventListener('keydown', (e) => {
                if (e.key === 'Tab' && !e.shiftKey) {
                    e.preventDefault();
                    ed.setRangeText('    ', ed.selectionStart, ed.selectionEnd, 'end');
                    grow(ed);
                } else if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                    e.preventDefault();
                    ed.form.requestSubmit();
                }
            });
        });

        // ─── Check one drill without leaving the page, then refresh its score + the total ───
        const badgeClass = (s) => s === '' ? 'badge-quiet' : (Number(s) >= 70 ? 'badge-evaluated' : 'badge-pending');
        function refreshTotal() {
            const chips = [...document.querySelectorAll('#drill-total [data-score-for]')];
            const scores = chips.map((c) => c.dataset.score).filter((s) => s !== '').map(Number);
            const sum = scores.reduce((a, b) => a + b, 0);
            document.querySelector('[data-total-count]').textContent = scores.length;
            document.querySelector('[data-total-points]').textContent = sum;
            document.querySelector('[data-total-avg]').textContent = scores.length ? Math.round(sum / scores.length) : '—';
        }
        document.querySelectorAll('form.drill-form').forEach((form) => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const key = form.dataset.key;
                const button = form.querySelector('button');
                const status = form.querySelector('.drill-status');
                button.disabled = true;
                button.classList.add('animate-pulse');
                status.textContent = 'Checking — the model can take a moment…';
                try {
                    const res = await fetch(form.action, {
                        method: 'POST',
                        headers: { Accept: 'application/json' },
                        body: new FormData(form),
                    });
                    const data = await res.json();
                    if (!res.ok) throw new Error(data.error || data.message || 'Check failed');
                    const score = data.score === null ? '' : String(data.score);
                    document.querySelectorAll(`[data-score-for="${key}"]`).forEach((b) => {
                        b.dataset.score = score;
                        b.classList.remove('badge-quiet', 'badge-evaluated', 'badge-pending');
                        b.classList.add(badgeClass(score));
                        b.textContent = `${key} · ${score || '—'}` + (score && !b.closest('#drill-total') ? '/100' : '');
                    });
                    form.querySelector('.drill-feedback').innerHTML = data.feedback_html;
                    status.textContent = '';
                    refreshTotal();
                } catch (err) {
                    status.textContent = '✕ ' + err.message;
                } finally {
                    button.disabled = false;
                    button.classList.remove('animate-pulse');
                }
            });
        });
    </script>
@endsection
