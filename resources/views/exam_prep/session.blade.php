@extends('layouts.app', ['title' => "Exam Prep Session — {$session->exam_target}"])

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="/exam-prep" class="font-mono text-xs text-muted hover:underline">← Back to Exam Prep Hub</a>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ $session->exam_target }}</h1>
            <p class="font-mono text-xs text-muted">
                Method: <span class="uppercase font-semibold text-cyan-600">{{ $session->method }}</span> · Created {{ $session->created_at->diffForHumans() }}
            </p>
        </div>
        @if ($session->score !== null)
            <div class="card px-4 py-2 text-right">
                <p class="eyebrow">Session Score</p>
                <p class="font-mono text-xl font-bold text-cyan-600">{{ $session->score }}/100</p>
            </div>
        @endif
    </div>

    {{-- Evaluation Feedback Banner if evaluated --}}
    @if ($session->evaluation_md)
        <div class="card mb-8 p-6 bg-paper/90 border-cyan-500/30">
            <h2 class="text-lg font-semibold tracking-tight text-ink">Exam Performance &amp; Diagnostic Feedback</h2>
            <article class="mission mt-4 text-sm">
                {!! Illuminate\Support\Str::markdown($session->evaluation_md, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
            </article>
        </div>
    @endif

    {{-- Exam Questions & Interactive Practice --}}
    <div class="card mb-8 p-6">
        <h2 class="text-lg font-semibold tracking-tight">Exam Questions &amp; Interactive Drills</h2>

        @php
            $questions = $session->session_data['questions'] ?? [];
            $instructions = $session->session_data['instructions'] ?? null;
        @endphp

        @if ($instructions)
            <div class="mt-2 mb-6 rounded-md bg-white/50 p-4 text-xs text-muted border border-line">
                {!! Illuminate\Support\Str::markdown($instructions, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
            </div>
        @endif

        @if (! empty($questions))
            <div class="mt-6 space-y-6">
                @foreach ($questions as $q)
                    <div class="rounded-lg border border-line bg-paper/40 p-5">
                        <div class="flex items-start gap-3">
                            <span class="font-mono text-xs font-bold text-cyan-600">Q{{ $q['id'] ?? $loop->iteration }}</span>
                            <div class="flex-1">
                                <h3 class="text-sm font-semibold text-ink">{{ $q['question'] }}</h3>

                                @if (isset($q['options']) && is_array($q['options']))
                                    <div class="mt-3 grid gap-2 sm:grid-cols-2 text-xs">
                                        @foreach ($q['options'] as $key => $opt)
                                            <div class="rounded-md border border-line bg-white/80 p-3">
                                                <span class="font-mono font-bold text-cyan-700">{{ $key }}.</span>
                                                <span>{{ $opt }}</span>
                                                @if ($session->method === 'test_maker' && isset($q['distractor_analysis'][$key]))
                                                    <p class="mt-1 font-mono text-[11px] text-amber-700">
                                                        🔍 <strong>Distractor Trap:</strong> {{ $q['distractor_analysis'][$key] }}
                                                    </p>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @if (isset($q['explanation']) && $session->evaluation_md)
                                    <div class="mt-3 rounded-md bg-cyan-50/50 p-3 text-xs text-cyan-900 border border-cyan-500/20">
                                        <strong>Answer Explanation:</strong> {{ $q['explanation'] }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="mt-4 text-sm text-muted">
                {!! Illuminate\Support\Str::markdown($session->study_guide_text ?? 'No raw questions format.', ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
            </div>
        @endif
    </div>

    {{-- Answer & Metacognitive Logic Journal Submission Form --}}
    <div class="card p-6">
        <h2 class="text-lg font-semibold tracking-tight">Submit Answers &amp; Logic Journal</h2>
        <p class="mt-1 text-xs text-muted">
            @if ($session->method === 'metacognitive')
                <strong>Metacognitive Requirement ("Think-Aloud"):</strong> Type out your selected answers AND your step-by-step reasoning for each question ("Why I chose B, and why A/C were eliminated").
            @elseif ($session->method === 'test_maker')
                <strong>Test Maker Requirement:</strong> For each question, state your answer and identify the distractor words ("always", "never", misleading terms) that revealed the test maker's trap.
            @else
                Type or paste your answers below to receive immediate active recall feedback and diagnostic scoring.
            @endif
        </p>

        <form method="POST" action="/exam-prep/session/{{ $session->id }}/submit" data-llm class="mt-4 space-y-4">
            @csrf
            <div>
                <textarea name="submission" rows="8" class="input font-mono text-xs"
                    placeholder="Enter your answers and step-by-step thought process here..." required>{{ old('submission', $session->user_submission) }}</textarea>
            </div>
            <button type="submit" class="btn btn-accent">Submit for Active Recall &amp; Metacognitive Scoring</button>
        </form>
    </div>
@endsection
