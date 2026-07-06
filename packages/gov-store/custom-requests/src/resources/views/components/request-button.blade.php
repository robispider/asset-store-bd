{{-- 
    EXPECTED VARIABLES:
    $itemType (e.g. 'Consumable', 'Asset', 'Accessory')
    $itemId (e.g. 5)
    $itemName (e.g. 'Keyboard')
--}}


<!-- The Button -->
<!-- The Button -->
<button type="button" class="btn btn-primary btn-sm btn-block" data-toggle="modal" data-target="#govRequestModal_{{ $itemType }}_{{ $itemId }}">
    <i class="fas fa-shopping-cart"></i> Request Item
</button>

<!-- The Modal -->
<div class="modal fade" id="govRequestModal_{{ $itemType }}_{{ $itemId }}" tabindex="-1" role="dialog" aria-labelledby="govRequestModalLabel">
    <div class="modal-dialog" role="document">
        <form action="{{ route('gov.requests.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title">Request Item: {{ $itemName }}</h4>
                </div>
                
                <div class="modal-body">
                    <p>You are requesting 1x <strong>{{ $itemName }}</strong> ({{ $itemType }}).</p>
                    <p>Once submitted, an administrator will need to approve this request.</p>
                    
                    <!-- Hidden fields to tell the controller what we are requesting -->
                    <input type="hidden" name="item_type" value="{{ $itemType }}">
                    <input type="hidden" name="item_id" value="{{ $itemId }}">

                    <div class="form-group">
                        <label for="notes">Justification / Notes (Optional)</label>
                        <textarea class="form-control" name="notes" rows="3" placeholder="Why do you need this item?"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </div>
        </form>
    </div>
</div>