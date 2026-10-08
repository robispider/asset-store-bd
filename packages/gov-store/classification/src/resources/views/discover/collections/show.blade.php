@extends('layouts/default')
@section('title', $collection->name)

@section('content')
<div class="classification-theme">
<div class="row classify-inline-b75fad00">
    <div class="col-md-8">
        <h2 class="classify-inline-c3122899"><i class="{{ $collection->icon }} text-blue"></i> {{ $collection->name }}</h2>
        <p class="text-muted">{{ $collection->description }}</p>
    </div>
    <div class="col-md-4 text-right">
        <div class="well well-sm classify-inline-515f4ab4">
            <h4 class="classify-inline-ccc9ec44">Adoption Progress</h4>
            <div class="progress classify-inline-f3909943">
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
        <x-gs::table class="table table-striped table-hover">
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
            triggerBulkAdoption(codes); // From bulk-preview.blade.php
        }
    }
</script>
@endsection