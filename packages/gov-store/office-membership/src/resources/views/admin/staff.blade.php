@extends('layouts/default')

@php
    $m = 'office_membership::member.';
    $date = fn ($value) => $value ? $value->translatedFormat('j M Y') : '—';
    $fullName = fn ($row) => $row->user?->present()->fullName ?? __($m.'unknown_person');
    $first = fn ($row) => $row->user?->first_name ?: $fullName($row);
    $who = fn ($row) => collect([$row->user?->jobtitle, $row->user?->username])->filter()->implode(' · ');
    $waitTotal = $counts['waiting'] + $counts['leaving'] + $expected;
    $codeLive = $profile?->invitation_code && $profile->invitation_code_expires_at?->isFuture();
    $reopen = old('form');
    $tabs = collect(\GovStore\OfficeMembership\Http\Controllers\MembershipAdminController::TABS)->map(fn ($key) => [
        'key' => $key, 'label' => __($m.'staff_tab_'.$key), 'count' => $counts[$key], 'href' => route('gov.membership.admin.index', ['tab' => $key]),
    ])->all();
@endphp

@section('title', __($m.'nav_staff').' · '.$location->name)

{{-- Office staff (plan boards 7 and 8): tabs with counts, what waits on the administrator, and one dialog per decision. --}}
@section('content')
<div class="govmem">
    <x-gs::page-header :title="__($m.'nav_staff')"
        :subtitle="$location->name.' · '.trans_choice($m.'staff_people_count', $counts['staff'], ['count' => $counts['staff']])">
        <x-slot:actions>
            <button type="button" class="gs-btn gs-btn--light gs-btn--outline" data-toggle="modal" data-target="#govmem-code">
                <i class="fas fa-qrcode" aria-hidden="true"></i> {{ __($m.'staff_office_code') }}
            </button>
            <button type="button" class="gs-btn gs-btn--primary" data-toggle="modal" data-target="#govmem-add">
                <i class="fas fa-user-plus" aria-hidden="true"></i> {{ __($m.'nav_add_staff') }}
            </button>
        </x-slot:actions>
    </x-gs::page-header>

    @include('govmem::partials.errors')

    @if ($waitTotal > 0)
        <x-gs::alert tone="info" :title="trans_choice($m.'staff_wait_title', $waitTotal, ['count' => $waitTotal])">
            {{ collect([
                $counts['waiting'] ? trans_choice($m.'staff_wait_join', $counts['waiting'], ['count' => $counts['waiting']]) : null,
                $counts['leaving'] ? trans_choice($m.'staff_wait_leave', $counts['leaving'], ['count' => $counts['leaving']]) : null,
                $expected ? trans_choice($m.'staff_wait_arrive', $expected, ['count' => $expected]) : null,
            ])->filter()->implode(', ') }}.
            <a href="{{ route('gov.membership.admin.index', ['tab' => $counts['waiting'] ? 'waiting' : ($counts['leaving'] ? 'leaving' : 'arriving')]) }}">{{ __($m.'staff_wait_review') }}</a>
        </x-gs::alert>
    @endif

    <x-gs::status-tabs :tabs="$tabs" :active="$tab" :label="__($m.'staff_tabs')" />

    <form method="GET" action="{{ route('gov.membership.admin.index') }}" class="govmem-filters" role="search">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <label class="sr-only" for="staff-search">{{ __($m.'staff_search') }}</label>
        <input type="search" class="form-control govmem-filters__search" id="staff-search" name="q" value="{{ $term }}" placeholder="{{ __($m.'staff_search') }}">
        <x-gs::button type="submit" tone="light" outline>{{ __($m.'search') }}</x-gs::button>
        @if ($term !== '')<a href="{{ route('gov.membership.admin.index', ['tab' => $tab]) }}">{{ __($m.'clear') }}</a>@endif
    </form>

    @if ($tab === 'arriving')
        <p class="govmem-muted govmem-flush">{{ __($m.'staff_arriving_hint') }}</p>
    @endif

    @if ($rows->isEmpty())
        <x-gs::empty-state icon="fa-users" :title="__($m.'staff_empty_'.$tab)">
            @if ($term !== ''){{ __($m.'staff_empty_search') }}@endif
        </x-gs::empty-state>
    @else
        <x-gs::table :caption="__($m.'staff_caption_'.$tab, ['office' => $location->name])">
            <thead>
                <tr>
                    <th scope="col">{{ __($m.'col_name') }}</th>
                        @if ($tab === 'waiting')
                            <th scope="col">{{ __($m.'col_home_office') }}</th><th scope="col">{{ __($m.'col_asked') }}</th>
                        @elseif ($tab === 'leaving')
                            <th scope="col">{{ __($m.'col_going') }}</th><th scope="col">{{ __($m.'col_ready') }}</th>
                        @elseif ($tab === 'arriving')
                            <th scope="col">{{ __($m.'col_coming_from') }}</th><th scope="col">{{ __($m.'col_signed_off') }}</th>
                        @elseif ($tab === 'past')
                            <th scope="col">{{ __($m.'col_left') }}</th><th scope="col">{{ __($m.'col_how') }}</th>
                        @else
                            <th scope="col">{{ __($m.'col_works_as') }}</th><th scope="col">{{ __($m.'col_role') }}</th><th scope="col">{{ __($m.'col_since') }}</th>
                    @endif
                    @if ($tab !== 'past')<th scope="col"><span class="sr-only">{{ __($m.'col_actions') }}</span></th>@endif
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    @php $self = (int) $row->user_id === (int) auth()->id(); @endphp
                    <tr>
                        <td>
                            <strong>{{ $fullName($row) }}</strong>@if ($self) <span class="govmem-muted">{{ __($m.'you') }}</span>@endif
                            <span class="govmem-meta">{{ $who($row) }}</span>
                        </td>
                            @if ($tab === 'waiting')
                                <td>{{ $homes[(int) $row->user_id] ?? '—' }}</td>
                                <td>{{ $row->updated_at?->diffForHumans() }}</td>
                                <td>
                                    <div class="govmem-toolbar">
                                        <form method="POST" action="{{ route('gov.membership.admin.approve', $row->id) }}" class="govmem-inline-form">
                                            @csrf
                                            <x-gs::button type="submit" size="sm" icon="fa-check">{{ __($m.'staff_approve', ['name' => $first($row)]) }}</x-gs::button>
                                        </form>
                                        <details class="govmem-decline">
                                            <summary class="gs-btn gs-btn--light gs-btn--outline gs-btn--sm">{{ __($m.'staff_decline_open') }}</summary>
                                            <form method="POST" action="{{ route('gov.membership.admin.reject', $row->id) }}" class="govmem-stack">
                                                @csrf
                                                <x-gs::form-row for="decline-{{ $row->id }}" :label="__($m.'staff_decline_reason')" :help="__($m.'staff_decline_help', ['name' => $first($row)])">
                                                    <textarea class="form-control" id="decline-{{ $row->id }}" name="reason" rows="2" maxlength="255" aria-describedby="decline-{{ $row->id }}-help"></textarea>
                                                </x-gs::form-row>
                                                <div><x-gs::button type="submit" size="sm" tone="light" outline>{{ __($m.'staff_decline', ['name' => $first($row)]) }}</x-gs::button></div>
                                            </form>
                                        </details>
                                    </div>
                                </td>
                            @elseif ($tab === 'leaving')
                                @php
                                    $checks = $readiness[$row->id] ?? [];
                                    $clear = $engine->isCleared($checks);
                                    $open = collect($checks)->reject(fn ($r) => $r->isPassed)->map(fn ($r) => $r->summary());
                                @endphp
                                <td>{{ $row->is_home_office ? ($row->destination?->name ?? __($m.'staff_going_unknown')) : __($m.'additional_office') }}</td>
                                <td>
                                    @if ($clear)
                                        <span class="govmem-ok"><i class="fas fa-circle-check" aria-hidden="true"></i> {{ __($m.'staff_clear') }}</span>
                                    @else
                                        <span><i class="fas fa-list-check" aria-hidden="true"></i> {{ __($m.'staff_not_clear', ['items' => $open->implode('; ')]) }}</span>
                                    @endif
                                </td>
                                <td>
                                    <button type="button" class="gs-btn gs-btn--primary gs-btn--sm" data-toggle="modal" data-target="#govmem-signoff-{{ $row->id }}">{{ __($m.'staff_signoff_open') }}</button>
                                </td>
                            @elseif ($tab === 'arriving')
                                <td>
                                    {{ $row->location->name ?? '—' }}
                                    @if ((int) $row->destination_location_id === (int) $location->id)
                                        <x-gs::badge tone="success" outline>{{ __($m.'staff_said_coming') }}</x-gs::badge>
                                    @endif
                                </td>
                                <td>{{ collect([$date($row->approved_at), $row->approver?->present()->fullName])->filter()->implode(' · ') }}</td>
                                <td>
                                    <button type="button" class="gs-btn gs-btn--primary gs-btn--sm" data-toggle="modal" data-target="#govmem-welcome-{{ $row->id }}">{{ __($m.'staff_welcome_open', ['name' => $first($row)]) }}</button>
                                </td>
                            @elseif ($tab === 'past')
                                <td>{{ $date($row->updated_at) }}</td>
                                <td>{{ in_array((int) $row->user_id, $overridden, true) ? __($m.'past_how_override')
                                    : __($m.'past_how_'.($row->status === 'released' ? 'released' : $row->status)) }}</td>
                            @else
                                <td>
                                    @if ($row->is_home_office)
                                        {{ __($m.'home_office') }}
                                    @else
                                        {{ $row->valid_until ? __($m.'staff_additional_until', ['date' => $date($row->valid_until)]) : __($m.'additional_office') }}
                                        @if (isset($homes[(int) $row->user_id]))<span class="govmem-meta">{{ __($m.'staff_home_is', ['office' => $homes[(int) $row->user_id]]) }}</span>@endif
                                    @endif
                                    @if ($row->status === 'suspended') <x-gs::status-badge :status="$row->statusKey()" />@endif
                                </td>
                                <td>{{ implode(', ', $roles[(int) $row->user_id] ?? []) ?: '—' }}</td>
                                <td>{{ ($row->approved_at ?? $row->created_at)?->translatedFormat('M Y') }}</td>
                                <td>
                                    @if (! $row->is_home_office && ! $self && $row->status === 'active')
                                        <button type="button" class="gs-btn gs-btn--light gs-btn--outline gs-btn--sm" data-govmem-dialog="#govmem-end"
                                            data-action="{{ route('gov.membership.admin.end', $row->id) }}" data-name="{{ $fullName($row) }}">{{ __($m.'staff_end_open') }}</button>
                                    @endif
                                </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </x-gs::table>
        {{ $rows->links() }}
    @endif
