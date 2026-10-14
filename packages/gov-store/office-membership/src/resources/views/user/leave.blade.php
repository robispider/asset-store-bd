@extends('layouts/default')

@php
    $m = 'office_membership::member.';
    $officeName = $membership->location->name ?? __($m.'unknown_office');
    $office = (int) $membership->location_id;
    $home = (bool) $membership->is_home_office;
    $status = $membership->status;
    $adminName = $admin?->present()->fullName ?? __($m.'the_office_admin');
    $date = fn ($value) => $value ? $value->translatedFormat('j M Y') : '';
    $cleared = $status !== 'active' || $engine->isCleared($checks);
    $items = collect($checks)->map(fn ($result, $name) => ['label' => $name, 'done' => $result->isPassed,
        'value' => $result->isPassed ? null : $result->summary()])->values()->all();
    $notNeeded = collect($checks)->filter(fn ($r, $name) => $r->isPassed && $r->reason !== '' && $name === __($m.'rule_physical_inventory_name'))->pluck('reason')->all();
    $destination = $membership->destination?->name;
    $steps = [
        ['label' => __($m.'leave_step_ready'), 'state' => $status === 'active' ? ($cleared ? 'done' : 'current') : 'done'],
        ['label' => __($m.'leave_step_ask'), 'state' => $status === 'active' ? ($cleared ? 'current' : 'upcoming') : 'done'],
        ['label' => __($m.'leave_step_signoff', ['office' => $officeName]), 'state' => match ($status) { 'release_requested' => 'current', 'released' => 'done', default => 'upcoming' }],
    ];
    if ($home) {
        $steps[] = ['label' => __($m.'leave_step_welcome'), 'state' => $status === 'released' ? 'current' : 'upcoming'];
    }
@endphp

@section('title', __($m.'leave_title', ['office' => $officeName]))

