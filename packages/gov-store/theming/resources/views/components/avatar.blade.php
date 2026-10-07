{{-- Image or initials. Decorative by default (the name is normally printed next to it). --}}
@props(['name' => '', 'image' => null, 'size' => 'md', 'tone' => 'primary', 'decorative' => true])
@php
    $initials = collect(preg_split('/\s+/u', trim($name)) ?: [])->filter()->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
@endphp
<span {{ $attributes->class(['gs-avatar', 'gs-avatar--'.$size, 'gs-tone-'.$tone]) }} @if ($decorative) aria-hidden="true" @else role="img" aria-label="{{ $name }}" @endif>
    @if ($image)<img src="{{ $image }}" alt="" loading="lazy">@else{{ $initials ?: '?' }}@endif
</span>
