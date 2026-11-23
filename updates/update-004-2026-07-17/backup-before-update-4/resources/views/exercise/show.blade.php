@extends('layouts.app', ['title' => 'Mission — Study Engine'])

@section('content')
    <div class="card mb-10 overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-paper/60 px-6 py-4">
            <div>
                <p class="eyebrow">Mission // {{ $exercise->date->format('d M Y') }}</p>
                <h1 class="mt-0.5 text-lg font-semibold tracking-tight">{{ $exercise->title() }}</h1>
            </div>
            <span class="badge badge-{{ $exercise->status }}">{{ $exercise->status }}</span>
        </div>
        <article class="mission px-6 py-6 text-sm">
            {!! Illuminate\Support\Str::markdown($exercise->mission_md, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
        </article>
    </div>

    <div class="mb-10">
        <p class="eyebrow">Debrief</p>
        <h2 class="mb-4 mt-1 text-lg font-semibold tracking-tight">Your result</h2>
        <form method="POST" action="/exercise/{{ $exercise->id }}/submit" data-llm class="card space-y-4 p-6">
            @csrf
            <textarea name="pasted_result" rows="12" required
                class="input font-mono text-[13px] leading-relaxed"
                placeholder="Paste your code, answers, terminal output…">{{ old('pasted_result', $attempt?->pasted_result) }}</textarea>
            @error('pasted_result') <p class="text-sm text-danger">{{ $message }}</p> @enderror
            <div class="flex flex-wrap items-center gap-4">
                <button class="btn-accent">Submit for evaluation</button>
                <p class="text-xs text-muted">Resubmitting re-evaluates and updates the schedule again.</p>
            </div>
            <p class="border-t border-line pt-3 text-xs text-muted">
                Beginner workflow: write the code yourself → run it → paste it here.
                Use the <a href="/chat" class="text-accent hover:underline">Tutor</a> to explain errors or
                review your finished code — not to write it for you. Writing the first draft is where the learning happens.
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
                @if (! is_null($attempt->score))
                    <span class="rounded-md bg-ink px-3 py-1.5 font-mono text-sm font-semibold text-white">{{ $attempt->score }}<span class="text-white/50">/100</span></span>
                @endif
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
@endsection
