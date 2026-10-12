@extends('layouts/default')
@section('title', 'Discover Collections')
@section('content')
<div class="classification-theme">
<h2 class="page-header classify-inline-291b7bbb">Standard Catalog Collections</h2>
<p class="lead text-muted">Curated bundles of categories designed for specific office types.</p>

<div class="row">
    @foreach($collections as $collection)
    <div class="col-md-4 col-sm-6">
        <div class="info-box classify-inline-8bf7ca3b">
            <span class="info-box-icon classify-inline-2799c2c6"><i class="{{ $collection->icon }}"></i></span>
            <div class="info-box-content classify-inline-a64b6f2f">
                <span class="info-box-text classify-inline-7dd97374">{{ $collection->name }}</span>
                <span class="info-box-number classify-inline-ce1b7a99">{{ $collection->nodes_count }} Categories</span>
                <a href="{{ route('gov.catalog.discover.collections.show', $collection->id) }}" class="btn btn-primary btn-sm" class="classify-inline-260a102f">Explore & Adopt</a>
            </div>
        </div>
    </div>
    @endforeach
</div>
</div>
@endsection