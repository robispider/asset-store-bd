<?php

namespace GovStore\Committee\Http\Controllers;

use Carbon\CarbonImmutable;
use GovStore\Committee\Contracts\{PurposeRegistry,ScopeTypeRegistry,CommitteeTabRegistry};
use GovStore\Committee\DTOs\ScopeRef;
use GovStore\Committee\Domain\MemoNumber;
use GovStore\Committee\Http\Transformers\CommitteeTransformer;
use GovStore\Committee\Models\{Committee,CommitteeType,SeatRole,CommitteeOrder,CommitteeTenure,ExternalMember,PurposeBinding,LedgerEntry};
use GovStore\Committee\Policies\CommitteePolicy;
use GovStore\Committee\Scopes\CommitteeBoundaryScope;
use GovStore\Committee\Services\{CommitteeService,CommitteeAssignmentService,CommitteeMembershipService,CommitteeQueryService,CommitteeResolverService,CommitteeLedger,CompositionValidator,OrderAttachmentStore,MemberDirectory,ExternalMemberService,CatalogService,ImpactAnalyzer};
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommitteeController extends \App\Http\Controllers\Controller
{
    public function __construct(private CommitteePolicy $policy, private CommitteeBoundaryScope $boundary, private TenantContext $context, private CommitteeQueryService $queries, private CommitteeTransformer $transformer) {}
    private function date(Request $request): CarbonImmutable
    {
        $request->validate(['as_of'=>'nullable|date_format:Y-m-d']); return CarbonImmutable::parse($request->input('as_of',now('Asia/Dhaka')->toDateString()));
    }
    private function visibleTypes()
    {
        return CommitteeType::where(fn ($q) => $q->whereNull('owner_company_id')->orWhere('owner_company_id',$this->context->companyId))->orderBy('name_en')->get();
    }
    public function page(Request $request)
    {
        $committee = $request->route('committee');
        $mode = $request->route()->defaults['page'] ?? 'dashboard'; $date = $this->date($request);
        $c = $committee ? Committee::findOrFail($committee) : null;
        if ($c) { $this->policy->check($c,'committee.view'); }
        $rows = $this->boundary->apply(Committee::with('type'))->orderByDesc('created_at')->paginate(50);
        $types = $this->visibleTypes(); $roles = SeatRole::where('is_active',true)->orderBy('sort_order')->get();
        $view = $c ? $this->queries->view($c,$date) : null;
        $health = $c ? $this->queries->health($c->id,$date) : null;
        $orders = $c ? $c->orders()->orderByDesc('issued_on')->get()->map($this->transformer->order(...))->all() : [];
        $history = $c ? LedgerEntry::where('lineage_id',$c->lineage_id)->orderByDesc('id')->get() : collect();
        $coverage = $c ? $c->scopes : collect();
        $chain = $c ? app(CommitteeLedger::class)->verifyChain($c->lineage_id) : [];
        $purposes = app(PurposeRegistry::class)->all(); $bindings = PurposeBinding::where(fn ($q) => $q->whereNull('owner_company_id')->orWhere('owner_company_id',$this->context->companyId))->get();
        $tabs = app(CommitteeTabRegistry::class)->all();
        return view($mode === 'print' ? 'committee::print' : 'committee::workspace',compact('mode','c','date','rows','types','roles','view','health','orders','history','coverage','chain','purposes','bindings','tabs'));
    }
    public function mine(Request $request)
    {
        $date = $this->date($request);
        $all = CommitteeTenure::where('user_id',auth()->id())->orWhereIn('external_member_id',ExternalMember::where('linked_user_id',auth()->id())->pluck('id'))->get();
        $committees = Committee::whereIn('id',$all->pluck('committee_id'))->get()->keyBy('id');
        return view('committee::mine',compact('all','committees','date'));
    }
    public function registry(Request $request)
    {
        $data = $request->validate(['q'=>'nullable|string|max:150','status'=>'nullable|string|max:20','type_id'=>'nullable|integer','page'=>'nullable|integer|min:1']);
        $q = $this->boundary->apply(Committee::with('type'));
        if (! empty($data['status'])) { $q->where('status',$data['status']); }
        if (! empty($data['type_id'])) { $q->where('committee_type_id',$data['type_id']); }
        if (! empty($data['q'])) {
            $term = '%'.$data['q'].'%'; $memo = '%'.MemoNumber::normalize($data['q']).'%';
            $q->where(fn ($q) => $q->where('name_en','like',$term)->orWhere('name_bn','like',$term)->orWhere('committee_number','like',$term)
                ->orWhereHas('orders',fn ($o) => $o->where('memo_no_normalized','like',$memo))
                ->orWhereHas('tenures',fn ($t) => $t->where('name_snapshot_en','like',$term)->orWhere('name_snapshot_bn','like',$term)));
        }
        $page = $q->orderByDesc('created_at')->paginate(50);
        return response()->json(['total'=>$page->total(),'rows'=>$page->getCollection()->map($this->transformer->row(...)),'page'=>$page->currentPage(),'last_page'=>$page->lastPage()]);
    }
    public function detailApi(Request $request, string $committee)
    {
        $c = Committee::findOrFail($committee); $this->policy->check($c,'committee.view'); $date = $this->date($request);
        return response()->json(match ($request->route()->defaults['query']) {
            'roster' => $this->queries->view($c,$date),
            'composition' => ['findings'=>app(CompositionValidator::class)->evaluate($c,$date->toDateString())],
            'impact' => ['impacts'=>app(ImpactAnalyzer::class)->ifEnded($c->id),'snapshot_usage'=>app(ImpactAnalyzer::class)->snapshotsAfter($c->id,$date)],
        });
    }
    public function coverageApi(Request $request)
    {
        $request->validate(['scope_type'=>'required|string','scope_id'=>'required|string']);
        $scope = new ScopeRef($request->scope_type,$request->scope_id); $this->policy->scope($scope); $date = $this->date($request);
        $rows = [];
        foreach (app(PurposeRegistry::class)->all() as $purpose) {
            if (in_array($scope->type,$purpose->allowedScopeTypes)) {
                $rows[] = ['purpose'=>$purpose,'resolution'=>app(CommitteeResolverService::class)->resolve($purpose->code,$scope,$date)];
            }
        }
        return response()->json(['rows'=>$rows]);
    }
    public function people(Request $request)
    {
        $request->validate(['q'=>'nullable|string|max:100']);
        $offices = $this->context->allowedLocationIds ?? [$this->context->locationId ?? 0];
        $users = DB::table('users')->where('activated',1)->whereNull('deleted_at')->where(fn ($q) => $q->where('first_name','like','%'.$request->input('q','').'%')->orWhere('display_name','like','%'.$request->input('q','').'%'))
            ->whereIn('id',DB::table('gov_office_memberships')->where('status','active')->whereIn('location_id',$offices)->whereIn('location_id',DB::table('locations')->where('company_id',$this->context->companyId)->select('id'))->select('user_id'))
            ->limit(50)->get(['id','first_name','last_name','display_name','jobtitle']);
        $external = ExternalMember::where('owner_company_id',$this->context->companyId)->where('full_name_en','like','%'.$request->input('q','').'%')->limit(50)->get(['id','full_name_en','full_name_bn','designation_en']);
        return response()->json(['people'=>$users->map(fn ($u) => ['id'=>$u->id,'text'=>$u->display_name ?: trim($u->first_name.' '.$u->last_name),'designation'=>$u->jobtitle]),'external'=>$external->map(fn ($e) => ['id'=>$e->id,'text'=>$e->full_name_bn ?: $e->full_name_en,'designation'=>$e->designation_en])]);
    }
    public function byCode(Request $request)
    {
        $request->validate(['code'=>'required|string|max:100']); return response()->json(app(MemberDirectory::class)->byCode($request->code));
    }
    public function transferSeats(Request $request)
    {
        $request->validate(['user_id'=>'required|integer']);
        app(MemberDirectory::class)->person((int)$request->user_id);
        $committees = $this->boundary->apply(Committee::where('owner_location_id',$this->context->locationId)->where('status','ACTIVE'))->get()->keyBy('id');
        $seats = CommitteeTenure::whereIn('committee_id',$committees->keys())->where('user_id',$request->user_id)->where('status','ACTIVE')->get()->map(function ($t) use ($committees) {
            $c = $committees[$t->committee_id];
            return ['committee_id'=>$c->id,'committee_number'=>$c->committee_number,'seat_id'=>$t->seat_id,'name'=>$t->name_snapshot_bn,
                'from_date'=>now('Asia/Dhaka')->toDateString(),'orders'=>$c->orders()->where('kind','AMENDMENT')->get()->map(fn ($o) => ['id'=>$o->id,'text'=>$o->memo_no])];
        });
        return response()->json(['seats'=>$seats]);
    }
    public function scopeSearch(Request $request, string $type)
    {
        $request->validate(['q'=>'nullable|string|max:100']); $registry = app(ScopeTypeRegistry::class); abort_unless(in_array($type,$registry->keys()),404);
        return response()->json(['results'=>$registry->get($type)->search($request->input('q',''),$this->context,50)]);
    }
    public function file(Request $request, int $order)
    {
        $o = CommitteeOrder::findOrFail($order); $c = Committee::findOrFail($o->committee_id); $this->policy->check($c,'committee.view');
        return app(OrderAttachmentStore::class)->stream($o->attachment_path ?? '',$o->attachment_sha256 ?? '');
    }
    public function declarationFile(Request $request, int $tenure)
    {
        $t = CommitteeTenure::findOrFail($tenure); $c = Committee::findOrFail($t->committee_id); $this->policy->check($c,'committee.view');
        return app(OrderAttachmentStore::class)->stream($t->declaration_attachment_path ?? '',$t->declaration_attachment_sha256 ?? '');
    }
    public function ownDeclarationFile(Request $request, int $tenure)
    {
        $this->policy->ability('committee.self');
        $t = CommitteeTenure::findOrFail($tenure);
        $userId = $t->user_id ?? ExternalMember::find($t->external_member_id)?->linked_user_id;
        abort_unless($userId === auth()->id(),404);
        return app(OrderAttachmentStore::class)->stream($t->declaration_attachment_path ?? '',$t->declaration_attachment_sha256 ?? '');
    }
    public function command(Request $request)
    {
        $committee = $request->route('committee');
        $command = $request->route()->defaults['command']; $input = $request->except(['_token']);
        $service = app(CommitteeService::class); $members = app(CommitteeMembershipService::class); $assignments = app(CommitteeAssignmentService::class);
        $result = match ($command) {
            'create' => $service->createDraft($input), 'update' => $service->updateDraft($committee,$input),
            'discard' => $service->discardDraft($committee,$request->input('reason','')),
            'order' => $service->addOrder($committee,$input,$request->file('file')),
            'activate' => $service->activate($committee,$input),
            'suspend','resume','dissolve','extend' => $service->changeState($committee,$command,$input),
            'reconstitute' => $service->startReconstitution($committee,$input),
            'seat' => $members->addSeat($committee,$input), 'removeSeat' => $members->removeSeat($committee,(int)$request->route('seat')),
            'appoint' => $members->appoint($committee,(int)$request->route('seat'),$input),
            'replace' => $members->replace($committee,(int)$request->route('seat'),$input),
            'changeDraftHolder' => $members->changeDraftHolder($committee,(int)$request->route('seat'),$input),
            'release' => $members->release($committee,(int)$request->route('tenure'),$input),
            'correct' => $members->correctTenure($committee,(int)$request->route('tenure'),$input),
            'declare' => $this->declare($request,$committee,$members),
            'scope' => $assignments->assignScope($committee,$input),
            'withdraw' => $assignments->withdrawScope($committee,(int)$request->route('scope'),$input),
            'external' => app(ExternalMemberService::class)->create($input),
            'link' => app(ExternalMemberService::class)->link((int)$request->route('member'),$request->input('code','')),
            'type','binding' => app(CatalogService::class)->save($command === 'type' ? 'types' : 'bindings',$request->route('record') ? (int)$request->route('record') : null,$input),
            'transfer' => app(\GovStore\Committee\Services\TransferMembersService::class)->apply($input),
        };
        // Mutations expose identifiers and navigation only, never raw Eloquent attributes or file paths.
        if ($result instanceof Committee) { return response()->json(['saved'=>true,'id'=>$result->id,'url'=>route('committee.show',$result->id)]); }
        return response()->json(is_array($result) ? $result : ['saved'=>true,'id'=>$result?->id]);
    }
    private function declare(Request $request, string $committee, CommitteeMembershipService $members): void
    {
        $request->validate(['file'=>'required|file']); $members->recordDeclaration($committee,(int)$request->route('tenure'),$request->all(),$request->file('file'));
    }
}
