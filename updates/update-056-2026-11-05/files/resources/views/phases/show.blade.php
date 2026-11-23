@extends('layouts.app', ['title' => "Phase {$phaseNumber} — Study Engine"])

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="/phases" class="font-mono text-xs text-muted hover:underline">← Back to All Phases</a>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight">Phase {{ $phaseNumber }}: {{ $phaseInfo['title'] }}</h1>
            <p class="font-mono text-xs text-muted">Weeks {{ $phaseInfo['weeks'] }}</p>
        </div>
        <div>
            <form method="POST" action="/phases/{{ $phaseNumber }}/complete">
                @csrf
                <button type="submit" class="btn btn-accent">Mark Phase Completed</button>
            </form>
        </div>
    </div>

    @if ($phaseInfo['notes'] ?? false)
        <div class="card mb-6 p-5 text-sm text-muted">
            <p class="eyebrow mb-1">Phase Notes &amp; Strategy</p>
            <p>{{ $phaseInfo['notes'] }}</p>
        </div>
    @endif

    {{-- Concept Coverage Validation --}}
    <div class="card mb-8 p-6">
        <h2 class="text-lg font-semibold tracking-tight">1. Concept Mastery Check</h2>
        <p class="mt-1 text-sm text-muted">
            The Study Engine checks your concepts database to ensure you have learned and practiced the essential topics required for this phase.
        </p>

        <div class="mt-4">
            @if (empty($check['missing_topics']))
                <div class="rounded-md border border-emerald-500/30 bg-emerald-50/50 p-4 text-sm text-emerald-800">
                    <span class="font-semibold">✓ Concept Coverage Approved!</span> You have learned all required core concepts for this phase.
                </div>
            @else
                <div class="rounded-md border border-amber-500/30 bg-amber-50/50 p-4 text-sm text-amber-900">
                    <p class="font-semibold text-amber-800">⚠️ Phase Completion Declined — Missing Topics:</p>
                    <p class="mt-1 font-mono text-xs">First learn and practice these topics:</p>
                    <ul class="mt-2 list-disc pl-5 space-y-1 font-mono text-xs text-amber-950">
                        @foreach ($check['missing_topics'] as $topic)
                            <li>{{ $topic }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>

    {{-- Mega Portfolio Project --}}
    <div class="card mb-8 overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-paper/60 px-6 py-4">
            <div>
                <p class="eyebrow">Portfolio Milestone // {{ \App\Support\StudyMode::label() }}</p>
                <h2 class="text-lg font-semibold tracking-tight">2. Mega Portfolio Project</h2>
            </div>
            <div class="flex items-center gap-2">
                @if ($progress?->mega_project_md)
                    <button id="btn-new-research" type="button" class="btn btn-outline btn-sm" onclick="openResearchFlow()">
                        🔬 New Research + Regenerate
                    </button>
                    <form method="POST" action="/phases/{{ $phaseNumber }}/project" data-llm>
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline" title="Regenerate without new research">
                            ↺ Quick Regenerate
                        </button>
                    </form>
                @else
                    <button id="btn-start-research" type="button" class="btn btn-outline btn-sm" onclick="openResearchFlow()">
                        🔬 Generate Mega Project Brief
                    </button>
                @endif
            </div>
        </div>

        {{-- Research Ingestion Flow Panel --}}
        <div id="research-flow" class="hidden border-b border-line bg-paper/40">

            {{-- Stage 1: Research Prompt --}}
            <div id="stage-1" class="px-6 py-6">
                <div class="mb-4 flex items-start gap-3">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent/10 text-sm font-bold text-accent">1</div>
                    <div>
                        <h3 class="font-semibold text-ink">Copy this prompt into Claude Pro</h3>
                        <p class="mt-0.5 text-xs text-muted">Claude Pro will research Reddit, GitHub, HN, job boards, CVE feeds, and DEF CON talks to find what 2026 AI Security employers actually need — then paste its response back here.</p>
                    </div>
                </div>

                <div class="relative">
                    <div id="prompt-loading" class="flex items-center gap-2 py-8 text-center text-sm text-muted">
                        <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-accent border-t-transparent"></span>
                        <span>Building your research prompt…</span>
                    </div>
                    <textarea id="research-prompt-box"
                        class="input hidden font-mono text-xs leading-relaxed"
                        rows="20"
                        readonly
                        placeholder="Loading prompt…"></textarea>
                </div>

                <div class="mt-3 flex items-center justify-between">
                    <button id="copy-btn" type="button"
                        class="btn btn-accent btn-sm hidden"
                        onclick="copyPrompt()">
                        📋 Copy Prompt to Clipboard
                    </button>
                    <button id="stage1-next" type="button"
                        class="btn btn-sm btn-outline hidden ml-auto"
                        onclick="goToStage2()">
                        I've got the research → Stage 2
                    </button>
                </div>
            </div>

            {{-- Stage 2: Paste Research + Generate --}}
            <div id="stage-2" class="hidden border-t border-line px-6 py-6">
                <div class="mb-4 flex items-start gap-3">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent text-sm font-bold text-white">2</div>
                    <div>
                        <h3 class="font-semibold text-ink">Paste Claude Pro's research here</h3>
                        <p class="mt-0.5 text-xs text-muted">Paste the full response from Claude Pro. Your app's LLM will use this real-world research to generate a market-grounded portfolio project brief.</p>
                    </div>
                </div>

                <form method="POST" action="/phases/{{ $phaseNumber }}/project-from-research" data-llm>
                    @csrf
                    @error('research')
                        <div class="mb-3 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-xs text-red-800">
                            {{ $message }}
                        </div>
                    @enderror
                    <textarea
                        id="research-input"
                        name="research"
                        rows="16"
                        class="input font-mono text-xs leading-relaxed"
                        placeholder="Paste Claude Pro's research output here…&#10;&#10;Expected format: Top 5 hottest project areas, key technologies, attack vectors, what makes a GitHub project stand out, emerging standards…"
                        required>{{ old('research', $progress?->research_context) }}</textarea>
                    <div class="mt-3 flex items-center justify-between">
                        <button type="button" class="text-xs text-muted hover:text-ink hover:underline" onclick="goToStage1()">← Back to Stage 1</button>
                        <button type="submit" class="btn btn-accent">
                            ✦ Generate Brief from Research
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="px-6 py-6">
            @if ($progress?->mega_project_md)
                @if ($progress->research_context)
                    <div class="mb-4 flex items-center gap-2 rounded-md border border-accent/20 bg-accent-wash px-4 py-2.5 text-xs text-accent-deep">
                        <span>✦</span>
                        <span><strong>Research-grounded brief</strong> — generated from real 2026 market research you ingested.</span>
                    </div>
                @endif
                {{-- Document tabs: one of Brief / Plan / Skeleton is shown at a time --}}
                <div class="mb-6 flex flex-wrap gap-2 border-b border-line pb-3" role="tablist">
                    <button type="button" class="doc-tab btn btn-sm btn-outline" data-tab="brief" role="tab">📄 Brief</button>
                    <button type="button" class="doc-tab btn btn-sm btn-outline" data-tab="plan" role="tab">
                        🗺 Implementation Plan @unless ($progress->implementation_plan_md)<span class="text-muted">(not yet)</span>@endunless
                    </button>
                    <button type="button" class="doc-tab btn btn-sm btn-outline" data-tab="skeleton" role="tab">
                        🦴 Plain-English Skeleton @unless ($progress->skeleton_md)<span class="text-muted">(not yet)</span>@endunless
                    </button>
                </div>

                <div class="doc-panel" data-panel="brief">
                <article class="mission text-sm">
                    {!! Illuminate\Support\Str::markdown($progress->mega_project_md, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                </article>
                @include('phases.partials.export-buttons', ['phase' => $phaseNumber, 'document' => 'brief'])
                </div>

                {{-- Stage 3: Implementation Plan --}}
                <div class="doc-panel hidden" data-panel="plan">
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-ink">Implementation Plan</h3>
                            <p class="mt-0.5 text-xs text-muted">A milestone-based work breakdown with binary Definition-of-Done gates — optional, recommended before you start building.</p>
                        </div>
                        <form method="POST" action="/phases/{{ $phaseNumber }}/implementation-plan" data-llm class="flex flex-wrap items-center gap-3">
                            @csrf
                            <label class="flex items-center gap-1.5 text-xs font-medium text-muted" title="Full hand-holding: every step says what to do, in which file, with which tool — and how to check it works">
                                <input type="checkbox" name="learner_mode" value="1" @checked(session('learner_mode'))>
                                <span>Learner mode</span>
                            </label>
                            <button type="submit" class="btn btn-sm btn-outline">
                                {{ $progress->implementation_plan_md ? '↺ Regenerate Plan' : '✦ Generate Implementation Plan' }}
                            </button>
                        </form>
                    </div>

                    @if ($progress->implementation_plan_md)
                        <article class="mission text-sm">
                            {!! Illuminate\Support\Str::markdown($progress->implementation_plan_md, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                        </article>
                        @include('phases.partials.cut-off-warning', ['md' => $progress->implementation_plan_md])
                        @include('phases.partials.export-buttons', ['phase' => $phaseNumber, 'document' => 'plan'])
                    @else
                        <p class="text-xs text-muted">No plan yet — click Generate above (tick Learner mode for step-by-step hand-holding).</p>
                    @endif
                </div>

                {{-- Stage 4: Plain-English Skeleton --}}
                <div class="doc-panel hidden" data-panel="skeleton">
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-ink">Plain-English Skeleton</h3>
                            <p class="mt-0.5 text-xs text-muted">A no-code pseudocode walkthrough of the whole project, grounded in the plan's milestones.</p>
                        </div>
                        @if ($progress->implementation_plan_md)
                            <form method="POST" action="/phases/{{ $phaseNumber }}/skeleton" data-llm class="flex flex-wrap items-center gap-3">
                                @csrf
                                <label class="flex items-center gap-1.5 text-xs font-medium text-muted" title="Full hand-holding: every step says what to do, in which file, with which tool — and how to check it works">
                                    <input type="checkbox" name="learner_mode" value="1" @checked(session('learner_mode'))>
                                    <span>Learner mode</span>
                                </label>
                                <button type="submit" class="btn btn-sm btn-outline">
                                    {{ $progress->skeleton_md ? '↺ Regenerate Skeleton' : '✦ Generate Skeleton' }}
                                </button>
                            </form>
                        @endif
                    </div>

                    @if ($progress->skeleton_md)
                        <article class="mission text-sm">
                            {!! Illuminate\Support\Str::markdown($progress->skeleton_md, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                        </article>
                        @include('phases.partials.cut-off-warning', ['md' => $progress->skeleton_md])
                        @include('phases.partials.export-buttons', ['phase' => $phaseNumber, 'document' => 'skeleton'])
                    @elseif (! $progress->implementation_plan_md)
                        <p class="text-xs text-muted">Generate the implementation plan first (Implementation Plan tab) to unlock this step.</p>
                    @endif
                </div>

                {{-- Project Evaluation Status (below the tabs, always visible) --}}
                @if ($progress->project_eval_md)
                    <div class="my-8 rounded-lg border p-5 text-sm {{ $progress->project_score >= 70 ? 'border-emerald-500/30 bg-emerald-50/30' : 'border-amber-500/30 bg-amber-50/30' }}">
                        <div class="flex items-center justify-between font-mono text-xs">
                            <span class="font-semibold">Evaluation Score: {{ $progress->project_score }}/100</span>
                            <span class="badge {{ $progress->project_score >= 70 ? 'bg-emerald-600 text-white' : 'bg-amber-600 text-white' }}">
                                {{ $progress->project_score >= 70 ? 'PASSED' : 'NEEDS REVISION (Requires 70+)' }}
                            </span>
                        </div>
                        <div class="mission mt-4 text-xs">
                            {!! Illuminate\Support\Str::markdown($progress->project_eval_md, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                        </div>
                    </div>
                @endif

                {{-- Submit Project Form --}}
                <div class="mt-8 border-t border-line pt-6">
                    <h3 class="text-base font-semibold">Submit Your Implementation</h3>
                    <p class="mt-1 text-xs text-muted">
                        Paste your code, test results, architectural documentation, or GitHub README text below for LLM evaluation.
                    </p>

                    <form method="POST" action="/phases/{{ $phaseNumber }}/submit" data-llm class="mt-4 space-y-4">
                        @csrf
                        <div>
                            <textarea name="submission" rows="12" class="input font-mono text-xs"
                                placeholder="Paste your project code, architecture specs, and test output here..." required>{{ old('submission', $progress->project_submission) }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-accent">Submit Mega Project for AI Evaluation</button>
                    </form>
                </div>
            @else
                <div class="py-8 text-center text-muted">
                    <p class="text-2xl mb-3">🔬</p>
                    <p class="text-sm font-medium">No Mega Project brief yet for Phase {{ $phaseNumber }}.</p>
                    <p class="mt-1 text-xs">Click <strong>Generate Mega Project Brief</strong> above to start the research-grounded workflow.</p>
                </div>
            @endif
        </div>
    </div>

    <script>
    (function () {
        const phaseNumber = {{ $phaseNumber }};
        let promptText = '';

        function openResearchFlow() {
            const flow = document.getElementById('research-flow');
            flow.classList.remove('hidden');
            flow.scrollIntoView({ behavior: 'smooth', block: 'start' });
            // Auto-load the prompt
            loadResearchPrompt();
        }

        function loadResearchPrompt() {
            const box = document.getElementById('research-prompt-box');
            const loading = document.getElementById('prompt-loading');
            const copyBtn = document.getElementById('copy-btn');
            const nextBtn = document.getElementById('stage1-next');

            if (promptText) {
                // Already loaded
                showPromptBox(box, loading, copyBtn, nextBtn);
                return;
            }

            loading.classList.remove('hidden');
            box.classList.add('hidden');
            copyBtn.classList.add('hidden');
            nextBtn.classList.add('hidden');

            fetch(`/phases/${phaseNumber}/research-prompt`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(data => {
                if (data.error) throw new Error(data.error);
                promptText = data.prompt;
                box.value = promptText;
                showPromptBox(box, loading, copyBtn, nextBtn);
            })
            .catch(err => {
                loading.innerHTML = `<span class="text-red-600">Failed to load prompt: ${err.message}</span>`;
            });
        }

        function showPromptBox(box, loading, copyBtn, nextBtn) {
            loading.classList.add('hidden');
            box.classList.remove('hidden');
            copyBtn.classList.remove('hidden');
            nextBtn.classList.remove('hidden');
        }

        function copyPrompt() {
            const btn = document.getElementById('copy-btn');
            navigator.clipboard.writeText(promptText).then(() => {
                btn.textContent = '✓ Copied!';
                btn.classList.remove('btn-accent');
                btn.classList.add('btn-outline');
                setTimeout(() => {
                    btn.textContent = '📋 Copy Prompt to Clipboard';
                    btn.classList.add('btn-accent');
                    btn.classList.remove('btn-outline');
                }, 2500);
            }).catch(() => {
                // Fallback: select the textarea
                document.getElementById('research-prompt-box').select();
                btn.textContent = '👆 Selected — Ctrl+C to copy';
                setTimeout(() => { btn.textContent = '📋 Copy Prompt to Clipboard'; }, 3000);
            });
        }

        function goToStage2() {
            document.getElementById('stage-2').classList.remove('hidden');
            document.getElementById('stage-2').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function goToStage1() {
            document.getElementById('stage-2').classList.add('hidden');
            document.getElementById('stage-1').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        // Expose to onclick handlers
        window.openResearchFlow = openResearchFlow;
        window.copyPrompt = copyPrompt;
        window.goToStage2 = goToStage2;
        window.goToStage1 = goToStage1;

        // Learner-mode "*→ why*" notes show as a small teal line under their action
        document.querySelectorAll('.doc-panel article.mission em').forEach(em => {
            if (em.textContent.trim().startsWith('→')) em.classList.add('why');
        });

        // "Reference solution" headings fold into a closed <details> (open only after the checkpoint passes)
        document.querySelectorAll('.doc-panel article.mission').forEach(article => {
            article.querySelectorAll('h2, h3, h4, h5').forEach(h => {
                if (! /^reference solution/i.test(h.textContent.trim())) return;
                const level = Number(h.tagName[1]);
                const details = document.createElement('details');
                details.className = 'reference-solution';
                const summary = document.createElement('summary');
                summary.textContent = '🔒 ' + h.textContent.trim() + ' — open only after the checkpoint passes or 20 minutes stuck';
                details.appendChild(summary);
                let node = h.nextElementSibling;
                while (node && ! (/^H[1-6]$/.test(node.tagName) && Number(node.tagName[1]) <= level)) {
                    const following = node.nextElementSibling;
                    details.appendChild(node);
                    node = following;
                }
                h.replaceWith(details);
            });
        });

        // Brief / Plan / Skeleton tabs — the URL hash (#plan, set by the generate redirects) wins, then the last tab viewed
        const tabKey = `phase-${phaseNumber}-tab`;
        function showTab(name) {
            const panels = document.querySelectorAll('.doc-panel');
            if (![...panels].some(p => p.dataset.panel === name)) name = 'brief';
            panels.forEach(p => p.classList.toggle('hidden', p.dataset.panel !== name));
            document.querySelectorAll('.doc-tab').forEach(b => {
                const on = b.dataset.tab === name;
                b.classList.toggle('btn-accent', on);
                b.classList.toggle('btn-outline', ! on);
                b.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            try { localStorage.setItem(tabKey, name); } catch (e) {}
        }
        document.querySelectorAll('.doc-tab').forEach(b => b.addEventListener('click', () => {
            showTab(b.dataset.tab);
            history.replaceState(null, '', '#' + b.dataset.tab);
        }));
        if (document.querySelector('.doc-panel')) {
            let saved = null;
            try { saved = localStorage.getItem(tabKey); } catch (e) {}
            showTab(location.hash.slice(1) || saved || 'brief');
        }

        // If there's a validation error on 'research', auto-open to stage 2
        @error('research')
            document.addEventListener('DOMContentLoaded', () => {
                openResearchFlow();
                goToStage2();
            });
        @enderror
    })();
    </script>
@endsection
