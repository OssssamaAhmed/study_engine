@extends('layouts.app', ['title' => 'Phases & Portfolio Projects — Study Engine'])

@section('content')
    <div class="mb-8">
        <p class="eyebrow">Curriculum Roadmap</p>
        <h1 class="text-2xl font-semibold tracking-tight">Phases &amp; Mega Portfolio Projects</h1>
        <p class="mt-1 text-sm text-muted">
            Each phase requires mastering core concepts and building an AI Security Engineer Mega Portfolio Project before completion.
        </p>
    </div>

    <div class="space-y-4">
        @foreach ($phases as $item)
            @php
                $p = $item['phase'];
                $check = $item['check'];
                $isCurrent = $p['phase_number'] === $currentPhaseNumber;
                $isDone = $check['progress']?->status === 'completed';
            @endphp
            <div class="card p-5 transition-colors {{ $isCurrent ? 'border-l-4 border-l-cyan-400 bg-white/40' : '' }}">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs font-semibold uppercase text-muted">Phase {{ $p['phase_number'] }} · Weeks {{ $p['week_start'] }}–{{ $p['week_end'] }}</span>
                            @if ($isDone)
                                <span class="badge bg-emerald-500/15 text-emerald-700">Completed</span>
                            @elseif ($isCurrent)
                                <span class="badge bg-cyan-500/15 text-cyan-700">Active Phase</span>
                            @endif
                        </div>
                        <h2 class="mt-1 text-lg font-semibold text-ink">
                            <a href="/phases/{{ $p['phase_number'] }}" class="hover:text-accent hover:underline">
                                {{ $p['name'] }}
                            </a>
                        </h2>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="/phases/{{ $p['phase_number'] }}" class="btn btn-outline btn-sm">View Phase &amp; Project →</a>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-4 border-t border-line/60 pt-3 text-xs text-muted">
                    <div>
                        <span>Concept Check: </span>
                        @if (empty($check['missing_topics']))
                            <span class="font-semibold text-emerald-600">✓ All Topics Covered</span>
                        @else
                            <span class="text-amber-700">⚠️ {{ count($check['missing_topics']) }} missing topics</span>
                        @endif
                    </div>
                    <div>
                        <span>Mega Project Score: </span>
                        @if ($check['project_score'] !== null)
                            <span class="font-mono font-semibold {{ $check['project_passed'] ? 'text-emerald-600' : 'text-amber-700' }}">
                                {{ $check['project_score'] }}/100 {{ $check['project_passed'] ? '(Passed)' : '(Failed)' }}
                            </span>
                        @else
                            <span class="font-mono text-muted">Not submitted</span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
