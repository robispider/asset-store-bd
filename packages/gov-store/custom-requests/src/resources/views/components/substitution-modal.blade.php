<div class="modal fade" id="substitutionModal" tabindex="-1" role="dialog" aria-labelledby="substitutionModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title" id="substitutionModalLabel">
                    <i class="fas fa-exchange-alt text-orange"></i> 
                    {{ __('requestlabels::requests.fulfillment_show_modal_title') ?? 'Product Substitution' }}
                </h4>
            </div>
            <div class="modal-body">
                <p>Select an alternative item to fulfill the request for: <strong id="modalOriginalItemName" class="text-blue"></strong></p>
                
                <input type="hidden" id="modalLineItemId">
                <input type="hidden" id="modalItemType">

                <div class="form-group">
                    <label for="substituteSelector">{{ __('requestlabels::requests.fulfillment_show_modal_search_label') ?? 'Search Alternative Inventory:' }}</label>
                    <select id="substituteSelector" class="form-control" style="width: 100%;"></select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default pull-left" data-dismiss="modal">
                    {{ __('requestlabels::requests.fulfillment_show_modal_btn_cancel') ?? 'Cancel' }}
                </button>
                <button type="button" class="btn btn-primary" onclick="applySubstitution()">
                    {{ __('requestlabels::requests.fulfillment_show_modal_btn_save') ?? 'Save Substitution' }}
                </button>
            </div>
        </div>
    </div>
</div>