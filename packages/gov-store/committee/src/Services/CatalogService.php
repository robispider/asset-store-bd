<?php

namespace GovStore\Committee\Services;

use GovStore\Committee\Contracts\{PurposeRegistry,ScopeTypeRegistry};
use GovStore\Committee\Domain\{CompositionPolicy,CanonicalJson};
use GovStore\Committee\Models\{CommitteeType,PurposeBinding};
use GovStore\Committee\Policies\CommitteePolicy;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\GovAccess;
use Illuminate\Support\Facades\{DB,Validator};
use Illuminate\Support\Str;

class CatalogService
{
    public function __construct(private CommitteePolicy $policy, private TenantContext $context, private GovAccess $access, private PurposeRegistry $purposes, private ScopeTypeRegistry $scopes) {}
    public function save(string $kind, ?int $id, array $input): array
    {
        $ability = $kind === 'types' ? 'committee.types.manage' : 'committee.purposes.manage';
        $this->policy->ability($ability);
        // Catalogue mutations always require actual role assignment, even in office shadow mode.
        abort_unless($this->access->decide(auth()->user(),$ability)->allowed,403);
        $national = ($input['scope'] ?? 'ministry') === 'national';
        $owner = $national ? null : $this->context->companyId;
        abort_unless($national ? auth()->user()->isSuperUser() : (bool)$owner,403);
        return DB::transaction(function () use ($kind,$id,$input,$national,$owner) {
            $class = $kind === 'types' ? CommitteeType::class : PurposeBinding::class;
            if ($national) { DB::table('gov_committee_catalog_mutexes')->where('key',$kind.':national')->lockForUpdate()->firstOrFail(); }
            // Lock the core company row for ministry catalogue uniqueness (including first insertion).
            if ($owner) { DB::table('companies')->where('id',$owner)->lockForUpdate()->first(); }
            $existing = $id ? $class::whereKey($id)->lockForUpdate()->firstOrFail() : null;
            if ($existing) { abort_unless($existing->owner_company_id === $owner,404); }
            $current = $class::where('owner_company_id',$owner)->orderBy('id')->get()->toArray();
            if ($national) {
                $review = $this->review($kind,$id,$input,$current);
                if (isset($review['review_token'])) { return $review; }
                $input = $review;
            }
            Validator::make($input,['change_reason'=>'required|string|min:5|max:1000'])->validate();
            if ($kind === 'types') {
                if (isset($input['composition_policy']) && is_array($input['composition_policy'])) {
                    $input['composition_policy'] += ['incompatible_duties'=>[]];
                }
                $data = Validator::make($input,['code'=>'required|string|max:20|regex:/^[A-Z][A-Z0-9_]+$/','name_en'=>'required|string|min:5|max:150','name_bn'=>'required|string|min:5|max:150',
                    'category'=>'required|in:inventory,disposal,audit,other','default_term_basis'=>'required|in:FIXED,FISCAL_YEAR,SINGLE_MATTER,UNTIL_FURTHER_ORDER',
                    'allowed_scope_types'=>'required|array|min:1','allow_concurrent'=>'required|boolean','composition_policy'=>'required|array','is_active'=>'required|boolean'])->validate();
                abort_if(array_diff($data['allowed_scope_types'],$this->scopes->keys()),422);
                if (in_array($data['code'],['TOC','TEC','TSC'],true)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['code'=>__('committee::committee.unsupported_procurement')]);
                }
                $data['composition_policy'] = CompositionPolicy::validate($data['composition_policy']);
                abort_if($class::where('owner_company_id',$owner)->where('code',$data['code'])->when($id,fn ($q) => $q->whereKeyNot($id))->exists(),409);
                $data['policy_version'] = ($existing?->policy_version ?? 0)+1; $data['updated_by'] = auth()->id();
                if (! $existing) { $data['created_by'] = auth()->id(); }
            } else {
                $data = Validator::make($input,['purpose_code'=>'required|string|max:80','committee_type_id'=>'required|integer','priority'=>'required|integer|min:0|max:100','allow_ancestor_fallback'=>'required|boolean','is_active'=>'required|boolean'])->validate();
                abort_unless($this->purposes->get($data['purpose_code']),422);
                $type = CommitteeType::findOrFail($data['committee_type_id']);
                abort_unless(! $type->owner_company_id || $type->owner_company_id === $owner,404);
                abort_if($class::where('owner_company_id',$owner)->where('purpose_code',$data['purpose_code'])->where('committee_type_id',$type->id)->when($id,fn ($q) => $q->whereKeyNot($id))->exists(),409);
                $data['changed_by'] = auth()->id(); $data['change_reason'] = $input['change_reason'];
            }
            $record = $existing ?? new $class; $record->fill($data + ['owner_company_id'=>$owner]); $record->save();
            \Illuminate\Support\Facades\Log::notice('Committee catalogue changed',['actor_id'=>auth()->id(),'kind'=>$kind,'record_id'=>$record->id,'owner_company_id'=>$owner,'reason'=>$input['change_reason']]);
            return ['id'=>$record->id,'saved'=>true];
        });
    }
    private function review(string $kind, ?int $id, array $input, array $current): array
    {
        $target = $kind.':'.($id ?? 'new'); $hash = hash('sha256',CanonicalJson::encode($current));
        if (empty($input['review_token'])) {
            $token = Str::random(64); $proposal = array_diff_key($input,array_flip(['confirmation','change_reason','review_token']));
            DB::table('gov_committee_reviews')->insert(['token_hash'=>hash('sha256',$token),'actor_id'=>auth()->id(),'target'=>$target,'proposal'=>CanonicalJson::encode($proposal),'configuration_hash'=>$hash,'expires_at'=>now()->addMinutes(15)]);
            return ['review_token'=>$token,'proposal'=>$proposal,'expires_in'=>900,'review_required'=>true];
        }
        $review = DB::table('gov_committee_reviews')->where('token_hash',hash('sha256',$input['review_token']))->lockForUpdate()->first();
        abort_unless($review && $review->actor_id === auth()->id() && $review->target === $target && ! $review->consumed_at && $review->expires_at > now()->format('Y-m-d H:i:s'),409);
        abort_unless(hash_equals($review->configuration_hash,$hash) && ($input['confirmation'] ?? '') === 'CHANGE',422);
        Validator::make($input,['change_reason'=>'required|string|min:5|max:1000'])->validate();
        DB::table('gov_committee_reviews')->where('token_hash',$review->token_hash)->update(['consumed_at'=>now()]);
        return json_decode($review->proposal,true,512,JSON_THROW_ON_ERROR) + ['change_reason'=>$input['change_reason']];
    }
}
