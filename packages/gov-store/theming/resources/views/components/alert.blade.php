@props(['tone' => 'info', 'dismissible' => false, 'title' => null])
@php($icons = ['info' => 'fa-circle-info', 'success' => 'fa-circle-check', 'warning' => 'fa-triangle-exclamation', 'danger' => 'fa-circle-exclamation'])
<div {{ $attributes->class(['gs-alert', 'gs-alert--'.$tone]) }} role="{{ in_array($tone, ['danger', 'warning']) ? 'alert' : 'status' }}">
    <i class="fas {{ $icons[$tone] ?? $icons['info'] }} gs-alert__icon" aria-hidden="true"></i>
    <div class="gs-alert__body">
        @if ($title)<strong>{{ $title }}</strong>@endif
        {{ $slot }}
    </div>
    @if ($dismissible)
        <button type="button" class="gs-alert__close" data-gs-dismiss aria-label="{{ __('gs-theme::appearance.kit.dismiss') }}">&times;</button>
    @endif
</div>
