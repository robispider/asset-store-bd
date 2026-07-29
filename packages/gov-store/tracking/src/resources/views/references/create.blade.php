@extends('layouts/default')

@section('title', 'Create Tracking Reference')

@section('content')
<div class="row">
    <div class="col-md-6 col-md-offset-3">
        <form action="{{ route('gov.tracking.references.store') }}" method="POST" class="form-horizontal">
            @csrf
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title">Tracking Reference Properties</h3>
                </div>
                <div class="box-body">
                    <div class="form-group {{ $errors->has('tracking_type_id') ? 'has-error' : '' }}">
                        <label class="col-sm-3 control-label">Type</label>
                        <div class="col-sm-9">
                            <select name="tracking_type_id" class="form-control">
                                @foreach($types as $type)
                                    <option value="{{ $type->id }}" {{ old('tracking_type_id') == $type->id ? 'selected' : '' }}>
                                        {{ $type->display_name }}
                                    </option>
                                @endforeach
                            </select>
                            {!! $errors->first('tracking_type_id', '<span class="help-block">:message</span>') !!}
                        </div>
                    </div>
                    <div class="form-group {{ $errors->has('reference_code') ? 'has-error' : '' }}">
                        <label class="col-sm-3 control-label">Reference Code</label>
                        <div class="col-sm-9">
                            <input type="text" name="reference_code" class="form-control" value="{{ old('reference_code') }}" placeholder="e.g. REF-001">
                            {!! $errors->first('reference_code', '<span class="help-block">:message</span>') !!}
                        </div>
                    </div>
                    <div class="form-group {{ $errors->has('title') ? 'has-error' : '' }}">
                        <label class="col-sm-3 control-label">Title</label>
                        <div class="col-sm-9">
                            <input type="text" name="title" class="form-control" value="{{ old('title') }}" placeholder="e.g. Operational Reference Title">
                            {!! $errors->first('title', '<span class="help-block">:message</span>') !!}
                        </div>
                    </div>
                    <div class="form-group {{ $errors->has('description') ? 'has-error' : '' }}">
                        <label class="col-sm-3 control-label">Description</label>
                        <div class="col-sm-9">
                            <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                            {!! $errors->first('description', '<span class="help-block">:message</span>') !!}
                        </div>
                    </div>
                    <div class="form-group {{ $errors->has('status') ? 'has-error' : '' }}">
                        <label class="col-sm-3 control-label">Status</label>
                        <div class="col-sm-9">
                            <select name="status" class="form-control">
                                <option value="DRAFT" {{ old('status') == 'DRAFT' ? 'selected' : '' }}>Draft</option>
                                <option value="APPROVED" {{ old('status') == 'APPROVED' ? 'selected' : '' }}>Approved</option>
                                <option value="ACTIVE" {{ old('status') == 'ACTIVE' ? 'selected' : '' }}>Active</option>
                                <option value="SUSPENDED" {{ old('status') == 'SUSPENDED' ? 'selected' : '' }}>Suspended</option>
                                <option value="COMPLETED" {{ old('status') == 'COMPLETED' ? 'selected' : '' }}>Completed</option>
                                <option value="CANCELLED" {{ old('status') == 'CANCELLED' ? 'selected' : '' }}>Cancelled</option>
                            </select>
                            {!! $errors->first('status', '<span class="help-block">:message</span>') !!}
                        </div>
                    </div>
                    <div class="form-group {{ $errors->has('effective_from') ? 'has-error' : '' }}">
                        <label class="col-sm-3 control-label">Effective From</label>
                        <div class="col-sm-9">
                            <input type="date" name="effective_from" class="form-control" value="{{ old('effective_from') }}">
                            {!! $errors->first('effective_from', '<span class="help-block">:message</span>') !!}
                        </div>
                    </div>
                    <div class="form-group {{ $errors->has('effective_until') ? 'has-error' : '' }}">
                        <label class="col-sm-3 control-label">Effective Until</label>
                        <div class="col-sm-9">
                            <input type="date" name="effective_until" class="form-control" value="{{ old('effective_until') }}">
                            {!! $errors->first('effective_until', '<span class="help-block">:message</span>') !!}
                        </div>
                    </div>
                </div>
                <div class="box-footer text-right">
                    <a href="{{ route('gov.tracking.references.index') }}" class="btn btn-default">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Reference</button>
                </div>
            </div>
        </form>
    </div>
</div>
@stop
