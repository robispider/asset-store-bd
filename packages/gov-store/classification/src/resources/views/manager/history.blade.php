@extends('layouts/default')

@section('title', __('classification::texts.history_title'))

@section('content')

<div class="row">
    <div class="col-md-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('classification::texts.history_header_title') }}</h3>
            </div>

            <div class="box-body">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>{{ __('classification::texts.history_col_date') }}</th>
                            <th>{{ __('classification::texts.history_col_scheme') }}</th>
                            <th>Version</th>
                            <th>{{ __('classification::texts.history_col_nodes_imported') }}</th>
                            <th>Warnings</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($history as $record)
                        <tr>
                            <td>{{ $record->imported_at ? \Carbon\Carbon::parse($record->imported_at)->format('Y-m-d H:i') : '—' }}</td>
                            <td><span class="label label-info">{{ $record->scheme ?? '—' }}</span></td>
                            <td>{{ $record->version ?? '—' }}</td>
                            
                            <!-- Mapped to actual database column 'rows_processed' -->
                            <td><span class="label label-success">{{ $record->rows_processed ?? '0' }} Nodes</span></td>
                            
                            <!-- Mapped to actual database column 'warnings' -->
                            <td>
                                @if($record->warnings > 0)
                                    <span class="label label-warning">{{ $record->warnings }} Warnings</span>
                                @else
                                    <span class="label label-default">Clean</span>
                                @endif
                            </td>

                            <!-- Mapped to actual database column 'duration_seconds' -->
                            <td>{{ $record->duration_seconds ?? '0' }}s</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted">{{ __('classification::texts.history_empty_state') }}</td>
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