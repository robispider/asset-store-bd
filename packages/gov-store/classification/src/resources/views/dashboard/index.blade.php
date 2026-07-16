@extends('layouts/default')

@section('title')
    {{ __('admin/general/global_catalog_dashboard') }}
@endsection

@section('content')
<div class="row">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                
                <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('general.dashboard') }}</a></li>
                <li class="breadcrumb-item active">{{ __('admin/general/global_catalog_dashboard') }}</li>
            </ol>
        </nav>

        <!-- Dashboard Stats -->
        <div class="row">
            <div class="col-md-3 col-sm-6 col-xs-12">
                <div class="small-box bg-aqua">
                    <div class="inner">
                        <h3>{{ __('admin/general.total_nodes') }}</h3>
                        <p>{{ __('admin/general.catalog_nodes') }}</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-sitemap"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-3 col-sm-6 col-xs-12">
                <div class="small-box bg-green">
                    <div class="inner">
                        <h3>{{ __('admin/general.mapped_count') }}</h3>
                        <p>{{ __('admin/general.snipe_mapped') }}</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-link"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-3 col-sm-6 col-xs-12">
                <div class="small-box bg-yellow">
                    <div class="inner">
                        <h3>{{ __('admin/general.unmapped_count') }}</h3>
                        <p>{{ __('admin/general.not_mapped') }}</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-3 col-sm-6 col-xs-12">
                <div class="small-box bg-red">
                    <div class="inner">
                        <h3>{{ __('admin/general.import_count') }}</h3>
                        <p>{{ __('admin/general.total_imports') }}</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-history"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <i class="fas fa-bolt"></i>
                    {{ __('admin/general.quick_actions') }}
                </h3>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-4">
                        <a href="{{ route('gov.catalog.search') }}" class="btn btn-primary btn-block btn-lg">
                            <i class="fas fa-search"></i> {{ __('admin/general.search_catalog') }}
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="#" class="btn btn-info btn-block btn-lg">
                            <i class="fas fa-upload"></i> {{ __('admin/general.import_data') }}
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="#" class="btn btn-success btn-block btn-lg">
                            <i class="fas fa-cog"></i> {{ __('admin/general.settings') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
