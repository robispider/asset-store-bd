@extends('layouts/default')
@section('title', 'Product Rules Studio')

@section('content')
<style>
    .studio-container { display: flex; background: #fff; border: 1px solid #ddd; min-height: 600px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .studio-left-pane { width: 320px; border-right: 1px solid #eee; background: #f9f9f9; padding: 20px 0; overflow-y: auto; }
    .studio-right-pane { flex: 1; background: #fff; }
    .tree-group { font-weight: bold; padding: 10px 20px; color: #555; background: #f1f1f1; border-top: 1px solid #ddd; border-bottom: 1px solid #ddd; margin-top: 10px; cursor: pointer; }
    .tree-group:first-child { margin-top: 0; border-top: none; }
    .tree-item { padding: 8px 30px; cursor: pointer; color: #3c8dbc; border-bottom: 1px dashed #eee; transition: background 0.1s; font-size: 14px; }
    .tree-item:hover { background: #eef5f9; }
    .tree-item.active { background: #3c8dbc; color: #fff; font-weight: bold; }
    
    .dictionary-card { border: 1px solid #eee; padding: 15px; border-radius: 4px; margin-bottom: 15px; background: #fafafa; }
    .dict-title { font-size: 16px; font-weight: bold; color: #333; margin: 0 0 5px 0; }
    .dict-badge { font-size: 11px; margin-left: 10px; padding: 3px 6px; }
    .dict-desc { color: #666; margin-top: 10px; font-size: 13px; line-height: 1.5; }
</style>

<div class="row">
    <div class="col-md-12">
        <div class="studio-container">
            
            <!-- LEFT PANE: The Category Tree -->
            <div class="studio-left-pane">
                <div style="padding: 0 20px 15px 20px; text-align: center; border-bottom: 1px solid #eee;">
                    <h4 style="margin: 0; color: #333; font-weight: bold;">Product Tree</h4>
                    <small class="text-muted">Select a category to inspect.</small>
                </div>
                
                @foreach($categoryTree as $groupName => $categories)
                    @if($categories->count() > 0)
                        <!-- FIXED: Used fully qualified \Illuminate\Support\Str::slug -->
                        <div class="tree-group" data-toggle="collapse" data-target="#group_{{ \Illuminate\Support\Str::slug($groupName) }}">
                            <i class="fas fa-caret-down"></i> {{ $groupName }}
                        </div>
                        <div id="group_{{ \Illuminate\Support\Str::slug($groupName) }}" class="collapse in">
                            @foreach($categories as $cat)
                                <div class="tree-item" data-id="{{ $cat->id }}">
                                    <i class="fas fa-box" style="opacity: 0.5; margin-right: 5px;"></i> {{ $cat->name }}
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </div>

            <!-- RIGHT PANE: Tabs & Content -->
            <div class="studio-right-pane">
                <div class="nav-tabs-custom" style="box-shadow: none; margin-bottom: 0;">
                    <ul class="nav nav-tabs">
                        <li class="active"><a href="#tab_inspector" data-toggle="tab"><i class="fas fa-search"></i> Policy Inspector</a></li>
                        <li><a href="#tab_dictionary" data-toggle="tab"><i class="fas fa-book"></i> Behaviors Dictionary</a></li>
                    </ul>
                    
                    <div class="tab-content" style="padding: 30px;">
                        
                        <!-- TAB 1: Policy Inspector (AJAX Loaded) -->
                        <div class="tab-pane active" id="tab_inspector">
                            <div class="text-center text-muted" style="margin-top: 100px;">
                                <i class="fas fa-mouse-pointer fa-3x" style="opacity: 0.3; margin-bottom: 15px;"></i>
                                <h4>Select a product category from the left menu to inspect its rules.</h4>
                            </div>
                        </div>

                        <!-- TAB 2: Behaviors Dictionary (Static Glossary) -->
                        <div class="tab-pane" id="tab_dictionary">
                            <h3 style="margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 10px;">System Behaviors Dictionary</h3>
                            <p class="text-muted" style="margin-bottom: 25px;">This glossary explains the purpose of the business rules currently available in the platform engine.</p>
                            
                            @foreach($dictionary as $code => $details)
                                <div class="dictionary-card">
                                    <div style="display: flex; align-items: center;">
                                        <h4 class="dict-title">{{ $details['name'] }}</h4>
                                        <span class="label bg-blue dict-badge">{{ $details['group'] }}</span>
                                        <span class="label bg-gray dict-badge" style="font-family: monospace;">{{ $code }}</span>
                                    </div>
                                    <p class="dict-desc">{{ $details['desc'] }}</p>
                                </div>
                            @endforeach
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- INVISIBLE FORM FOR MAPPING (Passed via AJAX Partial) -->
<div class="hidden" id="availablePoliciesData">
    <select id="masterPolicySelect" class="form-control">
        @foreach($publishedProfiles as $profile)
            <option value="{{ $profile->id }}">{{ $profile->name }}</option>
        @endforeach
    </select>
</div>
@endsection

@section('moar_scripts')
<script>
$(document).ready(function() {
    $('.tree-item').click(function() {
        // Highlight active item
        $('.tree-item').removeClass('active');
        $(this).addClass('active');

        // Automatically switch to Inspector Tab if they were reading the dictionary
        $('.nav-tabs a[href="#tab_inspector"]').tab('show');

        // Fetch inspector details
        let categoryId = $(this).data('id');
        $('#tab_inspector').html('<div class="text-center" style="margin-top: 100px;"><i class="fas fa-spinner fa-spin fa-3x text-blue"></i><p style="margin-top: 15px; color:#888;">Analyzing product rules...</p></div>');

        $.get('{{ route("storeops.admin.rules.inspector") }}', { category_id: categoryId }, function(html) {
            $('#tab_inspector').html(html);
        });
    });
});
</script>
@endsection