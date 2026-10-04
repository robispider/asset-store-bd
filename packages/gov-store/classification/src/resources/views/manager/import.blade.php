@extends('layouts/default')
@section('title', __('classification::texts.import_title'))

@section('content')

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
            <div class="box-body text-center" style="padding: 15px;">
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
            <div class="box-body" style="padding: 20px;">
                <p class="lead text-muted" style="margin-bottom: 25px;">{{ __('classification::texts.import_lead_desc') }}</p>
                
                <!-- OPTION A: Bundled Dataset -->
                <div class="well" style="background-color: #f4f8fb; border-left: 4px solid #0073b7;">
                    <form method="POST">
                        {{ csrf_field() }}
                        <input type="hidden" name="source" value="bundle">
                        <input type="hidden" name="scheme" value="UNSPSC">
                        <input type="hidden" name="version" value="UNv260801">
                        
                        <h4>{{ __('classification::texts.import_option_a_title') }} <span class="label label-success pull-right">{{ __('classification::texts.import_option_a_recommended') }}</span></h4>
                        <p class="text-muted" style="margin-bottom: 15px;">{{ __('classification::texts.import_option_a_desc') }}</p>
                        
                        <div class="row">
                            <div class="col-md-12 text-right">
                                <!-- Analyze & Review posts to the validate route -->
                                <button type="submit" formaction="{{ route('gov.catalog.import.validate') }}" class="btn btn-primary" style="margin-left: 10px;">
                                    {{ __('classification::texts.import_btn_analyze_review') }} <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <hr style="border-top: 2px dashed #ddd; margin: 30px 0;">

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
                    <div class="box-body" style="padding: 20px;">
                        
                        <h4><i class="fas fa-file-alt text-blue"></i> Source Verification</h4>
                        <ul class="list-unstyled" style="margin-left: 20px; font-size: 15px; line-height: 1.8;">
                            <li><i class="fas fa-check-circle text-success"></i> {{ __('classification::texts.import_validation_datasets_found') }}</li>
                            <li><strong>{{ __('classification::texts.import_validation_scheme_name') }}</strong> <span class="label label-primary">{{ $scheme }}</span></li>
                            <li><strong>{{ __('classification::texts.import_validation_release_tag') }}</strong> <span class="label label-default">{{ $version }}</span></li>
                            <li><strong>{{ __('classification::texts.import_validation_source') }}</strong> <span class="label label-default">{{ ucfirst($source) }}</span></li>
                        </ul>

                        <hr>

                        <h4><i class="fas fa-chart-pie text-blue"></i> {{ __('classification::texts.import_validation_impact_title') }}</h4>
                        <div class="row text-center" style="margin-top: 20px;">
                            <div class="col-md-4">
                                <h3 class="text-green" style="margin-top: 0; font-weight: bold; font-size: 28px;">{{ number_format($report['additional']) }}</h3>
                                <p class="text-muted" style="font-size: 14px;">{{ __('classification::texts.import_validation_new_nodes') }}</p>
                            </div>
                            <div class="col-md-4">
                                <h3 class="text-yellow" style="margin-top: 0; font-weight: bold; font-size: 28px;">{{ number_format($report['matched']) }}</h3>
                                <p class="text-muted" style="font-size: 14px;">{{ __('classification::texts.import_validation_existing_update') }}</p>
                            </div>
                            <div class="col-md-4">
                                <h3 class="text-blue" style="margin-top: 0; font-weight: bold; font-size: 28px;">{{ number_format($report['missing']) }}</h3>
                                <p class="text-muted" style="font-size: 14px;">{{ __('classification::texts.import_validation_missing_nodes') }}</p>
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
                    <div class="box-body" style="font-size: 14px; line-height: 1.6;">
                        <p>This import is strictly additive and protective. The following localized database sections are <strong>locked and safe</strong> from being overwritten:</p>
                        <ul class="list-unstyled" style="margin-top: 15px;">
                            <li style="margin-bottom: 10px;"><i class="fas fa-check-circle text-green"></i> {{ __('classification::texts.import_protection_bangla') }} <span class="pull-right label label-success">{{ __('classification::texts.import_protection_safe') }}</span></li>
                            <li style="margin-bottom: 10px;"><i class="fas fa-check-circle text-green"></i> {{ __('classification::texts.import_protection_local_notes') }} <span class="pull-right label label-success">{{ __('classification::texts.import_protection_safe') }}</span></li>
                            <li style="margin-bottom: 10px;"><i class="fas fa-check-circle text-green"></i> {{ __('classification::texts.import_protection_mappings') }} <span class="pull-right label label-success">{{ __('classification::texts.import_protection_safe') }}</span></li>
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
                    <a href="{{ route('gov.catalog.import') }}" class="btn btn-default btn-block btn-lg" style="margin-top: 10px;">{{ __('classification::texts.import_btn_cancel') }}</a>
                </form>
            </div>
        </div>

        @elseif($step == 3)
        <!-- ==============================================
             STEP 3: SUCCESS SUMMARY
             ============================================== -->
        <div class="box box-success">
            <div class="box-body text-center" style="padding: 50px 20px;">
                <i class="fas fa-check-circle text-success" style="font-size: 80px; margin-bottom: 20px;"></i>
                <h2>{{ __('classification::texts.import_success_title') }}</h2>
                <br>
                <div class="row">
                    <div class="col-md-6 col-md-offset-3 text-left" style="font-size: 16px;">
                        <ul class="list-group">
                            <li class="list-group-item"><strong>{{ __('classification::texts.import_success_catalog_scheme') }}</strong> <span class="pull-right">{{ $scheme }}</span></li>
                            <li class="list-group-item"><strong>{{ __('classification::texts.import_success_release_tag') }}</strong> <span class="pull-right">{{ $version }}</span></li>
                            <li class="list-group-item"><strong>{{ __('classification::texts.import_success_imported_nodes') }}</strong> <span class="pull-right text-success" style="font-weight: bold;">{{ number_format($results['nodes']) }}</span></li>
                            <li class="list-group-item"><strong>{{ __('classification::texts.import_success_enriched_defs') }}</strong> <span class="pull-right">{{ number_format($results['defs']) }}</span></li>
                            <li class="list-group-item"><strong>{{ __('classification::texts.import_success_mapped_synonyms') }}</strong> <span class="pull-right">{{ number_format($results['syns']) }}</span></li>
                            <li class="list-group-item"><strong>{{ __('classification::texts.import_success_execution_time') }}</strong> <span class="pull-right">{{ $results['time'] }} sec</span></li>
                        </ul>
                    </div>
                </div>
                
                <div style="margin-top: 40px;">
                    <a href="{{ route('gov.catalog.dashboard') }}" class="btn btn-primary btn-lg" style="margin-right: 15px;">
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
@endsection