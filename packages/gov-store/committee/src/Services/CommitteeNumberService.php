<?php

namespace GovStore\Committee\Services;

use Illuminate\Support\Facades\DB;

class CommitteeNumberService
{
    public function next(int $office, string $fy): string
    {
        DB::table('gov_committee_sequences')->insertOrIgnore(['owner_location_id'=>$office,'fiscal_year'=>$fy,'last_no'=>0]);
        $q = DB::table('gov_committee_sequences')->where('owner_location_id',$office)->where('fiscal_year',$fy);
        $row = (clone $q)->lockForUpdate()->first();
        $number = $row->last_no + 1; $q->update(['last_no'=>$number]);
        return 'CM-'.$office.'-'.substr($fy,2,2).substr($fy,5,2).'-'.str_pad((string)$number,4,'0',STR_PAD_LEFT);
    }
}
