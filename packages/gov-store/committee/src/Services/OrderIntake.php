<?php

namespace GovStore\Committee\Services;

use GovStore\Committee\Policies\CommitteePolicy;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Storage,Validator};

/** A person's unfinished order entry, private to their current office. Final commands still validate everything. */
class OrderIntake
{
    public function __construct(private TenantContext $context, private CommitteePolicy $policy, private OrderAttachmentStore $files) {}
    private function query()
    {
        $this->policy->ability('committee.manage');
        abort_unless($this->context->isActive && $this->context->locationId && $this->context->companyId,403);
        return DB::table('gov_committee_order_intakes')->where('user_id',auth()->id())->where('location_id',$this->context->locationId)->where('company_id',$this->context->companyId);
    }
    public function current(): array
    {
        $row=$this->query()->first();
        return $row ? ['fields'=>json_decode($row->payload,true),'has_file'=>(bool)$row->attachment,'revision'=>$row->revision] : ['fields'=>[],'has_file'=>false,'revision'=>0];
    }
    public function save(array $input, ?UploadedFile $file): array
    {
        $this->checkContext($input);
        $fields=Validator::make($input,[
            'memo_no'=>'nullable|string|max:150','office_order_no'=>'nullable|string|max:100','nothi_no'=>'nullable|string|max:150',
            'issued_on'=>'nullable|date_format:Y-m-d','issued_on_bangla'=>'nullable|string|max:60','issuing_authority_name'=>'nullable|string|max:150',
            'issuing_authority_designation_en'=>'nullable|string|max:150','job'=>'nullable|in:form,replace,extend,reconstitute,suspend,resume,dissolve,correct',
            'target_committee'=>'nullable|uuid','committee_type_id'=>'nullable|integer','name_bn'=>'nullable|string|max:200','name_en'=>'nullable|string|max:200',
            'term_basis'=>'nullable|in:FIXED,FISCAL_YEAR,SINGLE_MATTER,UNTIL_FURTHER_ORDER','effective_from'=>'nullable|date_format:Y-m-d',
            'effective_to'=>'nullable|date_format:Y-m-d','terms_of_reference'=>'nullable|string|max:10000','intake_revision'=>'required|integer|min:0',
        ])->validate();
        $revision=(int)$fields['intake_revision']; unset($fields['intake_revision']);
        if (!empty($fields['target_committee'])) { $this->policy->check(\GovStore\Committee\Models\Committee::findOrFail($fields['target_committee']),'committee.manage',true,['ACTIVE','SUSPENDED','EXPIRED']); }
        if (!empty($fields['committee_type_id'])) { abort_unless(\GovStore\Committee\Models\CommitteeType::whereKey($fields['committee_type_id'])->where(fn ($q)=>$q->whereNull('owner_company_id')->orWhere('owner_company_id',$this->context->companyId))->exists(),404); }
        $attachment=null;
        try {
            DB::transaction(function () use ($fields,$file,$revision,&$attachment) {
                $query=$this->query(); $row=$query->lockForUpdate()->first();
                abort_unless((int)($row->revision ?? 0)===$revision,409);
                $attachment=$file ? $this->files->store($file) : null;
                $values=['payload'=>json_encode($fields),'attachment'=>$attachment ? json_encode($attachment) : ($row->attachment ?? null),'revision'=>$revision+1,'updated_at'=>now()];
                if ($row) { $query->update($values); }
                else { DB::table('gov_committee_order_intakes')->insert($values+['user_id'=>auth()->id(),'location_id'=>$this->context->locationId,'company_id'=>$this->context->companyId]); }
                if ($attachment && $row?->attachment) { $old=json_decode($row->attachment,true); DB::afterCommit(fn()=>Storage::disk('committee_private')->delete($old['attachment_path'])); }
            });
        } catch (\Throwable $e) { if ($attachment) { Storage::disk('committee_private')->delete($attachment['attachment_path']); } throw $e; }
        return $this->current();
    }
    public function checkContext(array $input): void
    {
        $this->policy->ability('committee.manage');
        Validator::make($input,['intake_location_id'=>'required|integer','intake_company_id'=>'required|integer'])->validate();
        abort_unless((int)$input['intake_location_id']===$this->context->locationId && (int)$input['intake_company_id']===$this->context->companyId,404);
    }
    /** Called inside the final command transaction; rollback retains the intake and its file. */
    public function consume(int $revision, array $input): ?UploadedFile
    {
        $this->checkContext($input);
        $row=$this->query()->lockForUpdate()->first(); abort_unless($row && (int)$row->revision===$revision,409);
        $attachment=$row->attachment ? json_decode($row->attachment,true) : null;
        $file=$attachment ? $this->files->uploaded($attachment) : null;
        $this->query()->delete();
        if ($attachment) { DB::afterCommit(fn()=>Storage::disk('committee_private')->delete($attachment['attachment_path'])); }
        return $file;
    }
    public function discard(int $revision, array $input): void
    {
        $this->checkContext($input);
        DB::transaction(function () use ($revision) {
            $row=$this->query()->lockForUpdate()->first(); abort_unless($row && (int)$row->revision===$revision,409);
            $this->query()->delete();
            if ($row->attachment) { $attachment=json_decode($row->attachment,true); DB::afterCommit(fn()=>Storage::disk('committee_private')->delete($attachment['attachment_path'])); }
        });
    }
}
