@extends('layouts/default')

@section('title', 'Reference: ' . $reference->reference_code)

@section('content')
<div class="row">
    <div class="col-md-4">
        <!-- Reference Details Box -->
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">General Information</h3>
            </div>
            <div class="box-body">
                <table class="table table-bordered">
                    <tr>
                        <th>Title</th>
                        <td>{{ $reference->title }}</td>
                    </tr>
                    <tr>
                        <th>Type</th>
                        <td>
                            <span class="label" style="background-color: {{ $reference->trackingType->color }}">
                                <i class="{{ $reference->trackingType->icon }}"></i> {{ $reference->trackingType->display_name }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td><span class="label label-default">{{ $reference->status }}</span></td>
                    </tr>
                    <tr>
                        <th>Effective From</th>
                        <td>{{ $reference->effective_from?->format('Y-m-d') ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <th>Effective Until</th>
                        <td>{{ $reference->effective_until?->format('Y-m-d') ?? 'N/A' }}</td>
                    </tr>
                </table>
                <p class="margin-top-15"><strong>Description:</strong></p>
                <p class="text-muted">{{ $reference->description ?? 'No description provided.' }}</p>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="margin-bottom-15">
            <a href="{{ route('gov.tracking.references.targets.index', $reference->id) }}" class="btn btn-sm btn-primary">
                <i class="fa fa-bullseye"></i> Manage Planning Targets
            </a>
            <a href="{{ route('gov.tracking.references.scopes.index', $reference->id) }}" class="btn btn-sm btn-warning">
                <i class="fa fa-shield"></i> Configure Boundaries
            </a>
            <a href="{{ route('gov.tracking.references.dashboard', $reference->id) }}" class="btn btn-sm btn-info">
                <i class="fa fa-dashboard"></i> View Lifecycle Dashboard
            </a>
        </div>
    </div>

    <div class="col-md-8">
        <!-- Direct Document Store Widget -->
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">Attached Administrative Documents</h3>
            </div>
            <div class="box-body">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>File Name</th>
                            <th>Size</th>
                            <th>Uploaded By</th>
                            <th>Date</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reference->documents as $doc)
                            <tr>
                                <td>{{ $doc->file_name }}</td>
                                <td>{{ number_format($doc->file_size / 1024, 2) }} KB</td>
                                <td>{{ $doc->uploader?->first_name }} {{ $doc->uploader?->last_name }}</td>
                                <td>{{ $doc->created_at->format('Y-m-d H:i') }}</td>
                                <td class="text-right">
                                    <a href="{{ route('gov.tracking.documents.download', $doc->id) }}" class="btn btn-xs btn-primary">
                                        <i class="fa fa-download"></i>
                                    </a>
                                    <form action="{{ route('gov.tracking.documents.destroy', $doc->id) }}" method="POST" style="display:inline-block;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('Delete this document permanently?')">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">No documents uploaded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="box-footer">
                <form action="{{ route('gov.tracking.documents.store', $reference->id) }}" method="POST" enctype="multipart/form-data" class="form-inline">
                    @csrf
                    <div class="form-group">
                        <input type="file" name="document" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-success">Upload Document</button>
                </form>
            </div>
        </div>
    </div>
</div>
@stop
