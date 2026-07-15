@extends('layouts/default')

@section('title', 'Import History')

@section('content')

<div class="row">
    <div class="col-md-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Catalog Import History</h3>
            </div>

            <div class="box-body">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Scheme</th>
                            <th>Nodes Imported</th>
                            <th>Status</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($history as $record)
                        <tr>
                            <td>{{ $record->imported_at ? $record->imported_at->format('Y-m-d H:i') : '—' }}</td>
                            <td><span class="label label-info">{{ $record->scheme ?? '—' }}</span></td>
                            <td>{{ $record->nodes_imported ?? '—' }}</td>
                            <td>
                                @if($record->status === 'success')
                                    <span class="label label-success">Success</span>
                                @elseif($record->status === 'failed')
                                    <span class="label label-danger">Failed</span>
                                @else
                                    <span class="label label-warning">{{ $record->status ?? 'Unknown' }}</span>
                                @endif
                            </td>
                            <td>
                                @if($record->errors)
                                    <a href="#" class="btn btn-xs btn-danger" data-toggle="modal" data-target="#errorModal{{ $record->id }}">
                                        <i class="fas fa-exclamation-triangle"></i> View Errors
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted">No import history found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>

                {{ $history->links() }}
            </div>
        </div>
    </div>
</div>

@endsection
