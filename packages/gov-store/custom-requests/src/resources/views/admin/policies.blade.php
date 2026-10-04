@extends('layouts/default')

@section('title', __('requestlabels::requests.policies_title'))

@section('content')
<div class="box box-primary">
    <div class="box-header"><h3 class="box-title">{{ __('requestlabels::requests.item_override') }}</h3></div>
    <form action="{{ route('gov.requests.admin.policies.store') }}" method="POST" class="box-body">
        @csrf
        <div class="row">
            <div class="col-md-3 form-group">
                <label for="target_type">{{ __('requestlabels::requests.item_type') }}</label>
                <select id="target_type" name="target_type" class="form-control">
                    @foreach(['asset_model', 'accessory', 'consumable'] as $type)
                        <option value="{{ $type }}">{{ __('requestlabels::requests.type_'.$type) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 form-group">
                <label for="target_id">{{ __('requestlabels::requests.item_id') }}</label>
                <input id="target_id" name="target_id" type="number" class="form-control" min="1" required>
            </div>
            <div class="col-md-3 form-group">
                <label for="policy_name">{{ __('requestlabels::requests.approval_policy') }}</label>
                <select id="policy_name" name="policy_name" class="form-control">
                    @foreach(\GovStore\CustomRequests\Support\RequestWorkflow::POLICIES as $policyName)
                        <option value="{{ $policyName }}">{{ __('requestlabels::requests.policies_policy_'.strtolower($policyName)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 form-group">
                <label for="threshold_qty">{{ __('requestlabels::requests.threshold_qty') }}</label>
                <input id="threshold_qty" name="threshold_qty" type="number" class="form-control" min="1" max="10000">
            </div>
            <div class="col-md-2 form-group">
                <label for="threshold_value">{{ __('requestlabels::requests.threshold_value') }}</label>
                <input id="threshold_value" name="threshold_value" type="number" class="form-control" min="0.01" step="0.01">
            </div>
        </div>
        <p class="help-block">{{ __('requestlabels::requests.threshold_help') }}</p>
        <button class="btn btn-primary">{{ __('requestlabels::requests.policies_btn_update') }}</button>
    </form>
</div>
<div class="row">
    <div class="col-md-12">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-tags"></i> {{ __('requestlabels::requests.policies_header_title') }}</h3>
                <p class="text-muted" style="margin-top: 5px; margin-bottom: 0;">{{ __('requestlabels::requests.policies_header_description') }}</p>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th style="width: 30%;">{{ __('requestlabels::requests.category') }}</th>
                            <th>{{ __('requestlabels::requests.approval_policy') }}</th>
                            <th>{{ __('requestlabels::requests.threshold_qty') }}</th>
                            <th>{{ __('requestlabels::requests.threshold_value') }}</th>
                            <th style="width: 120px;">{{ __('requestlabels::requests.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($categories as $cat)
                            @php
                                $policy = $policies[$cat->id] ?? null;
                                $policyName = $policy ? $policy->policy_name : 'PRIMARY_ONLY'; // Fallback default
                            @endphp
                            <tr>
                                    <td style="vertical-align: middle;">
                                        <strong>{{ $cat->name }}</strong>
                                        <form id="category-policy-{{ $cat->id }}" action="{{ route('gov.requests.admin.policies.store') }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="category_id" value="{{ $cat->id }}">
                                        </form>
                                    </td>
                                    
                                    <td style="vertical-align: middle;">
                                        <select form="category-policy-{{ $cat->id }}" aria-label="{{ __('requestlabels::requests.approval_policy') }} — {{ $cat->name }}" name="policy_name" class="form-control input-sm" style="width: 100%;">
                                            <option value="AUTO_APPROVE" {{ $policyName === 'AUTO_APPROVE' ? 'selected' : '' }}>
                                                ✔️ {{ __('requestlabels::requests.policies_policy_auto_approve') }}
                                            </option>
                                            <option value="PRIMARY_ONLY" {{ $policyName === 'PRIMARY_ONLY' ? 'selected' : '' }}>
                                                👤 {{ __('requestlabels::requests.policies_policy_primary_only') }}
                                            </option>
                                            <option value="PRIMARY_AND_FINAL" {{ $policyName === 'PRIMARY_AND_FINAL' ? 'selected' : '' }}>
                                                👥 {{ __('requestlabels::requests.policies_policy_primary_and_final') }}
                                            </option>
                                        </select>
                                    </td>
                                    <td><input form="category-policy-{{ $cat->id }}" aria-label="{{ __('requestlabels::requests.threshold_qty') }} — {{ $cat->name }}" name="threshold_qty" type="number" class="form-control input-sm" min="1" max="10000" value="{{ $policy?->threshold_qty }}"></td>
                                    <td><input form="category-policy-{{ $cat->id }}" aria-label="{{ __('requestlabels::requests.threshold_value') }} — {{ $cat->name }}" name="threshold_value" type="number" class="form-control input-sm" min="0.01" step="0.01" value="{{ $policy?->threshold_value }}"></td>
                                    <td style="vertical-align: middle;">
                                        <button form="category-policy-{{ $cat->id }}" type="submit" class="btn btn-sm btn-success btn-block"><i class="fas fa-save"></i> {{ __('requestlabels::requests.policies_btn_update') }}</button>
                                    </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
