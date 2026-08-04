@extends('layouts/default')
@section('title', $collection->name)

@section('content')
<div class="row" style="margin-bottom: 20px;">
    <div class="col-md-8">
        <h2 style="margin-top: 0;"><i class="{{ $collection->icon }} text-blue"></i> {{ $collection->name }}</h2>
        <p class="text-muted">{{ $collection->description }}</p>
    </div>
    <div class="col-md-4 text-right">
        <div class="well well-sm" style="background-color: #fff; border-radius: 8px;">
            <h4 style="margin: 0 0 10px 0; font-weight: bold;">Adoption Progress</h4>
            <div class="progress" style="margin-bottom: 5px; height: 10px;">
                <div class="progress-bar progress-bar-success" role="progressbar" style="width: {{ $progress }}%"></div>
            </div>
            <small class="text-muted"><strong>{{ $adoptedCount }}</strong> out of <strong>{{ $collection->nodes->count() }}</strong> categories adopted.</small>
        </div>
    </div>
</div>

<div class="box box-primary">
    <div class="box-header with-border">
        <h3 class="box-title">Collection Members</h3>
        <div class="box-tools pull-right">
            @if(count($unadoptedCodes) > 0)
                <button type="button" class="btn btn-success btn-sm" onclick="adoptRemaining()">
                    <i class="fas fa-rocket"></i> Adopt Remaining ({{ count($unadoptedCodes) }})
                </button>
            @else
                <button type="button" class="btn btn-default btn-sm" disabled>
                    <i class="fas fa-check-circle text-success"></i> Fully Adopted
                </button>
            @endif
        </div>
    </div>
    <div class="box-body table-responsive no-padding">
        <table class="table table-striped table-hover">
            <tr>
                <th>Code</th>
                <th>Category Title</th>
                <th class="text-center">Local Status</th>
            </tr>
            @foreach($collection->nodes as $pivot)
            <tr>
                <td><code>{{ $pivot->code }}</code></td>
                <td>{{ $pivot->catalogNode->title_en ?? 'Unknown' }}</td>
                <td class="text-center">
                    @if($pivot->is_adopted)
                        <span class="label label-success"><i class="fas fa-check"></i> Adopted</span>
                    @else
                        <span class="label label-default">Not Adopted</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </table>
    </div>
</div>

<!-- Include the Phase 2 Bulk Adoption Modal -->
@include('gov-classification::adopt.partials.bulk-preview')

@endsection

@section('moar_scripts')
@parent
<script>
    // Trigger Phase 2 Modal with all unadopted codes from this collection
    function adoptRemaining() {
        const codes = @json($unadoptedCodes);
        if(codes.length > 0) {
            triggerBulkAdoption(codes); // From bulk-preview.blade.php
        }
    }
</script>
@endsection