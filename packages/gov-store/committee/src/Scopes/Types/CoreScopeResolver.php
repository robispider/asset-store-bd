<?php

namespace GovStore\Committee\Scopes\Types;

use GovStore\Committee\Contracts\ScopeTypeResolver;
use GovStore\Committee\DTOs\ScopeRef;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Support\Facades\DB;

class CoreScopeResolver implements ScopeTypeResolver
{
    public function __construct(private string $type) {}
    private function query()
    {
        if ($this->type === 'ministry') { return DB::table('companies')->whereNull('deleted_at'); }
        $query = DB::table('locations')->whereNull('locations.deleted_at');
        return $this->type === 'office'
            ? $query->whereExists(fn ($q) => $q->selectRaw('1')->from('gov_location_profiles')->whereColumn('location_id','locations.id'))
            : $query->whereNotNull('parent_id')->whereExists(fn ($q) => $q->selectRaw('1')->from('gov_location_profiles')->whereColumn('location_id','locations.parent_id'));
    }
    private function row(string $id): ?object { return $this->query()->where('id',$id)->first(); }
    public function exists(string $id): bool { return $this->row($id) !== null; }
    public function label(string $id, string $locale): string { return $this->row($id)?->name ?? ''; }
    public function ownerLocationId(string $id): ?int
    {
        $row = $this->row($id);
        return $this->type === 'ministry' ? null : ($this->type === 'store' ? $row?->parent_id : $row?->id);
    }
    public function ownerCompanyId(string $id): ?int
    {
        $row = $this->row($id);
        return $this->type === 'ministry' ? $row?->id : $row?->company_id;
    }
    public function parent(string $id): ?ScopeRef
    {
        $row = $this->row($id);
        return match ($this->type) {
            'store' => $row ? new ScopeRef('office',(string)$row->parent_id) : null,
            'office' => $row && $row->company_id ? new ScopeRef('ministry',(string)$row->company_id) : null,
            default => null,
        };
    }
    public function search(string $term, TenantContext $context, int $limit): array
    {
        $q = $this->query()->where('name','like','%'.$term.'%');
        if (! $context->isGlobal) {
            if ($this->type === 'ministry') { $q->where('id',$context->companyId ?? 0); }
            else {
                $q->where('company_id',$context->companyId ?? 0);
                $q->whereIn($this->type === 'store' ? 'parent_id' : 'id', $context->allowedLocationIds ?? [$context->locationId ?? 0]);
            }
        }
        return $q->limit(min(50,max(1,$limit)))->get(['id','name'])->map(fn ($r) => ['id'=>(string)$r->id,'text'=>$r->name])->all();
    }
}

