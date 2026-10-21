@extends('layouts/default')
@section('title', __('govtracking::general.ui.extra_task_component', ['code' => $trackingCode->tracking_code]))

@section('content')
@php
    $u = 'govtracking::general.ui.';
    $t = 'govtracking::general.task.';
    $digits = fn ($value) => \GovStore\Tracking\Services\TrackingMonitorMetrics::digits($value);
    $date = fn ($value) => $value ? $digits(\Illuminate\Support\Carbon::parse($value)->locale(app()->getLocale())->translatedFormat('d F Y')) : null;
    // The sheet is issued by the programme's owning organisation, never a fixed department name.
    $issuer = $initiative->ownerCompany?->name ?? __($u.'extra_unknown');
    $geoScope = $trackingCode->scopes->where('dimension', 'GEOGRAPHY')->first();
    $partScope = $trackingCode->scopes->where('dimension', 'PARTICIPANTS')->first();
    $geoArea = $geoScope && $geoScope->target_type === 'GeoArea' && class_exists('GovStore\GeoAreas\Models\GeoArea')
        ? \GovStore\GeoAreas\Models\GeoArea::find($geoScope->target_id) : null;
    $areaName = $geoArea ? ((str_starts_with(app()->getLocale(), 'bn') ? ($geoArea->bn_name ?? null) : null) ?: $geoArea->en_name) : null;
    $contacts = [['label' => __($t.'responsible'), 'unit' => $operationHead, 'missing' => __($t.'head_missing')],
        ['label' => __($u.'officer_list'), 'unit' => $operationOfficer, 'missing' => __($t.'officer_missing')]];
@endphp
<div class="gs-tracking-print-toolbar hidden-print">
    <x-gs::button size="lg" icon="fa-print" data-tracking-print>{{ __($t.'task_sheet') }}</x-gs::button>
</div>

