@extends('layouts/default')
@section('title', 'Welcome to GovStore Catalog')

@section('content')
<div class="classification-theme">
<div class="row">
    <div class="col-md-8 col-md-offset-2 text-center classify-inline-73011761">
        <i class="fas fa-cubes fa-5x text-blue classify-inline-ee64813d"></i>
        <h1 class="classify-inline-78def582">Welcome to the Global Catalog</h1>
        <p class="lead text-muted classify-inline-bf952bd8">
            Your office currently has no operational categories adopted. <br>
            To begin tracking assets, you must first provision your catalog. How would you like to start?
        </p>
    </div>
</div>

<div class="row">
    <div class="col-md-8 col-md-offset-2">
        
        <!-- Option 1: Collections (Hero Action) -->
        <a href="{{ route('gov.catalog.discover.collections') }}" class="classify-inline-e1dfa86c">
            <div class="info-box classify-inline-44cb201c">
                <span class="info-box-icon bg-green classify-inline-82879019"><i class="fas fa-layer-group"></i></span>
                <div class="info-box-content classify-inline-a64b6f2f">
                    <span class="info-box-text classify-inline-06960b4a">Use a Standard Collection (Recommended)</span>
                    <span class="info-box-number classify-inline-f7e65b7a">Pick curated bundles like "Hospital Equipment" or "ICT Office Setup" to instantly load dozens of standard categories.</span>
                </div>
            </div>
        </a>

        <!-- Option 2: Office Copy -->
        <a href="{{ route('gov.catalog.adopt.copy') }}" class="classify-inline-425eb784">
            <div class="info-box classify-inline-5c4464a8">
                <span class="info-box-icon bg-gray classify-inline-82879019"><i class="fas fa-copy"></i></span>
                <div class="info-box-content classify-inline-a64b6f2f">
                    <span class="info-box-text classify-inline-ef0f2298">Copy Another Office</span>
                    <span class="info-box-number classify-inline-ce1b7a99">Clone the exact catalog structure of a similar office within your Ministry.</span>
                </div>
            </div>
        </a>

        <!-- Option 3: Manual Explorer -->
        <a href="{{ route('gov.catalog.discover.explorer') }}" class="classify-inline-425eb784">
            <div class="info-box classify-inline-5c4464a8">
                <span class="info-box-icon bg-gray classify-inline-82879019"><i class="fas fa-folder-tree"></i></span>
                <div class="info-box-content classify-inline-a64b6f2f">
                    <span class="info-box-text classify-inline-ef0f2298">Browse the Explorer</span>
                    <span class="info-box-number classify-inline-ce1b7a99">Manually navigate through the 150,000+ official UNSPSC codes folder by folder.</span>
                </div>
            </div>
        </a>

    </div>
</div>
</div>
@endsection

@section('moar_scripts')

@endsection