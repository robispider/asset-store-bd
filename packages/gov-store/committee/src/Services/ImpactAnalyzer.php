<?php

namespace GovStore\Committee\Services;

use GovStore\Committee\Contracts\PurposeRegistry;
use GovStore\Committee\Models\{Committee,PurposeBinding};

class ImpactAnalyzer
{
    public function __construct(private PurposeRegistry $purposes) {}
    public function ifEnded(string $id): array
    {
        $c = Committee::findOrFail($id);
        $bindings = PurposeBinding::where('committee_type_id',$c->committee_type_id)->where('is_active',true)->get();
        $result = [];
        foreach ($c->scopes as $scope) {
            foreach ($bindings as $binding) {
                if ($purpose = $this->purposes->get($binding->purpose_code)) { $result[] = ['purpose'=>$purpose->labelBn,'scope'=>$scope->scope_label_snapshot,'effective_to'=>$scope->effective_to]; }
            }
        }
        return $result;
    }
    public function snapshotsAfter(string $id, \DateTimeInterface $date): array
    {
        $result = [];
        foreach (app()->tagged('committee.snapshot_usage_reporters') as $reporter) {
            if (! $reporter instanceof \GovStore\Committee\Contracts\SnapshotUsageReporter) { throw new \LogicException('Invalid committee usage reporter.'); }
            $result = array_merge($result,$reporter->snapshotsTakenAfter($id,$date));
        }
        return $result;
    }
}
