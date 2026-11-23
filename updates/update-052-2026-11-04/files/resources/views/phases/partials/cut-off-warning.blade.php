{{-- Learner-mode documents end with END OF DOCUMENT; without it the model was cut off --}}
@if (\App\Services\PhaseService::looksCutOff($md))
    <div class="mt-4 rounded-md border border-amber-500/30 bg-amber-50/50 px-4 py-3 text-xs text-amber-900">
        ⚠️ This document does not end with <code>END OF DOCUMENT</code>, so the model was cut off. Regenerate it.
    </div>
@endif
