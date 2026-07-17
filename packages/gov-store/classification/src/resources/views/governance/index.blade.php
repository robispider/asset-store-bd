@extends('layouts/default')

@section('title', 'Category Governance Center')

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-landmark text-blue"></i> Shared Operational Category Registry</h3>
                <p class="text-muted" style="margin-top: 5px; margin-bottom: 0;">Administrative control center for all categories mapped to the global catalog.</p>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-striped table-hover table-bordered">
                    <thead style="background-color: #f9f9f9;">
                        <tr>
                            <th>Operational Category</th>
                            <th>UNSPSC Code</th>
                            <th>Governance Type</th>
                            <th>Origin Owner</th>
                            <th class="text-center">Organizations Using</th>
                            <th class="text-center">Mapped Models</th>
                            <th class="text-center">Action</th>
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
                                        <span class="text-green"><i class="fas fa-globe"></i> Gov Standard</span>
                                    @elseif($cat->governance_type === 'company')
                                        <span class="text-orange"><i class="fas fa-building"></i> Org Managed</span>
                                    @else
                                        <span class="text-muted"><i class="fas fa-question-circle"></i> Unmanaged (Core)</span>
                                    @endif
                                </td>
                                <td>{{ $cat->owner_name ?? 'System' }}</td>
                                <td class="text-center"><span class="badge bg-blue">{{ $cat->adoption_count }}</span></td>
                                <td class="text-center">{{ $cat->models_count }}</td>
                                <td class="text-center">
                                    <a href="{{ route('gov.catalog.governance.show', $cat->id) }}" class="btn btn-sm btn-default">
                                        <i class="fas fa-search"></i> Inspect
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted" style="padding: 30px;">No operational categories found.</td>
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