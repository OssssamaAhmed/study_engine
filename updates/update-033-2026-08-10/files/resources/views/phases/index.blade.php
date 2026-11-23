@extends('layouts.app', ['title' => 'Phases & Portfolio Projects — Study Engine'])

@section('content')
    <div class="mb-8">
        <p class="eyebrow">Curriculum Roadmap</p>
        <h1 class="text-2xl font-semibold tracking-tight">Phases &amp; Mega Portfolio Projects</h1>
        <p class="mt-1 text-sm text-muted">
            Master core concepts and build your {{ \App\Support\StudyMode::label() }} Mega Portfolio Project in each phase.
        </p>
    </div>

    {{-- Phase timeline --}}
    <div class="relative space-y-3">
        {{-- Vertical timeline line --}}
        <div class="absolute left-[23px] top-2 bottom-2 hidden w-px bg-line/80 sm:block" aria-hidden="true"></div>

        @foreach ($phases as $item)
            @php
                $p = $item['phase'];
                $check = $item['check'];
                $isCurrent = $p['phase_number'] === $currentPhaseNumber;
                $isDone = $check['progress']?->status === 'completed';
                $topicTotal = count(app(\App\Services\RoadmapService::class)->getRequiredTopicsForPhase($p['phase_number']));
                $topicDone  = $topicTotal - count($check['missing_topics'] ?? []);
            @endphp
            <a href="/phases/{{ $p['phase_number'] }}"
               class="group relative block rounded-xl border bg-surface transition-all duration-200
                      {{ $isCurrent
                          ? 'border-cyan-400/50 shadow-sm shadow-cyan-500/10 ring-1 ring-cyan-400/20'
                          : ($isDone
                              ? 'border-emerald-400/30 hover:border-emerald-400/50'
                              : 'border-line hover:border-muted/60 hover:shadow-xs') }}">

                <div class="flex items-start gap-4 p-4 sm:pl-14">
                    {{-- Phase number circle (sits on the timeline) --}}
                    <div class="absolute left-3 top-4 z-10 hidden sm:flex">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-bold
                                    {{ $isDone
                                        ? 'bg-emerald-500 text-white'
                                        : ($isCurrent
                                            ? 'bg-cyan-500 text-white ring-4 ring-cyan-400/20'
                                            : 'border-2 border-line bg-surface text-muted') }}">
                            @if ($isDone)
                                <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            @else
                                {{ $p['phase_number'] }}
                            @endif
                        </div>
                    </div>

                    {{-- Mobile phase number (visible on small screens) --}}
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold sm:hidden
                                {{ $isDone
                                    ? 'bg-emerald-500 text-white'
                                    : ($isCurrent
                                        ? 'bg-cyan-500 text-white'
                                        : 'border-2 border-line bg-surface text-muted') }}">
                        @if ($isDone)
                            ✓
                        @else
                            {{ $p['phase_number'] }}
                        @endif
                    </div>

                    {{-- Content --}}
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-base font-semibold tracking-tight text-ink group-hover:text-accent transition-colors">
                                {{ $p['name'] }}
                            </h2>
                            @if ($isDone)
                                <span class="badge bg-emerald-500/10 text-emerald-700">Completed</span>
                            @elseif ($isCurrent)
                                <span class="badge bg-cyan-500/10 text-cyan-700">Active</span>
                            @endif
                        </div>

                        {{-- Compact meta row --}}
                        <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted">
                            <span class="font-mono">
                                Phase {{ $p['phase_number'] }} · Wk {{ $p['week_start'] }}{{ $p['week_end'] !== $p['week_start'] ? '–'.$p['week_end'] : '' }}
                            </span>

                            <span class="flex items-center gap-1">
                                @if (empty($check['missing_topics']))
                                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    <span class="text-emerald-700">{{ $topicDone }}/{{ $topicTotal }} topics</span>
                                @else
                                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                    <span class="text-amber-700">{{ $topicDone }}/{{ $topicTotal }} topics</span>
                                @endif
                            </span>

                            <span class="flex items-center gap-1">
                                @if ($check['project_score'] !== null)
                                    <span class="inline-block h-1.5 w-1.5 rounded-full {{ $check['project_passed'] ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                                    <span class="font-mono {{ $check['project_passed'] ? 'text-emerald-700' : 'text-amber-700' }}">
                                        Project {{ $check['project_score'] }}/100
                                    </span>
                                @else
                                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-gray-300"></span>
                                    <span>Project pending</span>
                                @endif
                            </span>
                        </div>
                    </div>

                    {{-- Arrow --}}
                    <div class="hidden shrink-0 self-center text-muted/40 transition-colors group-hover:text-accent sm:block">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </div>
                </div>
            </a>
        @endforeach
    </div>
@endsection

