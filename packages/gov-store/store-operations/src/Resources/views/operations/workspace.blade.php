@extends('layouts/default')
@section('title', __('storeops::storeops.workspace') . ' - ' . $document->getDocumentNumber())

@section('content')
<div class="storeops-theme">
<link rel="stylesheet" href="{{ url('css/dist/store-operations.css') }}">
@php
    $isDraft = $document->getStatus() === 'DRAFT' && app(\GovStore\TenantScope\Services\GovAccess::class)->permitsRequest(auth()->user(), 'storeops.documents.draft');
    $isReadOnly = !$isDraft;
    $isPosted = $document->getStatus() === 'POSTED';
    $mathDirection = $document->getDocumentType() === 'receipt' ? '+' : '-';
    $lineSectionTitle = match ($document->getDocumentType()) { 'issue' => __('storeops::storeops.issued_items'), 'adjustment' => __('storeops::storeops.adjusted_items'), 'transfer' => __('storeops::storeops.transferred_items'), default => __('storeops::storeops.received_items') };
@endphp

@if($isReadOnly)
<div class="alert alert-info" role="status">{{ __('tenantops::access.read_only') }}</div>
@endif
<div id="gov-access-inline" class="alert alert-warning" role="alert" hidden></div>
@if(!$ledgerOpen && in_array($document->status, ['DRAFT', 'READY'], true))
    <div class="alert alert-info" role="status">{{ __('storeops::storeops.opening_required_help') }}</div>