</div>

{{-- ---------- Add staff: three ways, explained ---------- --}}
<div class="modal fade" id="govmem-add" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="govmem-add-title" @if ($reopen === 'add') data-govmem-reopen @endif>
    <div class="modal-dialog" role="document">
        <form class="modal-content" method="POST" action="{{ route('gov.membership.admin.add-employee') }}">
            @csrf
            <input type="hidden" name="form" value="add">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ __($m.'close') }}"><span aria-hidden="true">&times;</span></button>
                <h2 class="modal-title" id="govmem-add-title">{{ __($m.'add_title', ['office' => $location->name]) }}</h2>
            </div>
            <div class="modal-body govmem-stack">
                <fieldset>
                    <legend class="sr-only">{{ __($m.'add_how') }}</legend>
                    <div class="gs-choices">
                        <x-gs::choice-card name="add_way" value="personal" icon="fa-key" :checked="$reopen === 'add'" id="add-way-personal"
                            :label="__($m.'add_way_personal')" :description="__($m.'add_way_personal_desc')" />
                        <x-gs::choice-card name="add_way" value="code" icon="fa-qrcode" id="add-way-code"
                            :label="__($m.'add_way_code')" :description="__($m.'add_way_code_desc')" />
                        <x-gs::choice-card name="add_way" value="transfer" icon="fa-right-left" id="add-way-transfer"
                            :label="__($m.'add_way_transfer')" :description="__($m.'add_way_transfer_desc')" />
                    </div>
                </fieldset>
                <div data-govmem-when="add_way=personal" class="govmem-stack" @if($reopen !== 'add') hidden @endif>
                    <x-gs::form-row for="username" :label="__($m.'field_username')" required>
                        <input type="text" class="form-control" id="username" name="username" value="{{ old('username') }}" required maxlength="191" autocomplete="off">
                    </x-gs::form-row>
                    <x-gs::form-row for="verification_code" :label="__($m.'field_personal_code')" required :help="__($m.'add_code_help')">
                        <input type="text" class="form-control govmem-code-input" id="verification_code" name="verification_code" value="{{ old('verification_code') }}"
                            required maxlength="20" autocomplete="off" autocapitalize="characters" spellcheck="false" aria-describedby="verification_code-help">
                    </x-gs::form-row>
                    <p class="govmem-muted govmem-small govmem-flush">{{ __($m.'add_ministry_only', ['ministry' => $location->company->name ?? '']) }}</p>
                </div>
                <div data-govmem-when="add_way=code" hidden>
                    <button type="button" class="gs-btn gs-btn--light gs-btn--outline" data-dismiss="modal" data-toggle="modal" data-target="#govmem-code">{{ __($m.'add_show_code') }}</button>
                </div>
                <div data-govmem-when="add_way=transfer" hidden>
                    <x-gs::button :href="route('gov.membership.admin.index', ['tab' => 'arriving'])" tone="light" outline>{{ __($m.'add_open_arriving') }}</x-gs::button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="gs-btn gs-btn--light gs-btn--outline" data-dismiss="modal">{{ __($m.'cancel') }}</button>
                <span data-govmem-when="add_way=personal" @if($reopen !== 'add') hidden @endif><button type="submit" class="gs-btn gs-btn--primary">{{ __($m.'add_submit') }}</button></span>
            </div>
        </form>
    </div>
