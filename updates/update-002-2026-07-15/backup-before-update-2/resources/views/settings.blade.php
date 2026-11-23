@extends('layouts.app', ['title' => 'Settings — Study Engine'])

@section('content')
    <div class="mb-8">
        <p class="eyebrow">Configuration</p>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight">Settings</h1>
    </div>

    <div class="mb-5 card p-6">
        <p class="eyebrow">AI provider</p>
        <dl class="mt-4 grid grid-cols-1 gap-x-8 gap-y-2.5 text-sm sm:grid-cols-[12rem_1fr]">
            <dt class="text-muted">Active provider</dt>
            <dd class="font-mono">{{ config('ai.provider') }}</dd>
            <dt class="text-muted">Chat model</dt>
            <dd class="font-mono">{{ config('ai.'.config('ai.provider').'.chat_model') ?? config('ai.'.config('ai.provider').'.model') }}</dd>
            <dt class="text-muted">Embedding model</dt>
            <dd class="font-mono">{{ config('ai.'.config('ai.provider').'.embed_model') ?? '(none for this provider)' }}</dd>
        </dl>
        <p class="mt-5 border-t border-line pt-4 text-xs leading-relaxed text-muted">
            To switch providers, edit <code class="rounded bg-paper px-1.5 py-0.5 font-mono">AI_PROVIDER</code> in
            <code class="rounded bg-paper px-1.5 py-0.5 font-mono">.env</code>
            (openrouter · ollama · anthropic), restart <code class="rounded bg-paper px-1.5 py-0.5 font-mono">php artisan serve</code>,
            then verify with <code class="rounded bg-paper px-1.5 py-0.5 font-mono">php artisan llm:test</code>.
        </p>
    </div>

    <div class="mb-5 card p-6">
        <p class="eyebrow">Profile</p>
        <form method="POST" action="/settings/profile" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
            @csrf
            <label class="grow">
                <span class="label">Your name</span>
                <input name="name" value="{{ old('name', $name) }}" maxlength="60" class="input sm:max-w-xs"
                    placeholder="How should the dashboard greet you?">
            </label>
            <button class="btn">Save name</button>
        </form>
        @error('name') <p class="mt-2 text-sm text-danger">{{ $message }}</p> @enderror
    </div>

    <div class="mb-5 card p-6">
        <p class="eyebrow">Personal instructions</p>
        <p class="mt-3 text-sm text-muted">
            Your standing memory, like personal context in ChatGPT or Gemini. Tell the app how to train you —
            e.g. “I'm about 70% confident with <code class="rounded bg-paper px-1.5 py-0.5 font-mono">for</code> loops;
            keep weaving them into upcoming practice until I say stop.”
            Every new mission and tutor chat reads this.
        </p>
        <form method="POST" action="/settings/instructions" class="mt-4 space-y-3">
            @csrf
            <textarea name="instructions" rows="5" maxlength="4000" class="input leading-relaxed"
                placeholder="Write instructions for your future missions and the tutor…">{{ old('instructions', $instructions) }}</textarea>
            @error('instructions') <p class="text-sm text-danger">{{ $message }}</p> @enderror
            <button class="btn">Save instructions</button>
        </form>
    </div>

    <div class="card p-6">
        <p class="eyebrow">Roadmap</p>
        <p class="mt-3 text-sm text-muted">
            Re-reads the JSON in <code class="rounded bg-paper px-1.5 py-0.5 font-mono">storage/app/roadmap/</code> and updates topics.
            Run after editing the roadmap file — concepts and schedules are untouched.
        </p>
        <form method="POST" action="/settings/resync" class="mt-5">
            @csrf
            <button class="btn">Resync roadmap</button>
        </form>
    </div>

    <div class="mt-5 card border-danger/30 p-6">
        <p class="eyebrow text-danger">Danger zone</p>
        <p class="mt-3 text-sm text-muted">
            Erases <strong class="text-ink">all progress</strong>: transcripts, concepts, missions, attempts,
            struggle notes, personal instructions and chat history. The 21-phase roadmap stays, and its
            start date becomes today. This cannot be undone.
        </p>
        <form method="POST" action="/settings/reset" class="mt-4 flex flex-col gap-3 sm:flex-row"
            onsubmit="return confirm('Really erase ALL progress? This cannot be undone.')">
            @csrf
            <input name="confirm" required class="input sm:max-w-xs" placeholder="Type RESET to confirm" autocomplete="off">
            <button class="btn bg-danger hover:bg-danger/85">Erase all progress</button>
        </form>
        @error('confirm') <p class="mt-2 text-sm text-danger">{{ $message }}</p> @enderror
    </div>
@endsection
