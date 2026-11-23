@extends('layouts.app', ['title' => 'History — Study Engine'])

@section('content')
    <div class="mb-8">
        <p class="eyebrow">Archive</p>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight">History</h1>
    </div>

    @if ($exercises->isEmpty())
        <div class="card px-6 py-14 text-center">
            <p class="eyebrow">No missions yet</p>
            <p class="mt-3 text-sm text-muted">Your first mission will appear here once generated.</p>
        </div>
    @endif

    <div class="space-y-2.5">
        @foreach ($exercises as $exercise)
            @php $latest = $exercise->attempts->sortByDesc('id')->first(); @endphp
            <a href="/exercise/{{ $exercise->id }}"
                class="card flex flex-wrap items-center justify-between gap-3 p-5 transition-colors hover:border-muted">
                <div class="min-w-0">
                    <p class="font-mono text-xs text-muted">M{{ $exercise->id }} · {{ $exercise->date->format('D d M Y') }}</p>
                    <p class="mt-1 font-medium">{{ $exercise->title() }}</p>
                    <p class="mt-0.5 text-xs text-muted">{{ $exercise->attempts->count() }} attempt{{ $exercise->attempts->count() === 1 ? '' : 's' }}</p>
                </div>
                <div class="flex items-center gap-3">
                    @if ($latest?->score !== null)
                        <span class="rounded-md bg-ink px-2.5 py-1 font-mono text-sm font-semibold text-white">{{ $latest->score }}<span class="text-white/50">/100</span></span>
                    @endif
                    <span class="badge badge-{{ $exercise->status }}">{{ $exercise->status }}</span>
                </div>
            </a>
        @endforeach
    </div>
@endsection
