{{-- Under every drill: copy + solve-here box. Under a Boss Fight: copy only (those go to VS Code). --}}
@php
    $isDrill = $key[0] === 'D';
    $done = $drillAttempts[$key] ?? null;
    $score = $done?->score;
@endphp
<div class="drill-tools" id="drill-{{ $key }}">
    <div class="flex flex-wrap items-center gap-2">
        <button type="button" class="btn-outline btn-sm" data-copy="{{ $md }}">⧉ Copy {{ $isDrill ? 'drill' : 'Boss Fight' }}</button>
        @if ($isDrill)
            <span class="badge {{ is_null($score) ? 'badge-quiet' : ($score >= 70 ? 'badge-evaluated' : 'badge-pending') }}"
                  data-score-for="{{ $key }}" data-score="{{ $score }}">{{ $key }} · {{ $score ?? '—' }}{{ is_null($score) ? '' : '/100' }}</span>
        @else
            <span class="text-xs text-muted">Solve this one in VS Code, then paste it under "Your result" below.</span>
        @endif
    </div>

    @if ($isDrill)
        <form method="POST" action="/exercise/{{ $exercise->id }}/drill/{{ $key }}" class="drill-form mt-3" data-key="{{ $key }}">
            @csrf
            <textarea name="answer" rows="5" required spellcheck="false"
                class="input drill-editor font-mono text-[13px] leading-relaxed"
                placeholder="Write your {{ $key }} answer here — Tab indents, Ctrl+Enter checks">{{ $done?->answer }}</textarea>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <button class="btn-accent btn-sm">Check {{ $key }}</button>
                <span class="drill-status text-xs text-muted"></span>
            </div>
            <div class="drill-feedback">@if ($done){!! Illuminate\Support\Str::markdown($done->feedback_md ?? '', ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}@endif</div>
        </form>
    @endif
</div>
