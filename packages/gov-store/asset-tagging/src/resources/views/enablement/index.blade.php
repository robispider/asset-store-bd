@extends('layouts/default')

@section('title')
    {{ __('asset-tagging::tagging.nav.enablement') }}
    @parent
@stop

@section('content')
@php
    $installationItems = collect($installation)->map(fn ($gate) => ['label' => $gate['key'].' · '.$gate['label'], 'value' => $gate['detail'], 'done' => $gate['passed']])->all();
    $installationReady = collect($installation)->every(fn ($gate) => $gate['passed']);
@endphp
<div class="at-page">
    <x-gs::page-header :title="__('asset-tagging::tagging.nav.enablement')" :subtitle="__('asset-tagging::tagging.enable.intro')" />
    @if (session('success'))
        <x-gs::alert tone="success" dismissible>{{ session('success') }}</x-gs::alert>
    @endif
    @if ($errors->any())
        <x-gs::alert tone="danger">{{ $errors->first() }}</x-gs::alert>
    @endif

    <x-gs::box :title="__('asset-tagging::tagging.enable.installation')" icon="fas fa-server">
        <x-gs::readiness :items="$installationItems" readonly :caption="__('asset-tagging::tagging.enable.installation_help')" />
    </x-gs::box>

    <x-gs::box :title="__('asset-tagging::tagging.enable.offices')" icon="fas fa-building">
        @if ($offices->isEmpty())
            <x-gs::empty-state icon="fa-building" :title="__('asset-tagging::tagging.enable.no_offices')" />
        @else
            <x-gs::table :caption="__('asset-tagging::tagging.enable.offices')">
                <thead>
                    <tr>
                        <th scope="col">{{ __('asset-tagging::tagging.enable.office') }}</th>
                        <th scope="col">{{ __('asset-tagging::tagging.enable.codes') }}</th>
                        <th scope="col">{{ __('asset-tagging::tagging.enable.gates') }}</th>
                        <th scope="col">{{ __('asset-tagging::tagging.enable.state') }}</th>
                        <th scope="col">{{ __('asset-tagging::tagging.enable.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($offices as $office)
                        @php
                            $officeReady = $installationReady && collect($office['gates'])->every(fn ($gate) => $gate['passed']);
                        @endphp
                        <tr>
                            <td>{{ $office['name'] }}</td>
                            <td class="gs-mono">
                                @if ($office['facts'])
                                    LL {{ $office['facts']['location_code'] }} · DD {{ implode(', ', $office['facts']['division_codes']) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @foreach ($office['gates'] as $gate)
                                    <x-gs::badge :tone="$gate['passed'] ? 'success' : 'warning'" outline :label="$gate['detail']">
                                        <i class="fas {{ $gate['passed'] ? 'fa-check' : 'fa-xmark' }}" aria-hidden="true"></i> {{ $gate['key'] }}
                                    </x-gs::badge>
                                @endforeach
                            </td>
                            <td>
                                <x-gs::badge :tone="$office['enabled'] ? 'success' : 'secondary'">{{ $office['enabled'] ? __('asset-tagging::tagging.readiness.enabled') : __('asset-tagging::tagging.readiness.not_enabled') }}</x-gs::badge>
                                @if ($office['reason'])<div class="gs-muted at-small">{{ $office['reason'] }}</div>@endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route($office['enabled'] ? 'asset-tags.enablement.disable' : 'asset-tags.enablement.enable', $office['id']) }}" class="at-inline-form">
                                    @csrf
                                    <label class="gs-sr-only" for="reason-{{ $office['id'] }}">{{ __('asset-tagging::tagging.enable.reason') }}</label>
                                    <input id="reason-{{ $office['id'] }}" name="reason" class="form-control input-sm" required minlength="5" maxlength="500"
                                        placeholder="{{ __('asset-tagging::tagging.enable.reason') }}">
                                    @if ($office['enabled'])
                                        <x-gs::button type="submit" tone="danger" outline size="sm">{{ __('asset-tagging::tagging.enable.disable') }}</x-gs::button>
                                    @else
                                        <x-gs::button type="submit" tone="primary" size="sm" :disabled="! $officeReady">{{ __('asset-tagging::tagging.enable.enable') }}</x-gs::button>
                                    @endif
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-gs::table>
        @endif
    </x-gs::box>

    <x-gs::box :title="__('asset-tagging::tagging.enable.activity')" icon="fas fa-clock-rotate-left">
        @if ($activity->isEmpty())
            <p class="gs-muted">{{ __('asset-tagging::tagging.enable.no_activity') }}</p>
        @else
            <ul class="at-activity">
                @foreach ($activity as $event)
                    <li>
                        <span class="gs-muted">{{ $event->created_at }}</span>
                        <code class="gs-mono">{{ $event->ability }}</code>
                        <x-gs::badge :tone="$event->outcome === 'allowed' ? 'success' : 'warning'" outline>{{ $event->outcome }}</x-gs::badge>
                        @if ($event->reason)<span>{{ $event->reason }}</span>@endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-gs::box>
</div>
@endsection
