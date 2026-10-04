<?php

namespace Tests\Feature\GovStore;

use App\Models\User;
use Carbon\CarbonImmutable;
use GovStore\Committee\Contracts\{CommitteeResolver,RosterSnapshotProvider,CommitteeQueries};
use GovStore\Committee\DTOs\ScopeRef;
use GovStore\Committee\Enums\ResolutionStatus;
use GovStore\Committee\Models\{Committee,CommitteeType,CommitteeTenure,CommitteeOrder,ExternalMember,PurposeBinding,LedgerEntry};
use GovStore\Committee\Services\{CommitteeService,CommitteeMembershipService,CommitteeAssignmentService,CommitteeLedger,CatalogService,ExternalMemberService,MemberDirectory,OrderAttachmentStore};
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\Committee\Policies\CommitteePolicy;
use GovStore\TenantScope\Http\Middleware\RequireGovAbility;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\{Request,UploadedFile};
use Illuminate\Support\Facades\{DB,Schema,Storage,Route};
use Illuminate\Validation\ValidationException;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Schema-only SQLite fixture. Never connects to or resets the development database. */
class CommitteeTest extends TestCase
{
    public function createApplication()
    {
        foreach (['DB_CONNECTION'=>'sqlite','DB_DATABASE'=>':memory:','LOG_CHANNEL'=>'stderr','CACHE_DRIVER'=>'array','SESSION_DRIVER'=>'array'] as $key=>$value) {
            putenv($key.'='.$value); $_ENV[$key] = $_SERVER[$key] = $value;
        }
        $app = require __DIR__.'/../../../bootstrap/app.php'; $app->make(Kernel::class)->bootstrap(); return $app;
    }
    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-10-05 10:00:00'); \Carbon\Carbon::setTestNow('2026-10-05 10:00:00');
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','govstore-access.mode'=>'enforce','cache.default'=>'array']); DB::purge('sqlite');
        foreach (['gov_company_admins'=>['user_id','company_id'],'gov_ict_jurisdictions'=>['user_id'],
            'gov_location_profiles'=>['location_id','office_admin_id'],'gov_office_responsibilities'=>['user_id','location_id','role_slug'],
            'gov_office_memberships'=>['user_id','location_id','status','is_home_office']] as $name=>$columns) {
            Schema::create($name,function (Blueprint $t) use ($columns) { $t->increments('id'); foreach ($columns as $column) { $t->string($column)->nullable(); } $t->timestamps(); });
        }
        Schema::create('companies',function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->softDeletes(); });
        Schema::create('locations',function (Blueprint $t) { $t->increments('id'); $t->integer('company_id'); $t->integer('parent_id')->nullable(); $t->string('name'); $t->softDeletes(); });
        Schema::create('users',function (Blueprint $t) { $t->increments('id'); foreach (['first_name','last_name','display_name','jobtitle'] as $column) { $t->string($column)->nullable(); } $t->boolean('activated')->default(true); $t->integer('location_id')->nullable(); $t->softDeletes(); });
        Schema::create('gov_employee_verification_tokens',function (Blueprint $t) { $t->increments('id'); $t->integer('user_id'); $t->string('token'); $t->timestamp('expires_at'); $t->timestamp('used_at')->nullable(); });
        Schema::create('action_logs',function (Blueprint $t) {
            $t->increments('id'); $t->string('item_type'); $t->integer('item_id'); $t->integer('company_id'); $t->integer('created_by')->nullable();
            $t->string('action_type'); $t->text('note'); $t->text('log_meta')->nullable(); $t->string('action_source')->nullable(); $t->string('remote_ip')->nullable(); $t->text('user_agent')->nullable(); $t->timestamp('action_date')->nullable(); $t->timestamps();
        });
        (require base_path('packages/gov-store/tenant-scope/src/database/migrations/2026_10_04_000001_create_gov_access_tables.php'))->up();
        foreach (glob(base_path('packages/gov-store/committee/src/Database/migrations/*.php')) as $file) { (require $file)->up(); }
        DB::table('companies')->insert([['id'=>20,'name'=>'Ministry A'],['id'=>21,'name'=>'Ministry B']]);
        DB::table('locations')->insert([['id'=>10,'company_id'=>20,'name'=>'Office A','parent_id'=>null],['id'=>11,'company_id'=>20,'name'=>'Office B','parent_id'=>null],['id'=>12,'company_id'=>21,'name'=>'Office C','parent_id'=>null],['id'=>13,'company_id'=>20,'name'=>'Store A','parent_id'=>10]]);
        DB::table('gov_location_profiles')->insert([['location_id'=>10,'office_admin_id'=>1],['location_id'=>11,'office_admin_id'=>5],['location_id'=>12,'office_admin_id'=>6]]);
        for ($i=1; $i<=6; $i++) {
            $office = $i <= 4 ? 10 : ($i === 5 ? 11 : 12);
            DB::table('users')->insert(['id'=>$i,'first_name'=>'Officer '.$i,'last_name'=>'Test','display_name'=>'পরীক্ষা সদস্য '.$i,'jobtitle'=>'Engineer','activated'=>true,'location_id'=>$office]);
            DB::table('gov_office_memberships')->insert(['user_id'=>$i,'location_id'=>$office,'status'=>'active','is_home_office'=>1]);
        }
        $context = app(TenantContext::class); $context->locationId=10; $context->companyId=20; $context->isActive=true; $context->allowedLocationIds=[10];
        (new \GovStore\Committee\Database\seeders\CommitteeSeeder)->run();
        CommitteeType::where('code','GRIC')->update(['is_active'=>true]);
        $type = CommitteeType::where('code','GRIC')->firstOrFail();
        // Fixture policy has no office-duty warning; real policy warnings have separate coverage.
        $p = $type->composition_policy; $p['incompatible_duties']=[]; $type->update(['composition_policy'=>$p]);
        PurposeBinding::create(['purpose_code'=>'storeops.receipt.inspection','owner_company_id'=>null,'committee_type_id'=>$type->id,'priority'=>0,'allow_ancestor_fallback'=>true,'is_active'=>true,'changed_by'=>1,'change_reason'=>'Test fixture']);
        Storage::fake('committee_private'); $this->actor(1);
    }
    protected function tearDown(): void { CarbonImmutable::setTestNow(); \Carbon\Carbon::setTestNow(); parent::tearDown(); }
    private function actor(int $id, bool $super = false): User
    {
        $user = Mockery::mock(User::class)->makePartial(); $user->id=$id;
        $user->shouldReceive('isSuperUser')->andReturn($super); $user->shouldReceive('hasAccess')->andReturn(false); auth()->setUser($user); return $user;
    }
    private function draft(array $overrides = []): Committee
    {
        return app(CommitteeService::class)->createDraft($overrides + ['committee_type_id'=>CommitteeType::where('code','GRIC')->value('id'),'name_en'=>'Inventory test committee','name_bn'=>'মজুদ পরীক্ষার কমিটি','term_basis'=>'FIXED','effective_from'=>'2026-10-01','effective_to'=>'2027-06-30']);
    }
    private function order(Committee $c, string $kind = 'CONSTITUTION', string $date = '2026-10-01'): CommitteeOrder
    {
        return app(CommitteeService::class)->addOrder($c->id,['kind'=>$kind,'memo_no'=>'৫৬.০৪.০০০০-'.($c->orders()->count()+1),'issued_on'=>$date,'issuing_authority_name'=>'Test authority','issuing_authority_designation_en'=>'Office head'],UploadedFile::fake()->createWithContent('order.pdf',"%PDF-1.4\nTest signed order"));
    }
    private function composed(?Committee $c = null, string $kind = 'CONSTITUTION'): array
    {
        $c ??= $this->draft(); $order = $this->order($c,$kind,$c->effective_from);
        foreach (['chairperson','member_secretary','member'] as $i=>$role) {
            $seat = $c->seats()->where('seat_no',$i+1)->first() ?? app(CommitteeMembershipService::class)->addSeat($c->id,['seat_no'=>$i+1,'seat_role_code'=>$role,'holder_kind'=>'PERSON']);
            app(CommitteeMembershipService::class)->appoint($c->id,$seat->id,['user_id'=>$i+1,'from_date'=>$c->effective_from,'order_id'=>$order->id]);
        }
        app(CommitteeAssignmentService::class)->assignScope($c->id,['scope_type'=>'office','scope_id'=>'10','effective_from'=>$c->effective_from,'order_id'=>$order->id]);
        return [$c->fresh(),$order];
    }
    private function active(): Committee { [$c,$o]=$this->composed(); return app(CommitteeService::class)->activate($c->id,['order_id'=>$o->id]); }
    private function assertHttpStatus(int $status, callable $call): void
    {
        try { $call(); $this->fail('Expected HTTP '.$status); } catch (HttpExceptionInterface $e) { $this->assertSame($status,$e->getStatusCode()); }
    }
    public function test_inventory_catalogue_excludes_procurement_and_starts_inactive(): void
    {
        $this->assertSame(['BOS','DSP','GRIC','SVC','TIC'],CommitteeType::orderBy('code')->pluck('code')->all());
        $this->assertFalse(CommitteeType::where('code','TIC')->first()->is_active);
        $this->assertCount(5,app(\GovStore\Committee\Contracts\PurposeRegistry::class)->all());
        $this->actor(1,true); $type=CommitteeType::where('code','GRIC')->first();
        $data=$type->only(['name_en','name_bn','category','default_term_basis','allowed_scope_types','allow_concurrent','composition_policy','is_active']) + ['code'=>'TEC','scope'=>'ministry','change_reason'=>'Test unsupported procurement'];
        try { app(CatalogService::class)->save('types',null,$data); $this->fail('Procurement template accepted'); } catch (ValidationException $e) { $this->assertArrayHasKey('code',$e->errors()); }
    }
    public function test_draft_is_unresolvable_activation_requires_complete_composition(): void
    {
        $c = $this->draft(); $o = $this->order($c);
        $resolver = app(CommitteeResolver::class); $date = CarbonImmutable::parse('2026-10-03');
        $this->assertSame(ResolutionStatus::NOT_FOUND,$resolver->resolve('storeops.receipt.inspection',new ScopeRef('office','10'),$date)->status);
        try { app(CommitteeService::class)->activate($c->id,['order_id'=>$o->id]); $this->fail('Incomplete constitution activated'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('composition',$e->errors()); }
        $this->assertSame('DRAFT',$c->fresh()->status);
    }
    public function test_exact_ancestor_and_ministry_override_resolution(): void
    {
        $c = $this->active(); $date = CarbonImmutable::parse('2026-10-03'); $r = app(CommitteeResolver::class);
        $this->assertSame($c->id,$r->resolve('storeops.receipt.inspection',new ScopeRef('office','10'),$date)->committee->id);
        $this->assertSame('ANCESTOR:office',$r->resolve('storeops.receipt.inspection',new ScopeRef('store','13'),$date)->resolvedVia);
        PurposeBinding::create(['purpose_code'=>'storeops.receipt.inspection','owner_company_id'=>20,'committee_type_id'=>CommitteeType::where('code','TIC')->value('id'),'priority'=>0,'is_active'=>true,'changed_by'=>1,'change_reason'=>'Ministry override']);
        $this->assertSame(ResolutionStatus::NOT_FOUND,$r->resolve('storeops.receipt.inspection',new ScopeRef('office','10'),$date)->status);
    }
    public function test_duplicate_activation_and_tenure_overlap_roll_back(): void
    {
        $first = $this->active(); [$second,$order] = $this->composed();
        $this->assertHttpStatus(409,fn () => app(CommitteeService::class)->activate($second->id,['order_id'=>$order->id]));
        $this->assertSame('DRAFT',$second->fresh()->status);
        $this->assertSame(1,DB::table('gov_committee_active_slots')->count());
        $amendment=$this->order($first,'AMENDMENT'); $t=$first->tenures()->first();
        $this->assertHttpStatus(409,fn () => app(CommitteeMembershipService::class)->appoint($first->id,$t->seat_id,['user_id'=>4,'from_date'=>'2026-10-03','order_id'=>$amendment->id]));
        $this->assertSame(3,$first->tenures()->count());
    }
    public function test_one_person_cannot_hold_two_seats_and_foreign_body_ids_are_rejected(): void
    {
        [$c,$o] = $this->composed(); $other=$this->draft(); $foreign=$this->order($other);
        $new = app(CommitteeMembershipService::class)->addSeat($c->id,['seat_no'=>4,'seat_role_code'=>'member','holder_kind'=>'PERSON']);
        $this->assertHttpStatus(409,fn () => app(CommitteeMembershipService::class)->appoint($c->id,$new->id,['user_id'=>1,'from_date'=>$c->effective_from,'order_id'=>$o->id]));
        try { app(CommitteeService::class)->activate($c->id,['order_id'=>$foreign->id]); $this->fail('Foreign order accepted'); } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) { $this->assertTrue(true); }
        $this->assertHttpStatus(404,fn () => app(CommitteeMembershipService::class)->appoint($c->id,$new->id,['user_id'=>5,'from_date'=>$c->effective_from,'order_id'=>$o->id]));
    }
    public function test_replacement_preserves_past_roster_and_snapshot(): void
    {
        $c = $this->active(); $provider=app(RosterSnapshotProvider::class); $date=CarbonImmutable::parse('2026-10-02'); $snapshot=$provider->snapshot($c->id,$date);
        $t=$c->tenures()->where('user_id',3)->first(); $o=$this->order($c,'AMENDMENT','2026-10-03');
        app(CommitteeMembershipService::class)->replace($c->id,$t->seat_id,['user_id'=>4,'from_date'=>'2026-10-04','order_id'=>$o->id,'release_reason'=>'TRANSFER','reason'=>'Officer transferred']);
        $q=app(CommitteeQueries::class); $this->assertTrue($q->isMember(3,$c->id,$date)); $this->assertFalse($q->isMember(3,$c->id,CarbonImmutable::parse('2026-10-04')));
        $this->assertTrue($q->isMember(4,$c->id,CarbonImmutable::parse('2026-10-04')));
        $this->assertSame('MATCHES',$provider->verify($snapshot)->status);
        $this->assertSame('TRANSFER',$t->fresh()->release_reason);
    }
    public function test_suspension_is_dated_and_dissolution_preserves_historical_resolution(): void
    {
        $c=$this->active(); $svc=app(CommitteeService::class); $o=$this->order($c,'SUSPENSION','2026-10-03');
        $svc->changeState($c->id,'suspend',['order_id'=>$o->id,'date'=>'2026-10-03','reason'=>'Temporary review']);
        $r=app(CommitteeResolver::class); $ref=new ScopeRef('office','10');
        $this->assertSame(ResolutionStatus::FOUND,$r->resolve('storeops.receipt.inspection',$ref,CarbonImmutable::parse('2026-10-02'))->status);
        $this->assertSame(ResolutionStatus::INOPERABLE,$r->resolve('storeops.receipt.inspection',$ref,CarbonImmutable::parse('2026-10-04'))->status);
        $o=$this->order($c,'DISSOLUTION','2026-10-05');
        $svc->changeState($c->id,'dissolve',['order_id'=>$o->id,'date'=>'2026-10-05','reason'=>'Office order dissolved','confirmation'=>$c->committee_number]);
        $this->assertSame(ResolutionStatus::FOUND,$r->resolve('storeops.receipt.inspection',$ref,CarbonImmutable::parse('2026-10-02'))->status);
        $this->assertSame(ResolutionStatus::NOT_FOUND,$r->resolve('storeops.receipt.inspection',$ref,CarbonImmutable::parse('2026-10-06'))->status);
    }
    public function test_reconstitution_swaps_exclusivity_on_the_effective_day(): void
    {
        $old=$this->active(); $svc=app(CommitteeService::class);
        $new=$svc->startReconstitution($old->id,['effective_from'=>'2026-10-04','effective_to'=>'2027-06-30']);
        [$new,$o]=$this->composed($new,'RECONSTITUTION'); $svc->activate($new->id,['order_id'=>$o->id]);
        $r=app(CommitteeResolver::class); $ref=new ScopeRef('office','10');
        $this->assertSame($old->id,$r->resolve('storeops.receipt.inspection',$ref,CarbonImmutable::parse('2026-10-03'))->committee->id);
        $this->assertSame($new->id,$r->resolve('storeops.receipt.inspection',$ref,CarbonImmutable::parse('2026-10-04'))->committee->id);
        $this->assertSame('SUPERSEDED',$old->fresh()->status);
        $this->assertSame([],app(CommitteeLedger::class)->verifyChain($old->lineage_id));
    }
    public function test_health_detects_disabled_account_and_departure_in_home_office(): void
    {
        $c=$this->active(); DB::table('users')->where('id',1)->update(['activated'=>false]);
        $report=app(CommitteeQueries::class)->health($c->id,CarbonImmutable::now());
        $this->assertContains('MEMBER_ACCOUNT_DISABLED',array_column($report->issues,'code'));
        $this->assertSame('INOPERABLE',$report->status->value);
        DB::table('gov_office_memberships')->where('user_id',2)->update(['status'=>'ended']);
        $this->assertContains('MEMBER_LEFT_OFFICE',array_column(app(CommitteeQueries::class)->health($c->id,CarbonImmutable::now())->issues,'code'));
    }
    public function test_boundary_always_enforces_and_covered_office_cannot_mutate(): void
    {
        $c=$this->active(); $this->actor(5); $ctx=app(TenantContext::class); $ctx->locationId=11; $ctx->allowedLocationIds=[11];
        foreach (['enforce','shadow'] as $mode) { config(['govstore-access.mode'=>$mode]); $this->assertHttpStatus(404,fn () => app(CommitteePolicy::class)->check($c,'committee.view')); }
        DB::table('gov_committee_scopes')->insert(['committee_id'=>$c->id,'scope_type'=>'office','scope_id'=>'11','scope_label_snapshot'=>'Office B','effective_from'=>'2026-10-01','order_id'=>1,'assigned_by'=>1]);
        app(CommitteePolicy::class)->check($c,'committee.view'); $this->assertTrue(true);
        $this->assertHttpStatus(404,fn () => app(CommitteeService::class)->addOrder($c->id,[],null));
        $this->actor(6); $ctx->companyId=21; $ctx->locationId=12; $ctx->allowedLocationIds=[12];
        $this->assertHttpStatus(404,fn () => app(CommitteePolicy::class)->check($c,'committee.view'));
    }
    public function test_code_lookup_is_exact_read_only_and_external_link_keeps_tenure(): void
    {
        $c=$this->draft(); $o=$this->order($c);
        DB::table('gov_employee_verification_tokens')->insert(['user_id'=>5,'token'=>'ABCDEFGH','expires_at'=>now()->addHour()]);
        $this->assertHttpStatus(422,fn () => app(MemberDirectory::class)->byCode('ABC'));
        $before=DB::table('gov_office_memberships')->count();
        $external=app(ExternalMemberService::class)->create(['full_name_en'=>'University expert','full_name_bn'=>'বিশ্ববিদ্যালয়ের বিশেষজ্ঞ','designation_en'=>'Professor','designation_bn'=>'অধ্যাপক','organization_name_en'=>'University','organization_name_bn'=>'বিশ্ববিদ্যালয়','organization_kind'=>'UNIVERSITY']);
        $seat=app(CommitteeMembershipService::class)->addSeat($c->id,['seat_no'=>1,'seat_role_code'=>'chairperson','holder_kind'=>'EXTERNAL']);
        $t=app(CommitteeMembershipService::class)->appoint($c->id,$seat->id,['external_member_id'=>$external->id,'from_date'=>'2026-10-01','order_id'=>$o->id]); $frozen=$t->fresh()->toArray();
        app(ExternalMemberService::class)->link($external->id,'ABCDEFGH');
        $this->assertSame($frozen,$t->fresh()->toArray());
        $this->assertTrue(app(CommitteeQueries::class)->isMember(5,$c->id,CarbonImmutable::now()));
        $this->assertFalse(app(CommitteeQueries::class)->isMember(5,$c->id,CarbonImmutable::parse('2026-10-04')));
        $this->assertSame($before,DB::table('gov_office_memberships')->count()); $this->assertNull(DB::table('gov_employee_verification_tokens')->value('used_at'));
    }
    public function test_private_files_hash_and_authorization(): void
    {
        $c=$this->active(); $o=$c->orders()->first();
        $this->assertSame('committee_private',$o->attachment_disk); $this->assertSame($o->attachment_sha256,hash('sha256',Storage::disk('committee_private')->get($o->attachment_path)));
        $controller=app(\GovStore\Committee\Http\Controllers\CommitteeController::class);
        $this->assertSame(200,$controller->file(Request::create('/'),$o->id)->getStatusCode());
        app(TenantContext::class)->locationId=11; $this->actor(5); $this->assertHttpStatus(404,fn () => $controller->file(Request::create('/'),$o->id));
        app(TenantContext::class)->locationId=10; $this->actor(1); Storage::disk('committee_private')->put($o->attachment_path,'tampered');
        $this->assertHttpStatus(409,fn () => $controller->file(Request::create('/'),$o->id));
    }
    public function test_ledger_detects_tampering_and_snapshot_rejects_forgery(): void
    {
        $c=$this->active(); $ledger=app(CommitteeLedger::class); $this->assertSame([],$ledger->verifyChain($c->lineage_id));
        DB::table('gov_committee_ledger')->where('committee_id',$c->id)->limit(1)->update(['reason'=>'tampered']);
        $this->assertNotEmpty($ledger->verifyChain($c->lineage_id));
        $provider=app(RosterSnapshotProvider::class); $s=$provider->snapshot($c->id,CarbonImmutable::now());
        $this->assertSame('INVALID_FINGERPRINT',$provider->verify(new \GovStore\Committee\DTOs\RosterSnapshot($s->roster,str_repeat('0',64)))->status);
        $this->assertSame(\GovStore\Committee\Domain\MemoNumber::normalize('৫৬.০৪.০০০০-১'),'56.04.0000-1');
    }
    public function test_national_review_freezes_proposal_and_prevents_replay(): void
    {
        $this->actor(1,true); $type=CommitteeType::where('code','GRIC')->first();
        $data=$type->only(['code','name_en','name_bn','category','default_term_basis','allowed_scope_types','allow_concurrent','composition_policy','is_active']) + ['scope'=>'national','change_reason'=>'Review national inventory template'];
        $svc=app(CatalogService::class); $review=$svc->save('types',$type->id,$data); $this->assertTrue($review['review_required']);
        $data['name_en']='Forged unreviewed name'; $data['review_token']=$review['review_token']; $data['confirmation']='CHANGE';
        $svc->save('types',$type->id,$data); $this->assertSame($type->name_en,$type->fresh()->name_en);
        $this->assertHttpStatus(409,fn () => $svc->save('types',$type->id,$data));
        $this->actor(2); config(['govstore-access.mode'=>'shadow']); $this->assertHttpStatus(403,fn () => $svc->save('types',$type->id,$data));
    }
    public function test_every_route_has_one_ability_and_employee_mutations_deny(): void
    {
        $user=$this->actor(4); $checked=0;
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(),'gov-store/committees')) { continue; }
            $abilities=array_values(array_filter($route->gatherMiddleware(),fn ($m)=>str_starts_with($m,'gov.can:')));
            $this->assertCount(1,$abilities); $this->assertArrayHasKey(substr($abilities[0],8),config('govstore-abilities'));
            if (in_array($route->methods()[0],['POST','PUT','DELETE']) && ! str_contains($abilities[0],'committee.declare')) {
                $request=Request::create('/'.$route->uri(),$route->methods()[0]); $request->headers->set('Accept','application/json'); $request->setUserResolver(fn () => $user); app()->instance('request',$request);
                $response=app(RequireGovAbility::class)->handle($request,fn () => throw new \LogicException('Should not reach mutation'),substr($abilities[0],8));
                $this->assertSame(403,$response->getStatusCode());
            }
            $checked++;
        }
        $this->assertGreaterThanOrEqual(50,$checked);
    }
    public function test_people_picker_and_http_route_defaults_use_real_identifiers(): void
    {
        $controller=app(\GovStore\Committee\Http\Controllers\CommitteeController::class);
        $people=$controller->people(Request::create('/gov-store/committees/api/people'))->getData(true);
        $this->assertCount(4,$people['people']); $this->assertNotContains(5,array_column($people['people'],'id'));
        $request=Request::create('/gov-store/committees/drafts','POST',['committee_type_id'=>CommitteeType::where('code','GRIC')->value('id'),'name_en'=>'HTTP default test committee','name_bn'=>'এইচটিটিপি পরীক্ষার কমিটি','term_basis'=>'FIXED','effective_from'=>'2026-10-01','effective_to'=>'2027-06-30']);
        $route=Route::getRoutes()->match($request); $route->bind($request); $request->setRouteResolver(fn () => $route);
        $response=$controller->command($request); $this->assertSame(200,$response->getStatusCode());
        $this->assertSame('DRAFT',Committee::findOrFail($response->getData(true)['id'])->status);
    }
    public function test_package_dependency_boundary_translations_and_compiled_blade_syntax(): void
    {
        $iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('packages/gov-store/committee/src')));
        foreach ($iterator as $file) {
            if (! $file->isFile()) { continue; } $source=file_get_contents($file->getPathname());
            foreach (['StoreOperations','Tracking','CustomRequests','Classification','Metadata','Experimentation'] as $consumer) { $this->assertStringNotContainsString('GovStore\\'.$consumer.'\\',$source); }
            if (str_ends_with($file->getFilename(),'.blade.php')) {
                $compiled=app('blade.compiler')->compileString($source); $temporary=tempnam(sys_get_temp_dir(),'cm_blade_');
                try { file_put_contents($temporary,$compiled); $p=proc_open([PHP_BINARY,'-l',$temporary],[1=>['pipe','w'],2=>['pipe','w']],$pipes); $output=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]); fclose($pipes[1]);fclose($pipes[2]); $this->assertSame(0,proc_close($p),$output); } finally { unlink($temporary); }
            }
        }
        $en=require base_path('packages/gov-store/committee/src/resources/lang/en-US/committee.php'); $bn=require base_path('packages/gov-store/committee/src/resources/lang/bn-BD/committee.php');
        $this->assertSame(array_keys($en),array_keys($bn)); $this->assertSame(array_keys($en['issues']),array_keys($bn['issues']));
    }
    public function test_draft_holder_changes_and_corrigenda_preserve_original_evidence(): void
    {
        [$c,$o]=$this->composed(); $svc=app(CommitteeMembershipService::class); $old=$c->tenures()->where('user_id',3)->first(); $original=$old->toArray();
        $new=$svc->changeDraftHolder($c->id,$old->seat_id,['user_id'=>4,'from_date'=>'2026-10-01','order_id'=>$o->id]);
        $this->assertSame($original,$old->fresh()->toArray()); $this->assertSame($old->id,$new->corrects_tenure_id);
        app(CommitteeService::class)->activate($c->id,['order_id'=>$o->id]);
        $provider=app(RosterSnapshotProvider::class); $snapshot=$provider->snapshot($c->id,CarbonImmutable::parse('2026-10-02'));
        $correction=$this->order($c,'CORRIGENDUM','2026-10-01');
        $corrected=$svc->correctTenure($c->id,$new->id,['user_id'=>3,'order_id'=>$correction->id,'reason'=>'Correct appointment transcription']);
        $this->assertSame($new->id,$corrected->corrects_tenure_id);
        $this->assertSame('CHANGED_SINCE',$provider->verify($snapshot)->status);
        $this->assertTrue(app(CommitteeQueries::class)->isMember(3,$c->id,CarbonImmutable::parse('2026-10-02')));
        $this->assertFalse(app(CommitteeQueries::class)->isMember(4,$c->id,CarbonImmutable::parse('2026-10-02')));
        $this->assertSame([],app(CommitteeLedger::class)->verifyChain($c->lineage_id));
        $this->assertHttpStatus(409,fn () => $svc->changeDraftHolder($c->id,$old->seat_id,[]));
    }
    public function test_concurrent_candidates_are_ambiguous_and_frozen_policy_is_stable(): void
    {
        CommitteeType::where('code','GRIC')->update(['allow_concurrent'=>true]); $a=$this->active(); $b=$this->active();
        $result=app(CommitteeResolver::class)->resolve('storeops.receipt.inspection',new ScopeRef('office','10'),CarbonImmutable::parse('2026-10-03'));
        $this->assertSame(ResolutionStatus::AMBIGUOUS,$result->status);
        $policy=$a->type->composition_policy; $policy['strength']['min']=5; CommitteeType::whereKey($a->committee_type_id)->update(['composition_policy'=>$policy]);
        $this->assertNotContains('BELOW_MIN_STRENGTH',array_column(app(CommitteeQueries::class)->health($a->id,CarbonImmutable::now())->issues,'code'));
        $this->assertSame(0,DB::table('gov_committee_active_slots')->count());
    }
    public function test_expiry_extension_reminders_clearance_and_native_audit(): void
    {
        CarbonImmutable::setTestNow('2026-10-02 10:00:00'); \Carbon\Carbon::setTestNow('2026-10-02 10:00:00');
        [$c,$o]=$this->composed($this->draft(['effective_to'=>'2026-10-03'])); $service=app(CommitteeService::class); $service->activate($c->id,['order_id'=>$o->id]);
        $health=app(\GovStore\Committee\Services\CommitteeHealthService::class); $health->sweep(); $health->sweep();
        $this->assertSame(2,LedgerEntry::where('committee_id',$c->id)->where('event_type','CommitteeExpiringSoon')->count());
        $rule=new \GovStore\Committee\Clearance\NoUnplannedSeatVacancyRule; $user=$this->actor(2);
        $this->assertTrue($rule->check($user,10)->isPassed); config(['committee.clearance.mode'=>'block_if_inoperable']);
        $this->assertFalse($rule->check($user,10)->isPassed); $this->actor(1);
        CarbonImmutable::setTestNow('2026-10-05 10:00:00'); \Carbon\Carbon::setTestNow('2026-10-05 10:00:00');
        $this->assertSame(1,$service->expireDue()); $this->assertSame('EXPIRED',$c->fresh()->status); $this->assertSame(0,DB::table('gov_committee_active_slots')->count());
        $extension=$this->order($c,'EXTENSION','2026-10-03');
        $service->changeState($c->id,'extend',['order_id'=>$extension->id,'date'=>'2026-10-03','effective_to'=>'2026-10-10','reason'=>'Extend inventory verification']);
        $this->assertSame('ACTIVE',$c->fresh()->status); $this->assertNull($c->fresh()->ended_on); $this->assertSame(1,DB::table('gov_committee_active_slots')->count());
        $this->assertSame([],app(CommitteeLedger::class)->verifyChain($c->lineage_id));
        $this->assertSame(LedgerEntry::where('committee_id',$c->id)->count(),DB::table('action_logs')->count());
        $this->assertSame([20],DB::table('action_logs')->distinct()->pluck('company_id')->all());
    }
    public function test_own_declaration_is_private_and_closed_state_cannot_be_changed(): void
    {
        $c=$this->active(); $tenure=$c->tenures()->where('user_id',2)->first(); $this->actor(4); $svc=app(CommitteeMembershipService::class);
        $file=fn () => UploadedFile::fake()->createWithContent('declaration.pdf',"%PDF-1.4\nTest declaration");
        $this->assertHttpStatus(403,fn () => $svc->recordDeclaration($c->id,$tenure->id,['filed_on'=>'2026-10-05'],$file()));
        $this->actor(2); $svc->recordDeclaration($c->id,$tenure->id,['filed_on'=>'2026-10-05'],$file());
        $t=$tenure->fresh(); $this->assertSame('FILED',$t->declaration_status);
        $controller=app(\GovStore\Committee\Http\Controllers\CommitteeController::class);
        $this->assertSame(200,$controller->ownDeclarationFile(Request::create('/'),$t->id)->getStatusCode());
        $this->actor(4); $this->assertHttpStatus(404,fn () => $controller->ownDeclarationFile(Request::create('/'),$t->id)); $this->actor(2);
        $this->assertSame($t->declaration_attachment_sha256,hash('sha256',Storage::disk('committee_private')->get($t->declaration_attachment_path)));
        $this->assertHttpStatus(409,fn () => $svc->recordDeclaration($c->id,$tenure->id,['filed_on'=>'2026-10-05'],$file()));
        $this->actor(1); $o=$this->order($c,'DISSOLUTION','2026-10-05'); app(CommitteeService::class)->changeState($c->id,'dissolve',['order_id'=>$o->id,'date'=>'2026-10-05','reason'=>'End test committee','confirmation'=>$c->committee_number]);
        $other=$c->tenures()->where('user_id',3)->first(); $this->actor(3);
        $this->assertHttpStatus(409,fn () => $svc->recordDeclaration($c->id,$other->id,['filed_on'=>'2026-10-05'],$file()));
    }
    public function test_withdrawal_releases_slot_next_day_but_preserves_historical_exclusivity(): void
    {
        $c=$this->active(); $o=$this->order($c,'AMENDMENT','2026-10-05'); $scope=$c->scopes()->first();
        $input=['order_id'=>$o->id,'effective_to'=>'2026-10-05','reason'=>'Office coverage ended'];
        try { app(CommitteeAssignmentService::class)->withdrawScope($c->id,$scope->id,$input); $this->fail('Last scope withdrawn without acknowledgement'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('acknowledgement',$e->errors()); }
        $this->assertNull($scope->fresh()->effective_to);
        app(CommitteeAssignmentService::class)->withdrawScope($c->id,$scope->id,$input + ['acknowledgement'=>'INOPERABLE']);
        $this->assertSame(1,DB::table('gov_committee_active_slots')->count());
        CarbonImmutable::setTestNow('2026-10-06 10:00:00'); \Carbon\Carbon::setTestNow('2026-10-06 10:00:00'); app(CommitteeService::class)->expireDue();
        $this->assertSame(0,DB::table('gov_committee_active_slots')->count());
        [$historical,$order]=$this->composed(); $this->assertHttpStatus(409,fn () => app(CommitteeService::class)->activate($historical->id,['order_id'=>$order->id]));
        [$next,$nextOrder]=$this->composed($this->draft(['effective_from'=>'2026-10-06'])); app(CommitteeService::class)->activate($next->id,['order_id'=>$nextOrder->id]);
        $this->assertSame('ACTIVE',$next->fresh()->status);
    }
    public function test_transfer_batch_rolls_back_every_committee_on_foreign_order(): void
    {
        CommitteeType::where('code','GRIC')->update(['allow_concurrent'=>true]); $a=$this->active(); $b=$this->active(); $first=$this->order($a,'AMENDMENT'); $second=$this->order($b,'AMENDMENT');
        $seatA=$a->tenures()->where('user_id',3)->value('seat_id'); $seatB=$b->tenures()->where('user_id',3)->value('seat_id');
        $input=['user_id'=>3,'reason'=>'Officer transfer test','changes'=>[
            ['committee_id'=>$a->id,'seat_id'=>$seatA,'order_id'=>$first->id,'from_date'=>'2026-10-04','user_id'=>4],
            ['committee_id'=>$b->id,'seat_id'=>$seatB,'order_id'=>$first->id,'from_date'=>'2026-10-04','user_id'=>4],
        ]];
        $before=LedgerEntry::count(); $native=DB::table('action_logs')->count();
        try { app(\GovStore\Committee\Services\TransferMembersService::class)->apply($input); $this->fail('Foreign amendment accepted'); } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) { $this->assertTrue(true); }
        $this->assertSame($before,LedgerEntry::count()); $this->assertSame($native,DB::table('action_logs')->count()); $this->assertSame(6,CommitteeTenure::count());
        $input['changes'][1]['order_id']=$second->id; $result=app(\GovStore\Committee\Services\TransferMembersService::class)->apply($input);
        $this->assertCount(2,$result['tenure_ids']); $this->assertSame(8,CommitteeTenure::count());
    }
    public function test_discarded_reconstitution_does_not_reuse_version_or_break_chain(): void
    {
        $old=$this->active(); $svc=app(CommitteeService::class);
        $discarded=$svc->startReconstitution($old->id,['effective_from'=>'2026-10-04','effective_to'=>'2027-06-30']);
        $svc->discardDraft($discarded->id,'Draft constitution withdrawn');
        $next=$svc->startReconstitution($old->id,['effective_from'=>'2026-10-05','effective_to'=>'2027-06-30']);
        $this->assertSame(3,$next->version_no); $this->assertSame('ACTIVE',$old->fresh()->status);
        $this->assertSame([],app(CommitteeLedger::class)->verifyChain($old->lineage_id));
    }
    public function test_visible_visitor_keeps_home_affiliation_without_creating_membership(): void
    {
        DB::table('gov_office_memberships')->insert(['user_id'=>5,'location_id'=>10,'status'=>'active','is_home_office'=>0]);
        $before=DB::table('gov_office_memberships')->count(); $person=app(MemberDirectory::class)->person(5);
        $this->assertSame(11,$person['home_location_id']); $this->assertSame(20,$person['home_company_id']);
        $c=$this->draft(); $o=$this->order($c); $seat=app(CommitteeMembershipService::class)->addSeat($c->id,['seat_no'=>1,'seat_role_code'=>'chairperson','holder_kind'=>'PERSON']);
        $t=app(CommitteeMembershipService::class)->appoint($c->id,$seat->id,['user_id'=>5,'from_date'=>$c->effective_from,'order_id'=>$o->id]);
        $this->assertTrue($t->is_external); $this->assertSame(11,$t->home_location_id_snapshot); $this->assertSame($before,DB::table('gov_office_memberships')->count());
    }
}
