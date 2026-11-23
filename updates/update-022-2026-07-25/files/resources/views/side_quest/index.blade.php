@extends('layouts.app', ['title' => 'Side Quest — Study Engine'])

@section('content')
    <div class="mb-8">
        <p class="eyebrow">Targeted practice</p>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight">Side Quest</h1>
        <p class="mt-2 text-sm text-muted">
            Describe the exact thing confusing you. The engine will build micro-ladder drills
            that start from the absolute simplest case and escalate one layer at a time until
            the confusion is cured.
        </p>
    </div>

    {{-- ─── Creation form ─── --}}
    <div class="card mb-10 p-6">
        <form method="POST" action="/side-quest" data-llm>
            @csrf

            <label class="block">
                <span class="label">What's confusing you?</span>
                <textarea name="topic" rows="4"
                    class="input mt-1.5 w-full"
                    placeholder="e.g. I get completely numb when I see nested dictionary / tuple / list / set unpacking. After the first level of unpacking my mind goes blank and I can't follow the logic anymore."
                    required minlength="5" maxlength="500">{{ old('topic', request('topic', '')) }}</textarea>
            </label>

            @if (request('concept_id'))
                <input type="hidden" name="concept_id" value="{{ request('concept_id') }}">
            @endif

            @error('topic')
                <p class="mt-1 text-sm text-danger">{{ $message }}</p>
            @enderror

            <div class="mt-5 flex flex-wrap items-end gap-4">
                <label>
                    <span class="label">Difficulty</span>
                    <select name="difficulty" class="input mt-1 w-auto">
                        <option>Easy</option>
                        <option>Medium</option>
                        <option>Hard</option>
                    </select>
                </label>
                <label>
                    <span class="label">Drills</span>
                    <input type="number" name="drills" value="5" min="3" max="20" class="input mt-1 w-20">
                </label>
                <label>
                    <span class="label">Boss Fights</span>
                    <input type="number" name="bosses" value="1" min="0" max="10" class="input mt-1 w-20">
                </label>
            </div>

            <div class="mt-6">
                <button class="btn-accent">⚔️ Launch Side Quest</button>
            </div>
        </form>
    </div>

    {{-- ─── Past side quests ─── --}}
    @if ($sideQuests->isNotEmpty())
        <div class="mb-4">
            <p class="eyebrow">Past side quests</p>
        </div>
        <div class="space-y-2.5">
            @foreach ($sideQuests as $sq)
                <a href="/exercise/{{ $sq->id }}" class="card block p-4 transition-colors hover:border-accent/40">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium">
                                <span class="font-mono text-xs text-accent">SQ{{ $sq->id }}</span>
                                {{ $sq->title() }}
                            </p>
                            <p class="mt-0.5 font-mono text-xs text-muted">
                                {{ $sq->date->format('D d M Y') }}
                            </p>
                        </div>
                        <span class="badge badge-{{ $sq->status }}">{{ $sq->status }}</span>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-6">{{ $sideQuests->links() }}</div>
    @endif
@endsection
