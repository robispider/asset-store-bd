@extends('layouts/default')

@section('title', __('classification::texts.unassigned_title'))

@section('content')
<div class="row">
    <div class="col-md-12">
        <!-- Human-Centered Explanation Banner -->
        <div class="alert alert-warning" style="background-color: #fcf8e3 !important; border-color: #faebcc !important; color: #8a6d3b !important; padding: 20px; border-radius: 4px; margin-bottom: 25px;">
            <h4><i class="icon fa fa-info-circle" style="font-size: 20px;"></i> Office Ministry Assignment Pending</h4>
            <p style="font-size: 14px; margin-top: 5px; line-height: 1.6;">
                This physical office location is not currently linked to any government Ministry or parent Company in the system. 
                Because of this, your local office does not have a private company-wide catalog.
            </p>
            <p style="font-size: 14px; margin-top: 10px; font-weight: bold;">
                However, you can still view and utilize the Globally Shared Government Standard categories listed below during your daily operations.
            </p>
        </div>

        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-globe text-blue"></i> {{ __('classification::texts.unassigned_header_title') }}</h3>
                <span class="label label-info pull-right" style="font-size: 12px;">{{ __('classification::texts.unassigned_label_shared_ref_data') }}</span>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-striped table-hover table-bordered">
                    <thead style="background-color: #f9f9f9;">
                        <tr>
                            <th>{{ __('classification::texts.unassigned_col_category_name') }}</th>
                            <th>{{ __('classification::texts.unassigned_col_category_type') }}</th>
                            <th>{{ __('classification::texts.unassigned_col_unspsc_code') }}</th>
                            <th>{{ __('classification::texts.unassigned_col_governance_status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categories as $cat)
                            <tr>
                                <td><strong>{{ $cat->name }}</strong></td>
                                <td>{{ ucfirst($cat->category_type) }}</td>
                                <td><code>{{ $cat->unspsc_code ?? 'Unmapped' }}</code></td>
                                <td>
                                    <span class="text-green"><i class="fas fa-globe"></i> {{ __('classification::texts.unassigned_shared_gov_standard') }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted" style="padding: 30px;">{{ __('classification::texts.unassigned_empty_state') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                {{ $categories->links() }}
            </div>
        </div>
    </div>
</div>
@endsection