<?php

namespace GovStore\CustomRequests\Services;

use App\Models\User;
use GovStore\CustomRequests\Models\Request;
use GovStore\CustomRequests\Models\RequestEvent;
use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\Services\GoodsReceiptService;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RequestReturnService
{
    private function bulkLines(Request $request): array
    {
        // Original issue quantities are immutable. A return never rewrites issue history.
        $lines = [];
        foreach ($request->items()->where('issued_qty', '>', 0)->orderBy('requested_type')->orderBy('requested_id')->get() as $line) {
            $type = $line->fulfilled_type ?: $line->requested_type;
            if (! in_array($type, ['accessory', 'consumable'], true)) {
                continue; // Serialized equipment uses native asset check-in and acceptance.
            }
            $model = app(RequestInventory::class)->validateItem($type, $line->fulfilled_id ?: $line->requested_id, true);
            $lines[] = ['type' => $type, 'id' => $model->id, 'qty' => $line->issued_qty,
                'unit_cost' => $model->purchase_cost ?? 0];
        }
        abort_unless($lines, 422);

        return $lines;
    }

    public function requestReturn(int $id, User $actor, ?string $reason): Request
    {
        Validator::make(['reason' => $reason], ['reason' => 'required|string|min:5|max:2000'])->validate();

        return DB::transaction(function () use ($id, $actor, $reason) {
            $request = Request::whereKey($id)->where('requested_by', $actor->id)->lockForUpdate()->firstOrFail();
            app(RequestAccess::class)->check($request, $actor, 'requests.submit');
            abort_unless(in_array($request->fulfillment_status, ['issued', 'closed']) && ! $request->return_requested_at, 409);
            $lines = $this->bulkLines($request);
            $request->update(['return_requested_at' => now()]);
            RequestEvent::create(['request_id' => $request->id, 'user_id' => $actor->id,
                'event_type' => 'return_requested', 'details' => ['reason' => $reason, 'lines' => $lines]]);

            return $request;
        }, 3);
    }

    public function draftReceipt(int $id, User $actor): Document
    {
        return DB::transaction(function () use ($id, $actor) {
            $request = Request::whereKey($id)->lockForUpdate()->firstOrFail();
            app(RequestAccess::class)->check($request, $actor, 'requests.fulfill');
            app(RequestAccess::class)->check($request, $actor, 'storeops.documents.draft');
            abort_unless((int) $request->office_id === app(TenantContext::class)->locationId, 404);
            abort_unless($request->return_requested_at && ! $request->return_document_id
                && in_array($request->fulfillment_status, ['issued', 'closed']), 409);
            $document = app(GoodsReceiptService::class)->saveDraft([
                'reference_no' => $request->request_number,
                'reference_date' => now()->toDateString(),
            ], $this->bulkLines($request), $actor->id);
            $request->update(['return_document_id' => $document->id]);
            RequestEvent::create(['request_id' => $request->id, 'user_id' => $actor->id,
                'event_type' => 'return_drafted', 'details' => ['document_id' => $document->id, 'document_number' => $document->document_number]]);

            // Physical receipt, required metadata, evidence and posting stay in the document workspace.
            return $document;
        }, 3);
    }
}
