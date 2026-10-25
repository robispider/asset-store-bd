@extends('layouts/default')

@section('title', __('office_membership::member.user_page_title'))

{{--
    My offices (plan board 3): what waits for the person first, then each office with its status and next
    step, their roles, history as sentences and the personal code. Expects the data from MembershipController::index.
--}}
@php
    $m = 'office_membership::member.';
    $date = fn ($value) => $value ? $value->translatedFormat('j M Y') : '';
@endphp

@section('content')
<div class="govmem">
    <x-gs::page-header :title="__($m.'user_page_title')"
        :subtitle="collect([$user->present()->fullName, $user->jobtitle, $user->username])->filter()->implode(' · ')">
        <x-slot:actions>
            <x-gs::button :href="route('gov.membership.join.form')" icon="fa-plus">{{ __($m.'join_title') }}</x-gs::button>
        </x-slot:actions>
    </x-gs::page-header>

    @include('govmem::partials.errors')

    @if ($incoming->isNotEmpty())
        <x-gs::box :title="__($m.'waiting_for_you')" tone="primary" id="waiting-for-you">
            <x-slot:tools><x-gs::badge tone="primary" pill>{{ $incoming->count() }}</x-gs::badge></x-slot:tools>
            <div class="govmem-stack">
                @foreach ($incoming as $request)
                    @php
                        $from = $request->outgoingUser?->present()->fullName ?? __($m.'unknown_person');
                        $officeName = $request->location->name ?? __($m.'unknown_office');
                    @endphp
                    <article class="govmem-request" aria-labelledby="handover-{{ $request->id }}">
                        <x-gs::avatar :name="$from" />
                        <div class="govmem-request__body govmem-stack">
                            <p class="govmem-flush" id="handover-{{ $request->id }}">
                                {{ __($m.'handover_incoming', ['name' => $from, 'role' => __($m.'role_'.$request->role_type), 'office' => $officeName]) }}
                            </p>
                            <p class="govmem-meta govmem-flush">{{ __($m.'sent_ago', ['time' => $request->created_at?->diffForHumans()]) }}</p>
                            @if (filled($request->note))
                                <p class="govmem-flush"><strong>{{ __($m.'handover_their_note', ['name' => $from]) }}</strong> <q class="govmem-quote">{{ $request->note }}</q></p>
                            @endif
                            <div>
                                <strong>{{ __($m.'handover_if_accept') }}</strong>
                                <ul class="govmem-effects">
                                    <li><i class="fas fa-briefcase" aria-hidden="true"></i><span>{{ __($m.'role_desc_'.$request->role_type) }}</span></li>
                                    <li><i class="fas fa-id-badge" aria-hidden="true"></i><span>{{ __($m.'handover_if_named', ['office' => $officeName]) }}</span></li>
                                    <li><i class="fas fa-right-left" aria-hidden="true"></i><span>{{ __($m.'handover_if_again') }}</span></li>
                                </ul>
                            </div>
                            <div class="govmem-toolbar">
                                <form method="POST" action="{{ route('gov.membership.handshake.accept', $request->id) }}" class="govmem-inline-form">
                                    @csrf
                                    <x-gs::button type="submit" icon="fa-check">{{ __($m.'handover_accept') }}</x-gs::button>
                                </form>
                                <details class="govmem-decline">
                                    <summary class="gs-btn gs-btn--light gs-btn--outline">{{ __($m.'handover_decline_open') }}</summary>
                                    <form method="POST" action="{{ route('gov.membership.handshake.reject', $request->id) }}" class="govmem-stack">
                                        @csrf
                                        <x-gs::form-row for="reason-{{ $request->id }}" :label="__($m.'handover_decline_reason', ['name' => $request->outgoingUser?->first_name ?? ''])"
                                            :help="__($m.'handover_decline_help', ['name' => $request->outgoingUser?->first_name ?? ''])">
                                            <textarea class="form-control" id="reason-{{ $request->id }}" name="reason" rows="2" maxlength="500" aria-describedby="reason-{{ $request->id }}-help"></textarea>
                                        </x-gs::form-row>
                                        <div><x-gs::button type="submit" tone="light" outline>{{ __($m.'handover_decline') }}</x-gs::button></div>
                                    </form>
                                </details>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </x-gs::box>
    @endif

    <div class="govmem-layout">
        <div class="govmem-layout__main govmem-stack">
            <section aria-labelledby="your-offices" class="govmem-stack">
                <h2 class="govmem-heading" id="your-offices">{{ __($m.'offices_title') }} <span class="govmem-muted">{{ $current->count() }}</span></h2>

                @forelse ($current as $membership)
                    @php
                        $office = (int) $membership->location_id;
                        $officeName = $membership->location->name ?? __($m.'unknown_office');
                        $admin = $admins[$office] ?? null;
                        $adminName = $admin?->present()->fullName ?? __($m.'the_office_admin');
                        $officeRoles = $roles[$office] ?? [];
                        $checks = $readiness[$membership->id] ?? [];
                        $open = collect($checks)->reject(fn ($r) => $r->isPassed);
                        $live = $membership->isLive();
                    @endphp
                    <article class="gs-card govmem-office" id="office-{{ $membership->id }}">
                        <div class="gs-card__body govmem-stack">
                            <div class="govmem-office__head">
                                <div>
                                    <h3 class="govmem-office__name">{{ $officeName }}</h3>
                                    <span class="govmem-meta">{{ $membership->location->company->name ?? __($m.'standalone') }}</span>
                                </div>
                                <div class="govmem-toolbar">
                                    <x-gs::status-badge :status="$membership->statusKey()"
                                        :label="$membership->status === 'active' && $membership->valid_until ? __($m.'active_until', ['date' => $date($membership->valid_until)]) : null" />
                                    <x-gs::badge :tone="$membership->is_home_office ? 'primary' : 'secondary'" outline>
                                        {{ __($m.($membership->is_home_office ? 'home_office' : 'additional_office')) }}
                                    </x-gs::badge>
                                    @if ($live && $office === $workingOffice)
                                        <x-gs::badge tone="success" outline>{{ __($m.'working_here_now') }}</x-gs::badge>
                                    @endif
                                </div>
                            </div>

                            <p class="govmem-flush">
                                    @if ($membership->status === 'pending')
                                        {{ __($m.'office_pending', ['time' => $membership->updated_at?->diffForHumans(), 'admin' => $adminName]) }}
                                    @elseif ($membership->status === 'release_requested')
                                        {{ __($m.'office_leaving', ['time' => $membership->updated_at?->diffForHumans(), 'admin' => $adminName]) }}
                                    @elseif ($membership->status === 'released')
                                        {{ $membership->destination
                                            ? __($m.'office_ready_to', ['office' => $membership->destination->name])
                                            : __($m.'office_ready') }}
                                    @elseif ($membership->status === 'suspended')
                                        {{ __($m.'office_paused', ['admin' => $adminName]) }}
                                    @else
                                        {{ $membership->approver
                                            ? __($m.'office_since_by', ['date' => $date($membership->approved_at ?? $membership->created_at), 'name' => $membership->approver->present()->fullName])
                                            : __($m.'office_since', ['date' => $date($membership->approved_at ?? $membership->created_at)]) }}
                                        @if ($membership->status === 'active' && ! $live)
                                            {{ __($m.'office_expired') }}
                                        @elseif ($membership->valid_until)
                                            {{ __($m.'office_ends_on_own', ['date' => $date($membership->valid_until)]) }}
                                        @endif
                                @endif
                            </p>

                            @if ($officeRoles)
                                <p class="govmem-flush">{{ __($m.'office_your_roles', ['roles' => collect($officeRoles)->map(fn ($r) => __($m.'role_'.$r))->implode(', ')]) }}</p>
                            @endif

                            @if ($membership->status === 'active' && $live)
                                <p class="govmem-flush govmem-small">
                                    @if ($open->isEmpty())
                                        <i class="fas fa-circle-check govmem-ok" aria-hidden="true"></i> {{ __($m.'office_free_to_leave') }}
                                    @else
                                        <i class="fas fa-list-check" aria-hidden="true"></i>
                                        {{ __($m.'office_before_leave', ['items' => $open->map(fn ($r) => $r->summary())->implode('; ')]) }}
                                        <a href="{{ route('gov.membership.leave', $membership->id) }}">{{ __($m.'office_see_needed') }}</a>
                                    @endif
                                </p>
                            @endif
                        </div>
                        <div class="gs-card__actions govmem-toolbar">
                            @if ($membership->status === 'active')
                                @if ($live && $office !== $workingOffice)
                                    <form method="POST" action="{{ route('gov.membership.switch') }}" class="govmem-inline-form">
                                        @csrf
                                        <input type="hidden" name="membership_id" value="{{ $membership->id }}">
                                        <x-gs::button type="submit" size="sm" icon="fa-location-dot">{{ __($m.'work_here') }}</x-gs::button>
                                    </form>
                                @endif
                                <x-gs::button :href="route('gov.membership.leave', $membership->id)" tone="light" outline size="sm">{{ __($m.'leave_button') }}</x-gs::button>
                            @elseif ($membership->status === 'pending')
                                <form method="POST" action="{{ route('gov.membership.cancel-join', $membership->id) }}" class="govmem-inline-form">
                                    @csrf
                                    <x-gs::button type="submit" tone="light" outline size="sm">{{ __($m.'join_cancel') }}</x-gs::button>
                                </form>
                            @elseif (in_array($membership->status, ['release_requested', 'released'], true))
                                <x-gs::button :href="route('gov.membership.leave', $membership->id)" size="sm" icon="fa-route">{{ __($m.'transfer_see') }}</x-gs::button>
                                @if ($membership->status === 'release_requested')
                                    <form method="POST" action="{{ route('gov.membership.withdraw-release', $membership->id) }}" class="govmem-inline-form">
                                        @csrf
                                        <x-gs::button type="submit" tone="light" outline size="sm">{{ __($m.'leave_withdraw') }}</x-gs::button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </article>
                @empty
                    <x-gs::empty-state icon="fa-building" :title="__($m.'offices_none_title')">
                        {{ __($m.'offices_none_body') }}
                        <x-slot:actions><x-gs::button :href="route('gov.membership.join.form')">{{ __($m.'join_title') }}</x-gs::button></x-slot:actions>
                    </x-gs::empty-state>
                @endforelse

                @if ($past->isNotEmpty())
                    <details class="govmem-past">
                        <summary>{{ __($m.'past_offices', ['count' => $past->count()]) }}</summary>
                        <ul class="govmem-sentences">
                            @foreach ($past as $membership)
                                @php $pastName = $membership->location->name ?? __($m.'unknown_office'); @endphp
                                <li>
                                    <x-gs::status-badge :status="$membership->statusKey()" />
                                    {{ __($m.'past_'.(in_array($membership->status, ['released', 'rejected', 'inactive', 'cancelled'], true) ? $membership->status : 'inactive'),
                                        ['office' => $pastName, 'date' => $date($membership->updated_at)]) }}
                                    @if ($membership->status === 'rejected' && $membership->approval_note)
                                        <q class="govmem-quote">{{ $membership->approval_note }}</q>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </section>

            <x-gs::box :title="__($m.'notices_title')" id="what-changed">
                @if ($notices->isEmpty())
                    <p class="govmem-muted govmem-flush">{{ __($m.'no_notices') }}</p>
                @else
                    <ul class="govmem-feed">
                        @foreach ($notices as $notice)
                            <li>
                                <i class="fas {{ $notice['icon'] }} govmem-feed__icon" aria-hidden="true"></i>
                                <div>
                                    <span>{{ $notice['text'] }}</span>
                                    @if ($notice['quote'])<q class="govmem-quote">{{ $notice['quote'] }}</q>@endif
                                    <time class="govmem-meta" title="{{ $notice['when'] }}">{{ $notice['at'] }}</time>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-gs::box>
        </div>

        <aside class="govmem-layout__aside govmem-stack">
            <x-gs::box :title="__($m.'roles_title')" id="your-roles">
                @php $anyRole = false; @endphp
                @foreach ($current->filter(fn ($mem) => isset($roles[(int) $mem->location_id]) || isset($cover[(int) $mem->location_id])) as $membership)
                    @php $anyRole = true; @endphp
                    @include('govmem::partials.role-slots', ['office' => (int) $membership->location_id, 'officeName' => $membership->location->name ?? '',
                        'officeRoles' => $roles[(int) $membership->location_id] ?? [], 'officeCover' => $cover[(int) $membership->location_id] ?? []])
                @endforeach
                @if ($anyRole)
                    <p class="govmem-muted govmem-small govmem-flush">{{ __($m.'roles_hint') }}</p>
                @else
                    <p class="govmem-muted govmem-flush">{{ __($m.'roles_none') }}</p>
                @endif
            </x-gs::box>

            @include('govmem::partials.personal-code')
        </aside>
    </div>
</div>

@include('govmem::partials.handover-dialog')
@endsection

@section('moar_scripts')
@include('govmem::partials.script')
@endsection
