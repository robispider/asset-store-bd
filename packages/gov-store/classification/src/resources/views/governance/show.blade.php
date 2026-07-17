@extends('layouts/default')

@section('title', 'Category Inspection: ' . $category->name)

@section('content')
<div class="row">
    <!-- LEFT: Profile & Mapping -->
    <div class="col-md-6">
        <div class="box box-solid box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Category Profile</h3>
            </div>
            <div class="box-body">
                <table class="table table-striped">
                    <tr>
                        <th style="width: 200px;">Operational Name</th>
                        <td style="font-size: 16px;"><strong>{{ $category->name }}</strong></td>
                    </tr>
                    <tr>
                        <th>Category Type</th>
                        <td>{{ ucfirst($category->category_type) }}</td>
                    </tr>
                    <tr>
                        <th>Core Snipe-IT ID</th>
                        <td><code>{{ $category->id }}</code></td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="box box-solid box-success">
            <div class="box-header with-border">
                <h3 class="box-title">Global Master Data Mapping</h3>
            </div>
            <div class="box-body">
                @if($mapping)
                    <table class="table">
                        <tr>
                            <th style="width: 200px;">UNSPSC Code</th>
                            <td><code>{{ $mapping->code }}</code></td>
                        </tr>
                        <tr>
                            <th>Classification Title</th>
                            <td>{{ $mapping->title_en }}</td>
                        </tr>
                        <tr>
                            <th>Structural Hierarchy</th>
                            <td><span class="text-muted" style="word-break: break-all;">{{ $mapping->hid }}</span></td>
                        </tr>
                    </table>
                @else
                    <div class="alert alert-warning" style="margin-bottom: 0;">
                        <i class="fas fa-exclamation-triangle"></i> This category is an orphan and is not linked to the Global UNSPSC Catalog.
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- RIGHT: Governance & Analytics -->
    <div class="col-md-6">
        <div class="box box-solid box-warning">
            <div class="box-header with-border">
                <h3 class="box-title">Governance Details</h3>
            </div>
            <div class="box-body">
                <table class="table table-striped">
                    <tr>
                        <th style="width: 200px;">Governance Scope</th>
                        <td>
                            @if($governance && $governance->governance_type === 'global')
                                <span class="text-green"><i class="fas fa-globe"></i> Shared Government Standard</span>
                            @elseif($governance && $governance->governance_type === 'company')
                                <span class="text-orange"><i class="fas fa-building"></i> Organization Managed</span>
                            @else
                                <span class="text-muted">Unmanaged Core Asset</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>Originating Owner</th>
                        <td><strong>{{ $governance->company_name ?? 'System' }}</strong></td>
                    </tr>
                    <tr>
                        <th>Created By</th>
                        <td>{{ $governance ? ($governance->user_first_name . ' ' . $governance->user_last_name) : 'N/A' }}</td>
                    </tr>
                    <tr>
                        <th>Creation Timestamp</th>
                        <td>{{ $governance->created_at ?? $category->created_at }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="box box-solid box-info">
            <div class="box-header with-border">
                <h3 class="box-title">Live Operational Analytics</h3>
            </div>
            <div class="box-body">
                <div class="row text-center" style="margin-bottom: 15px;">
                    <div class="col-xs-6" style="border-right: 1px solid #eee;">
                        <h2 style="margin: 0; color: #3c8dbc;">{{ $stats['adoptions'] }}</h2>
                        <span class="text-muted">Organizations Adopted</span>
                    </div>
                    <div class="col-xs-6">
                        <h2 style="margin: 0; color: #00a65a;">{{ $stats['models'] }}</h2>
                        <span class="text-muted">Mapped Asset Models</span>
                    </div>
                </div>
                <table class="table table-condensed table-striped text-muted">
                    <tr><th>Active Hardware Assets</th><td class="text-right"><strong>{{ $stats['assets'] }}</strong></td></tr>
                    <tr><th>Consumables</th><td class="text-right"><strong>{{ $stats['consumables'] }}</strong></td></tr>
                    <tr><th>Accessories</th><td class="text-right"><strong>{{ $stats['accessories'] }}</strong></td></tr>
                    <tr><th>Components</th><td class="text-right"><strong>{{ $stats['components'] }}</strong></td></tr>
                    <tr><th>Licenses</th><td class="text-right"><strong>{{ $stats['licenses'] }}</strong></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection