@extends('layouts.app', ['title' => 'Tutor — Study Engine'])

@section('content')
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="eyebrow">Ask anything</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight">Tutor</h1>
        </div>
        @if ($messages !== [])
            <form method="POST" action="/chat/clear">
                @csrf
                <button class="btn-outline">Clear conversation</button>
            </form>
        @endif
    </div>

    <div class="space-y-4">
        @forelse ($messages as $m)
            @if ($m['role'] === 'learner')
                <div class="ml-auto max-w-xl whitespace-pre-wrap rounded-lg bg-ink px-4 py-3 text-sm text-white">{{ $m['content'] }}</div>
            @else
                <div class="card mission max-w-3xl px-5 py-4 text-sm">
                    {!! Illuminate\Support\Str::markdown($m['content'], ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                    @if (str_contains($m['content'], '**Task'))
                        <form method="POST" action="/chat/mission" class="mt-4 border-t border-line pt-3">
                            @csrf
                            <input type="hidden" name="index" value="{{ $loop->index }}">
                            <button class="btn-outline btn-sm">Save as mission</button>
                            <span class="ml-2 text-xs text-muted">turns this practice into a scored mission</span>
                        </form>
                    @endif
                </div>
            @endif
        @empty
            <p class="card px-6 py-10 text-center text-sm text-muted">
                Ask about your drills, concepts, or code — the tutor explains with the simplest approach first.<br>
                Reference missions by number instead of pasting: <code class="rounded bg-paper px-1 py-0.5 font-mono">M3</code> = mission 3,
                <code class="rounded bg-paper px-1 py-0.5 font-mono">M3 D2</code> = its drill 2,
                <code class="rounded bg-paper px-1 py-0.5 font-mono">B1</code> = the Boss Fight of your current mission.
            </p>
        @endforelse
    </div>

    <form method="POST" action="/chat" data-llm class="card mt-6 space-y-3 p-4">
        @csrf
        <textarea name="message" rows="3" required maxlength="20000"
            class="input font-mono text-[13px] leading-relaxed"
            placeholder="Ask the tutor…">{{ old('message') }}</textarea>
        @error('message') <p class="text-sm text-danger">{{ $message }}</p> @enderror
        <button class="btn-accent">Send</button>
    </form>
@endsection
