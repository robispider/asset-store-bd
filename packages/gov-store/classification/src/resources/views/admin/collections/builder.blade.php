@extends('layouts/default')
@section('title', 'Builder: ' . $collection->name)
@section('content')
<div class="classification-theme">
<div class="row">
    <!-- Left Pane: Search & Add -->
    <div class="col-md-6">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">1. Search Master Catalog</h3></div>
            <div class="box-body">
                <div class="input-group classify-inline-b75fad00">
                    <input type="text" id="catalog-search" class="form-control" placeholder="Type keyword or code...">
                    <span class="input-group-btn"><button class="btn btn-default" type="button"><i class="fas fa-search"></i></button></span>
                </div>
                <div class="list-group classify-inline-6f6a6183" id="search-results">
                    <!-- Populated by JS -->
                </div>
            </div>
        </div>
    </div>

    <!-- Right Pane: Current Collection -->
    <div class="col-md-6">
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="{{ $collection->icon }}"></i> 2. Current Members ({{ $collection->nodes->count() }})</h3>
            </div>
            <div class="box-body no-padding classify-inline-f5ee3d4f">
                <x-gs::table class="table table-striped">
                    @foreach($collection->nodes as $pivot)
                    <tr>
                        <td><code>{{ $pivot->code }}</code></td>
                        <td>{{ $pivot->catalogNode->title_en ?? 'Unknown Node' }}</td>
                        <td class="text-right">
                            <form action="{{ route('gov.catalog.collections.detach', $collection->id) }}" method="POST">
                                @csrf
                                <input type="hidden" name="code" value="{{ $pivot->code }}">
                                <button type="submit" class="btn btn-xs btn-danger"><i class="fas fa-times"></i></button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </x-gs::table>
            </div>
        </div>
    </div>
</div>
</div>
@endsection

@section('moar_scripts')
<script>
    // Simple Debounced AJAX Search
    $('#catalog-search').on('keyup', function() {
        let q = $(this).val();
        if(q.length < 3) return;
        
        $.get('{{ route("gov.catalog.search.ajax") }}', {q: q}, function(res) {
            let html = '';
            res.results.forEach(node => {
                // Filter to level 4 (Commodities) mostly, but allow any
                html += `
                <div class="list-group-item">
                    <div class="pull-right">
                        <button class="btn btn-sm btn-primary btn-add-node" data-code="${node.code}"><i class="fas fa-plus"></i> Add</button>
                    </div>
                    <h5 class="classify-inline-855276ba">${node.text}</h5>
                    <small class="text-muted">${node.code}</small>
                </div>`;
            });
            $('#search-results').html(html);
        });
    });

    $(document).on('click', '.btn-add-node', function() {
        let code = $(this).data('code');
        let btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.post('{{ route("gov.catalog.collections.attach", $collection->id) }}', {
            _token: '{{ csrf_token() }}',
            code: code
        }, function(res) {
            window.location.reload();
        });
    });
</script>
@endsection