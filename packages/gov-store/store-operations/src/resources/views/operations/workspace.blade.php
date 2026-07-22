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
            
            <!-- SECTION 1: Document Information -->
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">Document Information</h3>
                </div>
                <div class="box-body row">
                    <div class="col-md-4 form-group">
                        <label>Receiving Source</label>
                        <select name="purchase_type" class="form-control" {{ $isPosted ? 'disabled' : '' }}>
                            <option value="Purchase" {{ $document->purchase_type == 'Purchase' ? 'selected' : '' }}>Purchase</option>
                            <option value="Transfer" {{ $document->purchase_type == 'Transfer' ? 'selected' : '' }}>Transfer</option>
                            <option value="Donation" {{ $document->purchase_type == 'Donation' ? 'selected' : '' }}>Donation</option>
                            <option value="Confiscated" {{ $document->purchase_type == 'Confiscated' ? 'selected' : '' }}>Confiscated / Found</option>
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Reference (Challan / Nothi) No</label>
                        <input type="text" name="reference_no" class="form-control" value="{{ $document->reference_no ?? '' }}" {{ $isPosted ? 'readonly' : '' }}>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Reference Date</label>
                        <input type="date" name="reference_date" class="form-control" value="{{ $document->reference_date ?? '' }}" {{ $isPosted ? 'readonly' : '' }}>
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