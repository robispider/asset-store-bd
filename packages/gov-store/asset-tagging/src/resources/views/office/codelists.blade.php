@extends('layouts/default')

@section('title')
    {{ __('asset-tagging::tagging.nav.codes') }}
    @parent
@stop

@section('content')
@php
    $old = ['locations' => old('locations'), 'divisions' => old('divisions')];
    $revision = $conflict['revision'] ?? $row?->revision;
@endphp
<div class="at-page">
    @include('asset-tagging::office.partials.tabs', ['canCodes' => true])

    @if ($conflict)
        <x-gs::alert tone="warning" :title="__('asset-tagging::tagging.conflict.title')">{{ __('asset-tagging::tagging.conflict.codes') }}</x-gs::alert>
    @endif

    <form method="POST" action="{{ route('asset-tags.codes.update') }}" class="at-stack">
        @csrf
        @if ($revision)<input type="hidden" name="revision" value="{{ $revision }}">@endif
        <p class="gs-muted">{{ __('asset-tagging::tagging.codes.intro', ['company' => $company->name ?? '']) }}</p>
        <div class="at-cols">
            @foreach (['locations', 'divisions'] as $list)
                @php
                    $entries = $old[$list] ?? $lists[$list];
                    $entries = array_merge(array_values($entries), [['code' => '', 'name' => ''], ['code' => '', 'name' => '']]);
                @endphp
                <x-gs::box :title="__('asset-tagging::tagging.codes.'.$list)" :icon="$list === 'locations' ? 'fas fa-location-dot' : 'fas fa-sitemap'">
                    <x-gs::table :caption="__('asset-tagging::tagging.codes.'.$list)">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('asset-tagging::tagging.codes.code') }}</th>
                                <th scope="col">{{ __('asset-tagging::tagging.codes.name') }}</th>
                                <th scope="col">{{ __('asset-tagging::tagging.codes.usage') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($entries as $i => $entry)
                                @php
                                    $used = $entry['code'] !== '' ? ($usage[$list][$entry['code']] ?? 0) : 0;
                                @endphp
                                <tr>
                                    <td>
                                        <label class="gs-sr-only" for="{{ $list }}-{{ $i }}-code">{{ __('asset-tagging::tagging.codes.code') }}</label>
                                        <input id="{{ $list }}-{{ $i }}-code" name="{{ $list }}[{{ $i }}][code]" value="{{ $entry['code'] }}" class="form-control at-code-input"
                                            inputmode="numeric" pattern="[0-9]{2}" maxlength="2" @readonly($used > 0)>
                                    </td>
                                    <td>
                                        <label class="gs-sr-only" for="{{ $list }}-{{ $i }}-name">{{ __('asset-tagging::tagging.codes.name') }}</label>
                                        <input id="{{ $list }}-{{ $i }}-name" name="{{ $list }}[{{ $i }}][name]" value="{{ $entry['name'] }}" class="form-control" maxlength="120">
                                    </td>
                                    <td>
                                        @if ($used > 0)
                                            <x-gs::badge tone="info" outline>{{ trans_choice('asset-tagging::tagging.codes.used_by', $used, ['count' => $used]) }}</x-gs::badge>
                                            <span class="gs-sr-only">{{ __('asset-tagging::tagging.codes.locked') }}</span>
                                        @else
                                            <span class="gs-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-gs::table>
                    <p class="gs-muted at-small">{{ __('asset-tagging::tagging.codes.help') }}</p>
                </x-gs::box>
            @endforeach
        </div>
        <div class="at-row">
            <x-gs::button type="submit" tone="primary" icon="fa-floppy-disk">{{ __('asset-tagging::tagging.codes.save') }}</x-gs::button>
            @if ($row)
                <span class="gs-muted at-small">{{ __('asset-tagging::tagging.office.revision', ['revision' => $row->revision, 'date' => $row->updated_at?->format('Y-m-d H:i')]) }}</span>
            @endif
        </div>
    </form>
</div>
@endsection