<x-gs::document :title="$trackingCode->task_title" :doc-no="$trackingCode->tracking_code" :date="$date($trackingCode->created_at)">
    <x-slot:office>
        <div class="gs-tracking-doc-ministry">{{ $issuer }}</div>
        <div class="gs-tracking-doc-initiative">[ {{ $initiative->title }} ]</div>
    </x-slot:office>

    <div class="row">
        <!-- LEFT COLUMN: Task Info, Scope, & Allocation Schedules -->
        <div class="col-md-8">

            <!-- Task / Component Information -->
            <x-gs::box :title="__($u.'extra_task_component_information')" icon="fas fa-circle-info">
                <div class="gs-tracking-doc-field gs-tracking-doc-field--bordered">
                    <span class="gs-tracking-doc-label">{{ __($t.'task_title') }}</span>
                    <span class="gs-tracking-doc-value--lg">{{ $trackingCode->task_title }}</span>
                </div>

                <div class="row">
                    <!-- Left Sub-Column (Basic Identifiers) -->
                    <div class="col-sm-6">
                        <div class="gs-tracking-doc-field">
                            <span class="gs-tracking-doc-label">{{ __($t.'official_code') }}</span>
                            <b class="gs-tracking-doc-value gs-tracking-code">{{ $trackingCode->tracking_code }}</b>
                        </div>
                        <div class="gs-tracking-doc-field">
                            <span class="gs-tracking-doc-label">{{ __($u.'current_status') }}</span>
                            <x-gs::badge :tone="$trackingCode->status === 'ACTIVE' ? 'success' : 'secondary'">{{ \Illuminate\Support\Facades\Lang::has('govtracking::general.manage.status.'.$trackingCode->status) ? __('govtracking::general.manage.status.'.$trackingCode->status) : $trackingCode->status }}</x-gs::badge>
                        </div>
                        <div>
                            <span class="gs-tracking-doc-label">{{ __($u.'created_date') }}</span>
                            <span class="gs-tracking-doc-value">{{ $date($trackingCode->created_at) }}</span>
                        </div>
                    </div>

                    <!-- Right Sub-Column (Accounting & Budget Parameters) -->
                    <div class="col-sm-6 gs-tracking-doc-col-divider">
                        <div class="gs-tracking-doc-field">
                            <span class="gs-tracking-doc-label">{{ __($t.'fiscal_period') }}</span>
                            <b class="gs-tracking-doc-value">{{ $digits(__('govtracking::general.manage.fy', ['year' => $trackingCode->fiscal_year])) }}</b>
                        </div>
                        <div class="gs-tracking-doc-field">
                            <span class="gs-tracking-doc-label">{{ __($t.'budget_sector') }}</span>
                            <span class="gs-tracking-doc-value">{{ $initiative->primary_funding }} {{ __($u.'extra_sector_budget') }}</span>
                        </div>
                        <div>
                            <span class="gs-tracking-doc-label">{{ __($u.'funding_classification_segment') }}</span>
                            <b class="gs-tracking-doc-value">{{ $trackingCode->fundingType->name ?? __($u.'not_recorded') }}</b>
                        </div>
                    </div>
                </div>
            </x-gs::box>

            <!-- Authorized Scope (Two-Sub-Column Layout) -->
            <x-gs::box :title="__($u.'extra_authorized_scope')" icon="fas fa-location-dot">
                <div class="row">
                    <div class="col-sm-6">
                        <span class="gs-tracking-doc-label">{{ __($t.'geography') }}</span>
                        @if($geoScope && $geoScope->target_type === 'GeoArea')
                            <b class="gs-tracking-doc-value">{{ $areaName ?? __('govtracking::general.monitor.no_area') }}</b>
                            @if($geoArea?->geo_type)<x-gs::badge tone="warning">{{ $geoArea->geo_type }}</x-gs::badge>@endif
                        @else
                            <b class="gs-tracking-doc-value">{{ __($t.'nationwide') }}</b>
                            <x-gs::badge tone="secondary">{{ __($t.'bangladesh') }}</x-gs::badge>
                        @endif
                    </div>

                    <div class="col-sm-6 gs-tracking-doc-col-divider">
                        <span class="gs-tracking-doc-label">{{ __($t.'participating_orgs') }}</span>
                        @if($partScope && $partScope->target_type === 'CrossTenant')
                            <b class="gs-tracking-doc-value">{{ __($t.'scope_cross') }}</b>
                            <p class="gs-tracking-doc-note">{{ __($t.'any_office') }}</p>
                        @else
                            <b class="gs-tracking-doc-value">{{ __($t.'scope_internal') }}</b>
                            <p class="gs-tracking-doc-note">{{ __($t.'restricted_office') }}</p>
                        @endif
                    </div>
                </div>
            </x-gs::box>

            <!-- Approved Allocation Schedule -->
            <x-gs::box :title="__($u.'extra_approved_allocation_schedule')" icon="fas fa-table">
                @if($trackingCode->specificity_level === '1_BLANKET')
                    <div class="gs-tracking-doc-empty">
                        <i class="fas fa-globe gs-tracking-doc-empty__icon" aria-hidden="true"></i>
                        <h3 class="gs-tracking-doc-value--lg">{{ __($t.'open_mode') }}</h3>
                        <p>{{ __($u.'this_program_operates_in_blanket_mode_no_category_level_allocations_are_enforced_storekeepers_at_permitted_office_locations_can_receive_any_categories_under_this_task') }}</p>
                    </div>
                @else
                    <x-gs::table>
                        <thead>
                            <tr class="gs-tracking-doc-table-head">
                                <th scope="col" class="gs-tracking-doc-serial">{{ __($u.'serial') }}</th>
                                <th scope="col">{{ __($t.'item_category') }}</th>
                                <th scope="col" class="gs-num">{{ __($t.'approved') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($trackingCode->targets as $index => $target)
                                @php
                                    $allocated = $trackingCode->specificity_level === '3_MATRIX' ? $target->allocated_qty : $target->planned_qty;
                                @endphp
                                <tr>
                                    <td class="gs-tracking-doc-serial">{{ $digits($index + 1) }}</td>
                                    <td>
                                        <b class="gs-tracking-doc-value">{{ $target->category->name ?? __($u.'extra_unknown_category') }}</b>
                                        @if($target->economic_code)
                                            <div class="gs-tracking-doc-label">{{ __($t.'economic') }} <code>{{ $target->economic_code }}</code></div>
                                        @endif
                                    </td>
                                    <td class="gs-num"><strong>{{ $digits(number_format($allocated)) }} {{ __($u.'extra_units') }}</strong></td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="gs-table__empty">{{ __($t.'no_targets') }}</td></tr>
                            @endforelse
                        </tbody>
                    </x-gs::table>
                @endif

                @if($trackingCode->specificity_level === '3_MATRIX')
                    <x-slot:footer>
                        <span class="gs-tracking-doc-note"><i class="fas fa-building" aria-hidden="true"></i> {{ __($t.'office_allocation') }} <b>{{ $locationName }}</b></span>
                    </x-slot:footer>
                @endif
            </x-gs::box>
        </div>

        <!-- RIGHT COLUMN: Supporting Files & Side Directory -->
        <div class="col-md-4">
            <x-gs::box :title="__($u.'extra_attached_order')" icon="fas fa-paperclip">
                @if($trackingCode->order_pdf_path)
                    <x-gs::button icon="fa-download" class="gs-tracking-block-btn" :href="route('gov.tracking.tracking-codes.download', $trackingCode->id)">{{ __($t.'signed_memo') }}</x-gs::button>
                @else
                    <p class="gs-tracking-doc-note"><i class="fas fa-circle-info" aria-hidden="true"></i> {{ __($t.'no_order') }}</p>
                @endif
            </x-gs::box>

            <x-gs::box :title="__($u.'extra_responsible_operation_unit')" icon="fas fa-users">
                @foreach($contacts as $contact)
                    <div @class(['gs-tracking-doc-field', 'gs-tracking-doc-field--bordered' => $loop->first])>
                        <span class="gs-tracking-doc-label">{{ $contact['label'] }}</span>
                        @if($contact['unit'])
                            <b class="gs-tracking-doc-value">{{ trim(($contact['unit']->user->first_name ?? '').' '.($contact['unit']->user->last_name ?? '')) }}</b>
                            @if($contact['unit']->user?->phone)<div class="gs-tracking-doc-contact"><i class="fas fa-phone" aria-hidden="true"></i> {{ $contact['unit']->user->phone }}</div>@endif
                            @if($contact['unit']->user?->email)<div class="gs-tracking-doc-contact"><i class="fas fa-envelope" aria-hidden="true"></i> {{ $contact['unit']->user->email }}</div>@endif
                        @else
                            <p class="gs-tracking-doc-note"><i class="fas fa-circle-exclamation" aria-hidden="true"></i> {{ $contact['missing'] }}</p>
                        @endif
                    </div>
                @endforeach
            </x-gs::box>
        </div>
    </div>
</x-gs::document>
@stop
