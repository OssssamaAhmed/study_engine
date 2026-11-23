@extends('layouts.app', ['title' => 'History — Study Engine'])

@section('content')
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="eyebrow">Archive</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight">History</h1>
        </div>
        <form method="GET" action="/history" class="flex items-center gap-2 text-sm">
            <label for="concept_id" class="text-muted">Filter by concept</label>
            <select id="concept_id" name="concept_id" onchange="this.form.submit()"
                class="rounded-md border border-line bg-surface px-3 py-2 font-medium text-ink-soft">
                <option value="">All concepts</option>
                @foreach ($concepts as $concept)
                    <option value="{{ $concept->id }}" @selected((string) $conceptId === (string) $concept->id)>{{ $concept->name }}</option>
                @endforeach
            </select>
            @if ($conceptId)
                <a href="/history" class="text-muted underline underline-offset-2 hover:text-ink-soft">clear</a>
            @endif
        </form>
    </div>

    @if ($exercises->isEmpty())
        <div class="card px-6 py-14 text-center">
            <p class="eyebrow">No missions yet</p>
            <p class="mt-3 text-sm text-muted">
                @if ($conceptId)
                    No missions cover this concept yet.
                @else
                    Your first mission will appear here once generated.
                @endif
            </p>
        </div>
    @endif

    <div class="space-y-2.5">
        @foreach ($exercises as $exercise)
            @php
                $latest = $exercise->attempts->sortByDesc('id')->first();
                $newConcepts = $exercise->concepts->where('pivot.role', 'new');
                $reviewConcepts = $exercise->concepts->where('pivot.role', 'review');
            @endphp
            <div class="card relative flex flex-wrap items-center justify-between gap-3 p-5 transition-colors hover:border-muted">
                <a href="/exercise/{{ $exercise->id }}" class="absolute inset-0 z-0" aria-label="Open {{ $exercise->title() }}"></a>
                <div class="pointer-events-none relative z-10 min-w-0">
                    <p class="font-mono text-xs text-muted">M{{ $exercise->id }} · {{ $exercise->date->format('D d M Y') }}</p>
                    <p class="mt-1 font-medium">{{ $exercise->title() }}</p>
                    <p class="mt-0.5 text-xs text-muted">{{ $exercise->attempts->count() }} attempt{{ $exercise->attempts->count() === 1 ? '' : 's' }}</p>
                    @if ($exercise->concepts->isNotEmpty())
                        <details class="pointer-events-auto mt-1.5 inline-block">
                            <summary class="cursor-pointer select-none text-xs font-medium text-muted hover:text-ink-soft">
                                {{ $exercise->concepts->count() }} concept{{ $exercise->concepts->count() === 1 ? '' : 's' }}
                            </summary>
                            <div class="mt-2 flex max-w-xs flex-wrap gap-1.5">
                                @foreach ($newConcepts as $concept)
                                    <span class="badge badge-pending">{{ $concept->name }}</span>
                                @endforeach
                                @foreach ($reviewConcepts as $concept)
                                    <span class="badge badge-quiet">{{ $concept->name }}</span>
                                @endforeach
                            </div>
                        </details>
                    @endif
                </div>
                <div class="pointer-events-none relative z-10 flex items-center gap-3">
                    @if ($latest?->score !== null)
                        <span class="rounded-md bg-ink px-2.5 py-1 font-mono text-sm font-semibold text-white">{{ $latest->score }}<span class="text-white/50">/100</span></span>
                    @endif
                    <span class="badge badge-{{ $exercise->status }}">{{ $exercise->status }}</span>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-6">{{ $exercises->links() }}</div>
@endsection
