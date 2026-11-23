@extends('layouts.app', ['title' => 'Concepts — Study Engine'])

@section('content')
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="eyebrow">Knowledge base</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight">Concepts</h1>
        </div>
        <div class="flex gap-1.5 text-sm">
            @foreach (['all' => 'All', 'due' => 'Due', 'struggling' => 'Struggling'] as $key => $label)
                <a href="/concepts?filter={{ $key }}"
                    class="rounded-md px-3.5 py-2 font-medium transition-colors {{ $filter === $key ? 'bg-ink text-white' : 'border border-line bg-surface text-ink-soft hover:border-muted' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    @if ($concepts->isEmpty())
        <div class="card px-6 py-14 text-center">
            <p class="eyebrow">Nothing here</p>
            <p class="mt-3 text-sm text-muted">
                @if ($filter === 'all')
                    Ingest a transcript to create your first concepts.
                @else
                    No {{ $filter }} concepts right now — that's a good sign.
                @endif
            </p>
        </div>
    @endif

    <div class="space-y-2.5">
        @foreach ($concepts as $concept)
            <div class="card p-5 {{ $concept->retired ? 'opacity-55' : '' }}">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="flex flex-wrap items-center gap-2 font-medium">
                            {{ $concept->name }}
                            @if ($concept->retired)
                                <span class="badge badge-quiet">retired</span>
                            @elseif ($concept->next_review_at && $concept->next_review_at->lte(today()))
                                <span class="badge badge-pending">due</span>
                            @endif
                        </p>
                        <p class="mt-1 text-sm text-muted">{{ $concept->summary }}</p>
                        <p class="mt-2 font-mono text-xs text-muted">
                            phase {{ $concept->topic?->phase_number }} · ease {{ $concept->ease }} ·
                            interval {{ $concept->interval_days }}d ·
                            next {{ $concept->next_review_at?->toDateString() ?? '—' }} ·
                            <span class="{{ $concept->lapses > 0 ? 'text-danger' : '' }}">lapses {{ $concept->lapses }}</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        @unless ($concept->retired)
                            <a href="/side-quest?topic={{ urlencode($concept->name) }}&concept_id={{ $concept->id }}"
                                class="btn-outline btn-sm whitespace-nowrap">⚔️ Side Quest</a>
                        @endunless
                        <form method="POST" action="/concepts/{{ $concept->id }}/retire">
                            @csrf
                            <button class="btn-outline btn-sm">{{ $concept->retired ? 'Reactivate' : 'Retire' }}</button>
                        </form>
                    </div>
                </div>

                @foreach ($concept->struggleNotes as $note)
                    <div class="mt-3 flex items-center justify-between gap-3 rounded-md border border-warn/20 bg-warn-wash px-3.5 py-2.5 text-sm">
                        <span class="text-warn">{{ $note->note }} <span class="font-mono text-xs opacity-70">w{{ $note->weight }}</span></span>
                        <form method="POST" action="/notes/{{ $note->id }}/resolve">
                            @csrf
                            <button class="whitespace-nowrap font-mono text-xs text-warn underline underline-offset-2 hover:opacity-70">resolve</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>

    <div class="mt-6">{{ $concepts->links() }}</div>
@endsection