@endif
<div class="row">
    <!-- Main Form: Wraps the workspace for integrated draft saves and posting -->
    <form id="workspaceForm" action="{{ route('storeops.documents.post', ['type' => $type, 'id' => $document->id]) }}" method="POST">
        @csrf
        <input type="hidden" name="document_type" value="{{ $document->getDocumentType() }}">

        <!-- LEFT COLUMN: The Working Area -->
        <div class="col-md-8">

            <!-- SECTION 1: Administrative Details & References -->
            <!-- SECTION 1: Administrative Details & References -->
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('storeops::storeops.administrative_details') }}</h3>
                </div>
                <div class="box-body">

                    @if($document->type === 'transfer')
                    <div class="form-group">
                        <label for="destination_location_id">{{ __('storeops::storeops.destination_office') }}</label>
                        <select id="destination_location_id" name="destination_location_id" class="form-control" required {{ $isReadOnly ? 'disabled' : '' }}>
                            <option value="">{{ __('storeops::storeops.select_destination_office') }}</option>
                            @foreach($transferOffices as $office)
                                <option value="{{ $office->id }}" @selected((int) $document->destination_location_id === (int) $office->id)>{{ $office->name }}</option>
                            @endforeach
                        </select>
                        <p class="help-block">{{ __('storeops::storeops.transfer_authority_help') }}</p>
                        @if($transferOffices->isEmpty() && $isDraft)
                            <p class="alert alert-info" role="status">{{ __('storeops::storeops.no_transfer_offices') }}</p>
                        @endif
                    </div>
                    <div class="form-group">
                        <label for="transfer_reason">{{ __('storeops::storeops.transfer_reason') }}</label>
                        <textarea id="transfer_reason" name="transfer_reason" class="form-control" required minlength="5" maxlength="500" {{ $isReadOnly ? 'readonly' : '' }}>{{ $document->transfer_reason }}</textarea>
                        <p class="help-block">{{ __('storeops::storeops.transfer_save_help') }}</p>
                    </div>
                    @elseif($document->type === 'adjustment')
                    <div class="row storeops-inline-223" >
                        <div class="col-md-6 form-group">
                            <label>{{ __('storeops::storeops.reason') }}</label>
                            <select name="adjustment_reason" class="form-control" {{ $isReadOnly ? 'disabled' : '' }}>
                                @foreach(['PHYSICAL_COUNT' => __('storeops::storeops.physical_count'), 'DAMAGE' => __('storeops::storeops.damage'), 'LOSS' => __('storeops::storeops.loss'), 'EXPIRED' => __('storeops::storeops.expired'), 'CORRECTION' => __('storeops::storeops.correction')] as $key => $label)
                                    <option value="{{ $key }}" @selected($document->adjustment_reason === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>{{ __('storeops::storeops.source_document') }}</label>
                            <select name="source_document_id" class="form-control" {{ $isReadOnly ? 'disabled' : '' }}>
                                <option value="">{{ __('storeops::storeops.select_posted_document') }}</option>
                                @foreach($adjustmentSources as $source)
                                    <option value="{{ $source->id }}" @selected($document->source_document_id === $source->id)>{{ $source->document_number }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @elseif($document->type === 'issue')
                    <div class="row storeops-inline-222" >
                        <div class="col-md-6 form-group">
                            <label>{{ __('storeops::storeops.issue_member') }}</label>
                            <select name="issued_to_user_id" class="form-control" {{ $isReadOnly ? 'disabled' : '' }}>
                                <option value="">{{ __('storeops::storeops.select_office_member') }}</option>
                                @foreach($officeRecipients as $recipient)
                                    <option value="{{ $recipient->id }}" @selected((int) $document->issued_to_user_id === (int) $recipient->id)>{{ trim($recipient->first_name.' '.$recipient->last_name) ?: $recipient->username }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>{{ __('storeops::storeops.or_department') }}</label>
                            <input type="text" name="issue_department" value="{{ $document->issue_department }}" class="form-control" maxlength="150" {{ $isReadOnly ? 'readonly' : '' }}>
                        </div>
                    </div>
                    @else
                    <div class="row storeops-inline-221" >
                        <div class="col-md-12 form-group">
                            <label  class="storeops-inline-220">{{ __('storeops::storeops.receiving_source') }}</label>
                            <select name="purchase_type" class="form-control storeops-inline-219" {{ $isReadOnly ? 'disabled' : '' }} >
                                <option value="Purchase" {{ $document->purchase_type == 'Purchase' ? 'selected' : '' }}>{{ __('storeops::storeops.standard_purchase') }}</option>
                                <option value="Transfer" {{ $document->purchase_type == 'Transfer' ? 'selected' : '' }}>{{ __('storeops::storeops.office_transfer') }}</option>
                                <option value="Donation" {{ $document->purchase_type == 'Donation' ? 'selected' : '' }}>{{ __('storeops::storeops.donation') }}</option>
                                <option value="Confiscated" {{ $document->purchase_type == 'Confiscated' ? 'selected' : '' }}>{{ __('storeops::storeops.confiscated') }}</option>
                            </select>
                        </div>
                    </div>
                    @endif

                    @if($document->type === 'receipt')
                    <div class="form-group">
                        <label for="supplier_id">{{ __('storeops::storeops.supplier') }}</label>
                        <select id="supplier_id" name="supplier_id" class="form-control" {{ $isReadOnly ? 'disabled' : '' }}>
                            <option value="">{{ __('storeops::storeops.select_supplier') }}</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((int) $document->supplier_id === (int) $supplier->id)>{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                        <p class="help-block">{{ __('storeops::storeops.supplier_help') }}</p>
                    </div>
                    @php
                        // Helper to extract existing reference data for the static fields
                        $getRef = function($type) use ($document) {
                            return $document->references->where('reference_type', $type)->first();
                        };
                        $challan    = $getRef('Supplier Challan');
                        $po         = $getRef('Purchase Order');
                        $nothi      = $getRef('Nothi / Approval Letter');
                        $allocation = $getRef('Special Allocation');
                    @endphp

                    <div class="row">
                        <!-- 1. Supplier Challan -->
                        <div class="col-md-6">
                            <div class="form-group storeops-inline-218" >
                                <label  class="storeops-inline-217"><i class="fa fa-truck text-blue storeops-inline-216" ></i> {{ __('storeops::storeops.supplier_challan') }}</label>
                                <div  class="storeops-inline-215">
                                    <input type="hidden" name="references[0][reference_type]" value="Supplier Challan">
                                    <input type="text" name="references[0][reference_number]" class="form-control input-sm" placeholder="{{ __('storeops::storeops.challan_number') }}" value="{{ $challan->reference_number ?? '' }}" {{ $isReadOnly ? 'readonly' : '' }}>

                                    <!-- FIXED: Changed 'readonly' to 'disabled' to prevent calendar updates on posted documents -->
                                    <input type="date" name="references[0][reference_date]" class="form-control input-sm storeops-inline-214"  value="{{ $challan->reference_date ?? '' }}" {{ $isReadOnly ? 'disabled' : '' }} title="{{ __('storeops::storeops.optional_date') }}">
                                </div>
                            </div>
                        </div>

                        <!-- 2. Purchase Order / Tender -->
                        <div class="col-md-6">
                            <div class="form-group storeops-inline-213" >
                                <label  class="storeops-inline-212"><i class="fa fa-file-text-o text-purple storeops-inline-211" ></i> {{ __('storeops::storeops.purchase_order') }}</label>
                                <div  class="storeops-inline-210">
                                    <input type="hidden" name="references[1][reference_type]" value="Purchase Order">
                                    <input type="text" name="references[1][reference_number]" class="form-control input-sm" placeholder="{{ __('storeops::storeops.po_number') }}" value="{{ $po->reference_number ?? '' }}" {{ $isReadOnly ? 'readonly' : '' }}>

                                    <!-- FIXED: Changed 'readonly' to 'disabled' -->
                                    <input type="date" name="references[1][reference_date]" class="form-control input-sm storeops-inline-209"  value="{{ $po->reference_date ?? '' }}" {{ $isReadOnly ? 'disabled' : '' }} title="{{ __('storeops::storeops.optional_date') }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- 3. Nothi / Approval Letter -->
                        <div class="col-md-6">
                            <div class="form-group storeops-inline-208" >
                                <label  class="storeops-inline-207"><i class="fa fa-check-square-o text-green storeops-inline-206" ></i> {{ __('storeops::storeops.approval_letter') }}</label>
                                <div  class="storeops-inline-205">
                                    <input type="hidden" name="references[2][reference_type]" value="Nothi / Approval Letter">
                                    <input type="text" name="references[2][reference_number]" class="form-control input-sm" placeholder="Nothi Number" value="{{ $nothi->reference_number ?? '' }}" {{ $isReadOnly ? 'readonly' : '' }}>

                                    <!-- FIXED: Changed 'readonly' to 'disabled' -->
                                    <input type="date" name="references[2][reference_date]" class="form-control input-sm storeops-inline-204"  value="{{ $nothi->reference_date ?? '' }}" {{ $isReadOnly ? 'disabled' : '' }} title="{{ __('storeops::storeops.optional_date') }}">
                                </div>
                            </div>
                        </div>

                        <!-- 4. Special Ministry Allocation -->
                        <div class="col-md-6">
                            <div class="form-group storeops-inline-203" >
                                <label  class="storeops-inline-202"><i class="fa fa-star text-yellow storeops-inline-201" ></i> {{ __('storeops::storeops.allocation_code') }}</label>
                                <div  class="storeops-inline-200">
                                    <input type="hidden" name="references[3][reference_type]" value="Special Allocation">
                                    <input type="text" id="tracking_code_input" name="references[3][reference_number]" class="form-control input-sm storeops-inline-199" placeholder="{{ __('storeops::storeops.tracking_optional') }}" value="{{ $allocation->reference_number ?? '' }}" {{ $isReadOnly ? 'readonly' : '' }} >

                                    <!-- FIXED: Changed 'readonly' to 'disabled' -->

                                </div>
                                <!-- HANDSHAKE A1 FEEDBACK CONTAINER -->
                                <div id="tracking_a1_feedback"></div>
                            </div>
                        </div>
                    </div>
                    @endif

                </div>
            </div>

            <!-- SECTION 2: Received Items (The Interactive Grid) -->
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ $lineSectionTitle }}</h3>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-bordered gs-table" id="itemsGrid">
                        <thead  class="storeops-inline-198">
                            <tr>
                                <th  class="storeops-inline-197">{{ __('storeops::storeops.item_name') }}</th>
                                <th  class="storeops-inline-196">{{ __('storeops::storeops.current_stock') }}</th>
                                <th  class="storeops-inline-195">{{ __('storeops::storeops.quantity') }}</th>
                                <th  class="storeops-inline-194">{{ __('storeops::storeops.unit_cost') }}</th>
                                <th  class="storeops-inline-193">{{ __('storeops::storeops.balance_after') }}</th>
                                @if($isDraft) <th  class="storeops-inline-192"></th> @endif
                            </tr>
                        </thead>
                        <tbody id="gridBody">
                            <!-- JS automatically inserts default search rows and metadata sub-grids here -->
                        </tbody>
                    </table>
                    @if($isDraft)
                    <div  class="storeops-inline-191">
                        <button type="button" class="btn btn-sm btn-default" id="addRowBtn">
                            <i class="fa fa-plus"></i> {{ __('storeops::storeops.add_row') }}
                        </button>
                    </div>
                    @endif
                </div>
            </div>

            <!-- SECTION 3: Supporting Documents -->
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('storeops::storeops.supporting_documents') }}</h3>
                </div>
                <div class="box-body">
                    @if($isDraft)
                        <div class="row storeops-inline-190" >
                            <div class="col-md-5">
                                <select id="attachmentCategory" class="form-control input-sm">
                                    <option value="Challan">{{ __('storeops::storeops.challan') }}</option>
                                    <option value="Invoice">{{ __('storeops::storeops.invoice') }}</option>
                                    <option value="Committee_Report">{{ __('storeops::storeops.committee_report') }}</option>
                                    <option value="Tender_WO">{{ __('storeops::storeops.work_order') }}</option>
                                    <option value="Other">{{ __('storeops::storeops.other_document') }}</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <input type="file" id="attachmentFile" class="form-control input-sm">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-sm btn-primary btn-block" id="uploadFileBtn">
                                    <i class="fa fa-upload"></i> {{ __('storeops::storeops.upload') }}
                                </button>
                            </div>
                        </div>
                    @endif

                    <ul class="list-group list-group-unbordered" id="attachmentsList">
                        @forelse($document->attachments as $file)
                            <li class="list-group-item attachment-item storeops-inline-189" data-id="{{ $file->id }}" >
                                <i class="fa fa-file-text-o text-blue"></i>
                                <a href="{{ route('storeops.documents.attachments.download', ['type' => $type, 'id' => $document->id, 'attachmentId' => $file->id]) }}" target="_blank"  class="storeops-inline-188">
                                    <strong>{{ $file->original_name }}</strong>
                                </a>
                                @if($isDraft)
                                    <button type="button" class="btn btn-xs btn-danger pull-right delete-attachment" data-id="{{ $file->id }}">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                @endif
                            </li>
                        @empty
                            <li class="list-group-item text-center text-muted storeops-inline-187" id="noAttachmentsMsg" >
                                {{ __('storeops::storeops.no_attachments') }}
                            </li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: Contextual Panel -->
        <div class="col-md-4">

            <div class="box {{ $isPosted ? 'box-success' : 'box-warning' }}">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ $isPosted ? __('storeops::storeops.posted_document') : __('storeops::storeops.draft_workspace') }}</h3>
                </div>
                <div class="box-body">
                    <h4 class="text-center storeops-inline-186" ><strong>{{ $document->getDocumentNumber() }}</strong></h4>

                    <ul class="list-group list-group-unbordered storeops-inline-185" >
                        <li class="list-group-item">
                            <b>{{ __('storeops::storeops.total_lines') }}</b> <a class="pull-right" id="sumLines">{{ $document->items->count() }}</a>
                        </li>
                        <li class="list-group-item">
                            <b>{{ __('storeops::storeops.total_quantity') }}</b> <a class="pull-right" id="sumQty">{{ $document->items->sum('quantity') }}</a>
                        </li>
                    </ul>

                    @if($isDraft)
                        @include('storeops::operations.partials.validation-checklist')

                        <x-gov-action ability="storeops.documents.draft" class="btn btn-default btn-block" id="saveDraftBtn">{{ __('tenantops::access.save') }}</x-gov-action>
                        <x-gov-action ability="storeops.documents.post" class="btn btn-primary btn-block" id="triggerPostBtn" disabled>{{ __('tenantops::access.post') }}</x-gov-action>
                        <button type="button" class="btn btn-danger btn-block" data-toggle="modal" data-target="#voidDraftModal">{{ __('storeops::storeops.void_draft') }}</button>
                    @else
                        <x-gov-action ability="storeops.documents.draft" :locked="$document->status !== 'DRAFT'" class="btn btn-default btn-block">{{ __('tenantops::access.save') }}</x-gov-action>
                        <x-gov-action ability="storeops.documents.post" :locked="!in_array($document->status, ['DRAFT', 'READY'])" class="btn btn-primary btn-block" id="triggerPostBtn">{{ __('tenantops::access.post') }}</x-gov-action>
                        <a class="btn btn-default btn-block" href="{{ route('storeops.documents.print', ['type' => $type, 'id' => $document->id]) }}" target="_blank" rel="noopener">
                            <i class="fa fa-print"></i> {{ __('storeops::storeops.print_copy') }}
                        </a>
                    @endif
                </div>
            </div>

            <p>{{ __('tenantops::access.drafted_by') }}: {{ $document->drafter?->present()->fullName ?? $document->creator?->present()->fullName ?? '—' }}</p>
            <p>{{ __('tenantops::access.posted_by') }}: {{ $document->poster?->present()->fullName ?? '—' }}</p>
            @if($isDraft && $document->managed_by !== auth()->id())
            <button type="submit" form="takeoverForm" class="btn btn-default">{{ __('tenantops::access.takeover') }}</button>
            @endif
            <!-- Activity Timeline -->
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('storeops::storeops.activity_timeline') }}</h3>
                </div>
                <div class="box-body">
                    <ul class="timeline timeline-inverse storeops-inline-184" >
                        @foreach($document->timelines()->orderBy('created_at', 'desc')->get() as $event)
                            <li>
                                <i class="fa {{ $event->state === 'POSTED' ? 'fa-lock bg-green' : 'fa-edit bg-gray' }}"></i>
                                <div class="timeline-item">
                                    <span class="time">
                                        <i class="fa fa-clock-o"></i>
                                        {{ \Carbon\Carbon::parse($event->created_at)->format('H:i') }}
                                    </span>
                                    <h3 class="timeline-header no-border">
                                        <strong>{{ __('storeops::storeops.'.strtolower($event->state)) }}</strong> — {{ $event->user?->present()->fullName ?? __('storeops::storeops.system') }}
                                    </h3>
                                    @if($event->notes)
                                        <div class="timeline-body storeops-inline-183" >{{ $event->notes }}</div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                        <li><i class="fa fa-clock-o bg-gray"></i></li>
                    </ul>
                </div>
            </div>

        </div>
    </form>
