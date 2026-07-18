@extends('layouts/default')

@section('title', __('classification::texts.mycatalog_title'))

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-folder-open text-blue"></i> {{ __('classification::texts.mycatalog_header_title') }}</h3>
                <p class="text-muted" style="margin-top: 5px; margin-bottom: 0;">{{ __('classification::texts.mycatalog_header_desc') }}</p>
            </div>
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

                                <!-- Hide Admin Buttons for Standard Employees -->
                                @if(!$isReadOnly)
                                    <td class="text-center" style="vertical-align: middle;">
                                        <a href="{{ route('gov.catalog.my_catalog.show', $cat->id) }}" class="btn btn-sm btn-default">
                                            <i class="fas fa-cog"></i> {{ __('classification::texts.mycatalog_btn_manage') }}
                                        </a>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isReadOnly ? 5 : 6 }}" class="text-center text-muted" style="padding: 30px;">{{ __('classification::texts.mycatalog_empty_state') }}</td>
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