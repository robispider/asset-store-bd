@extends('layouts/default')

@section('title', __('classification::texts.mapping_title'))

@section('content')

<div class="row">
    <div class="col-md-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('classification::texts.mapping_header_title') }}</h3>
            </div>

            <div class="box-body">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>{{ __('classification::texts.mapping_col_catalog_code') }}</th>
                            <th>{{ __('classification::texts.mapping_col_catalog_title') }}</th>
                            <th>{{ __('classification::texts.mapping_col_scheme') }}</th>
                            <th>{{ __('classification::texts.mapping_col_snipe_category') }}</th>
                            <th>{{ __('classification::texts.mapping_col_actions') }}</th>
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
                                    <i class="fas fa-edit"></i> {{ __('classification::texts.mapping_btn_edit') }}
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted">{{ __('classification::texts.mapping_empty_state') }}</td>
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
