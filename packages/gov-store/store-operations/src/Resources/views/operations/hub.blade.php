@extends('layouts/default')
@section('title', 'Operational Hub')

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-cubes"></i> Store Documents Hub</h3>
                <div class="box-tools pull-right">
                    <form action="{{ route('storeops.documents.initialize') }}" method="POST" style="display:inline;">
                        @csrf
                        <input type="hidden" name="document_type" value="receipt">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fa fa-plus"></i> New Goods Receipt</button>
                    </form>
                </div>
            </div>
            <div class="box-body">
                <!-- Saved Filters Panel -->
                <div class="nav-tabs-custom">
                    <ul class="nav nav-tabs">
                        <li class="active"><a href="#all" data-toggle="tab">All Documents</a></li>
                        <li><a href="#drafts" data-toggle="tab">My Drafts</a></li>
                        <li><a href="#posted" data-toggle="tab">Posted (Ledger)</a></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane active" id="all">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Document No</th>
                                        <th>Date</th>
                                        <th>Source</th>
                                        <th>Reference (Challan)</th>
                                        <th>Operator</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($documents as $doc)
                                    <tr>
                                        <td>
                                            <a href="{{ route('storeops.documents.workspace', ['type' => 'receipt', 'id' => $doc->id]) }}">
                                                <strong>{{ $doc->getDocumentNumber() }}</strong>
                                            </a>
                                        </td>
                                        <td>{{ $doc->created_at->format('d M Y') }}</td>
                                        <td>{{ $doc->purchase_type ?? 'Standard' }}</td>
                                        <td>{{ $doc->reference_no ?? 'N/A' }}</td>
                                        <td>{{ $doc->creator->present()->fullName ?? 'System' }}</td>
                                        <td>
                                            @if($doc->getState()->value === 'DRAFT')
                                                <span class="label label-default">Draft</span>
                                            @elseif($doc->getState()->value === 'POSTED')
                                                <span class="label label-success">Posted</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            {{ $documents->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection