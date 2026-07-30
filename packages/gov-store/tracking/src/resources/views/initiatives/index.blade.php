@extends('layouts/default')
@section('title', 'Active Initiatives')

@section('header_right')
    <a href="{{ route('gov.tracking.initiatives.create') }}" class="btn btn-primary pull-right">
        <i class="fa fa-plus"></i> Launch New Initiative
    </a>
@stop

@section('content')
<div class="row">
    @forelse($initiatives as $initiative)
        <div class="col-md-4">
            <div class="box box-widget widget-user-2">
                <div class="widget-user-header bg-{{ $initiative->status == 'Active' ? 'green' : ($initiative->status == 'Planning' ? 'yellow' : 'gray') }}">
                    <h3 class="widget-user-username" style="margin-left: 0; font-size: 20px; font-weight: bold;">{{ $initiative->title }}</h3>
                    <h5 class="widget-user-desc" style="margin-left: 0;">{{ $initiative->ownerCompany->name ?? 'Unknown Owner' }}</h5>
                </div>
                <div class="box-footer no-padding">
                    <ul class="nav nav-stacked">
                        <li><a href="#">Status <span class="pull-right badge bg-blue">{{ strtoupper($initiative->status) }}</span></a></li>
                        <li><a href="#">Primary Funding <span class="pull-right text-muted">{{ $initiative->fundingType->name ?? 'N/A' }}</span></a></li>
                        <li><a href="#">Managing Office <span class="pull-right text-muted">{{ $initiative->managingOffice->name ?? 'N/A' }}</span></a></li>
                    </ul>
                </div>
                <div class="box-footer text-center">
                    <a href="{{ route('gov.tracking.initiatives.show', $initiative->id) }}" class="btn btn-default btn-block">Enter Workspace <i class="fa fa-arrow-right"></i></a>
                </div>
            </div>
        </div>
    @empty
        <div class="col-md-12">
            <div class="alert alert-info text-center">
                <h4>No active initiatives found.</h4>
                <p>Click "Launch New Initiative" to begin tracking a project or revenue budget.</p>
            </div>
        </div>
    @endforelse
</div>
@stop