</div>

@if($isDraft && (int) $document->managed_by !== (int) auth()->id())
<form id="takeoverForm" method="post" action="{{ route('storeops.documents.takeover', ['type' => $type, 'id' => $document->id]) }}">
    @csrf
    <label for="takeoverReason">{{ __('storeops::storeops.takeover_reason') }}</label>
    <textarea id="takeoverReason" name="reason" minlength="5" maxlength="500" required class="form-control"></textarea>
</form>
@endif
@if($isDraft)
<div class="modal fade" id="voidDraftModal" tabindex="-1" role="dialog" aria-labelledby="voidDraftTitle">
    <div class="modal-dialog" role="document"><div class="modal-content">
        <form method="POST" action="{{ route('storeops.documents.void', ['type' => $type, 'id' => $document->id]) }}">@csrf
            <div class="modal-header"><h4 class="modal-title" id="voidDraftTitle">{{ __('storeops::storeops.void_title') }}</h4></div>
            <div class="modal-body"><label for="voidReason">{{ __('storeops::storeops.reason') }}</label><textarea id="voidReason" name="reason" class="form-control" minlength="5" maxlength="500" required></textarea></div>
            <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">{{ __('storeops::storeops.cancel') }}</button><button type="submit" class="btn btn-danger">{{ __('storeops::storeops.void_draft') }}</button></div>
        </form>
    </div></div>
