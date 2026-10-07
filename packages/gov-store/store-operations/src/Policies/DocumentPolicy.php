<?php

namespace GovStore\StoreOperations\Policies;

use GovStore\StoreOperations\Models\Document;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\GovAccess;

class DocumentPolicy
{
    public function check(Document $document, string $type, string $action = 'view'): void
    {
        $context = app(TenantContext::class);
        abort_unless(in_array($type, ['receipt', 'issue', 'adjustment'], true) && $document->type === $type, 404);
        abort_unless($context->locationId && (int) $document->location_id === $context->locationId
            && (! $context->companyId || (int) $document->company_id === $context->companyId), 404);
        $ability = 'storeops.documents.'.($action === 'takeover' ? 'draft' : $action);
        abort_unless(app(GovAccess::class)->permitsRequest(auth()->user(), $ability), 403);
        if (in_array($action, ['draft', 'takeover'])) {
            abort_unless($document->status === 'DRAFT', 409, __('tenantops::access.state_locked'));
        }
        if ($action === 'post') {
            abort_unless(in_array($document->status, ['DRAFT', 'READY'], true), 409, __('tenantops::access.state_locked'));
        }
    }
}
