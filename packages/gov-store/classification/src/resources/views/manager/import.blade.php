@extends('layouts/default')
@section('title', __('classification::texts.import_title'))

@section('content')
<div class="classification-theme">

@if(session('error'))
    <div class="alert alert-danger alert-dismissible">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <h4><i class="icon fas fa-ban"></i> {{ __('classification::texts.import_error_header') }}</h4>
        {{ session('error') }}
    </div>
@endif

<div class="row">
    <div class="col-md-12">
        <!-- Progress Steps Indicator -->
        <div class="box box-default">
            <div class="box-body text-center classify-inline-cfb76d2b">
                <div class="btn-group btn-group-lg">
                    <button class="btn btn-{{ $step == 1 ? 'primary' : 'default' }}" style="opacity: {{ $step >= 1 ? '1' : '0.5' }};"><i class="fas fa-folder-open"></i> {{ __('classification::texts.import_step_select') }}</button>
                    <button class="btn btn-{{ $step == 2 ? 'primary' : 'default' }}" style="opacity: {{ $step >= 2 ? '1' : '0.5' }};"><i class="fas fa-clipboard-check"></i> {{ __('classification::texts.import_step_review') }}</button>
                    <button class="btn btn-{{ $step == 3 ? 'success' : 'default' }}" style="opacity: {{ $step >= 3 ? '1' : '0.5' }};"><i class="fas fa-check-circle"></i> {{ __('classification::texts.import_step_import') }}</button>
                </div>
            </div>
        </div>

        @if($step == 1)
        <!-- ==============================================
             STEP 1: SELECT CATALOG
             ============================================== -->
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-upload"></i> {{ __('classification::texts.import_header_title') }}</h3>
            </div>
            <div class="box-body classify-inline-f8d354e0">
                <p class="lead text-muted classify-inline-39ffb623">{{ __('classification::texts.import_lead_desc') }}</p>
                
                <!-- OPTION A: Bundled Dataset -->
                <div class="well classify-inline-447fe27d">
                    <form method="POST">
                        {{ csrf_field() }}
                        <input type="hidden" name="source" value="bundle">
                        <input type="hidden" name="scheme" value="UNSPSC">
                        <input type="hidden" name="version" value="UNv260801">
                        
                        <h4>{{ __('classification::texts.import_option_a_title') }} <span class="label label-success pull-right">{{ __('classification::texts.import_option_a_recommended') }}</span></h4>
                        <p class="text-muted classify-inline-4a3180e2">{{ __('classification::texts.import_option_a_desc') }}</p>
                        
                        <div class="row">
                            <div class="col-md-12 text-right">
                                <!-- Analyze & Review posts to the validate route -->
                                <button class="btn btn-primary classify-inline-22f61d20" type="submit" formaction="{{ route('gov.catalog.import.validate') }}">
                                    {{ __('classification::texts.import_btn_analyze_review') }} <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <hr class="classify-inline-6956e980">

                <p class="text-muted">{{ __('tenantops::access.bundle_only') }}</p>
            </div>
        </div>

        @elseif($step == 2)
        <!-- ==============================================
             STEP 2: REVIEW (VALIDATION REPORT)
             ============================================= -->
        <div class="row">
            <div class="col-md-8">
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fas fa-clipboard-list"></i> Catalog Validation Report</h3>
                    </div>
                    <div class="box-body classify-inline-f8d354e0">
                        
                        <h4><i class="fas fa-file-alt text-blue"></i> Source Verification</h4>
                        <ul class="list-unstyled classify-inline-8c98a98b">
                            <li><i class="fas fa-check-circle text-success"></i> {{ __('classification::texts.import_validation_datasets_found') }}</li>
                            <li><strong>{{ __('classification::texts.import_validation_scheme_name') }}</strong> <span class="label label-primary">{{ $scheme }}</span></li>
                            <li><strong>{{ __('classification::texts.import_validation_release_tag') }}</strong> <span class="label label-default">{{ $version }}</span></li>
                            <li><strong>{{ __('classification::texts.import_validation_source') }}</strong> <span class="label label-default">{{ ucfirst($source) }}</span></li>
                        </ul>

                        <hr>

                        <h4><i class="fas fa-chart-pie text-blue"></i> {{ __('classification::texts.import_validation_impact_title') }}</h4>
                        <div class="row text-center classify-inline-5fa693f3">
                            <div class="col-md-4">
                                <h3 class="text-green classify-inline-98e67884">{{ number_format($report['additional']) }}</h3>
                                <p class="text-muted classify-inline-df67104f">{{ __('classification::texts.import_validation_new_nodes') }}</p>
                            </div>
                            <div class="col-md-4">
                                <h3 class="text-yellow classify-inline-98e67884">{{ number_format($report['matched']) }}</h3>
                                <p class="text-muted classify-inline-df67104f">{{ __('classification::texts.import_validation_existing_update') }}</p>
                            </div>
                            <div class="col-md-4">
                                <h3 class="text-blue classify-inline-98e67884">{{ number_format($report['missing']) }}</h3>
                                <p class="text-muted classify-inline-df67104f">{{ __('classification::texts.import_validation_missing_nodes') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="box box-success box-solid">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fas fa-shield-alt"></i> {{ __('classification::texts.import_protection_title') }}</h3>
                    </div>
                    <div class="box-body classify-inline-20b5bd0f">
                        <p>This import is strictly additive and protective. The following localized database sections are <strong>locked and safe</strong> from being overwritten:</p>
                        <ul class="list-unstyled classify-inline-b62ee557">
                            <li class="classify-inline-2d98adf5"><i class="fas fa-check-circle text-green"></i> {{ __('classification::texts.import_protection_bangla') }} <span class="pull-right label label-success">{{ __('classification::texts.import_protection_safe') }}</span></li>
                            <li class="classify-inline-2d98adf5"><i class="fas fa-check-circle text-green"></i> {{ __('classification::texts.import_protection_local_notes') }} <span class="pull-right label label-success">{{ __('classification::texts.import_protection_safe') }}</span></li>
                            <li class="classify-inline-2d98adf5"><i class="fas fa-check-circle text-green"></i> {{ __('classification::texts.import_protection_mappings') }} <span class="pull-right label label-success">{{ __('classification::texts.import_protection_safe') }}</span></li>
                        </ul>
                    </div>
                </div>

                <form action="{{ route('gov.catalog.import.execute') }}" method="POST">
                    {{ csrf_field() }}
                    <input type="hidden" name="source" value="{{ $source }}">
                    <input type="hidden" name="scheme" value="{{ $scheme }}">
                    <input type="hidden" name="version" value="{{ $version }}">
                    <input type="hidden" name="catalog_review_token" value="{{ $reviewToken }}">
                    
                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        {{ __('classification::texts.import_btn_update_catalog') }}
                    </button>
                    <a class="btn btn-default btn-block btn-lg classify-inline-54d32d80" href="{{ route('gov.catalog.import') }}">{{ __('classification::texts.import_btn_cancel') }}</a>
                </form>
            </div>
        </div>

        @elseif($step == 3)
        <!-- ==============================================
             STEP 3: SUCCESS SUMMARY
             ============================================== -->
        <div class="box box-success">
            <div class="box-body text-center classify-inline-9731de73">
                <i class="fas fa-check-circle text-success classify-inline-b910ce94"></i>
                <h2>{{ __('classification::texts.import_success_title') }}</h2>
                <br>
                <div class="row">
                    <div class="col-md-6 col-md-offset-3 text-left classify-inline-b87efa5b">
                        <ul class="list-group">
                            <li class="list-group-item"><strong>{{ __('classification::texts.import_success_catalog_scheme') }}</strong> <span class="pull-right">{{ $scheme }}</span></li>
                            <li class="list-group-item"><strong>{{ __('classification::texts.import_success_release_tag') }}</strong> <span class="pull-right">{{ $version }}</span></li>
                            <li class="list-group-item"><strong>{{ __('classification::texts.import_success_imported_nodes') }}</strong> <span class="pull-right text-success classify-inline-78d7af8b">{{ number_format($results['nodes']) }}</span></li>
                            <li class="list-group-item"><strong>{{ __('classification::texts.import_success_enriched_defs') }}</strong> <span class="pull-right">{{ number_format($results['defs']) }}</span></li>
                            <li class="list-group-item"><strong>{{ __('classification::texts.import_success_mapped_synonyms') }}</strong> <span class="pull-right">{{ number_format($results['syns']) }}</span></li>
                            <li class="list-group-item"><strong>{{ __('classification::texts.import_success_execution_time') }}</strong> <span class="pull-right">{{ $results['time'] }} sec</span></li>
                        </ul>
                    </div>
                </div>
                
                <div class="classify-inline-3a55a5d4">
                    <a class="btn btn-primary btn-lg classify-inline-9f0d58f6" href="{{ route('gov.catalog.dashboard') }}">
                        <i class="fas fa-sitemap"></i> {{ __('classification::texts.import_btn_view_catalog') }}
                    </a>
                    <a href="{{ route('gov.catalog.history') }}" class="btn btn-default btn-lg">
                        <i class="fas fa-history"></i> {{ __('classification::texts.import_btn_view_history') }}
                    </a>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
</div>
@endsection