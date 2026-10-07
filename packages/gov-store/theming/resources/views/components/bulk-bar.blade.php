{{-- Listens to bootstrap-table check/uncheck events on #{{ $for }} (wired by gs-theme.js). --}}
@props(['for'])
<div {{ $attributes->class('gs-bulk-bar') }} data-gs-bulk-for="{{ $for }}" role="region" aria-live="polite"
    aria-label="{{ __('gs-theme::appearance.kit.bulk_actions') }}" hidden>
    <span class="gs-bulk-bar__count"><span data-gs-bulk-count>0</span> {{ __('gs-theme::appearance.kit.selected') }}</span>
    <div class="gs-bulk-bar__actions">{{ $actions ?? $slot }}</div>
</div>
