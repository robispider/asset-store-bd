@extends('layouts/default')
@section('title', 'Update Classification Catalog')

@section('content')

@if(session('error'))
    <div class="alert alert-danger alert-dismissible">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <h4><i class="icon fas fa-ban"></i> Error</h4>
        {{ session('error') }}
    </div>
@endif

<div class="row">
    <div class="col-md-12">
        <!-- Progress Indicator -->
        <div class="box box-default">
            <div class="box-body text-center" style="padding: 20px;">
                <div class="btn-group btn-group-lg">
                    <button class="btn btn-{{ $step == 1 ? 'primary' : 'default' }}" style="opacity: {{ $step >= 1 ? '1' : '0.5' }};"><i class="fas fa-folder-open"></i> 1. Select Catalog</button>
                    <button class="btn btn-{{ $step == 2 ? 'primary' : 'default' }}" style="opacity: {{ $step >= 2 ? '1' : '0.5' }};"><i class="fas fa-clipboard-check"></i> 2. Review</button>
                    <button class="btn btn-{{ $step == 3 ? 'success' : 'default' }}" style="opacity: {{ $step >= 3 ? '1' : '0.5' }};"><i class="fas fa-check-circle"></i> 3. Import</button>
                </div>
            </div>
        </div>

        @if($step == 1)
        <!-- ==============================================
             STEP 1: SELECT CATALOG
             ============================================== -->
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-upload"></i> Update Classification Catalog</h3>
            </div>
            <div class="box-body">
                <p class="lead text-muted" style="margin-bottom: 25px;">Import the official classification catalog. Your existing local translations, notes, and Snipe-IT category mappings will remain completely unchanged.</p>
                
                <!-- OPTION A: Bundled Dataset -->
                <div class="well" style="background-color: #f4f8fb; border-left: 4px solid #0073b7;">
                    <form action="{{ route('gov.catalog.import.validate') }}" method="POST">
                        {{ csrf_field() }}
                        <input type="hidden" name="source" value="bundle">
                        
                        <h4>Option A: Run Bundled Core Dataset <span class="label label-success pull-right">Recommended</span></h4>
                        <p class="text-muted" style="margin-bottom: 15px;">Automatically provisions the system using the pre-packaged, validated <strong>UNv260801</strong> official datasets included in this module.</p>
                        
                        <div class="row">
                            <div class="col-md-12 text-right">
                                <button type="submit" name="action" value="direct" class="btn btn-default" onclick="this.innerHTML='<i class=\'fas fa-spinner fa-spin\'></i> Importing...';">
                                    Direct Import (Skip Review)
                                </button>
                                <button type="submit" name="action" value="analyze" class="btn btn-primary" style="margin-left: 10px;" onclick="this.innerHTML='<i class=\'fas fa-spinner fa-spin\'></i> Analyzing...';">
                                    Analyze & Review <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <hr style="border-top: 2px dashed #ddd; margin: 30px 0;">

                <!-- OPTION B: Upload Custom Dataset -->
                <div class="well" style="background-color: #fcfcfc; border-left: 4px solid #d2d6de;">
                    <form id="catalog-upload-form" action="{{ route('gov.catalog.import.validate') }}" method="POST" enctype="multipart/form-data">
                        {{ csrf_field() }}
                        <input type="hidden" name="source" value="upload">
                        
                        <h4>Option B: Upload Custom Dataset</h4>
                        <p class="text-muted" style="margin-bottom: 20px;">Use this option if a newer standard is released (e.g., UNv27) or if migrating from an alternate governmental scheme (like CGA).</p>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Target Scheme</label>
                                    <input type="text" name="scheme" class="form-control" placeholder="e.g., UNSPSC or CGA" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Version Tag</label>
                                    <input type="text" name="version" class="form-control" placeholder="e.g., UNv270101" required>
                                </div>
                            </div>
                        </div>

                        <div class="row" style="margin-top: 10px;">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Official Dataset (Required) <small class="text-muted">Contains definitions and nodes.</small></label>
                                    <input type="file" name="metadata_file" id="metadata_file" class="form-control" accept=".csv" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Hierarchy Validation Dataset (Optional) <small class="text-muted">For structure verification.</small></label>
                                    <input type="file" name="tree_file" class="form-control" accept=".csv">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row" style="margin-top: 15px;">
                            <div class="col-md-12 text-right">
                                <button type="submit" name="action" value="direct" class="btn btn-default" onclick="this.innerHTML='<i class=\'fas fa-spinner fa-spin\'></i> Importing...';">
                                    Direct Import (Skip Review)
                                </button>
                                <button type="submit" name="action" value="analyze" id="btn-next" class="btn btn-primary" style="margin-left: 10px;">
                                    Analyze & Review <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @elseif($step == 2)
        <!-- ==============================================
             STEP 2: REVIEW (VALIDATION REPORT)
             ============================================== -->
        <div class="row">
            <div class="col-md-8">
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fas fa-clipboard-list"></i> Catalog Validation Report</h3>
                    </div>
                    <div class="box-body">
                        
                        <h4><i class="fas fa-file-alt text-blue"></i> Source Check</h4>
                        <ul class="list-unstyled" style="margin-left: 20px; font-size: 15px;">
                            <li><i class="fas fa-check text-success"></i> Official dataset verified</li>
                            <li><strong>Scheme:</strong> <span class="label label-primary">{{ $scheme }}</span></li>
                            <li><strong>Version:</strong> <span class="label label-default">{{ $version }}</span></li>
                            <li><strong>Source:</strong> <span class="label label-default">{{ ucfirst($source) }}</span></li>
                        </ul>

                        <hr>

                        <h4><i class="fas fa-chart-pie text-blue"></i> Import Impact</h4>
                        <div class="row text-center" style="margin-top: 15px;">
                            <div class="col-md-3">
                                <h3 class="text-green" style="margin-top: 0;">{{ number_format($report['additional']) }}</h3>
                                <p class="text-muted">New Nodes</p>
                            </div>
                            <div class="col-md-3">
                                <h3 class="text-yellow" style="margin-top: 0;">{{ number_format($report['matched']) }}</h3>
                                <p class="text-muted">Updated Nodes</p>
                            </div>
                            <div class="col-md-3">
                                <h3 class="text-blue" style="margin-top: 0;">{{ number_format($report['missing']) }}</h3>
                                <p class="text-muted">Existing Nodes</p>
                            </div>
                            <div class="col-md-3">
                                <h3 class="text-muted" style="margin-top: 0;">0</h3>
                                <p class="text-muted">Ignored Nodes</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="box box-success box-solid">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fas fa-shield-alt"></i> Data Protection</h3>
                    </div>
                    <div class="box-body" style="font-size: 15px;">
                        <p>Your local enhancements will <strong>not</strong> be overwritten by this import.</p>
                        <ul class="list-unstyled" style="margin-top: 15px;">
                            <li style="margin-bottom: 10px;"><i class="fas fa-check-circle text-green"></i> Local Translations <span class="pull-right text-muted">Protected</span></li>
                            <li style="margin-bottom: 10px;"><i class="fas fa-check-circle text-green"></i> Custom Notes <span class="pull-right text-muted">Protected</span></li>
                            <li style="margin-bottom: 10px;"><i class="fas fa-check-circle text-green"></i> Snipe-IT Mappings <span class="pull-right text-muted">Protected</span></li>
                        </ul>
                    </div>
                </div>

                <form action="{{ route('gov.catalog.import.execute') }}" method="POST">
                    {{ csrf_field() }}
                    <input type="hidden" name="source" value="{{ $source }}">
                    <input type="hidden" name="scheme" value="{{ $scheme }}">
                    <input type="hidden" name="version" value="{{ $version }}">
                    <input type="hidden" name="metaPath" value="{{ $metaPath }}">
                    <input type="hidden" name="treePath" value="{{ $treePath }}">
                    
                    <button type="submit" class="btn btn-primary btn-block btn-lg" onclick="this.innerHTML='<i class=\'fas fa-spinner fa-spin\'></i> Importing...';">
                        Update Catalog
                    </button>
                    <a href="{{ route('gov.catalog.import') }}" class="btn btn-default btn-block btn-lg" style="margin-top: 10px;">Cancel</a>
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
                <h2>Catalog Updated Successfully</h2>
                <br>
                <div class="row">
                    <div class="col-md-6 col-md-offset-3 text-left" style="font-size: 16px;">
                        <ul class="list-group">
                            <li class="list-group-item"><strong>Scheme:</strong> <span class="pull-right">{{ $scheme }}</span></li>
                            <li class="list-group-item"><strong>Version:</strong> <span class="pull-right">{{ $version }}</span></li>
                            <li class="list-group-item"><strong>Imported Nodes:</strong> <span class="pull-right">{{ number_format($results['nodes']) }}</span></li>
                            <li class="list-group-item"><strong>Definitions & Metadata:</strong> <span class="pull-right">{{ number_format($results['meta']) }}</span></li>
                            <li class="list-group-item"><strong>Completed in:</strong> <span class="pull-right">{{ $results['time'] }} sec</span></li>
                        </ul>
                    </div>
                </div>
                
                <div style="margin-top: 30px;">
                    <a href="{{ route('gov.catalog.dashboard') }}" class="btn btn-primary btn-lg" style="margin-right: 15px;">View Catalog</a>
                    <a href="{{ route('gov.catalog.history') }}" class="btn btn-default btn-lg">View History</a>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

@push('js')
<script>
    // 419 Prevention Guard for Uploaded files
    document.getElementById('catalog-upload-form')?.addEventListener('submit', function(e) {
        const fileInput = document.getElementById('metadata_file');
        if (fileInput && fileInput.files.length > 0) {
            const fileSizeMB = fileInput.files[0].size / (1024 * 1024);
            if (fileSizeMB > 8) {
                const confirmed = confirm("Warning: The file you selected is " + fileSizeMB.toFixed(1) + "MB. \n\nIf your server configuration limits POST sizes, the upload will fail. \n\nDo you want to continue?");
                if (!confirmed) {
                    e.preventDefault();
                    return false;
                }
            }
        }
        document.getElementById('btn-next').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analyzing...';
    });
</script>
@endpush