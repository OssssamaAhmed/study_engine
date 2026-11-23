{{-- Learner mode writes plan/skeleton one milestone per click; also flags a document the model cut off --}}
@if (\App\Services\PhaseService::looksCutOff($md))
    <div class="mt-4 rounded-md border border-amber-500/30 bg-amber-50/50 px-4 py-3 text-xs text-amber-900">
        ⚠️ This document does not end with <code>END OF DOCUMENT</code>, so the model was cut off. Regenerate it, or write the next milestone and check the end of this one.
    </div>
@endif
@if ($next)
    <form method="POST" action="/phases/{{ $phase }}/next-milestone/{{ $document }}" data-llm class="mt-4 flex flex-wrap items-center gap-3 rounded-md border border-accent/20 bg-accent-wash px-4 py-3">
        @csrf
        <span class="text-xs text-accent-deep">Learner mode works one milestone at a time. Finish this one first, then:</span>
        <button type="submit" class="btn btn-sm btn-accent">✦ Write Milestone {{ $next }}</button>
    </form>
@endif
