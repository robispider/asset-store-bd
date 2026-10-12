@extends('layouts/default')

@section('title', __('classification::texts.unassigned_title'))

@section('content')
<div class="classification-theme">
<div class="row">
    <div class="col-md-12">
        <!-- Human-Centered Explanation Banner -->
        <div class="alert alert-warning classify-inline-0b17496a">
            <h4><i class="icon fa fa-info-circle classify-inline-4ef9d2a6"></i> Office Ministry Assignment Pending</h4>
            <p class="classify-inline-52d81610">
                This physical office location is not currently linked to any government Ministry or parent Company in the system. 
                Because of this, your local office does not have a private company-wide catalog.
            </p>
            <p class="classify-inline-0457bc59">
                However, you can still view and utilize the Globally Shared Government Standard categories listed below during your daily operations.
            </p>
        </div>

        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-globe text-blue"></i> {{ __('classification::texts.unassigned_header_title') }}</h3>
                <span class="label label-info pull-right classify-inline-7a459431">{{ __('classification::texts.unassigned_label_shared_ref_data') }}</span>
            </div>
            <div class="box-body table-responsive">
                <x-gs::table class="table table-striped table-hover table-bordered">
                    <thead class="classify-inline-0e5be659">
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
                                <td class="text-center text-muted classify-inline-6b881e6b" colspan="4">{{ __('classification::texts.unassigned_empty_state') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-gs::table>
                {{ $categories->links() }}
            </div>
        </div>
    </div>
</div>
</div>
@endsection