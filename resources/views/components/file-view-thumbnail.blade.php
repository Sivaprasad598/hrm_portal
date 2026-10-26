@php
    // Images and PDFs open in the preview popup (sections.doc-preview); other files as before.
    $ext = strtolower(pathinfo((string) ($file->hashname ?? $file->filename ?? $file->file_url), PATHINFO_EXTENSION));
    $kind = $file->icon == 'images' ? 'image' : ($ext === 'pdf' ? 'pdf' : null);
    // Employee documents keep their address as doc_url, the others as file_url.
    $url = $file->file_url ?: ($file->doc_url ?? '');
@endphp
@if ($kind === 'image')
    <a class="doc-preview" data-preview-url="{{ $url }}" data-preview-kind="image" data-preview-name="{{ $file->filename ?? $file->name ?? '' }}" href="javascript:;">
        <img src="{{ $url }}">
    </a>
@elseif ($kind === 'pdf')
    <a class="doc-preview" data-preview-url="{{ $url }}" data-preview-kind="pdf" data-preview-name="{{ $file->filename ?? $file->name ?? '' }}" href="{{ $url }}">
        <i class="fa {{ $file->icon }} text-lightest"></i>
    </a>
@else
    <a href="{{ $url }}" target="_blank">
        <i class="fa {{ $file->icon }} text-lightest"></i>
    </a>
@endif
