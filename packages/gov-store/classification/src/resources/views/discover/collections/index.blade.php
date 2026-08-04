@extends('layouts/default')
@section('title', 'Discover Collections')
@section('content')
<h2 class="page-header" style="margin-top:0;">Standard Catalog Collections</h2>
<p class="lead text-muted">Curated bundles of categories designed for specific office types.</p>

<div class="row">
    @foreach($collections as $collection)
    <div class="col-md-4 col-sm-6">
        <div class="info-box" style="border-top: 3px solid #3c8dbc;">
            <span class="info-box-icon" style="background: transparent; color: #3c8dbc; font-size: 45px;"><i class="{{ $collection->icon }}"></i></span>
            <div class="info-box-content" style="padding-top: 15px;">
                <span class="info-box-text" style="font-size: 16px; font-weight: bold; color: #333; text-transform: none;">{{ $collection->name }}</span>
                <span class="info-box-number" style="font-size: 14px; font-weight: normal; color: #777;">{{ $collection->nodes_count }} Categories</span>
                <a href="{{ route('gov.catalog.discover.collections.show', $collection->id) }}" class="btn btn-primary btn-sm" style="margin-top: 10px; width: 100%;">Explore & Adopt</a>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endsection