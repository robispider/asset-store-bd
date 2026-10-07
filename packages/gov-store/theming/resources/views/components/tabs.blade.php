{{--
    In-page tabs (ARIA tabs pattern; gs-theme.js handles clicks and arrow keys). For links between
    filtered lists use <x-gs::status-tabs>. tabs: [['key' => 'basic', 'label' => 'Basic info', 'icon' => null], …]
    Panels: <x-gs::tab-panel for="basic">…</x-gs::tab-panel> inside the slot. `justified` gives equal-width tabs.
    `id` is required: panels read it (and `active`) through @aware to wire aria-controls / aria-labelledby.
--}}
@props(['id', 'tabs' => [], 'active' => null, 'justified' => false, 'label' => null])
@php($active = $active ?? ($tabs[0]['key'] ?? null))
<div {{ $attributes->class(['gs-tabset', 'gs-tabset--justified' => $justified]) }} data-gs-tabs id="{{ $id }}">
    <div class="gs-tabset__list" role="tablist" @if ($label) aria-label="{{ $label }}" @endif>
        @foreach ($tabs as $tab)
            @php($selected = $tab['key'] === $active)
            <button type="button" class="gs-tabset__tab" role="tab" id="{{ $id }}-tab-{{ $tab['key'] }}"
                aria-controls="{{ $id }}-panel-{{ $tab['key'] }}" aria-selected="{{ $selected ? 'true' : 'false' }}" tabindex="{{ $selected ? '0' : '-1' }}">
                @if (! empty($tab['icon']))<i class="fas {{ $tab['icon'] }}" aria-hidden="true"></i>@endif
                {{ $tab['label'] }}
            </button>
        @endforeach
    </div>
    {{ $slot }}
</div>
