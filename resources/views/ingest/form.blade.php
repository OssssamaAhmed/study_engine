@extends('layouts.app', ['title' => 'Ingest — Study Engine'])

@section('content')
    <div class="mb-8">
        <p class="eyebrow">Intake</p>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight">Paste a transcript</h1>
        <p class="mt-2 max-w-2xl text-sm text-muted">
            Drop in what you just studied — lecture notes, video transcript, your own summary.
            The model extracts the concepts; you confirm them before anything is saved.
        </p>
    </div>

    <form method="POST" action="/ingest/preview" data-llm class="card space-y-5 p-6">
        @csrf

        <div>
            <label for="raw_text" class="label">Transcript / notes</label>
            <textarea id="raw_text" name="raw_text" rows="14" required
                class="input font-mono text-[13px] leading-relaxed"
                placeholder="Paste what you learned today…">{{ old('raw_text') }}</textarea>
            @error('raw_text') <p class="mt-1.5 text-sm text-danger">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="topic_id" class="label">Topic</label>
                <select id="topic_id" name="topic_id" class="input">
                    <option value="">Let the AI infer it from the roadmap</option>
                    @foreach ($topics as $topic)
                        <option value="{{ $topic->id }}" @selected(old('topic_id') == $topic->id)>
                            Phase {{ $topic->phase_number }} — {{ $topic->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="source_note" class="label">Source <span class="font-normal text-muted">(optional)</span></label>
                <input id="source_note" name="source_note" value="{{ old('source_note') }}"
                    class="input" placeholder="e.g. ZTM Python — for loops section">
            </div>
        </div>

        <div class="flex items-center gap-4 border-t border-line pt-5">
            <button class="btn-accent">Extract concepts</button>
            <p class="text-xs text-muted">Next: you review the extracted concepts before saving.</p>
        </div>
    </form>
@endsection
