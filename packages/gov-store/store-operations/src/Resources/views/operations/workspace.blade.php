@extends('layouts/default')
@section('title', 'Workspace - ' . $document->getDocumentNumber())

@section('content')
@php 
    $isDraft = $document->getStatus() === 'DRAFT';
    $isPosted = $document->getStatus() === 'POSTED';
    $mathDirection = $document->getDocumentType() === 'receipt' ? '+' : '-';
@endphp

<div class="row">
    <!-- Main Form: Wraps the workspace for integrated draft saves and posting -->
    <form id="workspaceForm" action="{{ route('storeops.documents.post', ['type' => $type, 'id' => $document->id]) }}" method="POST">
        @csrf
        <input type="hidden" name="document_type" value="{{ $document->getDocumentType() }}">
        
        <!-- LEFT COLUMN: The Working Area -->
        <div class="col-md-8">
            
            <!-- SECTION 1: Administrative Details & References -->
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">Administrative Details & Approvals</h3>
                </div>
                <div class="box-body">
                    
                    <div class="row" style="margin-bottom: 15px;">
                        <div class="col-md-12 form-group">
                            <label style="color: #475569;">Receiving Source</label>
                            <select name="purchase_type" class="form-control" {{ $isPosted ? 'disabled' : '' }} style="border: 1px solid #cbd5e1; max-width: 300px;">
                                <option value="Purchase" {{ $document->purchase_type == 'Purchase' ? 'selected' : '' }}>Standard Purchase</option>
                                <option value="Transfer" {{ $document->purchase_type == 'Transfer' ? 'selected' : '' }}>Office Transfer</option>
                                <option value="Donation" {{ $document->purchase_type == 'Donation' ? 'selected' : '' }}>Donation / Grant</option>
                                <option value="Confiscated" {{ $document->purchase_type == 'Confiscated' ? 'selected' : '' }}>Confiscated / Found</option>
                            </select>
                        </div>
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
                            <div class="form-group" style="background: #f8fafc; padding: 15px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                <label style="color: #0f172a; font-size: 13px;"><i class="fa fa-truck text-blue" style="margin-right: 5px;"></i> Supplier Challan</label>
                                <div style="display: flex; gap: 10px; margin-top: 5px;">
                                    <input type="hidden" name="references[0][reference_type]" value="Supplier Challan">
                                    <input type="text" name="references[0][reference_number]" class="form-control input-sm" placeholder="Challan Number" value="{{ $challan->reference_number ?? '' }}" {{ $isPosted ? 'readonly' : '' }}>
                                    <input type="date" name="references[0][reference_date]" class="form-control input-sm" style="max-width: 140px;" value="{{ $challan->reference_date ?? '' }}" {{ $isPosted ? 'readonly' : '' }} title="Optional Date">
                                </div>
                            </div>
                        </div>

                        <!-- 2. Purchase Order / Tender -->
                        <div class="col-md-6">
                            <div class="form-group" style="background: #f8fafc; padding: 15px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                <label style="color: #0f172a; font-size: 13px;"><i class="fa fa-file-text-o text-purple" style="margin-right: 5px;"></i> Purchase Order / Tender</label>
                                <div style="display: flex; gap: 10px; margin-top: 5px;">
                                    <input type="hidden" name="references[1][reference_type]" value="Purchase Order">
                                    <input type="text" name="references[1][reference_number]" class="form-control input-sm" placeholder="PO / Tender Number" value="{{ $po->reference_number ?? '' }}" {{ $isPosted ? 'readonly' : '' }}>
                                    <input type="date" name="references[1][reference_date]" class="form-control input-sm" style="max-width: 140px;" value="{{ $po->reference_date ?? '' }}" {{ $isPosted ? 'readonly' : '' }} title="Optional Date">
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
                                    <input type="text" name="references[2][reference_number]" class="form-control input-sm" placeholder="Nothi Number" value="{{ $nothi->reference_number ?? '' }}" {{ $isPosted ? 'readonly' : '' }}>
                                    <input type="date" name="references[2][reference_date]" class="form-control input-sm" style="max-width: 140px;" value="{{ $nothi->reference_date ?? '' }}" {{ $isPosted ? 'readonly' : '' }} title="Optional Date">
                                </div>
                            </div>
                        </div>

                        <!-- 4. Special Ministry Allocation -->
                        <div class="col-md-6">
                            <div class="form-group" style="background: #fdfae8; padding: 15px; border: 1px solid #fef08a; border-radius: 6px;">
                                <label style="color: #854d0e; font-size: 13px;"><i class="fa fa-star text-yellow" style="margin-right: 5px;"></i> Special Project / Allocation Code</label>
                                <div style="display: flex; gap: 10px; margin-top: 5px;">
                                    <input type="hidden" name="references[3][reference_type]" value="Special Allocation">
                                    <input type="text" name="references[3][reference_number]" class="form-control input-sm" placeholder="Tracking Code (Optional)" value="{{ $allocation->reference_number ?? '' }}" {{ $isPosted ? 'readonly' : '' }} style="border-color: #fde047;">
                                    <input type="date" name="references[3][reference_date]" class="form-control input-sm" style="max-width: 140px; border-color: #fde047;" value="{{ $allocation->reference_date ?? '' }}" {{ $isPosted ? 'readonly' : '' }} title="Optional Date">
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- SECTION 2: Received Items (The Interactive Grid) -->
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">Received Items</h3>
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
                                <a href="{{ \Illuminate\Support\Facades\Storage::url($file->file_path) }}" target="_blank" style="margin-left: 5px;">
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

                        <button type="button" class="btn btn-default btn-block" id="saveDraftBtn">
                            <i class="fa fa-save"></i> Save Draft
                        </button>
                        <button type="button" class="btn btn-primary btn-block" style="margin-top: 10px;" id="triggerPostBtn" disabled>
                            <i class="fa fa-lock"></i> Post to Ledger
                        </button>
                    @else
                        <button type="button" class="btn btn-default btn-block" onclick="window.open('{{ route('storeops.documents.print', ['type' => $type, 'id' => $document->id]) }}', '_blank')">
                            <i class="fa fa-print"></i> Print Official Copy
                        </button>
                    @endif
                </div>
            </div>

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
                                        <strong>{{ ucfirst(strtolower($event->state)) }}</strong> by {{ $document->creator->first_name ?? 'System' }}
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

<!-- POSTING PREVIEW MODAL -->
<div class="modal fade" id="postingModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-yellow">
        <h4 class="modal-title"><i class="fa fa-warning"></i> Confirm Ledger Posting</h4>
      </div>
      <div class="modal-body">
        <p class="lead">You are about to post this document to the immutable inventory ledger.</p>
        <div class="well">
            <strong>Summary:</strong><br>
            <span id="previewLines">0</span> Items | <span id="previewQty">0</span> Total Quantity<br>
            Estimated Value: ৳<span id="previewValue">0.00</span><br>
            Reference: <span id="previewRef"></span>
        </div>
        <p class="text-danger"><i class="fa fa-info-circle"></i> <strong>Warning:</strong> Posting cannot be reversed or edited once completed.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" onclick="document.getElementById('workspaceForm').submit();">Confirm & Post</button>
      </div>
    </div>
  </div>
</div>

@section('moar_scripts')
    @include('storeops::operations.partials.grid-script', ['existingItems' => $document->items, 'isDraft' => $isDraft])
@endsection
@endsection