{{-- Leave an office (plan board 5): get ready → ask to leave → sign-off → (home office) the new office welcomes you. --}}
@section('content')
<div class="govmem">
    <x-gs::page-header :title="__($m.'leave_title', ['office' => $officeName])"
        :subtitle="__($m.($home ? 'leave_subtitle_home' : 'leave_subtitle_additional'), ['office' => $officeName])" />

    <x-gs::stepper :steps="$steps" :label="__($m.'leave_steps')" />

    @include('govmem::partials.errors')

    <div class="govmem-layout">
        <div class="govmem-layout__main govmem-stack">
            @if ($status === 'active')
                <x-gs::box id="before-you-leave">
                    <x-gs::readiness :items="$items" :title="__($m.'leave_ready_title')" :caption="__($m.'leave_ready_caption', ['office' => $officeName])" readonly />
                    @if ($notNeeded)
                        <p class="govmem-muted govmem-small govmem-flush">{{ __($m.'leave_not_needed') }} {{ implode(' ', $notNeeded) }}</p>
                    @endif
                </x-gs::box>

                @if ($roles || $cover)
                    <x-gs::box :title="__($m.'leave_roles_title')">
                        @include('govmem::partials.role-slots', ['officeName' => $officeName, 'officeRoles' => $roles, 'officeCover' => $cover])
                        <p class="govmem-muted govmem-small govmem-flush">{{ __($m.'roles_hint') }}</p>
                    </x-gs::box>
                @endif

                <x-gs::box :title="__($m.'leave_ask_title', ['office' => $officeName])" id="ask-to-leave">
                    <form method="POST" action="{{ route('gov.membership.request-release', $membership->id) }}" class="govmem-stack">
                        @csrf
                        <ul class="govmem-effects">
                            <li class="govmem-effect--stop"><i class="fas fa-door-open" aria-hidden="true"></i><span>{{ __($m.'leave_effect_stop', ['admin' => $adminName, 'office' => $officeName]) }}</span></li>
                            <li class="govmem-effect--ok"><i class="fas fa-rotate-left" aria-hidden="true"></i><span>{{ __($m.'leave_effect_until') }}</span></li>
                            @if ($others->isNotEmpty())
                                <li class="govmem-effect--info"><i class="fas fa-circle-info" aria-hidden="true"></i><span>{{ __($m.'leave_effect_others', ['offices' => $others->map(fn ($o) => $o->location->name ?? '')->implode(', ')]) }}</span></li>
                            @endif
                        </ul>
                        @if ($home)
                            <x-gs::form-row for="destination_location_id" :label="__($m.'leave_where')" :help="__($m.'leave_where_help')">
                                <select class="form-control" id="destination_location_id" name="destination_location_id" style="width: 100%"
                                    data-endpoint="{{ route('gov.membership.destinations', $membership->id) }}"
                                    data-placeholder="{{ __($m.'leave_where_placeholder') }}" aria-describedby="destination_location_id-help">
                                    <option value=""></option>
                                </select>
                            </x-gs::form-row>
                        @endif
                        <label class="govmem-ack" for="acknowledged">
                            <input type="checkbox" id="acknowledged" name="acknowledged" value="1" required @checked(old('acknowledged'))>
                            <span>{{ __($m.($home ? 'leave_ack_home' : 'leave_ack_additional'), ['office' => $officeName]) }}</span>
                        </label>
                        @error('acknowledged')<p class="gs-form-row__error" role="alert">{{ $message }}</p>@enderror
                        <div class="govmem-toolbar">
                            <x-gs::button type="submit" :disabled="! $cleared" aria-describedby="ask-to-leave-why">{{ __($m.'leave_ask_button', ['office' => $officeName]) }}</x-gs::button>
                            <x-gs::button :href="route('gov.membership.index')" tone="light" outline>{{ __($m.'back') }}</x-gs::button>
                        </div>
                        @unless ($cleared)
                            <p class="govmem-muted govmem-small govmem-flush" id="ask-to-leave-why"><i class="fas fa-lock" aria-hidden="true"></i> {{ __($m.'leave_ask_blocked') }}</p>
                        @endunless
                    </form>
                </x-gs::box>
            @else
                <x-gs::box :title="__($m.'transfer_title')" id="your-transfer">
                    <x-slot:tools><x-gs::status-badge :status="$membership->statusKey()" /></x-slot:tools>
                    <ol class="govmem-track">
                        <li class="govmem-track__step govmem-track__step--done">
                            <strong>{{ __($m.'track_asked', ['office' => $officeName]) }}</strong>
                            <span class="govmem-meta">{{ $date($membership->updated_at) }}@if ($destination) · {{ __($m.'track_going_to', ['office' => $destination]) }}@endif</span>
                        </li>
                        <li @class(['govmem-track__step', 'govmem-track__step--current' => $status === 'release_requested', 'govmem-track__step--done' => $status === 'released'])
                            @if ($status === 'release_requested') aria-current="step" @endif>
                            @if ($status === 'released')
                                <strong>{{ __($m.'track_signed_off', ['office' => $officeName]) }}</strong>
                                <span class="govmem-meta">{{ collect([$date($membership->approved_at), $membership->approver?->present()->fullName])->filter()->implode(' · ') }}</span>
                            @else
                                <strong>{{ __($m.'track_signoff', ['name' => $adminName]) }}</strong>
                                <span class="govmem-meta">{{ __($m.'track_signoff_meta', ['office' => $officeName]) }}</span>
                            @endif
                        </li>
                        @if ($home)
                            <li @class(['govmem-track__step', 'govmem-track__step--current' => $status === 'released']) @if ($status === 'released') aria-current="step" @endif>
                                <strong>{{ $destination ? __($m.'track_welcome_named', ['office' => $destination]) : __($m.'track_welcome') }}</strong>
                                <span class="govmem-meta">{{ __($m.'track_welcome_meta') }}</span>
                            </li>
                        @endif
                    </ol>
                    @if ($status === 'release_requested')
                        <form method="POST" action="{{ route('gov.membership.withdraw-release', $membership->id) }}" class="govmem-toolbar">
                            @csrf
                            <x-gs::button type="submit" tone="light" outline icon="fa-rotate-left">{{ __($m.'leave_withdraw') }}</x-gs::button>
                        </form>
                    @endif
                </x-gs::box>
            @endif
        </div>

        <aside class="govmem-layout__aside govmem-stack">
            <x-gs::box :title="__($m.'who_to_ask')">
                @if ($admin)
                    <x-gs::list>
                        <x-gs::list-item :title="$admin->present()->fullName" :avatar="$admin->present()->fullName"
                            :subtitle="__($m.'who_admin', ['office' => $officeName])">
                            @if ($admin->email)<a href="mailto:{{ $admin->email }}" class="govmem-small">{{ $admin->email }}</a>@endif
                        </x-gs::list-item>
                    </x-gs::list>
                @else
                    <p class="govmem-muted govmem-flush">{{ __($m.'who_none') }}</p>
                @endif
            </x-gs::box>
            @if ($home)
                <x-gs::box :title="__($m.'leave_additional_title')">
                    <p class="govmem-flush govmem-small">{{ __($m.'leave_additional_body') }}</p>
                </x-gs::box>
            @endif
        </aside>
    </div>
</div>

@include('govmem::partials.handover-dialog')
@endsection

@section('moar_scripts')
@include('govmem::partials.script')
@if ($home && $status === 'active')
<script>
$(function () {
    var $select = $('#destination_location_id');
    if (! $.fn.select2) { return; }
    $select.select2({
        allowClear: true, placeholder: $select.data('placeholder'), minimumInputLength: 2,
        ajax: { url: $select.data('endpoint'), dataType: 'json', delay: 250,
            data: function (params) { return { q: params.term, page: params.page || 1 }; } }
    });
});
</script>
@endif
@endsection
