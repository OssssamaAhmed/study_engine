@php
    $exportFormats = ['txt' => 'TXT', 'md' => 'MD', 'doc' => 'DOC', 'docx' => 'DOCX'];
@endphp
<div class="mt-3 mb-6 flex flex-wrap items-center gap-2">
    <span class="text-xs text-muted">Export:</span>
    @foreach ($exportFormats as $format => $label)
        <a href="/phases/{{ $phase }}/export/{{ $document }}/{{ $format }}" class="btn btn-outline btn-sm">{{ $label }}</a>
    @endforeach
</div>
