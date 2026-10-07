<?php

namespace GovStore\Committee\Services;

use Carbon\CarbonImmutable;
use GovStore\Committee\Contracts\RosterSnapshotProvider;
use GovStore\Committee\Domain\CanonicalJson;
use GovStore\Committee\DTOs\{RosterSnapshot,SnapshotVerification};
use GovStore\Committee\Models\{Committee,LedgerEntry};

class RosterSnapshotService implements RosterSnapshotProvider
{
    public function __construct(private CommitteeQueryService $queries) {}
    public function snapshot(string $committeeId, \DateTimeInterface $asOf): RosterSnapshot
    {
        $c = Committee::findOrFail($committeeId);
        $view = $this->queries->view($c,$asOf)->jsonSerialize();
        // Present-day status and subsequent tenure end dates must not change past evidence.
        unset($view['status'],$view['endedOn'],$view['effectiveTo']);
        foreach ($view['seats'] as $i => $seat) {
            $s = $seat->jsonSerialize();
            if ($s['holder']) {
                $holder = $s['holder']->jsonSerialize();
                unset($holder['toDate'],$holder['declarationStatus'],$holder['userId']);
                // Identity at appointment is stable; linking later does not rewrite frozen evidence.
                $holder['userId'] = \GovStore\Committee\Models\CommitteeTenure::find($holder['tenureId'])->user_id;
                $s['holder'] = $holder;
            }
            $view['seats'][$i] = $s;
        }
        $roster = ['schemaVersion'=>1,'asOf'=>$asOf->format('Y-m-d'),'committee'=>$view];
        return new RosterSnapshot($roster,hash('sha256',CanonicalJson::encode($roster)));
    }
    public function verify(RosterSnapshot $snapshot): SnapshotVerification
    {
        if (! hash_equals($snapshot->fingerprint,hash('sha256',CanonicalJson::encode($snapshot->roster)))) { return new SnapshotVerification('INVALID_FINGERPRINT'); }
        $id = $snapshot->roster['committee']['id'];
        if (! Committee::find($id)) { return new SnapshotVerification('UNKNOWN_COMMITTEE'); }
        $current = $this->snapshot($id,CarbonImmutable::parse($snapshot->roster['asOf']));
        return new SnapshotVerification(hash_equals($current->fingerprint,$snapshot->fingerprint) ? 'MATCHES' : 'CHANGED_SINCE',LedgerEntry::where('committee_id',$id)->pluck('id')->all());
    }
}
