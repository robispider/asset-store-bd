@props(['label' => null])
<div {{ $attributes->class('gs-btn-group') }} role="group" @if ($label) aria-label="{{ $label }}" @endif>{{ $slot }}</div>
