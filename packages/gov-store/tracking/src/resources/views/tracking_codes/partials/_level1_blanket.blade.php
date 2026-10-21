{{-- task-form.js shows the panel that matches the chosen strategy; the inline display is its initial state. --}}
<x-gs::box id="panel-level1" style="display: none;">
    <x-gs::alert tone="info" :title="__('govtracking::general.ui.extra_blanket_code_execution')">
        <p>{{ __('govtracking::general.task.blanket_help') }}</p>
    </x-gs::alert>
</x-gs::box>
