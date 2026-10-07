@extends('layouts/default')

@section('title')
    {{ __('gs-theme::appearance.assignments_title') }}
    @parent
@stop

@section('content')
@php($gs = app('gs.theme'))
<div class="gs-assignments">
    <x-gs::page-header :title="__('gs-theme::appearance.assignments_title')" :subtitle="__('gs-theme::appearance.assignments_intro')" />

    @if (session('success'))
        <x-gs::alert tone="success" dismissible>{{ session('success') }}</x-gs::alert>
    @endif
    @if ($errors->any())
        <x-gs::alert tone="danger">{{ $errors->first() }}</x-gs::alert>
    @endif

    @if ($any && (isset($cards['company']) || isset($cards['office'])))
        <x-gs::filter-bar :action="route('gs-theme.assignments')">
            @isset($cards['company'])
                <div>
                    <label for="gs-company-pick" class="control-label">{{ __('gs-theme::appearance.pick_company') }}</label>
                    <select id="gs-company-pick" name="company_id" class="form-control">
                        <option value="">—</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}" @selected($cards['company']['id'] == $company->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endisset
            @isset($cards['office'])
                <div>
                    <label for="gs-office-pick" class="control-label">{{ __('gs-theme::appearance.pick_office') }}</label>
                    <select id="gs-office-pick" name="office_id" class="form-control">
                        <option value="">—</option>
                        @foreach ($offices as $office)
                            <option value="{{ $office->id }}" @selected($cards['office']['id'] == $office->id)>{{ $office->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endisset
        </x-gs::filter-bar>
    @endif

    <div class="gs-assignment-grid">
        @foreach ($cards as $scope => $card)
            <x-gs::box :title="__('gs-theme::appearance.scope_'.$scope).($card['name'] && $scope !== 'organization' ? ' · '.$card['name'] : '')"
                :icon="['organization' => 'fas fa-landmark', 'company' => 'fas fa-building-columns', 'office' => 'fas fa-building'][$scope]"
                tone="primary" data-gs-assignment="{{ $scope }}">
                @if ($scope !== 'organization' && ! $card['id'])
                    <x-gs::empty-state icon="fa-hand-pointer" :title="__('gs-theme::appearance.no_scope_selected')" />
                @else
                    @php($effective = $card['effective'])
                    <p>
                        <strong>{{ __('gs-theme::appearance.assignment_effective') }}:</strong>
                        @if ($effective && ! $effective['own'])
                            {{ __('gs-theme::appearance.assignment_inherits', [
                                'theme' => ($themes[$effective['key']] ?? null)?->label() ?? $effective['key'],
                                'source' => __('gs-theme::appearance.source.'.$effective['source']),
                            ]) }}
                        @elseif ($effective)
                            {{ ($themes[$effective['key']] ?? null)?->label() ?? $effective['key'] }}
                        @endif
                    </p>
                    @if ($card['assignment'] && $card['updated_by'])
                        <p class="gs-muted"><small>{{ __('gs-theme::appearance.assignment_last_changed', [
                            'user' => $card['updated_by'],
                            'date' => \Illuminate\Support\Carbon::parse($card['assignment']['updated_at'])->toDayDateTimeString(),
                        ]) }}</small></p>
                    @endif

                    <form method="POST" action="{{ route('gs-theme.assignments.update', array_filter(['scope' => $scope, 'id' => $card['id']])) }}">
                        @csrf
                        @method('PUT')
                        @if ($any)
                            <input type="hidden" name="company_id" value="{{ $cards['company']['id'] ?? '' }}">
                            <input type="hidden" name="office_id" value="{{ $cards['office']['id'] ?? '' }}">
                        @endif
                        <x-gs::form-row :label="__('gs-theme::appearance.assignment_theme')" :for="'gs-theme-'.$scope">
                            <select id="gs-theme-{{ $scope }}" name="theme" class="form-control" @disabled(! $card['editable'])>
                                <option value="">{{ __('gs-theme::appearance.assignment_none') }}</option>
                                @foreach ($themes as $key => $theme)
                                    <option value="{{ $key }}" @selected(($card['assignment']['theme'] ?? null) === $key)>{{ $theme->label() }}</option>
                                @endforeach
                            </select>
                        </x-gs::form-row>
                        <x-gs::form-row :label="__('gs-theme::appearance.assignment_enforce')" :for="'gs-enforce-'.$scope" :help="__('gs-theme::appearance.assignment_enforce_help')">
                            <input type="hidden" name="enforced" value="0">
                            <input type="checkbox" id="gs-enforce-{{ $scope }}" name="enforced" value="1" @checked($card['assignment']['enforced'] ?? false) @disabled(! $card['editable'])>
                        </x-gs::form-row>
                        <div class="gs-assignment-previews" aria-hidden="true">
                            @foreach ($themes as $key => $theme)
                                @if ($url = $gs->previewUrl($key, 'light'))
                                    <img src="{{ $url }}" alt="" loading="lazy" width="160" height="100" data-gs-assignment-preview="{{ $key }}"
                                        @class(['is-selected' => ($card['assignment']['theme'] ?? null) === $key])>
                                @endif
                            @endforeach
                        </div>
                        @if ($card['editable'])
                            <button type="submit" class="gs-btn gs-btn--primary">{{ __('gs-theme::appearance.save') }}</button>
                        @endif
                    </form>
                @endif
            </x-gs::box>
        @endforeach
    </div>
</div>
@stop
