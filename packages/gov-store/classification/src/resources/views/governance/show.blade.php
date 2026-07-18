@extends('layouts/default')

@section('title', __('classification::texts.governance_show_title_prefix') . ' ' . $category->name)

@section('content')
<div class="row">
    <!-- LEFT: Profile & Mapping -->
    <div class="col-md-6">
        <div class="box box-solid box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('classification::texts.governance_show_profile_title') }}</h3>
            </div>
            <div class="box-body">
                <table class="table table-striped">
                    <tr>
                        <th style="width: 200px;">{{ __('classification::texts.governance_show_op_name') }}</th>
                        <td style="font-size: 16px;"><strong>{{ $category->name }}</strong></td>
                    </tr>
                    <tr>
                        <th>{{ __('classification::texts.governance_show_category_type') }}</th>
                        <td>{{ ucfirst($category->category_type) }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('classification::texts.governance_show_core_id') }}</th>
                        <td><code>{{ $category->id }}</code></td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="box box-solid box-success">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('classification::texts.governance_show_mapping_title') }}</h3>
            </div>
            <div class="box-body">
                @if($mapping)
                    <table class="table">
                        <tr>
                            <th style="width: 200px;">{{ __('classification::texts.governance_show_unspsc_code') }}</th>
                            <td><code>{{ $mapping->code }}</code></td>
                        </tr>
                        <tr>
                            <th>{{ __('classification::texts.governance_show_classification_title') }}</th>
                            <td>{{ $mapping->title_en }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('classification::texts.governance_show_hierarchy') }}</th>
                            <td><span class="text-muted" style="word-break: break-all;">{{ $mapping->hid }}</span></td>
                        </tr>
                    </table>
                @else
                    <div class="alert alert-warning" style="margin-bottom: 0;">
                        <i class="fas fa-exclamation-triangle"></i> {{ __('classification::texts.governance_show_orphan_alert') }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- RIGHT: Governance & Analytics -->
    <div class="col-md-6">
        <div class="box box-solid box-warning">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('classification::texts.governance_show_governance_title') }}</h3>
            </div>
            <div class="box-body">
                <table class="table table-striped">
                    <tr>
                        <th style="width: 200px;">{{ __('classification::texts.governance_show_gov_scope') }}</th>
                        <td>
                            @if($governance && $governance->governance_type === 'global')
                                <span class="text-green"><i class="fas fa-globe"></i> {{ __('classification::texts.governance_show_shared_gov_standard') }}</span>
                            @elseif($governance && $governance->governance_type === 'company')
                                <span class="text-orange"><i class="fas fa-building"></i> {{ __('classification::texts.governance_show_org_managed') }}</span>
                            @else
                                <span class="text-muted">{{ __('classification::texts.governance_show_unmanaged_core_asset') }}</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>{{ __('classification::texts.governance_show_origin_owner') }}</th>
                        <td><strong>{{ $governance->company_name ?? 'System' }}</strong></td>
                    </tr>
                    <tr>
                        <th>{{ __('classification::texts.governance_show_created_by') }}</th>
                        <td>{{ $governance ? ($governance->user_first_name . ' ' . $governance->user_last_name) : 'N/A' }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('classification::texts.governance_show_creation_timestamp') }}</th>
                        <td>{{ $governance->created_at ?? $category->created_at }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="box box-solid box-info">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('classification::texts.governance_show_analytics_title') }}</h3>
            </div>
            <div class="box-body">
                <div class="row text-center" style="margin-bottom: 15px;">
                    <div class="col-xs-6" style="border-right: 1px solid #eee;">
                        <h2 style="margin: 0; color: #3c8dbc;">{{ $stats['adoptions'] }}</h2>
                        <span class="text-muted">{{ __('classification::texts.governance_show_orgs_adopted') }}</span>
                    </div>
                    <div class="col-xs-6">
                        <h2 style="margin: 0; color: #00a65a;">{{ $stats['models'] }}</h2>
                        <span class="text-muted">{{ __('classification::texts.governance_show_mapped_models') }}</span>
                    </div>
                </div>
                <table class="table table-condensed table-striped text-muted">
                    <tr><th>{{ __('classification::texts.governance_show_active_assets') }}</th><td class="text-right"><strong>{{ $stats['assets'] }}</strong></td></tr>
                    <tr><th>{{ __('classification::texts.governance_show_consumables') }}</th><td class="text-right"><strong>{{ $stats['consumables'] }}</strong></td></tr>
                    <tr><th>{{ __('classification::texts.governance_show_accessories') }}</th><td class="text-right"><strong>{{ $stats['accessories'] }}</strong></td></tr>
                    <tr><th>{{ __('classification::texts.governance_show_components') }}</th><td class="text-right"><strong>{{ $stats['components'] }}</strong></td></tr>
                    <tr><th>{{ __('classification::texts.governance_show_licenses') }}</th><td class="text-right"><strong>{{ $stats['licenses'] }}</strong></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection