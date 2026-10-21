@extends('layouts/default')
@section('title', __('govtracking::general.ui.extra_operation_unit_management'))

@section('content')
@php
    $u = 'govtracking::general.ui.';
    $roles = [
        ['designation' => 'HEAD', 'members' => $head ? collect([$head]) : collect(), 'single' => true, 'icon' => 'fa-star', 'tone' => 'head',
            'title' => 'operation_head_project_director_lead', 'help' => 'full_authority_over_the_initiative_only_one_person_may_hold_this_designation',
            'assign' => 'assign_head', 'confirm' => 'confirm_head', 'empty' => null],
        ['designation' => 'OFFICER', 'members' => $officers, 'single' => false, 'icon' => 'fa-user-tie', 'tone' => 'officer',
            'title' => 'operation_officers_planners_approvers', 'help' => 'authorized_to_define_exact_delivery_matrices_and_manage_execution_tracking_codes',
            'assign' => 'assign_officer', 'confirm' => 'confirm_officer', 'empty' => 'no_operation_officers_designated_yet'],
        ['designation' => 'SUPPORT', 'members' => $support, 'single' => false, 'icon' => 'fa-users', 'tone' => 'support',
            'title' => 'support_staff_document_handlers', 'help' => 'optional_authorized_to_upload_official_documents_and_execute_retrospective_tagging',
            'assign' => 'assign_support', 'confirm' => 'confirm_support', 'empty' => 'no_support_staff_designated'],
    ];
    $person = fn ($member) => $member->user ? trim($member->user->first_name.' '.$member->user->last_name) : __($u.'unknown_user', ['id' => $member->user_id]);
@endphp
<div class="gs-tracking-form-page gs-op-unit-page" data-tracking-staff-url="{{ route('gov.tracking.operation-unit.search-users') }}" data-tracking-initiative="{{ $initiative->id }}" data-tracking-staff-placeholder="{{ __($u.'staff_search') }}">
    <x-gs::page-header :title="__($u.'extra_operation_unit_management')" :subtitle="$initiative->title">
        <x-slot:actions>
            <x-gs::button tone="secondary" outline icon="fa-arrow-left" :href="route('gov.tracking.initiatives.manage', $initiative->id)">{{ __('govtracking::general.task.back') }}</x-gs::button>
        </x-slot:actions>
    </x-gs::page-header>

    <x-gs::alert tone="info" :title="__($u.'extra_operation_unit_assignments')">
        <p>{{ __($u.'designate_the_administrative_authorities_for_this_initiative') }} <strong>{{ __($u.'a_single_operation_head_and_at_least_one_operation_officer_are_required') }}</strong> {{ __($u.'before_this_project_can_be_activated_for_procurement_operations') }}</p>
    </x-gs::alert>

    @foreach($roles as $role)
        <x-gs::box :title="__($u.$role['title'])" :icon="'fas '.$role['icon']" class="gs-op-team-card gs-op-team-card--{{ $role['tone'] }}">
            <p class="gs-tracking-help gs-op-team-help">{{ __($u.$role['help']) }}</p>

            @forelse($role['members'] as $member)
                <div @class(['gs-op-staff-row', 'gs-op-staff-row--head' => $role['single']])>
                    <div>
                        <span class="gs-op-staff-name">{{ $person($member) }}</span>
                        <small class="gs-op-staff-meta">{{ __($u.'extra_username') }} {{ $member->user->username ?? __($u.'not_recorded') }} · {{ __($u.'extra_emp_no') }} {{ $member->user->employee_num ?? __($u.'not_recorded') }}</small>
                    </div>
                    <form action="{{ route('gov.tracking.initiatives.operation-unit.destroy', [$initiative->id, $member->id]) }}" method="POST">
                        @csrf @method('DELETE')
                        <x-gs::button type="submit" tone="danger" outline size="sm" icon="fa-xmark" :aria-label="__('govtracking::general.task.remove').' '.$person($member)" data-tracking-confirm="{{ __($u.$role['confirm']) }}">{{ __('govtracking::general.task.remove') }}</x-gs::button>
                    </form>
                </div>
            @empty
                @if($role['empty'])<p class="gs-op-team-empty">{{ __($u.$role['empty']) }}</p>@endif
            @endforelse

            @if(! $role['single'] || $role['members']->isEmpty())
                <form action="{{ route('gov.tracking.initiatives.operation-unit.store', $initiative->id) }}" method="POST" class="gs-op-assign-form">
                    @csrf
                    <input type="hidden" name="designation" value="{{ $role['designation'] }}">
                    <label class="gs-sr-only" for="staff-{{ strtolower($role['designation']) }}">{{ __($u.'search_staff_directory') }}</label>
                    <select name="user_id" id="staff-{{ strtolower($role['designation']) }}" class="form-control user-search-select" required>
                        <option value="">{{ __($u.'search_staff_directory') }}</option>
                    </select>
                    <x-gs::button type="submit" :tone="$role['single'] ? 'primary' : 'secondary'" icon="fa-user-plus">{{ __($u.$role['assign']) }}</x-gs::button>
                </form>
            @endif
        </x-gs::box>
    @endforeach
</div>
@stop
