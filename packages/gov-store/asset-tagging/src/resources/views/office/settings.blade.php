@extends('layouts/default')

@section('title')
    {{ __('asset-tagging::tagging.nav.settings') }}
    @parent
@stop

@section('content')
@php
    $selectedLocation = old('location_code', $current['location_code'] ?? null);
    $selectedDivisions = old('division_codes', $current['division_codes'] ?? []);
    $selectedDefault = old('default_division_code', $current['default_division_code'] ?? null);
    $facts = app(\GovStore\AssetTagging\Services\OfficeTagFacts::class);
    $previewSegments = [
        ['value' => '··', 'label' => __('asset-tagging::tagging.segment.group'), 'tone' => 'muted'],
        ['value' => '··', 'label' => __('asset-tagging::tagging.segment.subgroup'), 'tone' => 'muted'],
        ['value' => '··', 'label' => __('asset-tagging::tagging.segment.item'), 'tone' => 'muted'],
        ['value' => '····', 'label' => __('asset-tagging::tagging.segment.month'), 'tone' => 'muted'],
        ['value' => $selectedLocation ?: '··', 'label' => __('asset-tagging::tagging.segment.location'), 'tone' => 'success'],
        ['value' => $selectedDefault ?: '··', 'label' => __('asset-tagging::tagging.segment.division'), 'tone' => 'success'],
        ['value' => '····', 'label' => __('asset-tagging::tagging.segment.sequence'), 'tone' => 'muted', 'pending' => true],
    ];
    $readiness = collect($gates)->map(fn ($gate) => ['label' => $gate['key'].' · '.$gate['label'], 'value' => $gate['detail'], 'done' => $gate['passed']])->all();
    $noLists = ! $lists['locations'] || ! $lists['divisions'];
@endphp
<div class="at-page">
    @include('asset-tagging::office.partials.tabs', ['canOffice' => true])

    @if ($conflict)
        <x-gs::alert tone="warning" :title="__('asset-tagging::tagging.conflict.title')">
            {{ __('asset-tagging::tagging.conflict.office', [
                'location' => $conflict['theirs']['location_code'] ?? '—',
                'divisions' => implode(', ', $conflict['theirs']['division_codes'] ?? []),
            ]) }}
        </x-gs::alert>
    @endif

    <div class="at-cols">
        <x-gs::box :title="__('asset-tagging::tagging.office.title', ['office' => $office->name ?? ''])" icon="fas fa-building">
            @if ($noLists)
                <x-gs::empty-state icon="fa-list-ol" :title="__('asset-tagging::tagging.office.no_lists')">
                    {{ __('asset-tagging::tagging.office.no_lists_help') }}
                </x-gs::empty-state>
            @else
                <form method="POST" action="{{ route('asset-tags.office.update') }}" class="at-stack">
                    @csrf
                    @php
                        $revision = $conflict['revision'] ?? $row?->revision;
                    @endphp
                    @if ($revision)<input type="hidden" name="revision" value="{{ $revision }}">@endif

                    <x-gs::form-row :label="__('asset-tagging::tagging.office.location')" for="location_code" required :help="__('asset-tagging::tagging.office.location_help')">
                        <select id="location_code" name="location_code" class="form-control" required>
                            <option value="">—</option>
                            @foreach ($lists['locations'] as $entry)
                                @php
                                    $takenBy = $taken[$entry['code']] ?? null;
                                @endphp
                                <option value="{{ $entry['code'] }}" @selected($selectedLocation === $entry['code']) @disabled($takenBy)>
                                    {{ $entry['code'] }} · {{ $entry['name'] }}@if ($takenBy) — {{ __('asset-tagging::tagging.office.code_taken', ['office' => $takenBy['name']]) }}@endif
                                </option>
                            @endforeach
                        </select>
                    </x-gs::form-row>

                    <fieldset class="at-fieldset">
                        <legend class="at-legend">{{ __('asset-tagging::tagging.office.divisions') }}</legend>
                        <p class="gs-muted">{{ __('asset-tagging::tagging.office.divisions_help') }}</p>
                        <div class="at-checks">
                            @foreach ($lists['divisions'] as $entry)
                                <label class="at-check-option">
                                    <input type="checkbox" name="division_codes[]" value="{{ $entry['code'] }}" @checked(in_array($entry['code'], $selectedDivisions, true))>
                                    <span><code class="gs-mono">{{ $entry['code'] }}</code> · {{ $entry['name'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <x-gs::form-row :label="__('asset-tagging::tagging.office.default_division')" for="default_division_code" required :help="__('asset-tagging::tagging.office.default_help')">
                        <select id="default_division_code" name="default_division_code" class="form-control" required data-at-default-division>
                            @foreach ($lists['divisions'] as $entry)
                                <option value="{{ $entry['code'] }}" @selected($selectedDefault === $entry['code']) @disabled(! in_array($entry['code'], $selectedDivisions, true))>
                                    {{ $entry['code'] }} · {{ $entry['name'] }}
                                </option>
                            @endforeach
                        </select>
                    </x-gs::form-row>

                    <div class="at-preview">
                        <span class="gs-muted">{{ __('asset-tagging::tagging.office.preview') }}</span>
                        <x-gs::code-segments :segments="$previewSegments" :label="__('asset-tagging::tagging.office.preview')" />
                    </div>

                    <div class="at-row">
                        <x-gs::button type="submit" tone="primary" icon="fa-floppy-disk">{{ __('asset-tagging::tagging.office.save') }}</x-gs::button>
                        @if ($row)
                            <span class="gs-muted at-small">{{ __('asset-tagging::tagging.office.revision', ['revision' => $row->revision, 'date' => $row->updated_at?->format('Y-m-d H:i')]) }}</span>
                        @endif
                    </div>
                </form>
            @endif
        </x-gs::box>

        <x-gs::box :title="__('asset-tagging::tagging.readiness.title')" icon="fas fa-list-check">
            <p>
                @if ($enabled)
                    <x-gs::badge tone="success">{{ __('asset-tagging::tagging.readiness.enabled') }}</x-gs::badge>
                @else
                    <x-gs::badge tone="secondary">{{ __('asset-tagging::tagging.readiness.not_enabled') }}</x-gs::badge>
                @endif
            </p>
            <x-gs::readiness :items="$readiness" readonly :caption="__('asset-tagging::tagging.readiness.caption')" />
        </x-gs::box>
    </div>
</div>
@endsection

@section('moar_scripts')
<script nonce="{{ csrf_token() }}">
    // The default division can only be one of the ticked divisions (the server checks this too).
    (function () {
        const select = document.querySelector('[data-at-default-division]');
        if (!select) return;
        const sync = () => {
            const ticked = new Set([...document.querySelectorAll('input[name="division_codes[]"]:checked')].map(box => box.value));
            [...select.options].forEach(option => { option.disabled = !ticked.has(option.value); });
            if (select.selectedOptions[0]?.disabled) select.value = [...ticked][0] || '';
        };
        document.querySelectorAll('input[name="division_codes[]"]').forEach(box => box.addEventListener('change', sync));
        sync();
    })();
</script>
@endsection
