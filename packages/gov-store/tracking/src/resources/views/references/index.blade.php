@extends('layouts/default')

@section('title', 'Operational Tracking References')

@section('header_right')
    <a href="{{ route('gov.tracking.references.create') }}" class="btn btn-primary pull-right">
        Create Tracking Reference
    </a>
@stop

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="box box-default">
            <div class="box-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Reference Code</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Effective Range</th>
                            <th>Docs</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($references as $reference)
                            <tr>
                                <td><code>{{ $reference->reference_code }}</code></td>
                                <td>{{ $reference->title }}</td>
                                <td>
                                    <span class="label" style="background-color: {{ $reference->trackingType->color }}">
                                        <i class="{{ $reference->trackingType->icon }}"></i> {{ $reference->trackingType->display_name }}
                                    </span>
                                </td>
                                <td>
                                    <span class="label label-default">{{ $reference->status }}</span>
                                </td>
                                <td>
                                    @if($reference->effective_from || $reference->effective_until)
                                        {{ $reference->effective_from?->format('Y-m-d') }} to {{ $reference->effective_until?->format('Y-m-d') ?? 'N/A' }}
                                    @else
                                        <span class="text-muted">Unbounded</span>
                                    @endif
                                </td>
                                <td>{{ $reference->documents_count }}</td>
                                <td class="text-right">
                                    <a href="{{ route('gov.tracking.references.show', $reference->id) }}" class="btn btn-xs btn-info">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                    <a href="{{ route('gov.tracking.references.edit', $reference->id) }}" class="btn btn-xs btn-warning">
                                        <i class="fa fa-pencil"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">No tracking references registered.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@stop
