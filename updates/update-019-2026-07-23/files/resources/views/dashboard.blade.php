@extends('layouts.app', ['title' => 'Dashboard — Study Engine'])

@section('content')
    <section class="hero mb-4">
        <p class="hero-eyebrow">Operation // AI Security Engineer</p>
        <h1 class="mt-2.5 text-3xl font-semibold tracking-tight text-white">
            Welcome back{{ $name !== '' ? ', '.$name : '' }}.
        </h1>
        <p class="mt-2 text-sm text-white/60">
            Phase {{ $phase->phase_number }} of {{ $topics->count() }} — {{ $phase->name }}
        </p>

        {{-- One segment per roadmap phase, width proportional to its week span. --}}
        <div class="progress-track mt-7" role="img"
            aria-label="Roadmap progress: {{ $progress['pct'] }}% covered">
            @foreach ($topics as $t)
                @php
                    $isDone    = in_array($t->phase_number, $completedPhaseNumbers);
                    $isCurrent = !$isDone && $t->phase_number === $phase->phase_number;
                @endphp
                <div class="progress-seg {{ $isDone ? 'is-done' : ($isCurrent ? 'is-current' : '') }}"
                    style="flex-grow: {{ max(1, $t->week_end - $t->week_start + 1) }}"
                    title="Phase {{ $t->phase_number }} — {{ $t->name }} (weeks {{ $t->week_start }}–{{ $t->week_end }}){{ $isDone ? ' ✓ Completed' : ($isCurrent ? ' ← Active' : '') }}"></div>
            @endforeach
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-x-6 gap-y-1 font-mono text-xs text-white/50">
            <span><span class="font-semibold text-cyan-300">{{ $progress['pct'] }}%</span> of the roadmap covered</span>
            <span>{{ count($completedPhaseNumbers) }} of {{ $topics->count() }} phases completed</span>
            <span>{{ $progress['left'] }} week{{ $progress['left'] === 1 ? '' : 's' }} remaining</span>
        </div>
    </section>

    @if ($notifications->isNotEmpty())
        <div class="mb-6 rounded-lg border border-cyan-500/30 bg-cyan-950/20 p-4 text-sm text-cyan-200">
            <div class="flex items-center justify-between font-medium">
                <span class="flex items-center gap-2 font-mono">
                    <span class="text-cyan-400">🔔 EXAM ALERT</span>
                    <span>({{ $notifications->count() }} unread)</span>
                </span>
                <a href="/exam-prep" class="text-xs font-semibold text-cyan-400 hover:underline">Go to Exam Prep →</a>
            </div>
            <ul class="mt-2 space-y-1.5 text-xs text-cyan-200/80">
                @foreach ($notifications->take(3) as $note)
                    <li class="flex items-start gap-2">
                        <span class="mt-0.5 font-mono text-cyan-400">•</span>
                        <span>{{ $note->message }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Current Phase & Portfolio Project Card --}}
    @if ($phase && $phaseCheck)
        <div class="card mb-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line pb-3">
                <div>
                    <p class="eyebrow">Active Phase {{ $phase->phase_number }}</p>
                    <h2 class="text-lg font-semibold text-ink">{{ $phase->name }}</h2>
                </div>
                <div class="flex items-center gap-2">
                    <a href="/phases/{{ $phase->phase_number }}" class="btn btn-outline btn-sm">View Phase &amp; Mega Project →</a>
                    <form method="POST" action="/phases/{{ $phase->phase_number }}/complete">
                        @csrf
                        <button type="submit" class="btn btn-accent btn-sm">Mark Phase Completed</button>
                    </form>
                </div>
            </div>
            <div class="mt-3 grid gap-3 text-xs sm:grid-cols-2">
                <div>
                    <p class="font-mono text-muted">Topic Coverage Check:</p>
                    @if (empty($phaseCheck['missing_topics']))
                        <p class="mt-1 font-semibold text-emerald-600">✓ All core phase concepts mastered!</p>
                    @else
                        <p class="mt-1 text-amber-700">
                            ⚠️ Lacking topics: <span class="font-mono font-medium text-ink">{{ implode(', ', $phaseCheck['missing_topics']) }}</span>
                        </p>
                    @endif
                </div>
                <div>
                    <p class="font-mono text-muted">AI Security Mega Project Status:</p>
                    @if ($phaseCheck['project_passed'])
                        <p class="mt-1 font-semibold text-emerald-600">✓ Mega Project Passed (Score: {{ $phaseCheck['project_score'] }}/100)</p>
                    @else
                        <p class="mt-1 text-amber-700">
                            ✕ Mega Project incomplete or pending evaluation (Score: {{ $phaseCheck['project_score'] ?? 'N/A' }})
                        </p>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Stats Grid — Row 1 --}}
    <div class="mb-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="card p-4">
            <p class="eyebrow">Due for review</p>
            <p class="stat-num mt-2">{{ $dueCount }}</p>
            <p class="mt-1 font-mono text-xs text-muted">concept{{ $dueCount === 1 ? '' : 's' }} waiting</p>
        </div>
        <div class="card p-4">
            <p class="eyebrow">Streak</p>
            <p class="stat-num mt-2">{{ $streak }}</p>
            <p class="mt-1 font-mono text-xs text-muted">day{{ $streak === 1 ? '' : 's' }} in a row</p>
        </div>
        <div class="card p-4">
            <p class="eyebrow">Total Concepts</p>
            <p class="stat-num mt-2">{{ $totalConcepts }}</p>
            <p class="mt-1 font-mono text-xs text-muted">in your knowledge base</p>
        </div>
    </div>

    {{-- Stats Grid — Row 2 + Today's Mission --}}
    <div class="mb-8 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="card p-4">
            <p class="eyebrow">Missions Completed</p>
            <p class="stat-num mt-2">{{ $missionsCompleted }}</p>
            <p class="mt-1 font-mono text-xs text-muted">evaluated &amp; scored</p>
        </div>
        <div class="card p-4">
            <p class="eyebrow">Side Quests Done</p>
            <p class="stat-num mt-2">{{ $sideQuestsCompleted }}</p>
            <p class="mt-1 font-mono text-xs text-muted">bonus missions cleared</p>
        </div>

        @if ($exercise)
            <div class="card flex flex-col justify-between p-4">
                <div>
                    <p class="eyebrow">Today's Mission</p>
                    <h3 class="mt-1.5 text-base font-semibold tracking-tight text-ink">{{ $exercise->title() }}</h3>
                    <div class="mt-2 flex items-center gap-2">
                        <span class="badge badge-{{ $exercise->status }}">{{ $exercise->status }}</span>
                        <span class="font-mono text-xs text-muted">M{{ $exercise->id }} · {{ $exercise->date->format('d M Y') }}</span>
                    </div>
                    @if ($exercise->status === 'pending' && $exercise->date->lt(today()))
                        <p class="mt-2 text-xs font-medium text-warn">
                            Overdue from {{ $exercise->date->format('d M') }} — open &amp; submit.
                        </p>
                    @endif
                </div>
                <a href="/exercise/{{ $exercise->id }}" class="btn btn-sm mt-4 w-full text-center">Open &amp; submit result →</a>
            </div>
        @else
            <div class="card flex flex-col items-center justify-center p-4 text-center">
                <p class="eyebrow">No mission yet today</p>
                <form method="POST" action="/exercise/generate" data-llm class="mt-3 flex flex-col items-center gap-2">
                    @csrf
                    @include('partials.generation-options')
                    <button class="btn-accent btn-sm">Generate Mission</button>
                </form>
                <div class="mt-3 flex items-center gap-2 text-xs">
                    <a href="/ingest" class="text-accent hover:underline">Ingest transcript →</a>
                    <span class="text-muted">·</span>
                    <a href="/side-quest" class="text-accent hover:underline">⚔️ Side quest →</a>
                </div>
            </div>
        @endif
    </div>
@endsection
