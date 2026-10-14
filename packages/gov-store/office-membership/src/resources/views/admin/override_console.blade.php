@extends('layouts/default')

@php
    $m = 'office_membership::member.';
    $personName = $person ? (trim($person->first_name.' '.$person->last_name) ?: $person->username) : '';
    $openMemberships = $reach ? $reach['memberships']->whereIn('status', \GovStore\OfficeMembership\Services\MembershipWorkflow::OPEN) : collect();
    $type = old('override_type');
@endphp

@section('title', __($m.'override_console_title'))

{{--
    Emergency changes (plan board 9): find the person, see their reach, pick one action (nothing preselected),
    give a reason. Submitting goes to the national review, which still asks for the typed confirmation.
--}}
@section('content')
<div class="govmem">
    <x-gs::page-header :title="__($m.'override_console_title')" :subtitle="__($m.'override_subtitle')" />

    <x-gs::status-tabs :active="$tab" :label="__($m.'override_console_title')" :tabs="[
        ['key' => 'change', 'label' => __($m.'override_tab_change'), 'href' => route('gov.membership.override.console')],
        ['key' => 'history', 'label' => __($m.'override_tab_history'), 'count' => $historyCount, 'href' => route('gov.membership.override.console', ['tab' => 'history'])],
    ]" />

    @include('govmem::partials.errors')

    @if ($tab === 'change')
        <x-gs::box :title="__($m.'override_step_who')" id="step-who">
            @if ($person)
                <div class="govmem-person-card">
                    <x-gs::avatar :name="$personName" />
                    <div>
                        <strong>{{ $personName }}</strong>
                        <span class="govmem-meta">{{ collect([$person->jobtitle, $person->username])->filter()->implode(' · ') }}</span>
                        @if (! empty($person->last_login))
                            <span class="govmem-meta">{{ __($m.'override_last_login', ['time' => \Illuminate\Support\Carbon::parse($person->last_login)->diffForHumans()]) }}</span>
                        @endif
                    </div>
                    <x-gs::button :href="route('gov.membership.override.console')" tone="light" outline size="sm">{{ __($m.'override_choose_other') }}</x-gs::button>
                </div>

                <dl class="govmem-reach">
                    <div>
                        <dt>{{ __($m.'override_reach_offices') }}</dt>
                        <dd>
                            @forelse ($reach['memberships'] as $membership)
                                <span class="govmem-reach__row">{{ $membership->location->name ?? '—' }}
                                    <x-gs::status-badge :status="$membership->statusKey()" />
                                    @if ($membership->is_home_office)<x-gs::badge tone="primary" outline>{{ __($m.'home_office') }}</x-gs::badge>@endif
                                </span>
                            @empty
                                {{ __($m.'override_reach_none') }}
                            @endforelse
                        </dd>
                    </div>
                    <div>
                        <dt>{{ __($m.'override_reach_roles') }}</dt>
                        <dd>
                            @forelse ($reach['roles'] as $office => $labels)
                                <span class="govmem-reach__row">{{ implode(', ', $labels) }} · {{ $reach['offices'][$office] ?? '—' }}</span>
                            @empty
                                {{ __($m.'override_reach_none') }}
                            @endforelse
                        </dd>
                    </div>
                    <div>
                        <dt>{{ __($m.'override_reach_holds') }}</dt>
                        <dd>
                            @php $held = collect($reach['holdings'])->filter(); @endphp
                            @forelse ($held as $office => $lines)
                                <span class="govmem-reach__row">{{ implode('; ', $lines) }} · {{ $reach['offices'][$office] ?? '—' }}</span>
                            @empty
                                {{ __($m.'override_reach_none') }}
                            @endforelse
                        </dd>
                    </div>
                </dl>
            @else
                <form method="GET" action="{{ route('gov.membership.override.console') }}" class="govmem-filters" role="search">
                    <label class="sr-only" for="person-search">{{ __($m.'override_search') }}</label>
                    <input type="search" class="form-control govmem-filters__search" id="person-search" name="q" value="{{ $term }}"
                        minlength="2" required placeholder="{{ __($m.'override_search') }}">
                    <x-gs::button type="submit" icon="fa-magnifying-glass">{{ __($m.'search') }}</x-gs::button>
                </form>
                @if ($term !== '' && mb_strlen($term) < 2)
                    <p class="govmem-muted govmem-flush">{{ __($m.'override_search_short') }}</p>
                @elseif ($term !== '')
                    @if ($people->isEmpty())
                        <p class="govmem-muted govmem-flush">{{ __($m.'override_search_none') }}</p>
                    @else
                        <x-gs::list>
                            @foreach ($people->take(20) as $match)
                                @php $matchName = trim($match->first_name.' '.$match->last_name) ?: $match->username; @endphp
                                <x-gs::list-item :title="$matchName" :avatar="$matchName" :subtitle="collect([$match->jobtitle, $match->username])->filter()->implode(' · ')">
                                    <x-slot:actions>
                                        <x-gs::button :href="route('gov.membership.override.console', ['user' => $match->id])" size="sm" tone="light" outline>{{ __($m.'override_choose', ['name' => $match->first_name ?: $matchName]) }}</x-gs::button>
                                    </x-slot:actions>
                                </x-gs::list-item>
                            @endforeach
                        </x-gs::list>
                        @if ($people->count() > 20)<p class="govmem-muted govmem-small govmem-flush">{{ __($m.'override_search_more') }}</p>@endif
                    @endif
                @endif
            @endif
        </x-gs::box>

        @if ($person)
            <form method="POST" action="{{ route('gov.membership.override') }}" class="govmem-stack">
                @csrf
                <input type="hidden" name="user_id" value="{{ $person->id }}">

                <x-gs::box :title="__($m.'override_step_what')" id="override_type">
                    <fieldset class="govmem-stack">
                        <legend class="sr-only">{{ __($m.'override_step_what') }}</legend>
                        <div class="gs-choices">
                            <x-gs::choice-card name="override_type" value="strip_roles" icon="fa-user-slash" :checked="$type === 'strip_roles'"
                                :label="__($m.'override_strip_roles_option')" :description="__($m.'override_strip_roles_desc')" />
                            <x-gs::choice-card name="override_type" value="force_release" icon="fa-door-open" :checked="$type === 'force_release'"
                                :label="__($m.'override_force_release_option')" :description="__($m.'override_force_release_desc')" />
                        </div>
                        <p class="govmem-muted govmem-small govmem-flush">{{ __($m.'override_nothing_chosen') }}</p>

                        <div data-govmem-when="override_type=force_release" class="govmem-stack">
                            <fieldset id="location_id">
                                <legend class="govmem-legend">{{ __($m.'override_which_office') }}</legend>
                                @forelse ($openMemberships as $membership)
                                    <label class="govmem-radio">
                                        <input type="radio" name="location_id" value="{{ $membership->location_id }}" required
                                            @checked((string) old('location_id') === (string) $membership->location_id)>
                                        <span>{{ $membership->location->name ?? '—' }}
                                            <span class="govmem-muted">({{ __($m.($membership->is_home_office ? 'home_office' : 'additional_office')) }} · {{ __('gs-theme::appearance.status.'.$membership->statusKey()) }})</span>
                                            @if (! empty($reach['roles'][$membership->location_id]))
                                                <span class="govmem-meta">{{ __($m.'override_office_roles', ['roles' => implode(', ', $reach['roles'][$membership->location_id])]) }}</span>
                                            @endif
                                            @if (! empty($reach['holdings'][$membership->location_id]))
                                                <span class="govmem-meta">{{ __($m.'override_office_holds', ['items' => implode('; ', $reach['holdings'][$membership->location_id])]) }}</span>
                                            @endif
                                        </span>
                                    </label>
                                @empty
                                    <p class="govmem-muted govmem-flush">{{ __($m.'override_no_open_office') }}</p>
                                @endforelse
                                @error('location_id')<p class="gs-form-row__error" role="alert">{{ $message }}</p>@enderror
                            </fieldset>
                            <input type="hidden" name="also_remove_roles" value="0">
                            <label class="govmem-ack" for="also_remove_roles">
                                <input type="checkbox" id="also_remove_roles" name="also_remove_roles" value="1" @checked(old('also_remove_roles', '1') === '1')>
                                <span>{{ __($m.'override_also_roles') }} <span class="govmem-muted">{{ __($m.'recommended') }}</span></span>
                            </label>
                            <div>
                                <strong>{{ __($m.'override_changes_title') }}</strong>
                                <ul class="govmem-effects">
                                    <li class="govmem-effect--stop"><i class="fas fa-door-open" aria-hidden="true"></i><span>{{ __($m.'override_release_effect_stop', ['name' => $person->first_name ?: $personName]) }}</span></li>
                                    <li class="govmem-effect--info"><i class="fas fa-right-left" aria-hidden="true"></i><span>{{ __($m.'override_release_effect_home') }}</span></li>
                                    <li class="govmem-effect--info"><i class="fas fa-box" aria-hidden="true"></i><span>{{ __($m.'override_release_effect_items') }}</span></li>
                                    <li class="govmem-effect--info"><i class="fas fa-user-tag" aria-hidden="true"></i><span>{{ __($m.'override_release_effect_roles') }}</span></li>
                                    <li class="govmem-effect--ok"><i class="fas fa-circle-check" aria-hidden="true"></i><span>{{ __($m.'override_release_effect_others') }}</span></li>
                                </ul>
                            </div>
                        </div>

                        <div data-govmem-when="override_type=strip_roles">
                            <strong>{{ __($m.'override_changes_title') }}</strong>
                            <ul class="govmem-effects">
                                <li class="govmem-effect--stop"><i class="fas fa-user-slash" aria-hidden="true"></i><span>{{ __($m.'override_strip_effect_roles', ['count' => count($reach['roles'])]) }}</span></li>
                                <li class="govmem-effect--info"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i><span>{{ __($m.'override_strip_effect_gaps') }}</span></li>
                                <li class="govmem-effect--ok"><i class="fas fa-circle-check" aria-hidden="true"></i><span>{{ __($m.'override_strip_effect_stays') }}</span></li>
                            </ul>
                        </div>
                    </fieldset>
                </x-gs::box>

                <x-gs::box :title="__($m.'override_step_why')">
                    <div class="govmem-stack">
                        <x-gs::form-row for="reason" :label="__($m.'override_reason')" required :help="__($m.'override_reason_help')">
                            <textarea class="form-control" id="reason" name="reason" rows="3" minlength="10" maxlength="1000" required aria-describedby="reason-help">{{ old('reason') }}</textarea>
                        </x-gs::form-row>
                        <x-gs::form-row for="reference" :label="__($m.'override_reference')" :help="__($m.'override_reference_help')">
                            <input type="text" class="form-control" id="reference" name="reference" maxlength="100" value="{{ old('reference') }}" aria-describedby="reference-help">
                        </x-gs::form-row>
                        <label class="govmem-ack" for="acknowledged">
                            <input type="checkbox" id="acknowledged" name="acknowledged" value="1" required>
                            <span>{{ __($m.'override_ack') }}</span>
                        </label>
                        @error('acknowledged')<p class="gs-form-row__error" role="alert">{{ $message }}</p>@enderror
                        <div class="govmem-toolbar">
                            <x-gs::button type="submit" tone="danger">{{ __($m.'override_continue') }}</x-gs::button>
                            <span class="govmem-muted govmem-small">{{ __($m.'override_continue_hint') }}</span>
                        </div>
                    </div>
                </x-gs::box>
            </form>
        @endif
    @else
        <form method="GET" action="{{ route('gov.membership.override.console') }}" class="govmem-filters" role="search">
            <input type="hidden" name="tab" value="history">
            <label class="sr-only" for="history-search">{{ __($m.'override_history_search') }}</label>
            <input type="search" class="form-control govmem-filters__search" id="history-search" name="q" value="{{ $term }}" placeholder="{{ __($m.'override_history_search') }}">
            <label class="sr-only" for="history-type">{{ __($m.'override_history_type') }}</label>
            <select class="form-control govmem-filters__select" id="history-type" name="type">
                <option value="">{{ __($m.'override_history_all') }}</option>
                <option value="strip_roles" @selected(request('type') === 'strip_roles')>{{ __($m.'override_history_roles') }}</option>
                <option value="force_release" @selected(request('type') === 'force_release')>{{ __($m.'override_history_released') }}</option>
            </select>
            <x-gs::button type="submit" tone="light" outline>{{ __($m.'search') }}</x-gs::button>
        </form>

        @if ($logs->isEmpty())
            <x-gs::empty-state icon="fa-shield-halved" :title="__($m.'override_audit_no_entries')" />
        @else
            <ul class="govmem-feed govmem-feed--boxed">
                @foreach ($logs as $log)
                    @php
                        $by = $log->executor?->present()->fullName ?? __($m.'system');
                        $target = $log->targetUser?->present()->fullName ?? __($m.'unknown_person');
                        $details = $log->details ?? [];
                    @endphp
                    <li>
                        <i class="fas {{ $log->override_type === 'force_release' ? 'fa-door-open' : 'fa-user-slash' }} govmem-feed__icon" aria-hidden="true"></i>
                        <div>
                            <span>{{ $log->override_type === 'force_release'
                                ? __($m.(! empty($details['roles_removed']) ? 'history_released_roles' : 'history_released'), ['by' => $by, 'name' => $target, 'office' => $log->office->name ?? '—'])
                                : __($m.'history_roles', ['by' => $by, 'name' => $target]) }}</span>
                            <q class="govmem-quote">{{ $log->reason }}</q>
                            <span class="govmem-meta">
                                <time title="{{ $log->created_at?->toDayDateTimeString() }}">{{ $log->created_at?->diffForHumans() }}</time>
                                @if ($log->reference) · {{ __($m.'history_reference', ['reference' => $log->reference]) }}@endif
                            </span>
                        </div>
                    </li>
                @endforeach
            </ul>
            {{ $logs->links() }}
        @endif
    @endif
</div>
@endsection

@section('moar_scripts')
@include('govmem::partials.script')
@endsection
