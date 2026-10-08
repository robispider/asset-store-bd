@extends('layouts/default')
@section('title', __('storeops::storeops.store_documents_hub'))

@section('content')
<div class="storeops-theme">
<div class="row">
    <div class="col-md-12">
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-cubes"></i> {{ __('storeops::storeops.store_documents_hub') }}</h3>
                <div class="box-tools pull-right">
                    <form action="{{ route('storeops.documents.initialize') }}" method="POST"  class="storeops-inline-155">@csrf<input type="hidden" name="document_type" value="transfer"><x-gov-action ability="storeops.documents.draft" type="submit" class="btn btn-sm btn-default">{{ __('storeops::storeops.new_transfer') }}</x-gov-action></form>
                    <form action="{{ route('storeops.documents.initialize') }}" method="POST"  class="storeops-inline-154">
                        @csrf
                        <input type="hidden" name="document_type" value="receipt">
                        <x-gov-action ability="storeops.documents.draft" type="submit" class="btn btn-sm btn-primary"><i class="fa fa-plus"></i> {{ __('storeops::storeops.new_receipt') }}</x-gov-action>
                    </form>
                    <form action="{{ route('storeops.documents.initialize') }}" method="POST"  class="storeops-inline-153">@csrf<input type="hidden" name="document_type" value="issue"><x-gov-action ability="storeops.documents.draft" type="submit" class="btn btn-sm btn-warning"><i class="fa fa-share"></i> {{ __('storeops::storeops.new_issue') }}</x-gov-action></form>
                    <form action="{{ route('storeops.documents.initialize') }}" method="POST"  class="storeops-inline-152">@csrf<input type="hidden" name="document_type" value="adjustment"><x-gov-action ability="storeops.documents.draft" type="submit" class="btn btn-sm btn-default"><i class="fa fa-sliders"></i> {{ __('storeops::storeops.new_adjustment') }}</x-gov-action></form>
                </div>
            </div>
            <div class="box-body">
                @if($notices->isNotEmpty())
                    <div class="alert alert-info" role="status"><strong>{{ __('storeops::storeops.document_updates') }}</strong>
                        <ul>@foreach($notices as $notice)<li>{{ $notice->document_number }} — {{ __('storeops::storeops.event_'.$notice->event_key) }}</li>@endforeach</ul>
                    </div>
                @endif
                <!-- Saved Filters Panel -->
                <div class="nav-tabs-custom">
                    <ul class="nav nav-tabs">
                        <li class="{{ $filter === 'all' ? 'active' : '' }}"><a href="{{ route('storeops.hub', ['filter' => 'all']) }}">{{ __('storeops::storeops.all_documents') }}</a></li>
                        <li class="{{ $filter === 'drafts' ? 'active' : '' }}"><a href="{{ route('storeops.hub', ['filter' => 'drafts']) }}">{{ __('storeops::storeops.my_drafts') }}</a></li>
                        <li class="{{ $filter === 'posted' ? 'active' : '' }}"><a href="{{ route('storeops.hub', ['filter' => 'posted']) }}">{{ __('storeops::storeops.posted_ledger') }}</a></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane active" id="all">
                            <table class="table table-striped table-hover gs-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('storeops::storeops.document_no') }}</th>
                                        <th>{{ __('storeops::storeops.date') }}</th>
                                        <th>{{ __('storeops::storeops.source') }}</th>
                                        <th>{{ __('storeops::storeops.reference_challan') }}</th>
                                        <th>{{ __('storeops::storeops.operator') }}</th>
                                        <th>{{ __('storeops::storeops.status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($documents as $doc)
                                    <tr>
                                        <td>
                                            <a href="{{ route('storeops.documents.workspace', ['type' => $doc->type, 'id' => $doc->id]) }}">
                                                <strong>{{ $doc->getDocumentNumber() }}</strong>
                                            </a>
                                        </td>
                                        <td>{{ $doc->created_at->format('d M Y') }}</td>
                                        <td>{{ $doc->purchase_type ? __('storeops::storeops.receiving_sources.'.$doc->purchase_type) : __('storeops::storeops.standard') }}</td>
                                        <td>{{ $doc->references->map(fn ($ref) => $ref->reference_number)->implode(' / ') ?: '—' }}</td>
                                        <td>{{ $doc->creator?->present()->fullName ?? __('storeops::storeops.system') }}</td>
                                        <td>
                                            <span class="label label-default">{{ __('storeops::storeops.'.strtolower($doc->status)) }}</span>
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
</div>
@endsection
