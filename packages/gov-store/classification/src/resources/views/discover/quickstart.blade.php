@extends('layouts/default')
@section('title', 'Welcome to GovStore Catalog')

@section('content')
<div class="row">
    <div class="col-md-8 col-md-offset-2 text-center" style="margin-top: 50px;">
        <i class="fas fa-cubes fa-5x text-blue" style="margin-bottom: 20px; opacity: 0.8;"></i>
        <h1 style="font-weight: 300; margin-bottom: 10px;">Welcome to the Global Catalog</h1>
        <p class="lead text-muted" style="margin-bottom: 40px;">
            Your office currently has no operational categories adopted. <br>
            To begin tracking assets, you must first provision your catalog. How would you like to start?
        </p>
    </div>
</div>

<div class="row">
    <div class="col-md-8 col-md-offset-2">
        
        <!-- Option 1: Collections (Hero Action) -->
        <a href="{{ route('gov.catalog.discover.collections') }}" style="display: block; text-decoration: none; color: inherit;">
            <div class="info-box" style="border: 2px solid #00a65a; border-radius: 8px; transition: transform 0.2s; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <span class="info-box-icon bg-green" style="border-radius: 6px 0 0 6px;"><i class="fas fa-layer-group"></i></span>
                <div class="info-box-content" style="padding-top: 15px;">
                    <span class="info-box-text" style="font-size: 18px; font-weight: bold; color: #00a65a; text-transform: none;">Use a Standard Collection (Recommended)</span>
                    <span class="info-box-number" style="font-size: 14px; font-weight: normal; color: #555;">Pick curated bundles like "Hospital Equipment" or "ICT Office Setup" to instantly load dozens of standard categories.</span>
                </div>
            </div>
        </a>

        <!-- Option 2: Office Copy -->
        <a href="{{ route('gov.catalog.adopt.copy') }}" style="display: block; text-decoration: none; color: inherit; margin-top: 20px;">
            <div class="info-box" style="border: 1px solid #d2d6de; border-radius: 8px; transition: transform 0.2s;">
                <span class="info-box-icon bg-gray" style="border-radius: 6px 0 0 6px;"><i class="fas fa-copy"></i></span>
                <div class="info-box-content" style="padding-top: 15px;">
                    <span class="info-box-text" style="font-size: 18px; font-weight: bold; color: #333; text-transform: none;">Copy Another Office</span>
                    <span class="info-box-number" style="font-size: 14px; font-weight: normal; color: #777;">Clone the exact catalog structure of a similar office within your Ministry.</span>
                </div>
            </div>
        </a>

        <!-- Option 3: Manual Explorer -->
        <a href="{{ route('gov.catalog.discover.explorer') }}" style="display: block; text-decoration: none; color: inherit; margin-top: 20px;">
            <div class="info-box" style="border: 1px solid #d2d6de; border-radius: 8px; transition: transform 0.2s;">
                <span class="info-box-icon bg-gray" style="border-radius: 6px 0 0 6px;"><i class="fas fa-folder-tree"></i></span>
                <div class="info-box-content" style="padding-top: 15px;">
                    <span class="info-box-text" style="font-size: 18px; font-weight: bold; color: #333; text-transform: none;">Browse the Explorer</span>
                    <span class="info-box-number" style="font-size: 14px; font-weight: normal; color: #777;">Manually navigate through the 150,000+ official UNSPSC codes folder by folder.</span>
                </div>
            </div>
        </a>

    </div>
</div>
@endsection

@section('moar_scripts')
<style>
    .info-box:hover { transform: translateY(-3px); box-shadow: 0 6px 12px rgba(0,0,0,0.15) !important; cursor: pointer; }
</style>
@endsection