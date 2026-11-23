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

    {{-- AI Security Engineer Mega Portfolio Project --}}
    <div class="card mb-8 overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-paper/60 px-6 py-4">
            <div>
                <p class="eyebrow">Portfolio Milestone // AI Security Engineer</p>
                <h2 class="text-lg font-semibold tracking-tight">2. Mega Portfolio Project</h2>
            </div>
            <form method="POST" action="/phases/{{ $phaseNumber }}/project" data-llm>
                @csrf
                <button type="submit" class="btn btn-outline btn-sm">
                    {{ $progress?->mega_project_md ? 'Regenerate Brief' : 'Generate Mega Project Brief' }}
                </button>
            </form>
        </div>

        <div class="px-6 py-6">
            @if ($progress?->mega_project_md)
                <article class="mission text-sm mb-8">
                    {!! Illuminate\Support\Str::markdown($progress->mega_project_md, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                </article>

                {{-- Project Evaluation Status --}}
                @if ($progress->project_eval_md)
                    <div class="mb-8 rounded-lg border p-5 text-sm {{ $progress->project_score >= 70 ? 'border-emerald-500/30 bg-emerald-50/30' : 'border-amber-500/30 bg-amber-50/30' }}">
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
                <div class="border-t border-line pt-6">
                    <h3 class="text-base font-semibold">Submit Your Implementation</h3>
                    <p class="mt-1 text-xs text-muted">
                        Paste your Python code, test results, architectural documentation, or GitHub README text below for LLM security evaluation.
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
                    <p class="text-sm">No Mega Project brief generated for Phase {{ $phaseNumber }} yet.</p>
                    <p class="mt-1 text-xs">Click the button above to generate a real-world AI Security Engineer project brief tailored to this phase.</p>
                </div>
            @endif
        </div>
    </div>
@endsection