</div>

{{-- ---------- Office code ---------- --}}
<div class="modal fade" id="govmem-code" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="govmem-code-title" @if (request('dialog') === 'code') data-govmem-reopen @endif>
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ __($m.'close') }}"><span aria-hidden="true">&times;</span></button>
                <h2 class="modal-title" id="govmem-code-title">{{ __($m.'code_office_title', ['office' => $location->name]) }}</h2>
            </div>
            <div class="modal-body govmem-stack">
                @if ($codeLive)
                    <p class="govmem-code"><span class="govmem-mono">{{ trim(chunk_split($profile->invitation_code, 4, ' ')) }}</span></p>
                    <x-gs::key-value :columns="1" :items="[
                        ['label' => __($m.'code_works_until'), 'value' => $profile->invitation_code_expires_at->translatedFormat('j M Y')],
                        ['label' => __($m.'code_requests'), 'value' => trans_choice($m.'code_requests_value', $codeUses, ['count' => $codeUses])],
                    ]" />
                    <p class="govmem-flush">{{ __($m.'code_office_hint') }}</p>
                @else
                    <p class="govmem-flush">{{ __($m.'code_office_none') }}</p>
                @endif
            </div>
            <div class="modal-footer">
                @if ($codeLive)
                    <button type="button" class="gs-btn gs-btn--light gs-btn--outline" data-govmem-copy="{{ $profile->invitation_code }}" data-copied="{{ __($m.'copied') }}">{{ __($m.'code_copy') }}</button>
                @endif
                <form method="POST" action="{{ route('gov.membership.admin.generate-invite-code') }}" class="govmem-inline-form">
                    @csrf
                    <button type="submit" class="gs-btn gs-btn--primary">{{ __($m.($codeLive ? 'code_office_new' : 'code_office_make')) }}</button>
                </form>
                @if ($codeLive)<p class="govmem-muted govmem-small govmem-flush govmem-full">{{ __($m.'code_office_new_warning') }}</p>@endif
            </div>
        </div>
    </div>
