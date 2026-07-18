@extends('layouts/default')

@section('title', __('classification::texts.governance_title'))

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-landmark text-blue"></i> {{ __('classification::texts.governance_registry_title') }}</h3>
                <p class="text-muted" style="margin-top: 5px; margin-bottom: 0;">{{ __('classification::texts.governance_registry_desc') }}</p>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-striped table-hover table-bordered">
                    <thead style="background-color: #f9f9f9;">
                        <tr>
                            <th>{{ __('classification::texts.governance_col_operational_category') }}</th>
                            <th>{{ __('classification::texts.governance_col_unspsc_code') }}</th>
                            <th>{{ __('classification::texts.governance_col_governance_type') }}</th>
                            <th>{{ __('classification::texts.governance_col_origin_owner') }}</th>
                            <th class="text-center">{{ __('classification::texts.governance_col_orgs_using') }}</th>
                            <th class="text-center">{{ __('classification::texts.governance_col_mapped_models') }}</th>
                            <th class="text-center">{{ __('classification::texts.governance_col_action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categories as $cat)
                            <tr>
                                <td>
                                    <strong>{{ $cat->name }}</strong><br>
                                    <small class="text-muted">{{ ucfirst($cat->category_type) }}</small>
                                </td>
                                <td><code>{{ $cat->unspsc_code ?? 'Unmapped' }}</code></td>
                                <td>
                                    @if($cat->governance_type === 'global')
                                        <span class="text-green"><i class="fas fa-globe"></i> {{ __('classification::texts.governance_gov_standard') }}</span>
                                    @elseif($cat->governance_type === 'company')
                                        <span class="text-orange"><i class="fas fa-building"></i> {{ __('classification::texts.governance_org_managed') }}</span>
                                    @else
                                        <span class="text-muted"><i class="fas fa-question-circle"></i> {{ __('classification::texts.governance_unmanaged_core') }}</span>
                                    @endif
                                </td>
                                <td>{{ $cat->owner_name ?? 'System' }}</td>
                                <td class="text-center"><span class="badge bg-blue">{{ $cat->adoption_count }}</span></td>
                                <td class="text-center">{{ $cat->models_count }}</td>
                                <td class="text-center">
                                    <a href="{{ route('gov.catalog.governance.show', $cat->id) }}" class="btn btn-sm btn-default">
                                        <i class="fas fa-search"></i> {{ __('classification::texts.governance_btn_inspect') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted" style="padding: 30px;">{{ __('classification::texts.governance_empty_state') }}</td>
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