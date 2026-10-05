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
    private function uiData(array $data): array
    {
        $display = \GovStore\Committee\Support\CommitteeDisplay::class;
        $enumOptions = fn ($keys) => collect(explode(' ',$keys))->mapWithKeys(fn ($key) => [$key => __('committee::committee.options.'.$key)])->all();
        $typeOptions = ($data['types'] ?? collect())->where('is_active',true)->mapWithKeys(fn ($t) => [$t->id=>$display::text($t->name_bn,$t->name_en)])->all();
        $orderOptions = collect($data['orders'] ?? [])->mapWithKeys(fn ($o) => [$o['id']=>$o['memo_no'].' · '.$display::date($o['issued_on'])])->all();
        return $data + compact('display','enumOptions','typeOptions','orderOptions') + ['context'=>$this->context];
    }
    public function page(Request $request)
    {
        $committee = $request->route('committee');
        $mode = $request->route()->defaults['page'] ?? ($committee ? 'show' : 'dashboard'); $date = $this->date($request);
        if ($mode === 'dashboard' && !app(\GovStore\TenantScope\Services\GovAccess::class)->permitsRequest(auth()->user(),'committee.manage')) { return redirect()->route('committee.mine'); }
        $c = $committee ? Committee::findOrFail($committee) : null;
        if ($c) { $this->policy->check($c,'committee.view'); }
        $request->validate(['q'=>'nullable|string|max:150','step'=>'nullable|in:1,2,3,4','action'=>'nullable|in:replace,release,correct,extend,suspend,resume,dissolve,scope,order','seat'=>'nullable|integer']);
        abort_if(!$c && $request->filled('action'),422);
        $list = $this->boundary->apply(Committee::with('type'));
        if ($request->filled('q')) {
            $term = '%'.$request->q.'%';
            $list->where(fn ($q) => $q->where('name_en','like',$term)->orWhere('name_bn','like',$term)->orWhere('committee_number','like',$term)
                ->orWhereHas('orders',fn ($q) => $q->where('memo_no_normalized','like','%'.MemoNumber::normalize($request->q).'%'))
                ->orWhereHas('tenures',fn ($q) => $q->where('name_snapshot_bn','like',$term)->orWhere('name_snapshot_en','like',$term)));
        }
        $rows = $list->orderByDesc('created_at')->paginate(20)->withQueryString();
        $types = $this->visibleTypes(); $roles = SeatRole::where('is_active',true)->orderBy('sort_order')->get();
        if ($c && $c->status === 'DRAFT' && ! $request->filled('as_of')) { $date = CarbonImmutable::parse($c->effective_from); }
        $view = $c ? $this->queries->view($c,$date) : null;
        $health = $c ? $this->queries->health($c->id,$date) : null;
        $orders = $c ? $c->orders()->orderByDesc('issued_on')->get()->map($this->transformer->order(...))->all() : [];
        $history = $c ? LedgerEntry::where('lineage_id',$c->lineage_id)->orderByDesc('id')->get()->map($this->transformer->history(...)) : collect();
        $coverage = $c ? $c->scopes : collect();
        $chain = $c ? app(CommitteeLedger::class)->verifyChain($c->lineage_id) : [];
        $purposes = app(PurposeRegistry::class)->all(); $bindings = PurposeBinding::where(fn ($q) => $q->whereNull('owner_company_id')->orWhere('owner_company_id',$this->context->companyId))->get();
        $tabs = app(CommitteeTabRegistry::class)->all();
        $desk = $mode === 'dashboard' ? app(\GovStore\Committee\Services\CommitteeDesk::class)->build() : [];
        $action = $request->input('action'); $step = (int)$request->input('step',$c && $orders ? 3 : 1);
        $own = $c && (int)$c->owner_location_id === $this->context->locationId && (int)$c->owner_company_id === $this->context->companyId;
        $previousSeats=collect(); $reconstitutionDiff=[];
        if ($c?->supersedes_id) {
            $previous=Committee::findOrFail($c->supersedes_id); $this->policy->check($previous,'committee.view');
            $previousDate=min($previous->effective_to ?? '9999-12-31',CarbonImmutable::parse($c->effective_from)->subDay()->toDateString());
            $previousSeats=collect($this->queries->view($previous,CarbonImmutable::parse($previousDate))->seats)->keyBy('number');
            $identity=fn ($holder)=>$holder?->userId ? 'user:'.$holder->userId : ($holder?->externalMemberId ? 'external:'.$holder->externalMemberId : null);
            $before=$previousSeats->filter(fn ($s)=>$s->holder)->mapWithKeys(fn ($s)=>[$identity($s->holder)=>$s->holder]);
            $after=collect($view->seats)->filter(fn ($s)=>$s->holder)->mapWithKeys(fn ($s)=>[$identity($s->holder)=>$s->holder]);
            $reconstitutionDiff=['kept'=>$after->intersectByKeys($before),'added'=>$after->diffKeys($before),'removed'=>$before->diffKeys($after)];
        }
        $editingDraft = $own && $c->status === 'DRAFT' && app(\GovStore\TenantScope\Services\GovAccess::class)->permitsRequest(auth()->user(),'committee.manage');
        $screen = $c ? ($mode === 'history' ? 'history' : ($mode === 'reconstitute' ? 'order/start' : ($c->status === 'DRAFT' ? 'order/'.([1=>'start',2=>'committee',3=>'members',4=>'confirm'][$step]) : ($action ? (in_array($action,['replace','release','correct']) ? 'replace' : ($action === 'dissolve' ? 'dissolve' : 'change')) : 'show'))))
            : match ($mode) { 'new'=>'order/start','dashboard'=>'desk','transfers'=>'transfer','types','purposes'=>'rules',default=>'registry' };
        if ($c && $c->status === 'DRAFT' && !$editingDraft && $mode !== 'history') { $screen='show'; }
        if ($action || $mode === 'reconstitute' || ($editingDraft && $mode !== 'history' && $mode !== 'print')) {
            $states=$mode === 'reconstitute' ? ['ACTIVE','EXPIRED'] : ($action ? match ($action) {
                'extend'=>['ACTIVE','EXPIRED'],'resume'=>['SUSPENDED'],'dissolve'=>['ACTIVE','SUSPENDED'],'order'=>['DRAFT','ACTIVE','SUSPENDED','EXPIRED'],default=>['ACTIVE'],
            } : ['DRAFT']);
            $this->policy->check($c,'committee.manage',true,$states);
        }
        $workingOrder = collect($orders)->first(fn ($o) => in_array($o['kind'],$c?->status === 'DRAFT' ? [$c->supersedes_id ? 'RECONSTITUTION' : 'CONSTITUTION'] : [match ($action) { 'extend'=>'EXTENSION','suspend'=>'SUSPENSION','resume'=>'RESUMPTION','dissolve'=>'DISSOLUTION','correct'=>'CORRIGENDUM',default=>'AMENDMENT' }]));
        if ($c && !$action && $c->status !== 'DRAFT') { $workingOrder=collect($orders)->firstWhere('id',$c->constitution_order_id); }
        $intake=$mode === 'new' ? app(\GovStore\Committee\Services\OrderIntake::class)->current() : ['fields'=>[],'has_file'=>false,'revision'=>0];
        $targets = in_array($mode,['new','reconstitute']) ? $this->boundary->apply(Committee::where('owner_location_id',$this->context->locationId)->where('owner_company_id',$this->context->companyId)->whereIn('status',['ACTIVE','SUSPENDED','EXPIRED']))->orderBy('name_en')->get() : collect();
        $officeName = DB::table('locations')->where('id',$c?->owner_location_id ?? $this->context->locationId)->value('name');
        return view($mode === 'print' ? 'committee::print' : 'committee::'.$screen,$this->uiData(compact('mode','c','date','rows','types','roles','view','health','orders','history','coverage','chain','purposes','bindings','tabs','desk','action','step','own','workingOrder','officeName','intake','targets','previousSeats','reconstitutionDiff')));
    }
    public function mine(Request $request)
    {
        $date = $this->date($request);
        $all = CommitteeTenure::with('seat.role')->where(fn ($q) => $q->where('user_id',auth()->id())->orWhereIn('external_member_id',ExternalMember::where('linked_user_id',auth()->id())->pluck('id')))->orderByDesc('from_date')->get();
        $corrected = CommitteeTenure::whereIn('corrects_tenure_id',$all->pluck('id'))->pluck('corrects_tenure_id');
        $all = $all->reject(fn ($t) => $corrected->contains($t->id));
        $committees = Committee::whereIn('id',$all->pluck('committee_id'))->get()->keyBy('id');
        $current = $all->filter(function ($t) use ($committees,$date) {
            $c=$committees->get($t->committee_id);$on=$date->toDateString();
            return $c && in_array($c->status,['ACTIVE','SUSPENDED']) && $c->effective_from <= $on && (!$c->effective_to || $c->effective_to >= $on)
                && (!$c->ended_on || $c->ended_on >= $on) && $t->from_date <= $on && (!$t->to_date || $t->to_date >= $on);
        });
        $past = $all->diff($current); $mode = 'mine';
        return view('committee::mine',$this->uiData(compact('all','committees','date','current','past','mode')));
    }
    public function deskApi() { $this->policy->ability('committee.view'); return response()->json(app(\GovStore\Committee\Services\CommitteeDesk::class)->build()); }
    public function ruleImpact(Request $request)
    {
        $this->policy->ability('committee.types.manage');
        abort_unless(app(\GovStore\TenantScope\Services\GovAccess::class)->decide(auth()->user(),'committee.types.manage')->allowed,403);
        $request->validate(['type_id'=>'required|integer','composition_policy'=>'required|array']);
        $type=$this->visibleTypes()->firstWhere('id',(int)$request->type_id); abort_unless($type,404);
        $proposed=\GovStore\Committee\Domain\CompositionPolicy::validate($request->input('composition_policy')+['incompatible_duties'=>[]]);
        $rows=$this->boundary->apply(Committee::where('owner_company_id',$this->context->companyId)->where('committee_type_id',$type->id)->whereIn('status',['ACTIVE','SUSPENDED']))->get();
        $count=0;
        foreach ($rows as $row) {
            // An in-memory policy comparison never changes the committee's frozen policy or health projection.
            $row->policy_snapshot=$proposed;
            if (app(CompositionValidator::class)->evaluate($row,now('Asia/Dhaka')->toDateString())) { $count++; }
        }
        return response()->json(['count'=>$count,'message'=>__('committee::committee.ux.impact_count',['count'=>\GovStore\Committee\Support\CommitteeDisplay::digits($count)])]);
    }
    public function saveIntake(Request $request)
    {
        return response()->json(app(\GovStore\Committee\Services\OrderIntake::class)->save($request->except('_token'),$request->file('file')));
    }
    public function discardIntake(Request $request)
    {
        $request->validate(['intake_revision'=>'required|integer|min:1']);
        app(\GovStore\Committee\Services\OrderIntake::class)->discard((int)$request->intake_revision,$request->all());
        return response()->json(['saved'=>true,'url'=>route('committee.new')]);
    }
    public function dismissReminder(Request $request, string $committee)
    {
        $c = Committee::findOrFail($committee); $this->policy->check($c,'committee.manage',true,['ACTIVE','SUSPENDED']);
        abort_unless($c->effective_to,422);
        DB::table('gov_committee_reminder_dismissals')->updateOrInsert(['user_id'=>auth()->id(),'committee_id'=>$c->id,'effective_to'=>$c->effective_to],['dismissed_at'=>now()]);
        return response()->json(['saved'=>true,'message'=>__('committee::committee.ux.reminder_dismissed')]);
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
        $request->validate(['q'=>'nullable|string|max:100','transfers'=>'nullable|boolean']);
        if ($request->boolean('transfers')) {
            $today=now('Asia/Dhaka')->toDateString();
            $owned=$this->boundary->apply(Committee::where('owner_location_id',$this->context->locationId)->where('owner_company_id',$this->context->companyId)->where('status','ACTIVE')->where('effective_from','<=',$today)->where(fn ($q)=>$q->whereNull('effective_to')->orWhere('effective_to','>=',$today)))->pluck('id');
            $all=CommitteeTenure::whereIn('committee_id',$owned)->whereNotNull('user_id')->get();$corrected=$all->pluck('corrects_tenure_id')->filter();
            $term=mb_strtolower($request->input('q',''));$display=\GovStore\Committee\Support\CommitteeDisplay::class;
            $rows=$all->filter(fn ($t)=>!$corrected->contains($t->id) && $t->status==='ACTIVE' && $t->from_date <= $today && (!$t->to_date || $t->to_date >= $today)
                && (!$term || str_contains(mb_strtolower($t->name_snapshot_bn.' '.$t->name_snapshot_en),$term)))->unique('user_id')->take(50);
            return response()->json(['people'=>$rows->map(fn ($t)=>['id'=>$t->user_id,'text'=>$display::text($t->name_snapshot_bn,$t->name_snapshot_en),'designation'=>$display::text($t->designation_snapshot_bn,$t->designation_snapshot_en)])->values(),'external'=>[]]);
        }
        $offices = $this->context->allowedLocationIds ?? [$this->context->locationId ?? 0];
        $usersQuery = DB::table('users')->where('activated',1)->whereNull('deleted_at')->where(fn ($q) => $q->where('first_name','like','%'.$request->input('q','').'%')->orWhere('last_name','like','%'.$request->input('q','').'%')->orWhere('display_name','like','%'.$request->input('q','').'%'));
        $usersQuery->whereIn('id',DB::table('gov_office_memberships')->where('status','active')->whereIn('location_id',$offices)->whereIn('location_id',DB::table('locations')->where('company_id',$this->context->companyId)->select('id'))->select('user_id'));
        $users = $usersQuery->limit(50)->get(['id','first_name','last_name','display_name','jobtitle']);
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
        $committees = $this->boundary->apply(Committee::where('owner_location_id',$this->context->locationId)->where('owner_company_id',$this->context->companyId)->where('status','ACTIVE'))->get()->keyBy('id');
        $corrected=CommitteeTenure::whereIn('committee_id',$committees->keys())->pluck('corrects_tenure_id')->filter()->all();$today=now('Asia/Dhaka')->toDateString();
        $current=CommitteeTenure::whereIn('committee_id',$committees->keys())->whereNotIn('id',$corrected)->where('user_id',$request->user_id)->where('status','ACTIVE')->where('from_date','<=',$today)->where(fn ($q)=>$q->whereNull('to_date')->orWhere('to_date','>=',$today));
        abort_unless((clone $current)->exists(),404);
        $seats = $current->get()->map(function ($t) use ($committees) {
            $c = $committees[$t->committee_id];
            $seat = $c->seats()->with('role')->findOrFail($t->seat_id); $display = \GovStore\Committee\Support\CommitteeDisplay::class;
            return ['committee_id'=>$c->id,'committee_number'=>$c->committee_number,'committee_name'=>$display::text($c->name_bn,$c->name_en),'role'=>$display::text($seat->role->name_bn,$seat->role->name_en),'seat_id'=>$t->seat_id,'name'=>$display::text($t->name_snapshot_bn,$t->name_snapshot_en),
                'from_date'=>now('Asia/Dhaka')->toDateString(),'orders'=>$c->orders()->where('kind','AMENDMENT')->get()->map(fn ($o) => ['id'=>$o->id,'text'=>$o->memo_no])];
        });
        // Only an officer already proven to hold a role in this office's committee may be looked up.
        // Expose their same-ministry owning-office notice, never another office's roster, IDs or evidence.
        $other = Committee::where('owner_location_id','<>',$this->context->locationId)->where('owner_company_id',$this->context->companyId)->where('status','ACTIVE')->whereHas('tenures',fn ($q)=>$q->where('user_id',$request->user_id)->where('status','ACTIVE'))->get()->keyBy('id');
        $otherCorrected=CommitteeTenure::whereIn('committee_id',$other->keys())->pluck('corrects_tenure_id')->filter()->all();
        $otherSeats = CommitteeTenure::whereIn('committee_id',$other->keys())->whereNotIn('id',$otherCorrected)->where('user_id',$request->user_id)->where('status','ACTIVE')->where('from_date','<=',$today)->where(fn ($q)=>$q->whereNull('to_date')->orWhere('to_date','>=',$today))->get()->map(function ($t) use ($other) {
            $c = $other[$t->committee_id]; $display = \GovStore\Committee\Support\CommitteeDisplay::class;
            return ['committee_name'=>$display::text($c->name_bn,$c->name_en),'name'=>$display::text($t->name_snapshot_bn,$t->name_snapshot_en),'office'=>DB::table('locations')->where('id',$c->owner_location_id)->value('name')];
        });
        return response()->json(['seats'=>$seats,'other_seats'=>$otherSeats]);
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
    public function ownOrderFile(Request $request, int $tenure)
    {
        $this->policy->ability('committee.self');
        $t = CommitteeTenure::findOrFail($tenure);
        $userId = $t->user_id ?? ExternalMember::find($t->external_member_id)?->linked_user_id;
        abort_unless($userId === auth()->id(),404);
        $order = CommitteeOrder::where('committee_id',$t->committee_id)->findOrFail($t->appointment_order_id);
        return app(OrderAttachmentStore::class)->stream($order->attachment_path ?? '',$order->attachment_sha256 ?? '');
    }
    public function preview(Request $request, string $committee)
    {
        $data = $request->validate(['operation'=>'required|in:replace,release,correct,appoint','seat_id'=>'required|integer','tenure_id'=>'nullable|integer']);
        $c = Committee::findOrFail($committee); $this->policy->check($c,'committee.manage',true,['ACTIVE']);
        $c->seats()->findOrFail($data['seat_id']);
        // Run the authoritative mutation in a transaction that is always rolled back.
        // Committee events dispatch after commit, so a preview cannot publish them.
        DB::beginTransaction();
        try {
            $input = $request->except(['operation','seat_id','tenure_id','_token']);
            if (empty($input['order_id'])) {
                $request->validate(['issued_on'=>'required|date_format:Y-m-d|before_or_equal:'.now('Asia/Dhaka')->toDateString()]);
                // A temporary, committee-bound order lets the same rules check a transfer before its signed copy is recorded.
                // It and every related ledger entry are discarded by the mandatory rollback below.
                $order=$c->orders()->create(['kind'=>$data['operation']==='correct'?'CORRIGENDUM':'AMENDMENT','memo_no'=>'Preview','memo_no_normalized'=>'preview',
                    'issued_on'=>$request->issued_on,'issuing_location_id'=>$c->owner_location_id,'issuing_authority_name'=>'Preview','issuing_authority_designation_en'=>'Preview','recorded_by'=>auth()->id(),'recorded_at'=>now()]);
                $input['order_id']=$order->id;
            }
            if ($request->filled('transferred_user_id')) {
                $request->validate(['transferred_user_id'=>'required|integer']);
                $corrected=$c->tenures()->pluck('corrects_tenure_id')->filter()->all();
                $outgoing=$c->tenures()->whereNotIn('id',$corrected)->where('seat_id',$data['seat_id'])->where('user_id',$request->transferred_user_id)->where('status','ACTIVE')
                    ->where('from_date','<',$input['from_date'] ?? '')->where(fn ($q)=>$q->whereNull('to_date')->orWhere('to_date','>=',$input['from_date'] ?? ''))->firstOrFail();
                $data['tenure_id']=$outgoing->id;
            }
            $input['acknowledgement'] = 'INOPERABLE';
            $input['acknowledged'] = array_keys(__('committee::committee.issues',[],'en-US'));
            $input['reason'] = 'Preview of proposed committee change';
            $members = app(CommitteeMembershipService::class);
            match ($data['operation']) {
                'replace'=>$members->replace($committee,$data['seat_id'],$input),
                'appoint'=>$members->appoint($committee,$data['seat_id'],$input),
                'release'=>$request->filled('transferred_user_id') ? $members->vacateForTransfer($committee,$data['seat_id'],(int)$request->transferred_user_id,$input) : $members->release($committee,$c->tenures()->where('seat_id',$data['seat_id'])->findOrFail($data['tenure_id'])->id,$input),
                'correct'=>$members->correctTenure($committee,$c->tenures()->where('seat_id',$data['seat_id'])->findOrFail($data['tenure_id'])->id,$input),
            };
            $directRelease=$data['operation'] === 'release' && !$request->filled('transferred_user_id');
            $on = CarbonImmutable::parse($directRelease ? $input['to_date'] : $input['from_date'])->addDays($directRelease ? 1 : 0);
            $findings = app(CompositionValidator::class)->evaluate($c->fresh(),$on->toDateString());
            $result = ['view'=>$this->queries->view($c->fresh(),$on),'findings'=>$findings];
        } finally { DB::rollBack(); }
        return response()->json($result);
    }
    public function command(Request $request)
    {
        $committee = $request->route('committee');
        $command = $request->route()->defaults['command']; $input = $request->except(['_token']);
        $service = app(CommitteeService::class); $members = app(CommitteeMembershipService::class); $assignments = app(CommitteeAssignmentService::class);
        // The order-first UI composes existing commands atomically. Direct command callers retain their contract.
        if ($request->boolean('order_flow') && in_array($command,['create','reconstitute'])) {
            $created = null; $recorded = null;
            try {
                $result = DB::transaction(function () use ($request,$input,$command,$committee,$service,&$created,&$recorded) {
                    $stagedFile=$request->has('intake_revision') ? app(\GovStore\Committee\Services\OrderIntake::class)->consume((int)$request->input('intake_revision'),$input) : null;
                    $created = $command === 'create' ? $service->createDraft($input) : $service->startReconstitution($committee,$input);
                    $recorded = $service->addOrder($created->id,['kind'=>$command === 'create' ? 'CONSTITUTION' : 'RECONSTITUTION'] + $input,$request->file('file') ?? $stagedFile);
                    app(CommitteeAssignmentService::class)->assignScope($created->id,['scope_type'=>'office','scope_id'=>(string)$created->owner_location_id,'effective_from'=>$created->effective_from,'order_id'=>$recorded->id]);
                    if ($command === 'reconstitute') {
                        $previous=Committee::findOrFail($committee); $previousDate=min($previous->effective_to ?? '9999-12-31',CarbonImmutable::parse($created->effective_from)->subDay()->toDateString());
                        foreach ($this->queries->view($previous,CarbonImmutable::parse($previousDate))->seats as $oldSeat) {
                            if (!$oldSeat->holder) { continue; }
                            // Preserve existing directory consent and boundary checks. Another office's officer needs a fresh code.
                            if (!$oldSeat->holder->externalMemberId) {
                                try { app(MemberDirectory::class)->person($oldSeat->holder->userId); }
                                catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { if (in_array($e->getStatusCode(),[404,422])) { continue; } throw $e; }
                            }
                            $seat=$created->seats()->where('seat_no',$oldSeat->number)->firstOrFail();
                            app(CommitteeMembershipService::class)->appoint($created->id,$seat->id,['user_id'=>$oldSeat->holder->externalMemberId ? null : $oldSeat->holder->userId,'external_member_id'=>$oldSeat->holder->externalMemberId,'from_date'=>$created->effective_from,'order_id'=>$recorded->id]);
                        }
                    }
                    return $created;
                });
            } catch (\Throwable $e) {
                if ($recorded) { \Illuminate\Support\Facades\Storage::disk('committee_private')->delete($recorded->attachment_path); }
                throw $e;
            }
            return response()->json(['saved'=>true,'id'=>$result->id,'url'=>route('committee.show',['committee'=>$result->id,'step'=>3]),'message'=>__('committee::committee.ux.draft_saved')]);
        }
        if ($request->boolean('person_flow') && in_array($command,['seat','appoint','changeDraftHolder'])) {
            $result = DB::transaction(function () use ($input,$command,$committee,$members) {
                if (($input['person_source'] ?? '') === 'external') {
                    $external = app(ExternalMemberService::class)->create($input);
                    $input['external_member_id'] = $external->id; unset($input['user_id']);
                }
                if ($command === 'seat') {
                    $seat = $members->addSeat($committee,$input);
                    return $members->appoint($committee,$seat->id,$input);
                }
                $seat = (int)request()->route('seat');
                return $command === 'appoint' ? $members->appoint($committee,$seat,$input) : $members->changeDraftHolder($committee,$seat,$input);
            });
        } elseif ($request->boolean('order_flow') && $command === 'transfer') {
            $recorded = [];
            try {
                $result = DB::transaction(function () use ($request,$input,$service,&$recorded) {
                    $request->validate(['changes'=>'required|array|min:1|max:20','changes.*.committee_id'=>'required|uuid']);
                    // Match the batch service's lock order before recording any orders.
                    $locked=Committee::whereIn('id',collect($input['changes'])->pluck('committee_id'))->get();
                    foreach ($locked->pluck('committee_type_id')->unique()->sort() as $type) { CommitteeType::whereKey($type)->lockForUpdate()->firstOrFail(); }
                    foreach ($locked->pluck('lineage_id')->unique()->sort() as $lineage) { Committee::where('lineage_id',$lineage)->orderBy('version_no')->lockForUpdate()->firstOrFail(); }
                    $orders = [];
                    foreach (collect($input['changes'])->pluck('committee_id')->unique()->sort() as $id) {
                        $recorded[] = $orders[$id] = $service->addOrder($id,['kind'=>'AMENDMENT'] + $input,$request->file('file'));
                    }
                    foreach ($input['changes'] as &$change) { $change['order_id'] = $orders[$change['committee_id']]->id; } unset($change);
                    return app(\GovStore\Committee\Services\TransferMembersService::class)->apply($input);
                });
            } catch (\Throwable $e) {
                foreach ($recorded as $order) { \Illuminate\Support\Facades\Storage::disk('committee_private')->delete($order->attachment_path); }
                throw $e;
            }
        } elseif ($command === 'order' && $request->has('intake_revision')) {
            $recorded=null;
            try { $result=DB::transaction(function () use ($request,$input,$committee,$service,&$recorded) {
                $file=app(\GovStore\Committee\Services\OrderIntake::class)->consume((int)$request->input('intake_revision'),$input);
                return $recorded=$service->addOrder($committee,$input,$request->file('file') ?? $file);
            }); } catch (\Throwable $e) { if ($recorded) { \Illuminate\Support\Facades\Storage::disk('committee_private')->delete($recorded->attachment_path); } throw $e; }
        } else { $result = match ($command) {
            'create' => $service->createDraft($input), 'update' => $service->updateDraft($committee,$input),
            'discard' => $service->discardDraft($committee,$request->input('reason','')),
            'order' => $service->addOrder($committee,$input,$request->file('file')),
            'activate' => $service->activate($committee,$input),
            'suspend','resume','dissolve','extend' => $service->changeState($committee,$command,$input),
            'reconstitute' => $service->startReconstitution($committee,$input),
            'seat' => $members->addSeat($committee,$input), 'updateSeat' => $members->updateDraftSeat($committee,(int)$request->route('seat'),$input), 'removeSeat' => $members->removeSeat($committee,(int)$request->route('seat')),
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
        }; }
        // Mutations expose identifiers and navigation only, never raw Eloquent attributes or file paths.
        if ($result instanceof Committee) { return response()->json(['saved'=>true,'id'=>$result->id,'lock_version'=>$result->lock_version,'url'=>route('committee.show',$result->id),'message'=>__('committee::committee.saved')]); }
        return response()->json(is_array($result) ? $result : ['saved'=>true,'id'=>$result?->id,'message'=>__('committee::committee.saved')]);
    }
    private function declare(Request $request, string $committee, CommitteeMembershipService $members): void
    {
        $request->validate(['file'=>'required|file']); $members->recordDeclaration($committee,(int)$request->route('tenure'),$request->all(),$request->file('file'));
    }
}
