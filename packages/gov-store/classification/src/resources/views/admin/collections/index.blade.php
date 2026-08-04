@extends('layouts/default')
@section('title', 'Collection Library')
@section('content')
<div class="row">
    <div class="col-md-4">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Create New Collection</h3></div>
            <form action="{{ route('gov.catalog.collections.store') }}" method="POST">
                @csrf
                <div class="box-body">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Hospital Equipment" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>FontAwesome Icon Class</label>
                        <input type="text" name="icon" class="form-control" value="fas fa-box" placeholder="fas fa-hospital">
                    </div>
                </div>
                <div class="box-footer"><button type="submit" class="btn btn-primary">Create Collection</button></div>
            </form>
        </div>
    </div>
    <div class="col-md-8">
        <div class="box box-default">
            <div class="box-header with-border"><h3 class="box-title">Existing Collections</h3></div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tr><th>Icon</th><th>Name</th><th>Nodes</th><th>Actions</th></tr>
                    @foreach($collections as $col)
                    <tr>
                        <td><i class="{{ $col->icon }} fa-lg text-blue"></i></td>
                        <td><strong>{{ $col->name }}</strong><br><small class="text-muted">{{ $col->description }}</small></td>
                        <td><span class="badge bg-green">{{ $col->nodes_count }}</span></td>
                        <td>
                            <a href="{{ route('gov.catalog.collections.edit', $col->id) }}" class="btn btn-sm btn-default">
                                <i class="fas fa-edit"></i> Edit Content
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </table>
            </div>
        </div>
    </div>
</div>
@endsection