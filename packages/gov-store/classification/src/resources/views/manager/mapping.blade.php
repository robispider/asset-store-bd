@extends('layouts/default')

@section('title', 'Category Mapping')

@section('content')

<div class="row">
    <div class="col-md-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Catalog → Snipe-IT Category Mapping</h3>
            </div>

            <div class="box-body">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>Catalog Code</th>
                            <th>Catalog Title</th>
                            <th>Scheme</th>
                            <th>Snipe-IT Category</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($mappings as $mapping)
                        <tr>
                            <td>{{ $mapping->catalog_code }}</td>
                            <td>{{ $mapping->catalog_title }}</td>
                            <td><span class="label label-info">{{ $mapping->scheme }}</span></td>
                            <td>{{ $mapping->snipe_category ?? '—' }}</td>
                            <td>
                                <a href="{{ route('gov.catalog.mapping.show', ['id' => $mapping->id]) }}" class="btn btn-xs btn-primary">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted">No mappings found. Import a catalog first.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>

                {{ $mappings->links() }}
            </div>
        </div>
    </div>
</div>

@endsection
