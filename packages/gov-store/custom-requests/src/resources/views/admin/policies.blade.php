@extends('layouts/default')

@section('title', __('requestlabels::requests.policies_title'))

@section('content')
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
                            <th style="width: 30%;">Inventory Category</th>
                            <th>Required Approval Policy</th>
                            <th style="width: 120px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($categories as $cat)
                            @php
                                $policy = $policies[$cat->id] ?? null;
                                $policyName = $policy ? $policy->policy_name : 'PRIMARY_ONLY'; // Fallback default
                            @endphp
                            <tr>
                                <form action="{{ route('gov.requests.admin.policies.store') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="category_id" value="{{ $cat->id }}">
                                    
                                    <td style="vertical-align: middle;"><strong>{{ $cat->name }}</strong></td>
                                    
                                    <td style="vertical-align: middle;">
                                        <select name="policy_name" class="form-control input-sm" style="width: 100%;">
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

                                    <td style="vertical-align: middle;">
                                        <button type="submit" class="btn btn-sm btn-success btn-block"><i class="fas fa-save"></i> {{ __('requestlabels::requests.policies_btn_update') }}</button>
                                    </td>
                                </form>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection