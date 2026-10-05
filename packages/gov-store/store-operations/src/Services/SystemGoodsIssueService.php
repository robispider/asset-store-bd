<?php

namespace GovStore\StoreOperations\Services;

use GovStore\StoreOperations\Contracts\StockIssuingServiceInterface;
use GovStore\StoreOperations\Enums\StockableType;
use GovStore\StoreOperations\Models\{Document, GoodsIssue, InventoryMovement};
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SystemGoodsIssueService implements StockIssuingServiceInterface
{
    public function __construct(
        private DocumentNumberService $numberService,
        private TenantContext $tenantContext,
        private LedgerPostingService $ledger
    ) {}

    public function issueSystemStock(array $items, int $issuedToUserId, $referenceDocument): array
    {
        if (! $this->tenantContext->locationId || (int) $referenceDocument->office_id !== $this->tenantContext->locationId) {
            abort(404);
        }

        $locationId = $this->tenantContext->locationId;
        $companyId = $this->tenantContext->companyId;
        $actorId = auth()->id() ?? throw new \RuntimeException('An authenticated storekeeper is required.');
        if (! DB::table('gov_office_memberships')->where('user_id', $issuedToUserId)->where('location_id', $locationId)
            ->where('status', 'active')->exists()
            || ! DB::table('users')->where('id', $issuedToUserId)->whereNull('deleted_at')->exists()) {
            abort(404);
        }

        return DB::transaction(function () use ($items, $issuedToUserId, $referenceDocument, $locationId, $companyId, $actorId) {
            $issueNo = $this->numberService->generate('GI', 'gov_documents', 'document_number');
            $document = Document::withoutGlobalScopes()->create([
                'document_number' => $issueNo, 'type' => 'issue', 'status' => 'POSTED',
                'compiled_profile_snapshot' => ['source' => 'custom_request'],
                'company_id' => $companyId, 'location_id' => $locationId, 'created_by' => $actorId,
                'drafted_by' => $actorId, 'posted_by' => $actorId, 'posted_at' => now(), 'managed_by' => $actorId,
                'issued_to_user_id' => $issuedToUserId,
            ]);
            $document->timelines()->create(['state' => 'POSTED', 'user_id' => $actorId, 'notes' => 'Fulfilled custom request '.(string) $referenceDocument->request_number]);
            $document->references()->create([
                'reference_type' => 'Custom Request', 'reference_number' => (string) $referenceDocument->request_number,
            ]);

            // Keep the legacy issue header/items as a read-compatible projection while
            // making the generic document and ledger the authoritative stock record.
            $legacyIssue = GoodsIssue::withoutGlobalScopes()->create([
                'issue_no' => $issueNo, 'issue_type' => 'SYSTEM_FULFILLMENT', 'issued_to_id' => $issuedToUserId,
                'reference_type' => get_class($referenceDocument), 'reference_id' => $referenceDocument->id,
                'status' => 'SUBMITTED', 'company_id' => $companyId, 'location_id' => $locationId, 'created_by' => $actorId,
            ]);

            $processed = [];
            foreach ($items as $item) {
                $type = StockableType::fromString((string) $item['type']);
                if ($type === StockableType::ASSET_MODEL) {
                    throw new \InvalidArgumentException('Serialized assets use their native checkout workflow.');
                }
                $quantity = (int) $item['qty'];
                if ($quantity < 1) {
                    throw new \InvalidArgumentException('Issued quantity must be positive.');
                }
                $productId = (int) $item['id'];
                $morphType = $type->value;
                $document->items()->create([
                    'product_type' => $morphType, 'product_id' => $productId,
                    'quantity' => $quantity, 'unit_cost' => null,
                ]);
                $legacyIssue->items()->create([
                    'stockable_type' => $morphType, 'stockable_id' => $productId, 'quantity' => $quantity,
                ]);
                $this->ledger->postMovement($morphType, $productId, 'OUT', $quantity, $document,
                    $companyId, $locationId, $actorId, 'Custom request '.$referenceDocument->request_number);

                $lineId = $item['line_id'];
                $processed[$lineId] = $issueNo;
            }

            if (! $items) {
                throw new \InvalidArgumentException('A system goods issue requires at least one stock line.');
            }

            return $processed;
        }, 3);
    }
}
