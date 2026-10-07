@extends('layouts/default')
@section('title', 'Workspace - ' . $document->getDocumentNumber())

@section('content')
@php 
    $isDraft = $document->getStatus() === 'DRAFT' && app(\GovStore\TenantScope\Services\GovAccess::class)->permitsRequest(auth()->user(), 'storeops.documents.draft');
    $isReadOnly = !$isDraft;
    $isPosted = $document->getStatus() === 'POSTED';
    $mathDirection = $document->getDocumentType() === 'receipt' ? '+' : '-';
    $lineSectionTitle = match ($document->getDocumentType()) { 'issue' => 'Issued Items', 'adjustment' => 'Adjusted Items', default => 'Received Items' };
@endphp

@if($isReadOnly)
<div class="alert alert-info" role="status">{{ __('tenantops::access.read_only') }}</div>
@endif
<div id="gov-access-inline" class="alert alert-warning" role="alert" hidden></div>
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
                    <h3 class="box-title">Administrative Details & Approvals</h3>
                </div>
                <div class="box-body">
                    
                    @if($document->type === 'adjustment')
                    <div class="row" style="margin-bottom: 15px;">
                        <div class="col-md-6 form-group">
                            <label>Reason</label>
                            <select name="adjustment_reason" class="form-control" {{ $isReadOnly ? 'disabled' : '' }}>
                                @foreach(['PHYSICAL_COUNT' => 'Physical count', 'DAMAGE' => 'Damage', 'LOSS' => 'Loss', 'EXPIRED' => 'Expired', 'CORRECTION' => 'Correction'] as $key => $label)
                                    <option value="{{ $key }}" @selected($document->adjustment_reason === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Source document</label>
                            <select name="source_document_id" class="form-control" {{ $isReadOnly ? 'disabled' : '' }}>
                                <option value="">Select a posted document</option>
                                @foreach($adjustmentSources as $source)
                                    <option value="{{ $source->id }}" @selected($document->source_document_id === $source->id)>{{ $source->document_number }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @elseif($document->type === 'issue')
                    <div class="row" style="margin-bottom: 15px;">
                        <div class="col-md-6 form-group">
                            <label>Issue to office member</label>
                            <select name="issued_to_user_id" class="form-control" {{ $isReadOnly ? 'disabled' : '' }}>
                                <option value="">Select an active office member</option>
                                @foreach($officeRecipients as $recipient)
                                    <option value="{{ $recipient->id }}" @selected((int) $document->issued_to_user_id === (int) $recipient->id)>{{ trim($recipient->first_name.' '.$recipient->last_name) ?: $recipient->username }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Or department</label>
                            <input type="text" name="issue_department" value="{{ $document->issue_department }}" class="form-control" maxlength="150" {{ $isReadOnly ? 'readonly' : '' }}>
                        </div>
                    </div>
                    @else
                    <div class="row" style="margin-bottom: 15px;">
                        <div class="col-md-12 form-group">
                            <label style="color: #475569;">Receiving Source</label>
                            <select name="purchase_type" class="form-control" {{ $isReadOnly ? 'disabled' : '' }} style="border: 1px solid #cbd5e1; max-width: 300px;">
                                <option value="Purchase" {{ $document->purchase_type == 'Purchase' ? 'selected' : '' }}>Standard Purchase</option>
                                <option value="Transfer" {{ $document->purchase_type == 'Transfer' ? 'selected' : '' }}>Office Transfer</option>
                                <option value="Donation" {{ $document->purchase_type == 'Donation' ? 'selected' : '' }}>Donation / Grant</option>
                                <option value="Confiscated" {{ $document->purchase_type == 'Confiscated' ? 'selected' : '' }}>Confiscated / Found</option>
                            </select>
                        </div>
                    </div>
                    @endif

                    @if($document->type === 'receipt')
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
                            <div class="form-group" style="background: #f8fafc; padding: 15px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                <label style="color: #0f172a; font-size: 13px;"><i class="fa fa-truck text-blue" style="margin-right: 5px;"></i> Supplier Challan</label>
                                <div style="display: flex; gap: 10px; margin-top: 5px;">
                                    <input type="hidden" name="references[0][reference_type]" value="Supplier Challan">
                                    <input type="text" name="references[0][reference_number]" class="form-control input-sm" placeholder="Challan Number" value="{{ $challan->reference_number ?? '' }}" {{ $isReadOnly ? 'readonly' : '' }}>
                                    
                                    <!-- FIXED: Changed 'readonly' to 'disabled' to prevent calendar updates on posted documents -->
                                    <input type="date" name="references[0][reference_date]" class="form-control input-sm" style="max-width: 140px;" value="{{ $challan->reference_date ?? '' }}" {{ $isReadOnly ? 'disabled' : '' }} title="Optional Date">
                                </div>
                            </div>
                        </div>

                        <!-- 2. Purchase Order / Tender -->
                        <div class="col-md-6">
                            <div class="form-group" style="background: #f8fafc; padding: 15px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                <label style="color: #0f172a; font-size: 13px;"><i class="fa fa-file-text-o text-purple" style="margin-right: 5px;"></i> Purchase Order / Tender</label>
                                <div style="display: flex; gap: 10px; margin-top: 5px;">
                                    <input type="hidden" name="references[1][reference_type]" value="Purchase Order">
                                    <input type="text" name="references[1][reference_number]" class="form-control input-sm" placeholder="PO / Tender Number" value="{{ $po->reference_number ?? '' }}" {{ $isReadOnly ? 'readonly' : '' }}>
                                    
                                    <!-- FIXED: Changed 'readonly' to 'disabled' -->
                                    <input type="date" name="references[1][reference_date]" class="form-control input-sm" style="max-width: 140px;" value="{{ $po->reference_date ?? '' }}" {{ $isReadOnly ? 'disabled' : '' }} title="Optional Date">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- 3. Nothi / Approval Letter -->
                        <div class="col-md-6">
                            <div class="form-group" style="background: #f8fafc; padding: 15px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                <label style="color: #0f172a; font-size: 13px;"><i class="fa fa-check-square-o text-green" style="margin-right: 5px;"></i> Nothi / Approval Letter</label>
                                <div style="display: flex; gap: 10px; margin-top: 5px;">
                                    <input type="hidden" name="references[2][reference_type]" value="Nothi / Approval Letter">
                                    <input type="text" name="references[2][reference_number]" class="form-control input-sm" placeholder="Nothi Number" value="{{ $nothi->reference_number ?? '' }}" {{ $isReadOnly ? 'readonly' : '' }}>
                                    
                                    <!-- FIXED: Changed 'readonly' to 'disabled' -->
                                    <input type="date" name="references[2][reference_date]" class="form-control input-sm" style="max-width: 140px;" value="{{ $nothi->reference_date ?? '' }}" {{ $isReadOnly ? 'disabled' : '' }} title="Optional Date">
                                </div>
                            </div>
                        </div>

                        <!-- 4. Special Ministry Allocation -->
                        <div class="col-md-6">
                            <div class="form-group" style="background: #fdfae8; padding: 15px; border: 1px solid #fef08a; border-radius: 6px;">
                                <label style="color: #854d0e; font-size: 13px;"><i class="fa fa-star text-yellow" style="margin-right: 5px;"></i> Special Project / Allocation Code</label>
                                <div style="display: flex; gap: 10px; margin-top: 5px;">
                                    <input type="hidden" name="references[3][reference_type]" value="Special Allocation">
                                    <input type="text" id="tracking_code_input" name="references[3][reference_number]" class="form-control input-sm" placeholder="Tracking Code (Optional)" value="{{ $allocation->reference_number ?? '' }}" {{ $isReadOnly ? 'readonly' : '' }} style="border-color: #fde047;">
                                    
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
                    <table class="table table-bordered" id="itemsGrid">
                        <thead style="background: #f9fafb;">
                            <tr>
                                <th style="width: 35%;">Item Name</th>
                                <th style="width: 15%; text-align: center;">Current Stock</th>
                                <th style="width: 15%;">Quantity</th>
                                <th style="width: 15%;">Unit Cost (৳)</th>
                                <th style="width: 15%; text-align: center;">Balance After</th>
                                @if($isDraft) <th style="width: 5%;"></th> @endif
                            </tr>
                        </thead>
                        <tbody id="gridBody">
                            <!-- JS automatically inserts default search rows and metadata sub-grids here -->
                        </tbody>
                    </table>
                    @if($isDraft)
                    <div style="padding: 10px;">
                        <button type="button" class="btn btn-sm btn-default" id="addRowBtn">
                            <i class="fa fa-plus"></i> Add Row
                        </button>
                    </div>
                    @endif
                </div>
            </div>

            <!-- SECTION 3: Supporting Documents -->
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">Supporting Documents (Challan / Nothi / Invoice Scans)</h3>
                </div>
                <div class="box-body">
                    @if($isDraft)
                        <div class="row" style="margin-bottom: 20px;">
                            <div class="col-md-5">
                                <select id="attachmentCategory" class="form-control input-sm">
                                    <option value="Challan">Challan (চালান)</option>
                                    <option value="Invoice">Invoice / Bill (ইনভয়েস)</option>
                                    <option value="Committee_Report">Committee Acceptance Report</option>
                                    <option value="Tender_WO">Work Order / Tender Copy</option>
                                    <option value="Other">Other Supporting Document</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <input type="file" id="attachmentFile" class="form-control input-sm">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-sm btn-primary btn-block" id="uploadFileBtn">
                                    <i class="fa fa-upload"></i> Upload
                                </button>
                            </div>
                        </div>
                    @endif

                    <ul class="list-group list-group-unbordered" id="attachmentsList">
                        @forelse($document->attachments as $file)
                            <li class="list-group-item attachment-item" data-id="{{ $file->id }}" style="border-bottom: 1px solid #f4f4f4; padding: 10px 0;">
                                <i class="fa fa-file-text-o text-blue"></i> 
                                <a href="{{ route('storeops.documents.attachments.download', ['type' => $type, 'id' => $document->id, 'attachmentId' => $file->id]) }}" target="_blank" style="margin-left: 5px;">
                                    <strong>{{ $file->original_name }}</strong>
                                </a>
                                @if($isDraft)
                                    <button type="button" class="btn btn-xs btn-danger pull-right delete-attachment" data-id="{{ $file->id }}">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                @endif
                            </li>
                        @empty
                            <li class="list-group-item text-center text-muted" id="noAttachmentsMsg" style="border:none;">
                                No supporting files attached yet.
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
                    <h3 class="box-title">{{ $isPosted ? 'Posted Document' : 'Draft Workspace' }}</h3>
                </div>
                <div class="box-body">
                    <h4 class="text-center" style="margin-top:0;"><strong>{{ $document->getDocumentNumber() }}</strong></h4>
                    
                    <ul class="list-group list-group-unbordered" style="margin-bottom: 15px;">
                        <li class="list-group-item">
                            <b>Total Lines</b> <a class="pull-right" id="sumLines">{{ $document->items->count() }}</a>
                        </li>
                        <li class="list-group-item">
                            <b>Total Quantity</b> <a class="pull-right" id="sumQty">{{ $document->items->sum('quantity') }}</a>
                        </li>
                    </ul>

                    @if($isDraft)
                        @include('storeops::operations.partials.validation-checklist')

                        <x-gov-action ability="storeops.documents.draft" class="btn btn-default btn-block" id="saveDraftBtn">{{ __('tenantops::access.save') }}</x-gov-action>
                        <x-gov-action ability="storeops.documents.post" class="btn btn-primary btn-block" id="triggerPostBtn" disabled>{{ __('tenantops::access.post') }}</x-gov-action>
                        <button type="button" class="btn btn-danger btn-block" data-toggle="modal" data-target="#voidDraftModal">Void draft</button>
                    @else
                        <x-gov-action ability="storeops.documents.draft" :locked="$document->status !== 'DRAFT'" class="btn btn-default btn-block">{{ __('tenantops::access.save') }}</x-gov-action>
                        <x-gov-action ability="storeops.documents.post" :locked="!in_array($document->status, ['DRAFT', 'READY'])" class="btn btn-primary btn-block" id="triggerPostBtn">{{ __('tenantops::access.post') }}</x-gov-action>
                        <button type="button" class="btn btn-default btn-block" onclick="window.open('{{ route('storeops.documents.print', ['type' => $type, 'id' => $document->id]) }}', '_blank')">
                            <i class="fa fa-print"></i> Print Official Copy
                        </button>
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
                    <h3 class="box-title">Activity Timeline</h3>
                </div>
                <div class="box-body">
                    <ul class="timeline timeline-inverse" style="margin-top: 10px;">
                        @foreach($document->timelines()->orderBy('created_at', 'desc')->get() as $event)
                            <li>
                                <i class="fa {{ $event->state === 'POSTED' ? 'fa-lock bg-green' : 'fa-edit bg-gray' }}"></i>
                                <div class="timeline-item">
                                    <span class="time">
                                        <i class="fa fa-clock-o"></i> 
                                        {{ \Carbon\Carbon::parse($event->created_at)->format('H:i') }}
                                    </span>
                                    <h3 class="timeline-header no-border">
                                        <strong>{{ ucfirst(strtolower($event->state)) }}</strong> by {{ $event->user?->present()->fullName ?? 'System' }}
                                    </h3>
                                    @if($event->notes)
                                        <div class="timeline-body" style="padding-top:0; color:#666;">{{ $event->notes }}</div>
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

<form id="takeoverForm" method="post" action="{{ route('storeops.documents.takeover', ['type' => $type, 'id' => $document->id]) }}">@csrf</form>
@if($isDraft)
<div class="modal fade" id="voidDraftModal" tabindex="-1" role="dialog" aria-labelledby="voidDraftTitle">
    <div class="modal-dialog" role="document"><div class="modal-content">
        <form method="POST" action="{{ route('storeops.documents.void', ['type' => $type, 'id' => $document->id]) }}">@csrf
            <div class="modal-header"><h4 class="modal-title" id="voidDraftTitle">Void this draft</h4></div>
            <div class="modal-body"><label for="voidReason">Reason</label><textarea id="voidReason" name="reason" class="form-control" minlength="5" maxlength="500" required></textarea></div>
            <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Void draft</button></div>
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
        <p class="lead">You are about to post this document to the immutable inventory ledger.</p>
        <div class="well">
            <strong>{{ $document->getDocumentNumber() }} ({{ $document->type }})</strong><br>{{ __('tenantops::access.office') }}: {{ $document->location_id }}<br><ul id="previewItems"></ul>
            <span id="previewLines">0</span> Items | <span id="previewQty">0</span> Total Quantity<br>
            Estimated Value: ৳<span id="previewValue">0.00</span><br>
            Reference: <span id="previewRef"></span>
        </div>
        <div id="postingWarning"><p lang="bn">{{ trans('tenantops::access.post_warning', [], 'bn-BD') }}</p><p lang="en">{{ trans('tenantops::access.post_warning', [], 'en-US') }}</p></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" onclick="document.getElementById('workspaceForm').submit();">{{ __('tenantops::access.post') }}</button>
      </div>
    </div>
  </div>
</div>

@section('moar_scripts')
    @if($document->status === 'READY')
    <script>document.getElementById('triggerPostBtn').addEventListener('click', function () {
        $.get(@json(route('storeops.documents.preview', ['type' => $type, 'id' => $document->id]))).done(function (data) {
            $('#previewLines').text(data.lines); $('#previewQty').text(data.total_qty);
            $('#previewValue').text(data.total_value); $('#previewRef').text(data.reference);
            $('#previewItems').empty();
            (data.items || []).forEach(function (item) { $('<li>').text(item.name + ': ' + item.quantity).appendTo('#previewItems'); });
            $('#postingModal').modal('show');
        });
    });</script>
    @endif
    @include('storeops::operations.partials.grid-script', ['existingItems' => $document->items, 'isDraft' => $isDraft])
    @include('storeops::operations.partials.tracking-handshake', ['document' => $document, 'isDraft' => $isDraft])

@endsection
@endsection
