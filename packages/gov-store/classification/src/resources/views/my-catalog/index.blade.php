@extends('layouts/default')

@section('title', __('classification::texts.mycatalog_title'))

@section('content')
<div class="row">
    <div class="col-md-12">
        
        <!-- Tabbed Navigation -->
        <div class="nav-tabs-custom">
            <ul class="nav nav-tabs">
                <li class="{{ $activeTab === 'all' ? 'active' : '' }}">
                    <a href="{{ route('gov.catalog.my_catalog.index', ['tab' => 'all']) }}">
                        <i class="fas fa-list"></i> All Active
                    </a>
                </li>
                <li class="{{ $activeTab === 'global' ? 'active' : '' }}">
                    <a href="{{ route('gov.catalog.my_catalog.index', ['tab' => 'global']) }}">
                        <i class="fas fa-globe text-green"></i> Global Standards
                    </a>
                </li>
                <li class="{{ $activeTab === 'company' ? 'active' : '' }}">
                    <a href="{{ route('gov.catalog.my_catalog.index', ['tab' => 'company']) }}">
                        <i class="fas fa-building text-orange"></i> Ministry Standards
                    </a>
                </li>
                <li class="{{ $activeTab === 'location' ? 'active' : '' }}">
                    <a href="{{ route('gov.catalog.my_catalog.index', ['tab' => 'location']) }}">
                        <i class="fas fa-map-marker-alt text-blue"></i> Office Locals
                    </a>
                </li>
                <li class="{{ $activeTab === 'archived' ? 'active' : '' }}">
                    <a href="{{ route('gov.catalog.my_catalog.index', ['tab' => 'archived']) }}">
                        <i class="fas fa-archive text-muted"></i> Archived
                    </a>
                </li>
                <li class="pull-right {{ $activeTab === 'unused' ? 'active' : '' }}">
                    <a href="{{ route('gov.catalog.my_catalog.index', ['tab' => 'unused']) }}" class="text-danger">
                        <i class="fas fa-broom"></i> Unused (Cleanup)
                    </a>
                </li>
            </ul>

            <div class="tab-content no-padding">
                <div class="box box-solid" style="margin-bottom: 0; box-shadow: none;">
                    
                    @if($activeTab === 'unused')
                    <div class="box-header">
                        <div class="alert alert-warning" style="margin-bottom: 0; padding: 10px 15px;">
                            <i class="fas fa-info-circle"></i> <strong>Taxonomy Cleanup:</strong> These categories have exactly 0 items (assets, consumables, components) currently assigned to your office. You can safely "Stop Using" them to declutter your dropdown menus.
                        </div>
                    </div>
                    @endif

                    <div class="box-body table-responsive">
                        <table class="table table-striped table-hover table-bordered">
                            <thead style="background-color: #f9f9f9;">
                                <tr>
                                    <th>{{ __('classification::texts.mycatalog_col_operational_category') }}</th>
                                    <th>{{ __('classification::texts.mycatalog_col_category_type') }}</th>
                                    <th>{{ __('classification::texts.mycatalog_col_governance_source') }}</th>
                                    <th>{{ __('classification::texts.mycatalog_col_adoption_date') }}</th>
                                    <th class="text-center">{{ __('classification::texts.mycatalog_col_status') }}</th>
                                    @if(!$isReadOnly)
                                        <th class="text-center">{{ __('classification::texts.mycatalog_col_action') }}</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($categories as $cat)
                                    <tr>
                                        <td>
                                            <strong>{{ $cat->name }}</strong><br>
                                            <code>{{ $cat->unspsc_code ?? 'Unmapped' }}</code>
                                        </td>
                                        <td>{{ ucfirst($cat->category_type) }}</td>
                                        <td>
                                            @if($cat->governance_type === 'global')
                                                <span class="text-green"><i class="fas fa-globe"></i> {{ __('classification::texts.mycatalog_gov_standard') }}</span>
                                            @elseif($cat->governance_type === 'company' || $cat->governance_type === 'location')
                                                <span class="text-orange"><i class="fas fa-building"></i> {{ __('classification::texts.mycatalog_org_standard') }}</span>
                                            @else
                                                <span class="text-muted"><i class="fas fa-server"></i> {{ __('classification::texts.mycatalog_native_creation') }}</span>
                                            @endif
                                        </td>
                                        <td>{{ \Carbon\Carbon::parse($cat->adopted_at)->format('d M Y') }}</td>
                                        
                                        <td class="text-center" style="vertical-align: middle;">
                                            @if($cat->is_adopted_active)
                                                <span class="label label-success">{{ __('classification::texts.mycatalog_label_active') }}</span>
                                            @else
                                                <span class="label label-default" style="background-color: #777 !important;">{{ __('classification::texts.mycatalog_label_archived') }}</span>
                                            @endif
                                        </td>

                                        @if(!$isReadOnly)
                                            <td class="text-center" style="vertical-align: middle;">
                                                <a href="{{ route('gov.catalog.my_catalog.show', $cat->id) }}" class="btn btn-sm btn-default">
                                                    <i class="fas fa-cog"></i> {{ __('classification::texts.mycatalog_btn_manage') }}
                                                </a>
                                                
                                                <!-- Quick Action for Unused items -->
                                                @if($activeTab === 'unused')
                                                    <button class="btn btn-sm btn-danger btn-abandon-quick" data-id="{{ $cat->id }}" style="margin-left: 5px;">
                                                        <i class="fas fa-trash-alt"></i> Drop
                                                    </button>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $isReadOnly ? 5 : 6 }}" class="text-center text-muted" style="padding: 40px;">
                                            <i class="fas fa-folder-open fa-3x" style="opacity: 0.3; margin-bottom: 10px;"></i><br>
                                            @if($activeTab === 'unused')
                                                No empty categories found. Your catalog is clean!
                                            @else
                                                {{ __('classification::texts.mycatalog_empty_state') }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                        {{ $categories->appends(['tab' => $activeTab])->links() }}
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>
@endsection

@section('moar_scripts')
@parent
<script>
$(document).ready(function() {
    // Quick Drop Action for the Unused Tab
    $('.btn-abandon-quick').on('click', function() {
        if(!confirm('Are you sure you want to stop using this category? It will be removed from your office.')) return;
        
        const btn = $(this);
        btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
        
        $.post('{{ route("gov.catalog.adoption.abandon") }}', {
            _token: '{{ csrf_token() }}',
            category_id: btn.data('id')
        }).done(function() {
            // Fade out the row for a smooth UX
            btn.closest('tr').fadeOut('fast', function() { $(this).remove(); });
        }).fail(function(xhr) {
            alert('Error: ' + (xhr.responseJSON?.message || 'Cannot remove category.'));
            btn.html('<i class="fas fa-trash-alt"></i> Drop').prop('disabled', false);
        });
    });
});
</script>
@endsection