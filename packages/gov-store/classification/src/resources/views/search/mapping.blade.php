@extends('layouts/default-blade')

@section('title')
    {{ __('admin/general.mapping_editor', ['code' => $node->code]) }}
@endsection

@section('content')
<div class="row">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('gov.catalog.search') }}">{{ __('admin/general/global_catalog') }}</a></li>
                <li class="breadcrumb-item active">Mapping: [{{ $node->code }}]</li>
            </ol>
        </nav>

        <!-- Node Info -->
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <i class="fas fa-info-circle fa-fw text-info"></i>
                    {{ __('admin/general.node_information') }}
                </h3>
            </div>
            <div class="box-body">
                <table class="table table-striped">
                    <tr><th>{{ __('general.code') }}</th><td><code>[{{ $node->code }}]</code></td></tr>
                    <tr><th>{{ __('admin/general/scheme') }}</th><td>{{ $node->scheme }}</td></tr>
                    <tr><th>{{ __('admin/general/version') }}</th><td>{{ $node->version }}</td></tr>
                    <tr><th>{{ __('admin/general/level') }}</th><td>
                        @switch($node->level)
                            @case(1) {{ __('admin/general/segment') }}
                            @case(2) {{ __('admin/general/family') }}
                            @case(3) {{ __('admin/general/class') }}
                            @case(4) {{ __('admin/general/commodity') }}
                        @endswitch
                    </td></tr>
                    <tr><th>{{ __('general.title_en') }}</th><td>{{ $node->title_en }}</td></tr>
                </table>
            </div>
        </div>

        <!-- Snipe-IT Category Mapping -->
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <i class="fas fa-link fa-fw text-primary"></i>
                    {{ __('admin/general.snipe_it_mapping') }}
                </h3>
            </div>
            <div class="box-body">
                @if($currentMapping && $currentMapping->category)
                    <div class="alert alert-success">
                        <strong>{{ __('admin/general/mapped') }}:</strong> 
                        <a href="{{ route('categories.show', $currentMapping->category_id) }}">
                            [{{ $currentMapping->category_id }}] {{ $currentMapping->category->name }}
                        </a>
                    </div>
                    <form action="{{ route('gov.catalog.mapping.unlink') }}" method="POST" style="display: inline;">
                        @csrf
                        <input type="hidden" name="code" value="{{ $node->code }}">
                        <button type="submit" class="btn btn-danger btn-sm" 
                                onclick="return confirm('{{ __('admin/general/unlink_confirm') }}')">
                            <i class="fas fa-unlink"></i> {{ __('admin/general.unlink_mapping') }}
                        </button>
                    </form>
                @else
                    <p class="text-muted">{{ __('admin/general.not_mapped') }}</p>
                    <button type="button" class="btn btn-success" data-toggle="modal" data-target="#provision-modal">
                        <i class="fas fa-plus"></i> {{ __('admin/general.provision_category') }}
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Provision Modal -->
<div class="modal fade" id="provision-modal" tabindex="-1">
    <div class="modal-dialog">
        <form id="provision-form" method="POST">
            @csrf
            <input type="hidden" name="code" value="{{ $node->code }}">
            <div class="modal-header"><h5 class="modal-title">{{ __('admin/general.provision_new_category') }}</h5></div>
            <div class="modal-body">
                <p>{{ __('admin/general.provision_confirm', ['name' => "[{$node->code}] {$node->title_en}"]) }}</p>
                <div class="form-group">
                    <label>{{ __('general.category_type') }}</label>
                    <select name="category_type" class="form-control">
                        <option value="asset">{{ __('general.asset') }}</option>
                        <option value="license">{{ __('general.license') }}</option>
                        <option value="accessory">{{ __('general.accessory') }}</option>
                        <option value="consumable">{{ __('general.consumable') }}</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('general.cancel') }}</button>
                <button type="submit" class="btn btn-success">{{ __('general.provision') }}</button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('js')
<script>
$(document).ready(function() {
    $('#provision-form').on('submit', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: '{{ route("gov.catalog.mapping.provision") }}',
            method: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                alert(response.message);
                location.reload();
            },
            error: function(xhr) {
                alert('Error: ' + (xhr.responseJSON?.message || 'Unknown error'));
            }
        });
    });
});
</script>
@endpush
