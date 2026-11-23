@extends('layouts.app', ['title' => 'Exam Preparation Engine — Study Engine'])

@section('content')
    <div class="mb-8">
        <p class="eyebrow font-mono text-cyan-600">Active Recall &amp; Certification Prep</p>
        <h1 class="text-2xl font-semibold tracking-tight">Exam Preparation Hub</h1>
        <p class="mt-1 text-sm text-muted">
            Prepare for Checkpoints and Certifications using 4 scientifically proven study strategies. Triggered strictly by your ingested study content and practice.
        </p>
    </div>

    {{-- Content-Triggered Exam Targets Grid --}}
    <div class="mb-8">
        <h2 class="eyebrow mb-3">Active Exam Targets (Triggered by your ingested content &amp; practice)</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($upcomingExams as $exam)
                <div class="card p-5 border-l-4 border-l-cyan-500">
                    <div class="flex items-center justify-between font-mono text-xs text-muted">
                        <span class="uppercase font-semibold text-cyan-600">{{ $exam['type'] }}</span>
                        @if (isset($exam['coverage_pct']))
                            <span class="font-semibold text-emerald-700">Coverage: {{ $exam['coverage_pct'] }}%</span>
                        @else
                            <span>{{ $exam['when'] }}</span>
                        @endif
                    </div>
                    <h3 class="mt-1 text-base font-semibold text-ink">{{ $exam['title'] }}</h3>
                    
                    @if (isset($exam['phase_range']))
                        <p class="mt-1 font-mono text-xs text-cyan-800">
                            Scope: {{ $exam['phase_range'] }} ({{ $exam['ingested_count'] }}/{{ $exam['required_count'] }} topics ingested)
                        </p>
                    @endif

                    <p class="mt-1.5 text-xs text-muted">
                        {{ $exam['data']['notes'] ?? ($exam['data']['priority'] ?? 'Roadmap checkpoint exam.') }}
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <form method="POST" action="/exam-prep/start" data-llm>
                            @csrf
                            <input type="hidden" name="target" value="{{ $exam['title'] }}">
                            <input type="hidden" name="method" value="active_recall">
                            <button type="submit" class="btn btn-accent btn-sm">Start Timed Mock Exam →</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Ingest Research Report / Study Guide --}}
    <div class="card mb-10 p-6 bg-paper/80 border-cyan-500/20">
        <div class="flex items-center gap-3">
            <span class="font-mono text-xl">📄</span>
            <div>
                <h2 class="text-base font-semibold tracking-tight">Ingest External Research Report / Study Guide</h2>
                <p class="mt-0.5 text-xs text-muted">
                    Paste a research report or exam study guide prepared using Claude (with web search) to generate custom mock exams and distractor drills.
                </p>
            </div>
        </div>

        <form method="POST" action="/exam-prep/ingest" class="mt-4 space-y-4">
            @csrf
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-1">
                    <label class="label">Exam Target</label>
                    <input type="text" name="target" class="input font-mono text-xs" placeholder="e.g. Machine Learning Exam / CCA-F" required>
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Report / Study Guide Text</label>
                    <textarea name="report_text" rows="4" class="input font-mono text-xs" placeholder="Paste your research report or study guide content here..." required></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-outline btn-sm">Ingest Report &amp; Save for Exam Prep</button>
        </form>
    </div>

    {{-- 4 Scientifically Proven Study Methods Selection Cards --}}
    <div class="mb-10">
        <h2 class="eyebrow mb-3">Choose a scientifically proven study method</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            
            {{-- Method 1 --}}
            <div class="card p-5 flex flex-col justify-between">
                <div>
                    <span class="badge bg-cyan-500/15 text-cyan-800 font-mono">Method 1</span>
                    <h3 class="mt-2 text-base font-semibold">Simulated Conditions (Active Recall)</h3>
                    <p class="mt-1 text-xs text-muted">
                        Timed mock exam under strict conditions. Builds exam stamina, eliminates retrieval anxiety, and provides immediate misconception corrections.
                    </p>
                </div>
                <form method="POST" action="/exam-prep/start" data-llm class="mt-4">
                    @csrf
                    <input type="hidden" name="method" value="active_recall">
                    <div class="flex gap-2">
                        <input type="text" name="target" class="input text-xs" placeholder="Target (e.g. Checkpoint 1)" required>
                        <button type="submit" class="btn btn-accent btn-sm whitespace-nowrap">Start Exam</button>
                    </div>
                </form>
            </div>

            {{-- Method 2 --}}
            <div class="card p-5 flex flex-col justify-between">
                <div>
                    <span class="badge bg-emerald-500/15 text-emerald-800 font-mono">Method 2</span>
                    <h3 class="mt-2 text-base font-semibold">The 1-3-5-7 Rule (Spaced Repetition)</h3>
                    <p class="mt-1 text-xs text-muted">
                        Scheduled review intervals at 1, 3, 5, and 7 days post-ingestion. Forces the brain to reload concepts just before forgetting.
                    </p>
                </div>
                <form method="POST" action="/exam-prep/start" data-llm class="mt-4">
                    @csrf
                    <input type="hidden" name="method" value="spaced_1357">
                    <div class="flex gap-2">
                        <input type="text" name="target" class="input text-xs" placeholder="Target (e.g. ML Fundamentals)" required>
                        <button type="submit" class="btn btn-accent btn-sm whitespace-nowrap">Start Drill</button>
                    </div>
                </form>
            </div>

            {{-- Method 3 --}}
            <div class="card p-5 flex flex-col justify-between">
                <div>
                    <span class="badge bg-amber-500/15 text-amber-800 font-mono">Method 3</span>
                    <h3 class="mt-2 text-base font-semibold">"Think Like a Test Maker" Strategy</h3>
                    <p class="mt-1 text-xs text-muted">
                        Deconstructs multiple-choice exam questions. Teaches you to spot distractor traps (absolute wording like "always" or "never") and understand test maker logic.
                    </p>
                </div>
                <form method="POST" action="/exam-prep/start" data-llm class="mt-4">
                    @csrf
                    <input type="hidden" name="method" value="test_maker">
                    <div class="flex gap-2">
                        <input type="text" name="target" class="input text-xs" placeholder="Target (e.g. CCA-F Exam)" required>
                        <button type="submit" class="btn btn-accent btn-sm whitespace-nowrap">Analyze Traps</button>
                    </div>
                </form>
            </div>

            {{-- Method 4 --}}
            <div class="card p-5 flex flex-col justify-between">
                <div>
                    <span class="badge bg-purple-500/15 text-purple-800 font-mono">Method 4</span>
                    <h3 class="mt-2 text-base font-semibold">Metacognitive Preparation ("Think-Aloud")</h3>
                    <p class="mt-1 text-xs text-muted">
                        Requires you to verbalize/type out your exact thought process step-by-step before selecting an answer. Catches reasoning flaws before the exam.
                    </p>
                </div>
                <form method="POST" action="/exam-prep/start" data-llm class="mt-4">
                    @csrf
                    <input type="hidden" name="method" value="metacognitive">
                    <div class="flex gap-2">
                        <input type="text" name="target" class="input text-xs" placeholder="Target (e.g. Agentic AI Security)" required>
                        <button type="submit" class="btn btn-accent btn-sm whitespace-nowrap">Start Think-Aloud</button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    {{-- Recent Sessions List --}}
    @if ($recentSessions->isNotEmpty())
        <div class="card p-6">
            <h2 class="eyebrow mb-3">Recent Exam Prep Sessions</h2>
            <div class="space-y-3">
                @foreach ($recentSessions as $sess)
                    <div class="flex items-center justify-between border-b border-line/60 pb-3 text-xs">
                        <div>
                            <span class="font-semibold text-ink">{{ $sess->exam_target }}</span>
                            <span class="font-mono text-muted"> · Method: {{ $sess->method }}</span>
                            <p class="mt-0.5 text-muted">{{ $sess->created_at->diffForHumans() }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            @if ($sess->score !== null)
                                <span class="font-mono font-semibold text-cyan-700">Score: {{ $sess->score }}/100</span>
                            @endif
                            <a href="/exam-prep/session/{{ $sess->id }}" class="btn btn-outline btn-sm">Open Session →</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endsection
