@extends('layouts.app', ['title' => 'Confirm concepts — Study Engine'])

@section('content')
    <div class="mb-8">
        <p class="eyebrow">Intake // review</p>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight">Confirm extracted concepts</h1>
        <p class="mt-2 text-sm text-muted">Uncheck anything wrong, edit names and summaries freely, then save — or save and get drills for this material immediately.</p>
    </div>

    <form method="POST" action="/ingest/confirm" data-llm class="space-y-5">
        @csrf
        <input type="hidden" name="raw_text" value="{{ $raw_text }}">
        <input type="hidden" name="source_note" value="{{ $source_note }}">

        <div class="card p-5">
            <label for="topic_id" class="label">Topic</label>
            <select id="topic_id" name="topic_id" class="input sm:max-w-md">
                @foreach ($topics as $t)
                    <option value="{{ $t->id }}" @selected($t->id === $topic?->id)>
                        Phase {{ $t->phase_number }} — {{ $t->name }}
                    </option>
                @endforeach
            </select>
        </div>

        @if (empty($concepts))
            <p class="card border-warn/30 bg-warn-wash px-5 py-4 text-sm text-warn">
                No concepts were extracted. Go back and try again, or rephrase the transcript.
            </p>
        @endif

        <div class="space-y-2.5">
            @foreach ($concepts as $i => $concept)
                <div class="card flex items-start gap-4 p-4">
                    <input type="checkbox" name="concepts[{{ $i }}][keep]" value="1" checked
                        class="mt-3 size-4 accent-accent">
                    <div class="grow space-y-2">
                        <input name="concepts[{{ $i }}][name]" value="{{ $concept['name'] }}"
                            class="input font-medium" aria-label="Concept name">
                        <input name="concepts[{{ $i }}][summary]" value="{{ $concept['summary'] }}"
                            class="input text-muted" aria-label="Concept summary">
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center gap-3 border-t border-line pt-5">
            @include('partials.generation-options')
            <button name="action" value="save_generate" class="btn-accent">Save &amp; generate drills</button>
            <button name="action" value="save" class="btn-outline">Save only</button>
            <a href="/ingest" class="px-2 text-sm text-muted hover:text-ink">Discard</a>
            <p class="basis-full text-xs text-muted sm:basis-auto sm:ml-auto">
                “Save &amp; generate drills” builds a mission from this material plus anything due for review.
            </p>
        </div>
    </form>
@endsection
