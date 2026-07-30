@extends('layouts/default')

@section('title', 'System Configuration: Funding Sources')

@section('content')
<div class="row">
    <div class="col-md-4">
        <form action="{{ route('gov.tracking.funding-types.store') }}" method="POST">
            @csrf
            <div class="box box-default">
                <div class="box-header with-border"><h3 class="box-title">Add New Funding Source</h3></div>
                <div class="box-body">
                    <div class="form-group">
                        <label>Primary Segment Type</label>
                        <select name="primary_type" class="form-control" required>
                            <option value="ADP">ADP (Development Budget)</option>
                            <option value="REVENUE">Revenue Budget (Non-Development)</option>
                            <option value="OTHER">Other Sources / Autonomous</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Sub-Source Name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Project Aid (PA), GoB (Taka)" required>
                    </div>
                    <div class="form-group">
                        <label>Description (Optional)</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="box-footer text-right">
                    <button type="submit" class="btn btn-primary">Save Funding Source</button>
                </div>
            </div>
        </form>
    </div>
    <div class="col-md-8">
        <div class="box box-default">
            <div class="box-header with-border"><h3 class="box-title">Active Funding Sources Dictionary</h3></div>
            <div class="box-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Primary Segment</th>
                            <th>Sub-Source Name</th>
                            <th>Description</th>
                            <th>Active Task Count</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($fundingTypes as $type)
                            <tr>
                                <td><span class="label label-{{ $type->primary_type == 'ADP' ? 'success' : ($type->primary_type == 'REVENUE' ? 'primary' : 'default') }}">{{ $type->primary_type }}</span></td>
                                <td><strong>{{ $type->name }}</strong></td>
                                <td>{{ $type->description }}</td>
                                <td>{{ $type->tracking_codes_count }}</td>
                                <td class="text-right">
                                    <form action="{{ route('gov.tracking.funding-types.destroy', $type->id) }}" method="POST">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-danger" {{ $type->tracking_codes_count > 0 ? 'disabled' : '' }} onclick="return confirm('Delete this funding source?')"><i class="fa fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">No funding sources configured yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@stop