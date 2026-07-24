@extends('layouts/default')
@section('title', 'Policy Simulator')

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="box box-solid">
            <div class="box-header with-border" style="background: #f8fafc;">
                <h3 class="box-title" style="color: #1e293b; font-weight: bold;"><i class="fa fa-flask"></i> The "Why" Simulator</h3>
                <p class="text-muted" style="margin-top: 5px; font-size: 13px;">Test how multi-layer policies merge in the real world. See exactly what the storekeeper sees.</p>
            </div>
            <div class="box-body" style="padding: 20px; background: #f1f5f9; border-bottom: 1px solid #e2e8f0;">
                <form id="simulatorForm" class="form-inline">
                    <div class="form-group" style="margin-right: 15px;">
                        <label style="margin-right: 10px; color: #475569;">Simulate User Location:</label>
                        <select name="location_id" class="form-control" style="width: 250px;">
                            <option value="">-- Select Office / Location --</option>
                            @foreach($locations as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="form-group" style="margin-right: 15px;">
                        <label style="margin-right: 10px; color: #475569;">Receiving Target:</label>
                        <select name="category_id" class="form-control" style="width: 250px;">
                            <option value="">-- Select Category --</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="fa fa-play"></i> Run Simulation</button>
                </form>
            </div>
            
            <!-- Result Pane -->
            <div id="simulation_results" style="min-height: 400px; padding: 0;">
                <div class="text-center text-muted" style="margin-top: 100px;">
                    <i class="fa fa-cogs fa-4x" style="opacity: 0.2; margin-bottom: 20px;"></i>
                    <h4>Select a context and click "Run Simulation"</h4>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('moar_scripts')
<script>
$(document).ready(function() {
    $('#simulatorForm').submit(function(e) {
        e.preventDefault();
        
        let loc = $('select[name="location_id"]').val();
        let cat = $('select[name="category_id"]').val();

        if(!loc || !cat) {
            alert('Please select both a Location and a Target Category to simulate.');
            return;
        }

        $('#simulation_results').html('<div class="text-center" style="margin-top: 100px;"><i class="fa fa-spinner fa-spin fa-3x text-blue"></i></div>');

        $.get('{{ route("storeops.admin.rules.simulator.run") }}', $(this).serialize(), function(html) {
            $('#simulation_results').html(html);
        });
    });
});
</script>
@endsection