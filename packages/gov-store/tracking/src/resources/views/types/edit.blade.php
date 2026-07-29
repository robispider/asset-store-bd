@extends('layouts/default')

@section('title', 'Edit Tracking Type')

@section('content')
<div class="row">
    <div class="col-md-6 col-md-offset-3">
        <form action="{{ route('gov.tracking.types.update', $type->id) }}" method="POST" class="form-horizontal">
            @csrf
            @method('PUT')
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">Tracking Type Properties</h3>
                </div>
                <div class="box-body">
                    <div class="form-group {{ $errors->has('code') ? 'has-error' : '' }}">
                        <label class="col-sm-3 control-label">Code</label>
                        <div class="col-sm-9">
                            <input type="text" name="code" class="form-control" value="{{ old('code', $type->code) }}" placeholder="e.g. ADP" disabled>
                            {!! $errors->first('code', '<span class="help-block">:message</span>') !!}
                        </div>
                    </div>
                    <div class="form-group {{ $errors->has('display_name') ? 'has-error' : '' }}">
                        <label class="col-sm-3 control-label">Display Name</label>
                        <div class="col-sm-9">
                            <input type="text" name="display_name" class="form-control" value="{{ old('display_name', $type->display_name) }}" placeholder="e.g. Annual Development Programme">
                            {!! $errors->first('display_name', '<span class="help-block">:message</span>') !!}
                        </div>
                    </div>
                    <div class="form-group {{ $errors->has('icon') ? 'has-error' : '' }}">
                        <label class="col-sm-3 control-label">Icon Class</label>
                        <div class="col-sm-9">
                            <input type="text" name="icon" class="form-control" value="{{ old('icon', $type->icon) }}">
                            {!! $errors->first('icon', '<span class="help-block">:message</span>') !!}
                        </div>
                    </div>
                    <div class="form-group {{ $errors->has('color') ? 'has-error' : '' }}">
                        <label class="col-sm-3 control-label">Hex Color</label>
                        <div class="col-sm-9">
                            <input type="color" name="color" class="form-control" value="{{ old('color', $type->color) }}" style="height:38px;">
                            {!! $errors->first('color', '<span class="help-block">:message</span>') !!}
                        </div>
                    </div>
                    <div class="form-group {{ $errors->has('validation_policy') ? 'has-error' : '' }}">
                        <label class="col-sm-3 control-label">Overshoot Policy</label>
                        <div class="col-sm-9">
                            <select name="validation_policy" class="form-control">
                                <option value="INFORM_ONLY" {{ old('validation_policy', $type->validation_policy) == 'INFORM_ONLY' ? 'selected' : '' }}>Inform Only</option>
                                <option value="WARN" {{ old('validation_policy', $type->validation_policy) == 'WARN' ? 'selected' : '' }}>Warn</option>
                                <option value="REQUIRE_OVERRIDE" {{ old('validation_policy', $type->validation_policy) == 'REQUIRE_OVERRIDE' ? 'selected' : '' }}>Require Override</option>
                                <option value="BLOCK" {{ old('validation_policy', $type->validation_policy) == 'BLOCK' ? 'selected' : '' }}>Block</option>
                            </select>
                            {!! $errors->first('validation_policy', '<span class="help-block">:message</span>') !!}
                        </div>
                    </div>
                </div>
                <div class="box-footer text-right">
                    <a href="{{ route('gov.tracking.types.index') }}" class="btn btn-default">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Type</button>
                </div>
            </div>
        </form>
    </div>
</div>
@stop