</div>

{{-- ---------- End additional access (shared; the button fills in who) ---------- --}}
<div class="modal fade" id="govmem-end" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="govmem-end-title">
    <div class="modal-dialog" role="document">
        <form class="modal-content" method="POST" action="">
            @csrf
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ __($m.'close') }}"><span aria-hidden="true">&times;</span></button>
                <h2 class="modal-title" id="govmem-end-title">{{ __($m.'end_title') }} <span data-govmem-text="name"></span></h2>
            </div>
            <div class="modal-body">
                <ul class="govmem-effects">
                    <li class="govmem-effect--stop"><i class="fas fa-door-closed" aria-hidden="true"></i><span>{{ __($m.'end_effect_stop', ['office' => $location->name]) }}</span></li>
                    <li class="govmem-effect--ok"><i class="fas fa-house" aria-hidden="true"></i><span>{{ __($m.'end_effect_home') }}</span></li>
                    <li class="govmem-effect--info"><i class="fas fa-list-check" aria-hidden="true"></i><span>{{ __($m.'end_effect_checked') }}</span></li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="gs-btn gs-btn--light gs-btn--outline" data-dismiss="modal">{{ __($m.'cancel') }}</button>
                <button type="submit" class="gs-btn gs-btn--danger">{{ __($m.'end_confirm') }}</button>
            </div>
        </form>
    </div>
</div>

