@extends('layouts.app', ['title' => 'Dashboard — Study Engine'])

@section('content')
    <section class="hero mb-6">
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
        <div class="card mb-6 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line pb-4">
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
            <div class="mt-4 grid gap-4 text-xs sm:grid-cols-2">
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

    <div class="mb-10 flex flex-wrap items-stretch gap-4">
        <div class="card min-w-40 flex-1 p-5">
            <p class="eyebrow">Due for review</p>
            <p class="stat-num mt-2">{{ $dueCount }}</p>
            <p class="mt-1 font-mono text-xs text-muted">concept{{ $dueCount === 1 ? '' : 's' }} waiting</p>
        </div>
        <div class="card min-w-40 flex-1 p-5">
            <p class="eyebrow">Streak</p>
            <p class="stat-num mt-2">{{ $streak }}</p>
            <p class="mt-1 font-mono text-xs text-muted">day{{ $streak === 1 ? '' : 's' }} in a row</p>
        </div>
        @if ($missions->isNotEmpty())
            <label class="w-full self-end sm:w-80">
                <span class="sr-only">Browse missions</span>
                <select class="input" onchange="if (this.value) window.location = '/exercise/' + this.value">
                    <option value="">Browse missions by day…</option>
                    @foreach ($missions as $mission)
                        <option value="{{ $mission->id }}">
                            M{{ $mission->id }} · {{ $mission->date->format('D d M Y') }} — {{ $mission->title() }} · {{ $mission->status }}
                        </option>
                    @endforeach
                </select>
            </label>
        @endif
    </div>

    @if ($exercise)
        <div class="card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-paper/60 px-6 py-4">
                <div>
                    <p class="eyebrow">Mission M{{ $exercise->id }} // {{ $exercise->date->format('d M Y') }}</p>
                    <h2 class="mt-0.5 text-lg font-semibold tracking-tight">{{ $exercise->title() }}</h2>
                    @if ($exercise->status === 'pending' && $exercise->date->lt(today()))
                        <p class="mt-1 text-xs font-medium text-warn">
                            Unfinished mission from {{ $exercise->date->format('d M') }} — nothing is lost.
                            Open it and submit your result.
                        </p>
                    @endif
                </div>
                <div class="flex items-center gap-3">
                    <span class="badge badge-{{ $exercise->status }}">{{ $exercise->status }}</span>
                    <a href="/exercise/{{ $exercise->id }}" class="btn btn-sm">Open &amp; submit result →</a>
                </div>
            </div>
            <article class="mission px-6 py-6 text-sm">
                {!! Illuminate\Support\Str::markdown($exercise->mission_md, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
            </article>
        </div>
    @else
        <div class="card px-6 py-14 text-center">
            <p class="eyebrow">No mission yet today</p>
            <p class="mx-auto mt-3 max-w-md text-sm text-muted">
                Generate a mission from your schedule, or paste a new transcript on the Ingest page and
                have drills built from it directly.
            </p>
            <form method="POST" action="/exercise/generate" data-llm class="mt-6 flex flex-wrap items-center justify-center gap-3">
                @csrf
                @include('partials.generation-options')
                <button class="btn-accent">Generate today's Mission</button>
            </form>
            <a href="/ingest" class="mt-4 inline-block text-sm text-accent hover:underline">or ingest a new transcript →</a>
            <span class="mx-2 mt-4 inline-block text-sm text-muted">·</span>
            <a href="/side-quest" class="mt-4 inline-block text-sm text-accent hover:underline">⚔️ launch a side quest →</a>
        </div>
    @endif
@endsection
