@extends('layouts/default')

@section('title', 'Lifecycle Tracking Dashboard: ' . $reference->reference_code)

@section('content')
<!-- Metric Visual Panels -->
<div class="row">
    <div class="col-md-2 col-sm-6 col-xs-12">
        <div class="info-box bg-aqua">
            <span class="info-box-icon"><i class="fa fa-bullseye"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Planned</span>
                <span class="info-box-number">{{ $metrics['planned'] }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-6 col-xs-12">
        <div class="info-box bg-orange">
            <span class="info-box-icon"><i class="fa fa-shopping-cart"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Ordered</span>
                <span class="info-box-number">{{ $metrics['ordered'] }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-6 col-xs-12">
        <div class="info-box bg-blue">
            <span class="info-box-icon"><i class="fa fa-download"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Received</span>
                <span class="info-box-number">{{ $metrics['received'] }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-6 col-xs-12">
        <div class="info-box bg-green">
            <span class="info-box-icon"><i class="fa fa-play-circle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Operational</span>
                <span class="info-box-number">{{ $metrics['deployed'] }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-6 col-xs-12">
        <div class="info-box bg-red">
            <span class="info-box-icon"><i class="fa fa-trash"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Disposed</span>
                <span class="info-box-number">{{ $metrics['disposed'] }}</span>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Progress Bars -->
    <div class="col-md-6">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">Target Progression Ratios</h3>
            </div>
            <div class="box-body">
                @php
                    $receivePercent = $metrics['planned'] > 0 ? round(($metrics['received'] / $metrics['planned']) * 100, 1) : 0;
                    $deployPercent = $metrics['planned'] > 0 ? round(($metrics['deployed'] / $metrics['planned']) * 100, 1) : 0;
                @endphp
                <h5>Received vs. Planned Targets ({{ $receivePercent }}%)</h5>
                <div class="progress">
                    <div class="progress-bar progress-bar-blue" role="progressbar" style="width: {{ $receivePercent }}%"></div>
                </div>

                <h5>Operational vs. Planned Targets ({{ $deployPercent }}%)</h5>
                <div class="progress">
                    <div class="progress-bar progress-bar-green" role="progressbar" style="width: {{ $deployPercent }}%"></div>
                </div>
            </div>
            <div class="box-footer">
                <a href="{{ route('gov.tracking.references.show', $reference->id) }}" class="btn btn-default">View Reference Profile</a>
            </div>
        </div>
    </div>

    <!-- Compile Timeline View Card -->
    <div class="col-md-6">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">Unified Tracking Timeline</h3>
            </div>
            <div class="box-body" style="max-height: 400px; overflow-y: auto;">
                <ul class="timeline timeline-inverse">
                    @forelse($timeline as $event)
                        <li>
                            @if($event['type'] === 'admin')
                                <i class="fa fa-user-shield bg-blue"></i>
                            @else
                                <i class="fa fa-exchange-alt bg-green"></i>
                            @endif

                            <div class="timeline-item">
                                <span class="time"><i class="fa fa-clock"></i> {{ $event['timestamp']->format('Y-m-d H:i') }}</span>
                                <h3 class="timeline-header">
                                    <strong>{{ $event['event_type'] }}</strong> processed by <code>{{ $event['actor_name'] }}</code>
                                </h3>
                                <div class="timeline-body">
                                    {{ $event['description'] }}
                                    @if(!empty($event['meta']['notes']))
                                        <p class="text-muted small margin-top-10"><em>Note: {{ $event['meta']['notes'] }}</em></p>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="text-center text-muted padding-20">No actions recorded for this reference yet.</li>
                    @endforelse
                    <li>
                        <i class="fa fa-clock bg-gray"></i>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
@stop
