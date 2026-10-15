<?php

namespace GovStore\StoreOperations\Http\Controllers\Concerns;

use GovStore\StoreOperations\Services\StockLedgerReader;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\GovAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** The working office and what the signed-in person may do there — shown on every store screen. */
trait ResolvesStoreContext
{
    protected function office(): array
    {
        $context = app(TenantContext::class);
        $id = $context->locationId ? (int) $context->locationId : null;
        $location = $id && Schema::hasTable('locations') ? DB::table('locations')->where('id', $id)->first() : null;
        $companyId = $location->company_id ?? $context->companyId;
        $company = $companyId && Schema::hasTable('companies') ? DB::table('companies')->where('id', $companyId)->value('name') : null;

        return [
            'id' => $id,
            'name' => $location->name ?? null,
            'company' => $company,
            'address' => $location ? collect([$location->address ?? null, $location->address2 ?? null, $location->city ?? null])->filter()->implode(', ') : null,
            'opened_at' => $id ? app(StockLedgerReader::class)->openedAt($id) : null,
        ];
    }

    protected function abilities(): array
    {
        $user = auth()->user();
        if (! $user) {
            return ['draft' => false, 'post' => false];
        }
        $access = app(GovAccess::class);

        return [
            'draft' => $access->decide($user, 'storeops.documents.draft')->allowed,
            'post' => $access->decide($user, 'storeops.documents.post')->allowed,
        ];
    }
}
