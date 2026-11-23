@extends('layouts.app', ['title' => 'Dashboard — Study Engine'])

@section('content')
    <section class="hero mb-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="hero-eyebrow">Operation // {{ \App\Support\StudyMode::label() }}</p>
            <div class="flex items-center gap-1 rounded-full bg-white/10 p-1" role="group" aria-label="Study track">
                @foreach (\App\Support\StudyMode::all() as $modeValue => $modeLabel)
                    @php $isActive = \App\Support\StudyMode::current() === $modeValue; @endphp
                    <form method="POST" action="/settings/mode">
                        @csrf
                        <input type="hidden" name="mode" value="{{ $modeValue }}">
                        <button type="submit"
                            class="rounded-full px-3 py-1 font-mono text-xs font-medium transition {{ $isActive ? 'bg-white text-ink' : 'text-white/55 hover:text-white' }}"
                            {{ $isActive ? 'disabled aria-current=true' : '' }}>
                            {{ \App\Support\StudyMode::shortLabel($modeValue) }}
                        </button>
                    </form>
                @endforeach
            </div>
        </div>
        <h1 class="mt-2.5 text-3xl font-semibold tracking-tight text-white">
            Welcome back{{ $name !== '' ? ', '.$name : '' }}.
        </h1>
        <p class="mt-2 text-sm text-white/60">
            Phase {{ $phase->phase_number }} of {{ $topics->count() }} — {{ $phase->name }} (week {{ $week }} of {{ $progress['total'] }})
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
            <span>{{ count($completedPhaseNumbers) - $revisingPhases->count() }} of {{ $topics->count() }} phases completed @if ($revisingPhases->isNotEmpty()) · {{ $revisingPhases->count() }} moved on (project still open) @endif</span>
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
                    @if ($phaseCheck['is_completable'])
                        <form method="POST" action="/phases/{{ $phase->phase_number }}/complete">
                            @csrf
                            <button type="submit" class="btn btn-accent btn-sm">{{ $phaseCheck['project_passed'] ? 'Mark Phase Completed' : 'Move on to next phase →' }}</button>
                        </form>
                    @else
                        <button type="button" class="btn btn-outline btn-sm" disabled title="{{ $phaseCheck['reason'] }}">Next phase locked</button>
                    @endif
                </div>
            </div>
            <div class="mt-3 grid gap-3 text-xs sm:grid-cols-2">
                <div>
                    <p class="font-mono text-muted">Topic Coverage Check:</p>
                    @if (empty($phaseCheck['missing_topics']))
                        <p class="mt-1 font-semibold text-emerald-600">✓ All core phase concepts mastered!</p>
                    @else
                        <p class="mt-1 text-blue-600/80">
                            💡 Optional gaps: <span class="font-mono font-medium text-ink">{{ implode(', ', $phaseCheck['missing_topics']) }}</span>
                            <span class="ml-1 text-muted font-normal">(advisory — won't block you)</span>
                        </p>
                    @endif
                </div>
                <div>
                    <p class="font-mono text-muted">{{ \App\Support\StudyMode::label() }} Mega Project Status:</p>
                    @if ($phaseCheck['project_passed'])
                        <p class="mt-1 font-semibold text-emerald-600">✓ Mega Project Passed (Score: {{ $phaseCheck['project_score'] }}/100)</p>
                    @elseif ($phaseCheck['project_attempted'])
                        <p class="mt-1 text-amber-700">
                            Attempted (Score: {{ $phaseCheck['project_score'] }}/100) — the next phase is unlocked; keep improving this project from its page.
                        </p>
                    @else
                        <p class="mt-1 text-amber-700">
                            ✕ Mega Project not submitted yet — one graded attempt (any score) unlocks the next phase.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Phases the learner moved on from after one graded attempt: their project is still being improved --}}
    @if ($revisingPhases->isNotEmpty())
        <div class="card mb-4 p-4">
            <p class="eyebrow">Projects still open // keep improving</p>
            <ul class="mt-2 space-y-1.5 text-sm">
                @foreach ($revisingPhases as $rev)
                    <li class="flex flex-wrap items-center justify-between gap-2">
                        <span>
                            <span class="font-mono text-xs text-muted">Phase {{ $rev->phase_number }}</span>
                            <span class="badge bg-amber-600 text-white">{{ $rev->project_score }}/100</span>
                            <span class="text-xs text-muted">needs 70 to turn Completed</span>
                        </span>
                        <a href="/phases/{{ $rev->phase_number }}" class="btn btn-outline btn-sm">Work on it →</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Unified 5-Stat Metrics Grid --}}
    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
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
            <p class="mt-1 font-mono text-xs text-muted">in knowledge base</p>
        </div>
        <div class="card p-4">
            <p class="eyebrow">Missions Done</p>
            <p class="stat-num mt-2">{{ $missionsCompleted }}</p>
            <p class="mt-1 font-mono text-xs text-muted">evaluated &amp; scored</p>
        </div>
        <div class="card p-4">
            <p class="eyebrow">Side Quests</p>
            <p class="stat-num mt-2">{{ $sideQuestsCompleted }}</p>
            <p class="mt-1 font-mono text-xs text-muted">bonus drills cleared</p>
        </div>
    </div>

    {{-- Today's Mission Action Hub --}}
    @if ($exercise)
        @php
            $isUnfinished = $exercise->status === 'pending' && $exercise->date->lt(today());
        @endphp
        <div class="card mb-8 border-accent/20 bg-accent-wash/20 p-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <p class="eyebrow text-accent-deep">{{ $isUnfinished ? 'Unfinished mission' : "Today's Mission" }}</p>
                        <span class="badge badge-{{ $exercise->status }}">{{ $exercise->status }}</span>
                        <span class="font-mono text-xs text-muted">M{{ $exercise->id }} · {{ $exercise->date->format('d M Y') }}</span>
                    </div>
                    <h3 class="mt-1.5 text-base font-semibold tracking-tight text-ink">{{ $exercise->title() }}</h3>
                    @if ($isUnfinished)
                        <p class="mt-1.5 text-xs font-medium text-warn">
                            ⚠️ Overdue from {{ $exercise->date->format('d M') }} — open &amp; submit your result.
                        </p>
                    @endif
                </div>
                <div class="shrink-0">
                    <a href="/exercise/{{ $exercise->id }}" class="btn-accent btn-sm whitespace-nowrap">Open &amp; Submit Result →</a>
                </div>
            </div>
        </div>
    @else
        <div class="card mb-8 p-5">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div>
                    <p class="eyebrow">Today's Mission</p>
                    <h3 class="mt-1 text-base font-semibold text-ink">No mission generated yet today</h3>
                    <p class="mt-0.5 text-xs text-muted">
                        Generate today's interleaved practice drill set or jump to ingest/side quest.
                    </p>
                </div>
                <div class="shrink-0">
                    <form method="POST" action="/exercise/generate" data-llm class="flex flex-wrap items-center gap-3">
                        @csrf
                        @include('partials.generation-options')
                        <button type="submit" class="btn-accent btn-sm whitespace-nowrap">⚡ Generate Mission</button>
                    </form>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-line/60 pt-3 text-xs text-muted">
                <span>Quick actions:</span>
                <a href="/ingest" class="font-medium text-accent hover:underline">Ingest transcript →</a>
                <span>·</span>
                <a href="/side-quest" class="font-medium text-accent hover:underline">⚔️ Target a weakness (Side quest) →</a>
            </div>
        </div>
    @endif
@endsection
