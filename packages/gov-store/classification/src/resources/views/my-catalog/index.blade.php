@extends('layouts/default')

@section('title', 'My Organization Catalog')

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-folder-open text-blue"></i> Adopted Operational Categories</h3>
                <p class="text-muted" style="margin-top: 5px; margin-bottom: 0;">These categories are currently active and available for use in your organization.</p>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-striped table-hover table-bordered">
                    <thead style="background-color: #f9f9f9;">
                        <tr>
                            <th>Operational Category</th>
                            <th>Category Type</th>
                            <th>Governance Source</th>
                            <th>Adoption Date</th>
                            <th class="text-center">Status</th>
                            @if(!$isReadOnly)
                                <th class="text-center">Action</th>
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
                                        <span class="text-green"><i class="fas fa-globe"></i> Gov Standard</span>
                                    @elseif($cat->governance_type === 'company')
                                        <span class="text-orange"><i class="fas fa-building"></i> Org Managed</span><br>
                                        <small class="text-muted">{{ $cat->owner_name ?? 'Unknown' }}</small>
                                    @else
                                        <span class="text-muted">Unmanaged</span>
                                    @endif
                                </td>
                                <td>{{ \Carbon\Carbon::parse($cat->adopted_at)->format('d M Y') }}</td>
                                
                                <td class="text-center" style="vertical-align: middle;">
                                    @if($cat->is_adopted_active)
                                        <span class="label label-success">Active</span>
                                    @else
                                        <span class="label label-default" style="background-color: #777 !important;">Archived</span>
                                    @endif
                                </td>

                                <!-- Hide Admin Buttons for Standard Employees -->
                                @if(!$isReadOnly)
                                    <td class="text-center" style="vertical-align: middle;">
                                        <a href="{{ route('gov.catalog.my_catalog.show', $cat->id) }}" class="btn btn-sm btn-default">
                                            <i class="fas fa-cog"></i> Manage
                                        </a>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isReadOnly ? 5 : 6 }}" class="text-center text-muted" style="padding: 30px;">Your organization has not adopted any categories yet.</td>
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