@extends('layouts/default')
@section('title', $collection->display_name)

@section('content')
<div class="classification-theme">
<div class="row classify-inline-b75fad00">
    <div class="col-md-8">
        <h2 class="classify-inline-c3122899"><i class="{{ $collection->icon }} text-blue"></i> {{ $collection->display_name }}</h2>
        <p class="text-muted">{{ $collection->display_description }}</p>
    </div>
    <div class="col-md-4 text-right">
        <div class="well well-sm classify-inline-515f4ab4">
            <h4 class="classify-inline-ccc9ec44">{{ __('classification::collections.progress') }}</h4>
            <div class="progress classify-inline-f3909943">
                <div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('classification::collections.progress') }}" style="width: {{ $progress }}%"></div>
            </div>
            <small class="text-muted">{{ __('classification::collections.adopted_count', ['adopted' => $adoptedCount, 'total' => $collection->nodes->count()]) }}</small>
        </div>
    </div>
</div>

<div class="box box-primary">
    <div class="box-header with-border">
        <h3 class="box-title">{{ __('classification::collections.members') }}</h3>
        <div class="box-tools pull-right">
            @if(count($unadoptedCodes) > 0 && app(\GovStore\TenantScope\Services\GovAccess::class)->decide(auth()->user(), 'catalog.office.adopt')->allowed)
                <button type="button" class="btn btn-success btn-sm" onclick="adoptRemaining()">
                    <i class="fas fa-rocket"></i> {{ __('classification::collections.adopt_remaining', ['count' => count($unadoptedCodes)]) }}
                </button>
            @elseif(count($unadoptedCodes) === 0)
                <button type="button" class="btn btn-default btn-sm" disabled>
                    <i class="fas fa-check-circle text-success"></i> {{ __('classification::collections.complete') }}
                </button>
            @endif
        </div>
    </div>
    <div class="box-body table-responsive no-padding">
        <p class="help-block">{{ __('classification::collections.no_stock') }}</p>
        <x-gs::table class="table table-striped table-hover">
            <tr>
                <th>{{ __('classification::collections.code') }}</th>
                <th>{{ __('classification::collections.item') }}</th>
                <th>{{ __('classification::collections.section') }}</th>
                <th>{{ __('classification::collections.inventory_type') }}</th>
                <th class="text-center">{{ __('classification::collections.status') }}</th>
            </tr>
            @foreach($collection->nodes as $pivot)
            <tr>
                <td><code>{{ $pivot->code }}</code></td>
                <td>{{ app()->getLocale() === 'bn-BD' ? ($pivot->catalogNode?->enrichment?->title_bn ?: $pivot->catalogNode?->title_en) : $pivot->catalogNode?->title_en }}</td>
                <td>{{ $pivot->section ? __('classification::collections.sections.'.$pivot->section) : '—' }}</td>
                <td>{{ $pivot->inventory_type ? __('classification::collections.types.'.$pivot->inventory_type) : '—' }}</td>
                <td class="text-center">
                    @if($pivot->is_adopted)
                        <span class="label label-success"><i class="fas fa-check"></i> {{ __('classification::collections.adopted') }}</span>
                    @else
                        <span class="label label-default">{{ __('classification::collections.not_adopted') }}</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </x-gs::table>
    </div>
</div>

<!-- Include the Phase 2 Bulk Adoption Modal -->
@include('gov-classification::adopt.partials.bulk-preview')

</div>
@endsection

@section('moar_scripts')
@parent
<script>
    // Trigger Phase 2 Modal with all unadopted codes from this collection
    function adoptRemaining() {
        const codes = @json($unadoptedCodes);
        if(codes.length > 0) {
            triggerBulkAdoption(codes, @json($collection->id));
        }
    }
</script>
@endsection
