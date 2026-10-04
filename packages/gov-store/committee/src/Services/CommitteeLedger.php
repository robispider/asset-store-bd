<?php

namespace GovStore\Committee\Services;

use GovStore\Committee\Domain\CanonicalJson;
use GovStore\Committee\Models\{Committee,LedgerEntry};
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\GovAccess;
use Illuminate\Support\Facades\DB;

class CommitteeLedger
{
    public function __construct(private TenantContext $context, private GovAccess $access) {}
    public function append(Committee $c, string $event, array $payload = [], ?int $order = null, ?string $reason = null): LedgerEntry
    {
        if (DB::transactionLevel() < 1) { throw new \LogicException('Ledger must be written in the mutation transaction.'); }
        // The first version is the stable lineage mutex, also during reconstitution.
        Committee::where('lineage_id',$c->lineage_id)->orderBy('version_no')->lockForUpdate()->firstOrFail();
        $previous = LedgerEntry::where('lineage_id',$c->lineage_id)->orderByDesc('id')->first();
        $row = ['lineage_id'=>$c->lineage_id,'committee_id'=>$c->id,'event_type'=>$event,'payload'=>$payload,'order_id'=>$order,
            'actor_id'=>auth()->id(),'actor_roles'=>$this->access->roles(auth()->user()),'actor_location_id'=>$this->context->locationId,
            'reason'=>$reason,'occurred_at'=>now()->utc()->format('Y-m-d H:i:s.u'),'prev_hash'=>$previous?->hash ?? str_repeat('0',64)];
        $hash = hash('sha256',$row['prev_hash'].CanonicalJson::encode($row));
        $entry = LedgerEntry::create($row + ['hash'=>$hash]);
        event(new \GovStore\Committee\Events\CommitteeRecorded($c->id,$c->committee_number,(int)$c->owner_location_id,(int)$c->owner_company_id,auth()->id(),$event,$entry->id));
        $class = 'GovStore\\Committee\\Events\\'.$event;
        if (class_exists($class)) { event(new $class($c->id,$c->lineage_id,auth()->id(),$row['occurred_at'],$payload)); }
        return $entry;
    }
    public function verifyChain(string $lineageId): array
    {
        $previous = str_repeat('0',64); $breaks = [];
        foreach (LedgerEntry::where('lineage_id',$lineageId)->orderBy('id')->get() as $entry) {
            $row = $entry->toArray(); unset($row['id'],$row['hash']);
            if ($entry->prev_hash !== $previous || ! hash_equals($entry->hash,hash('sha256',$previous.CanonicalJson::encode($row)))) { $breaks[] = $entry->id; }
            $previous = $entry->hash;
        }
        return $breaks;
    }
}
