@extends('layouts.app', ['title' => 'Notifications — Study Engine'])

@section('content')
    <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="eyebrow">Exam &amp; Schedule Alerts</p>
            <h1 class="text-2xl font-semibold tracking-tight">Notifications Center</h1>
        </div>
        <form method="POST" action="/notifications/read-all">
            @csrf
            <button type="submit" class="btn btn-outline btn-sm">Mark All as Read</button>
        </form>
    </div>

    @if ($notifications->isEmpty())
        <div class="card p-12 text-center text-muted">
            <p class="text-sm">No notifications right now.</p>
            <p class="mt-1 text-xs">Alerts will appear here when Checkpoints or Certification Exams approach.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($notifications as $note)
                <div class="card p-4 transition-colors {{ ! $note->is_read ? 'border-l-4 border-l-cyan-400 bg-white/40' : 'opacity-75' }}">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 font-mono text-base">
                                {{ $note->type === 'checkpoint_near' ? '🎯' : ($note->type === 'cert_near' ? '📜' : '🔔') }}
                            </span>
                            <div>
                                <h2 class="text-sm font-semibold text-ink">{{ $note->title }}</h2>
                                <p class="mt-0.5 text-xs text-muted">{{ $note->message }}</p>
                                <p class="mt-1 font-mono text-[11px] text-muted/70">{{ $note->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($note->link)
                                <form method="POST" action="/notifications/{{ $note->id }}/read">
                                    @csrf
                                    <button type="submit" class="btn btn-accent btn-sm">Go to Exam Prep →</button>
                                </form>
                            @elseif (! $note->is_read)
                                <form method="POST" action="/notifications/{{ $note->id }}/read">
                                    @csrf
                                    <button type="submit" class="btn btn-outline btn-sm">Mark Read</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $notifications->links() }}
        </div>
    @endif
@endsection
