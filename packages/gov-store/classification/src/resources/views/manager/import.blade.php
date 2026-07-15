@extends('layouts/default')

@section('title', 'Import Catalog')

@section('content')

<div class="row">
    <div class="col-md-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Import Government Classification Catalog</h3>
            </div>

            <div class="box-body">
                <p class="text-muted">
                    Import a government classification catalog (UNSPSC, eCl@ss, or custom scheme) to populate the Global Catalog.
                </p>

                <form action="{{ route('gov.catalog.import.store') }}" method="POST" enctype="multipart/form-data">
                    {{ csrf_field() }}

                    <div class="form-group">
                        <label for="catalog_type">Catalog Scheme</label>
                        <select name="scheme" id="catalog_type" class="form-control">
                            <option value="UNSPSC">UNSPSC (United Nations Standard Products and Services Code)</option>
                            <option value="eCl@ss">eCl@ss</option>
                            <option value="custom">Custom Scheme</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="file_upload">CSV / JSON File</label>
                        <input type="file" name="catalog_file" id="file_upload" class="form-control" accept=".csv,.json">
                        <p class="help-block">Upload a CSV or JSON file containing catalog nodes.</p>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="overwrite_existing" value="1">
                            Overwrite existing mappings
                        </label>
                    </div>

                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-cloud-upload-alt"></i> Import Catalog
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
