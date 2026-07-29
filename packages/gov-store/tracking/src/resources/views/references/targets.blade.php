@extends('layouts/default')

@section('title', 'Targets Allocation: ' . $reference->reference_code)

@section('content')
<div class="row">
    <div class="col-md-4">
        <!-- New Target Form -->
        <form action="{{ route('gov.tracking.references.targets.store', $reference->id) }}" method="POST">
            @csrf
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">Add Target Objective</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label>Category (Mandatory)</label>
                        <select name="category_id" class="form-control" required>
                            <option value="">-- Choose Category --</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Specific Model (Optional)</label>
                        <select name="model_id" class="form-control">
                            <option value="">-- All Models --</option>
                            @foreach($models as $model)
                                <option value="{{ $model->id }}">{{ $model->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Planned Target Quantity</label>
                        <input type="number" name="planned_qty" class="form-control" min="1" required>
                    </div>
                </div>
                <div class="box-footer text-right">
                    <a href="{{ route('gov.tracking.references.show', $reference->id) }}" class="btn btn-default">Back</a>
                    <button type="submit" class="btn btn-primary">Allocate Goal</button>
                </div>
            </div>
        </form>
    </div>

    <div class="col-md-8">
        <!-- Allocated Targets List -->
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">Current Planned Goals</h3>
            </div>
            <div class="box-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Specific Model</th>
                            <th>Planned Qty</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reference->targets as $target)
                            <tr>
                                <td>{{ $target->category->name }}</td>
                                <td>{{ $target->assetModel ? $target->assetModel->name : 'All Models' }}</td>
                                <td><strong>{{ $target->planned_qty }}</strong></td>
                                <td class="text-right">
                                    <form action="{{ route('gov.tracking.references.targets.destroy', [$reference->id, $target->id]) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('Remove this planning objective?')">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">No planned goals allocated to this reference yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@stop
