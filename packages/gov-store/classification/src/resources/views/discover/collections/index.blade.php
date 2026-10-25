@extends('layouts/default')
@section('title', __('classification::collections.title'))
@section('content')
<div class="classification-theme">
<h2 class="page-header classify-inline-291b7bbb">{{ __('classification::collections.title') }}</h2>
<p class="lead text-muted">{{ __('classification::collections.intro') }}</p>

<div class="row">
    @foreach($collections as $collection)
    <div class="col-md-4 col-sm-6">
        <div class="info-box classify-inline-8bf7ca3b">
            <span class="info-box-icon classify-inline-2799c2c6"><i class="{{ $collection->icon }}"></i></span>
            <div class="info-box-content classify-inline-a64b6f2f">
                <span class="info-box-text classify-inline-7dd97374" title="{{ $collection->display_name }}">{{ $collection->display_name }}</span>
                <span class="info-box-number classify-inline-ce1b7a99">{{ __('classification::collections.count', ['count' => $collection->nodes_count]) }}</span>
                <a href="{{ route('gov.catalog.discover.collections.show', $collection->id) }}" class="btn btn-primary btn-sm classify-inline-260a102f">{{ __('classification::collections.explore') }}</a>
            </div>
        </div>
    </div>
    @endforeach
</div>
</div>
@endsection
