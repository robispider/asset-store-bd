@extends('layouts/default')

@section('title', 'Tracking Types Dictionary')

@section('header_right')
    <a href="{{ route('gov.tracking.types.create') }}" class="btn btn-primary pull-right">
        Create New Type
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
                            <th>Code</th>
                            <th>Display Name</th>
                            <th>Icon</th>
                            <th>Color Theme</th>
                            <th>Validation Policy</th>
                            <th>Reference Count</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($types as $type)
                            <tr>
                                <td><code>{{ $type->code }}</code></td>
                                <td>{{ $type->display_name }}</td>
                                <td><i class="{{ $type->icon }}"></i> <code>{{ $type->icon }}</code></td>
                                <td>
                                    <span class="label" style="background-color: {{ $type->color }}">
                                        {{ $type->color }}
                                    </span>
                                </td>
                                <td>
                                    <span class="label label-default">{{ $type->validation_policy }}</span>
                                </td>
                                <td>{{ $type->references_count }}</td>
                                <td class="text-right">
                                    <a href="{{ route('gov.tracking.types.edit', $type->id) }}" class="btn btn-xs btn-warning">
                                        <i class="fa fa-pencil"></i>
                                    </a>
                                    <form action="{{ route('gov.tracking.types.destroy', $type->id) }}" method="POST" style="display:inline-block;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('Are you sure you want to delete this type?')" {{ $type->references_count > 0 ? 'disabled' : '' }}>
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">No tracking types defined yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@stop