</div>
@endif
<!-- POSTING PREVIEW MODAL -->
<div class="modal fade" id="postingModal" tabindex="-1" role="dialog" aria-labelledby="postingModalTitle" aria-describedby="postingWarning">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-yellow">
        <h4 class="modal-title" id="postingModalTitle" lang="bn">{{ trans('tenantops::access.post_confirm', [], 'bn-BD') }}</h4><p lang="en">{{ trans('tenantops::access.post_confirm', [], 'en-US') }}</p>
      </div>
      <div class="modal-body">
        <p class="lead">{{ __('storeops::storeops.post_lead') }}</p>
        <div class="well">
            <strong>{{ $document->getDocumentNumber() }} ({{ $document->type }})</strong><br>{{ __('tenantops::access.office') }}: {{ $document->location_id }}<br><ul id="previewItems"></ul>
            <span id="previewLines">0</span> {{ __('storeops::storeops.items_label') }} | <span id="previewQty">0</span> {{ __('storeops::storeops.total_quantity') }}<br>
            {{ __('storeops::storeops.estimated_value') }} ৳<span id="previewValue">0.00</span><br>
            {{ __('storeops::storeops.reference_label') }} <span id="previewRef"></span>
        </div>
        <div id="postingWarning"><p lang="bn">{{ trans('tenantops::access.post_warning', [], 'bn-BD') }}</p><p lang="en">{{ trans('tenantops::access.post_warning', [], 'en-US') }}</p></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('storeops::storeops.cancel') }}</button>
        <button type="button" class="btn btn-success" id="confirmPostBtn">{{ __('tenantops::access.post') }}</button>
      </div>
    </div>
  </div>
</div>

@section('moar_scripts')
    @include('storeops::operations.partials.workspace-config')
</div>
@endsection
@endsection