{{-- ---------- Sign off a leaver (checked again on submit) ---------- --}}
@if ($tab === 'leaving')
    @foreach ($rows as $row)
        @php
            $checks = $readiness[$row->id] ?? [];
            $clear = $engine->isCleared($checks);
        @endphp
        <div class="modal fade" id="govmem-signoff-{{ $row->id }}" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="govmem-signoff-{{ $row->id }}-title">
            <div class="modal-dialog" role="document">
                <form class="modal-content" method="POST" action="{{ route('gov.membership.admin.release', $row->id) }}">
                    @csrf
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="{{ __($m.'close') }}"><span aria-hidden="true">&times;</span></button>
                        <h2 class="modal-title" id="govmem-signoff-{{ $row->id }}-title">{{ __($m.'signoff_title', ['name' => $fullName($row)]) }}</h2>
                    </div>
                    <div class="modal-body govmem-stack">
                        <x-gs::readiness readonly :title="$clear ? __($m.'signoff_clear') : __($m.'signoff_not_clear')" :caption="__($m.'signoff_checked_now')"
                            :items="collect($checks)->map(fn ($r, $name) => ['label' => $name, 'done' => $r->isPassed,
                                'value' => $r->isPassed ? null : $r->summary()])->values()->all()" />
                        <ul class="govmem-effects">
                            <li class="govmem-effect--stop"><i class="fas fa-door-open" aria-hidden="true"></i><span>{{ __($m.'signoff_effect_stop', ['name' => $first($row), 'office' => $location->name]) }}</span></li>
                            @if ($row->is_home_office)
                                <li class="govmem-effect--info"><i class="fas fa-right-left" aria-hidden="true"></i><span>{{ $row->destination
                                    ? __($m.'signoff_effect_next_named', ['office' => $row->destination->name])
                                    : __($m.'signoff_effect_next') }}</span></li>
                            @endif
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="gs-btn gs-btn--light gs-btn--outline" data-dismiss="modal">{{ __($m.'not_yet') }}</button>
                        <button type="submit" class="gs-btn gs-btn--primary" @disabled(! $clear)>{{ __($m.'signoff_confirm', ['name' => $first($row)]) }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endif

{{-- ---------- Welcome a transferred employee (claim) ---------- --}}
@if ($tab === 'arriving')
    @foreach ($rows as $row)
        <div class="modal fade" id="govmem-welcome-{{ $row->id }}" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="govmem-welcome-{{ $row->id }}-title"
            @if ($reopen === 'welcome-'.$row->id) data-govmem-reopen @endif>
            <div class="modal-dialog" role="document">
                <form class="modal-content" method="POST" action="{{ route('gov.membership.claim') }}">
                    @csrf
                    <input type="hidden" name="form" value="welcome-{{ $row->id }}">
                    <input type="hidden" name="user_id" value="{{ $row->user_id }}">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="{{ __($m.'close') }}"><span aria-hidden="true">&times;</span></button>
                        <h2 class="modal-title" id="govmem-welcome-{{ $row->id }}-title">{{ __($m.'welcome_title', ['name' => $fullName($row), 'office' => $location->name]) }}</h2>
                    </div>
                    <div class="modal-body govmem-stack">
                        <x-gs::key-value :columns="2" :items="[
                            ['label' => __($m.'col_coming_from'), 'value' => $row->location->name ?? '—'],
                            ['label' => __($m.'col_signed_off'), 'value' => collect([$date($row->approved_at), $row->approver?->present()->fullName])->filter()->implode(' · ')],
                            ['label' => __($m.'welcome_job'), 'value' => $row->user?->jobtitle ?: '—'],
                            ['label' => __($m.'field_username'), 'value' => $row->user?->username ?: '—'],
                        ]" />
                        <ul class="govmem-effects">
                            <li class="govmem-effect--ok"><i class="fas fa-house" aria-hidden="true"></i><span>{{ __($m.'welcome_effect_home', ['name' => $first($row), 'office' => $location->name]) }}</span></li>
                            <li class="govmem-effect--info"><i class="fas fa-box" aria-hidden="true"></i><span>{{ __($m.'welcome_effect_register', ['office' => $location->name]) }}</span></li>
                            <li class="govmem-effect--info"><i class="fas fa-list-check" aria-hidden="true"></i><span>{{ __($m.'welcome_effect_checked', ['office' => $row->location->name ?? '']) }}</span></li>
                            <li class="govmem-effect--stop"><i class="fas fa-lock" aria-hidden="true"></i><span>{{ __($m.'welcome_effect_final') }}</span></li>
                        </ul>
                        <label class="govmem-ack" for="welcome-ack-{{ $row->id }}">
                            <input type="checkbox" id="welcome-ack-{{ $row->id }}" name="confirmation" value="1" required>
                            <span>{{ __($m.'welcome_ack', ['name' => $first($row), 'office' => $location->name]) }}</span>
                        </label>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="gs-btn gs-btn--light gs-btn--outline" data-dismiss="modal">{{ __($m.'cancel') }}</button>
                        <button type="submit" class="gs-btn gs-btn--primary">{{ __($m.'welcome_confirm', ['name' => $first($row), 'office' => $location->name]) }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endif
@endsection

@section('moar_scripts')
@include('govmem::partials.script')
@endsection
