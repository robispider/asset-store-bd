<?php

namespace GovStore\TenantScope\Services;

use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccessAudit
{
    public function record(AccessDecision $decision, string $outcome, ?string $reason = null): string
    {
        $reference = (string) Str::uuid();
        $context = app(TenantContext::class);
        $dedupe = in_array($outcome, ['denied', 'shadow'])
            ? hash('sha256', implode('|', [auth()->id(), $decision->ability, now()->toDateString(), $outcome])) : null;
        $values = [
            'reference_id' => $reference, 'dedupe_key' => $dedupe,
            'user_id' => auth()->id(), 'location_id' => $context->locationId, 'company_id' => $context->companyId,
            'ability' => $decision->ability, 'roles' => json_encode($decision->roles),
            'outcome' => $outcome, 'reason' => $reason ?? $decision->reason,
            'route_name' => request()->route()?->getName(), 'created_at' => now(),
        ];
        if ($dedupe) {
            DB::table('gov_access_events')->insertOrIgnore($values);

            return DB::table('gov_access_events')->where('dedupe_key', $dedupe)->value('reference_id');
        }
        DB::table('gov_access_events')->insert($values);

        return $reference;
    }
}
