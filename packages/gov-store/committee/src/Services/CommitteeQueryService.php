<?php

namespace GovStore\Committee\Services;

use Carbon\CarbonImmutable;
use GovStore\Committee\Contracts\CommitteeQueries;
use GovStore\Committee\DTOs\{CommitteeView,MemberView,SeatView,ScopeRef,HealthReport};
use GovStore\Committee\Models\{Committee,CommitteeTenure,ExternalMember};
use GovStore\Committee\Repositories\CommitteeRepository;

class CommitteeQueryService implements CommitteeQueries
{
    public function __construct(private CommitteeHealthService $healthService, private CommitteeRepository $repository) {}
    public function view(Committee $c, \DateTimeInterface $asOf): CommitteeView
    {
        $members = collect($this->membersOf($c->id,$asOf))->keyBy('tenureId');
        $bySeat = [];
        foreach (CommitteeTenure::whereIn('id',$members->keys())->get() as $t) { $bySeat[$t->seat_id] = $members[$t->id]; }
        $seats = $c->seats()->orderBy('seat_no')->get()->map(fn ($s) => new SeatView($s->id,$s->seat_no,$s->seat_role_code,$s->holder_kind,$s->post_title_en,$s->post_title_bn,$bySeat[$s->id] ?? null))->all();
        $order = $c->orders()->find($c->constitution_order_id);
        return new CommitteeView($c->id,$c->lineage_id,$c->version_no,$c->committee_number,$c->type->code,$c->name_en,$c->name_bn,$c->owner_location_id,$c->owner_company_id,$c->status,$c->effective_from,$c->effective_to,$c->ended_on,
            $order ? ['id'=>$order->id,'memo_no'=>$order->memo_no,'issued_on'=>$order->issued_on,'authority'=>$order->issuing_authority_name,'designation'=>$order->issuing_authority_designation_en] : null,$seats);
    }
    public function find(string $committeeId): ?CommitteeView { $c = Committee::find($committeeId); return $c ? $this->view($c,CarbonImmutable::now('Asia/Dhaka')) : null; }
    public function findByNumber(string $committeeNumber): ?CommitteeView { $c = Committee::where('committee_number',$committeeNumber)->first(); return $c ? $this->view($c,CarbonImmutable::now('Asia/Dhaka')) : null; }
    public function activeOfType(string $typeCode, ScopeRef $scope, \DateTimeInterface $asOf): array
    {
        $ids = \GovStore\Committee\Models\CommitteeType::where('code',$typeCode)->pluck('id')->all();
        return $this->repository->activeInScope($ids,$scope,$asOf->format('Y-m-d'))->map(fn ($c) => $this->view($c,$asOf))->all();
    }
    public function membersOf(string $committeeId, \DateTimeInterface $asOf): array
    {
        $date = $asOf->format('Y-m-d');
        $all = CommitteeTenure::where('committee_id',$committeeId)->orderBy('seat_id')->get();
        $invalid = $all->pluck('corrects_tenure_id')->filter()->all();
        $seats = \GovStore\Committee\Models\CommitteeSeat::with('role')->where('committee_id',$committeeId)->get()->keyBy('id');
        $external = ExternalMember::whereIn('id',$all->pluck('external_member_id')->filter())->get()->keyBy('id');
        return $all->filter(fn ($t) => ! in_array($t->id,$invalid) && $t->from_date <= $date && (! $t->to_date || $t->to_date >= $date))
            ->map(function ($t) use ($seats,$external,$date) {
                $s = $seats[$t->seat_id]; $r = $s->role;
                $ext = $external[$t->external_member_id] ?? null;
                $user = $t->user_id ?? ($ext?->linked_at && substr($ext->linked_at,0,10) <= $date ? $ext->linked_user_id : null);
                return new MemberView($t->id,$s->seat_role_code,$s->holder_kind,$user,$t->external_member_id,$t->name_snapshot_en,$t->name_snapshot_bn,$t->designation_snapshot_en,$t->designation_snapshot_bn,$t->home_location_id_snapshot,$t->home_company_id_snapshot,$t->is_external,$t->from_date,$t->to_date,$t->declaration_status,(bool)$r->is_presiding,(bool)$r->is_secretary,(bool)$r->counts_toward_strength);
            })->values()->all();
    }
    public function isMember(int $userId, string $committeeId, \DateTimeInterface $asOf): bool { return collect($this->membersOf($committeeId,$asOf))->contains(fn ($m) => $m->userId === $userId); }
    public function holdsRole(int $userId, string $committeeId, string $seatRole, \DateTimeInterface $asOf): bool { return collect($this->membersOf($committeeId,$asOf))->contains(fn ($m) => $m->userId === $userId && $m->seatRole === $seatRole); }
    public function isPresiding(int $userId, string $committeeId, \DateTimeInterface $asOf): bool { return collect($this->membersOf($committeeId,$asOf))->contains(fn ($m) => $m->userId === $userId && $m->isPresiding); }
    public function committeesOf(int $userId, \DateTimeInterface $asOf): array
    {
        $ids = CommitteeTenure::where('user_id',$userId)->orWhereIn('external_member_id',ExternalMember::where('linked_user_id',$userId)->pluck('id'))->pluck('committee_id')->unique();
        return Committee::whereIn('id',$ids)->get()->filter(fn ($c) => $this->isMember($userId,$c->id,$asOf))->map(fn ($c) => $this->view($c,$asOf))->values()->all();
    }
    public function health(string $committeeId, \DateTimeInterface $asOf): HealthReport { return $this->healthService->computeFor($committeeId,$asOf); }
    public function quorumOf(string $committeeId): ?int { $c = Committee::findOrFail($committeeId); return ($c->policy_snapshot ?? $c->type->composition_policy)['quorum']['min_present'] ?? null; }
    public function lineage(string $lineageId): array { return Committee::where('lineage_id',$lineageId)->orderBy('version_no')->get()->map(fn ($c) => $this->view($c,CarbonImmutable::now('Asia/Dhaka')))->all(); }
}
