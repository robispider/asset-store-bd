@extends('layouts/default')

@section('title', 'Metadata Platform Health')

@section('content')
<div class="row">
    <div class="col-md-12">
        <!-- Display Success Redirect Messages from Controller -->
        @if (session('success'))
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                <h4><i class="icon fa fa-check"></i> Process Dispatched!</h4>
                {{ session('success') }}
            </div>
        @endif

        @if ($report->healthScore < 100)
            <div class="callout callout-warning">
                <h4>Schema Mismatches Detected!</h4>
                <p>One or more active asset models are out of sync with your metadata providers. Click the button below to schedule a safe background migration to align your database models.</p>
                
                <!-- Trigger Form -->
                <form action="{{ route('gov.meta.converge.trigger') }}" method="POST" style="margin-top: 10px;">
                    @csrf
                    <button type="submit" class="btn btn-warning">
                        <i class="fa fa-refresh"></i> Queue Schema Convergence
                    </button>
                </form>
            </div>
        @endif

        <div class="box box-default">
            <div class="box-header with-border">
                <h2 class="box-title">System Status: 
                    @if ($report->healthScore === 100)
                        <span class="label label-success">Healthy ({{ $report->healthScore }}%)</span>
                    @elseif ($report->healthScore >= 80)
                        <span class="label label-warning">Warning ({{ $report->healthScore }}%)</span>
                    @else
                        <span class="label label-danger">Critical ({{ $report->healthScore }}%)</span>
                    @endif
                </h2>
            </div>

            <div class="box-body">
                <div class="row">
                    <div class="col-md-4">
                        <h3>Compliance Overview</h3>
                        <table class="table">
                            <tr><td>Total Models:</td><td><strong>{{ $report->totalModels }}</strong></td></tr>
                            <tr><td>Compliant Models:</td><td><strong class="text-green">{{ $report->compliantModels }}</strong></td></tr>
                            <tr><td>Requires Convergence:</td><td><strong class="text-red">{{ $report->nonCompliantModels }}</strong></td></tr>
                        </table>
                    </div>
                    <div class="col-md-8">
                        <h3>Loaded Metadata Providers</h3>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Provider Name</th>
                                    <th>Active Version</th>
                                    <th>Fields Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($report->providers as $provider)
                                <tr>
                                    <td><strong>{{ $provider['name'] }}</strong></td>
                                    <td><span class="label label-info">{{ $provider['version'] }}</span></td>
                                    <td>{{ $provider['fields_count'] }} fields</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                @if (!empty($report->nonCompliantModelDetails))
                <div class="row" style="margin-top: 20px;">
                    <div class="col-md-12">
                        <h3 class="text-orange">Non-Compliant Models</h3>
                        <ul class="list-group">
                            @foreach ($report->nonCompliantModelDetails as $model)
                            <li class="list-group-item">
                                [ID: {{ $model['id'] }}] <strong>{{ $model['name'] }}</strong>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@stop